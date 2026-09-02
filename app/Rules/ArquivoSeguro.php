<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * FONTE ÚNICA do que pode ser anexado no sistema. Use em TODO campo de upload — nunca
 * `['required','file']` sozinho, que aceita **.exe, .bat, .ps1, .jar** e afins e os guarda no
 * servidor. Mesmo sem execução direta, executável em repositório de anexos é vetor: basta alguém
 * baixar e abrir.
 *
 * Estratégia — ALLOWLIST (o que é permitido), não blocklist:
 *  1. **Extensão** tem de estar na lista do negócio (documentos, imagens, planilhas, mídia).
 *  2. **Conteúdo** (MIME real, adivinhado do arquivo — não o header enviado pelo cliente) tem de
 *     casar com a extensão. Barra `virus.exe` renomeado para `laudo.pdf`.
 *  3. **Extensão perigosa em QUALQUER posição** do nome é recusada — pega o truque da extensão
 *     dupla (`foto.jpg.exe`, que no Windows abre como executável).
 *  4. **Nome do arquivo** sem `../` (path traversal), sem caractere de controle e sem assinatura de
 *     SQLi/HTML (`--`, aspas, `<`, `>`, `;`). Além da higiene, o nome vai para a URL de download: o
 *     WAF da Prefeitura barra assinatura de SQLi na URL e o download morreria — com cara de erro de
 *     CORS (ver a LEI no CLAUDE.md).
 *
 * @example
 *   'arquivo' => ['required', 'file', 'max:20480', new ArquivoSeguro],
 *   'foto' => ['nullable', 'file', new ArquivoSeguro(ArquivoSeguro::IMAGENS)],
 */
class ArquivoSeguro implements ValidationRule
{
    /** Documentos aceitos em anexo de cadastro/processo. */
    public const DOCUMENTOS = ['pdf', 'doc', 'docx', 'odt', 'txt', 'rtf'];

    /** Imagens (foto do ambulante, foto da fiscalização, logo, fundo de tela). */
    public const IMAGENS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /** Planilhas (importação e anexo de listagem). */
    public const PLANILHAS = ['xlsx', 'xls', 'csv', 'ods'];

    /** Mídia (vídeo de evidência). */
    public const MIDIA = ['mp4', 'webm'];

    /** Áudio (fala do fiscal em campo — relato por voz). */
    public const AUDIO = ['m4a', 'mp4', 'aac', 'mp3', 'wav', 'ogg', 'webm', 'caf', 'amr'];

    /**
     * Extensões SEMPRE recusadas, em qualquer posição do nome. Redundante com a allowlist, de
     * propósito: garante mensagem clara e protege se alguém ampliar a lista sem pensar.
     *
     * @var list<string>
     */
    private const PERIGOSAS = [
        // Executáveis e scripts (Windows/Unix)
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'cpl', 'jar', 'app', 'dmg', 'deb', 'rpm',
        'sh', 'bash', 'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta', 'reg', 'lnk',
        // Executados pelo servidor se caírem numa pasta servida
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
        // Macro do Office (executam código ao abrir)
        'docm', 'xlsm', 'xlsb', 'pptm', 'dotm', 'xltm',
        // Bibliotecas
        'dll', 'so', 'dylib',
    ];

    /** @param  list<string>|null  $permitidas  extensões aceitas; null = documentos + imagens + planilhas. */
    public function __construct(private ?array $permitidas = null) {}

    /**
     * Atalho: tudo que o sistema aceita em anexo genérico.
     *
     * @return list<string>
     */
    public static function padrao(): array
    {
        return [...self::DOCUMENTOS, ...self::IMAGENS, ...self::PLANILHAS];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return; // deixa as regras `file`/`required` cuidarem disso
        }

        $nome = (string) $value->getClientOriginalName();
        $extensao = mb_strtolower((string) $value->getClientOriginalExtension());
        $permitidas = $this->permitidas ?? self::padrao();

        // 1 e 3 — extensão perigosa em qualquer posição do nome (pega `foto.jpg.exe`).
        foreach (explode('.', mb_strtolower($nome)) as $parte) {
            if (in_array($parte, self::PERIGOSAS, true)) {
                $fail("O arquivo \"{$nome}\" tem tipo não permitido (.{$parte}). Envie apenas ".$this->listaLegivel($permitidas).'.');

                return;
            }
        }

        // 4 — nome do arquivo saudável (path traversal, controle, assinatura de SQLi/HTML).
        if (str_contains($nome, '..') || preg_match('/[\/\\\\]/', $nome) === 1) {
            $fail('O nome do arquivo não pode conter caminhos (".." ou barras). Renomeie e envie novamente.');

            return;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $nome) === 1) {
            $fail('O nome do arquivo contém caracteres inválidos. Renomeie e envie novamente.');

            return;
        }
        if (preg_match('/--|[\'"<>;]|\/\*|\*\//', $nome) === 1) {
            $fail("O nome do arquivo tem caracteres não permitidos (aspas, <, >, ;, -- ou /*). Renomeie \"{$nome}\" e envie novamente.");

            return;
        }

        // 1 — extensão na allowlist.
        if ($extensao === '' || ! in_array($extensao, $permitidas, true)) {
            $fail('Tipo de arquivo não permitido. Envie apenas '.$this->listaLegivel($permitidas).'.');

            return;
        }

        // 2 — o CONTEÚDO é executável? Barra independentemente do nome.
        //
        // ⚠️ Esta checagem tem de vir ANTES da comparação com a extensão. A regra de baixo só reprova
        // quando o MIME é CONHECIDO e diverge da extensão; MIME desconhecido devolve `[]` e passa
        // ("não deu para determinar, não barramos"). Como `application/x-dosexec` não está no mapa,
        // sem esta guarda um **.exe renomeado para .pdf** seria ACEITO — o disfarce mais óbvio
        // furando a Rule inteira.
        if ($this->conteudoEhExecutavel($value)) {
            $fail("O arquivo \"{$nome}\" é um programa executável e não pode ser enviado, mesmo com outra extensão.");

            return;
        }

        // 3 — conteúdo casa com a extensão (MIME adivinhado do arquivo, não o header do cliente).
        $extensoesDoConteudo = $this->extensoesDoConteudo($value);
        if ($extensoesDoConteudo !== [] && ! in_array($extensao, $extensoesDoConteudo, true)) {
            $fail("O conteúdo do arquivo \"{$nome}\" não corresponde à extensão .{$extensao}. Envie o arquivo original.");
        }
    }

    /**
     * O conteúdo é um programa, seja qual for o nome do arquivo?
     *
     * Allowlist não resolve aqui: o problema não é "o MIME não bate com a extensão" (isso a regra
     * seguinte cobre), é "este conteúdo é executável" — e um MIME desconhecido nunca pode ser lido
     * como inofensivo. Cobre PE/Windows (`x-dosexec`), ELF/Linux, Mach-O/macOS e script shell.
     */
    private function conteudoEhExecutavel(UploadedFile $arquivo): bool
    {
        $mime = (string) $arquivo->getMimeType();

        $executaveis = [
            'application/x-dosexec',            // .exe/.dll/.scr (PE)
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/vnd.microsoft.portable-executable',
            'application/x-executable',         // ELF
            'application/x-sharedlib',
            'application/x-pie-executable',
            'application/x-mach-binary',        // Mach-O
            'application/x-elf',
            'application/x-sh',                 // shell script
            'application/x-shellscript',
            'text/x-shellscript',
            'application/x-bat',
            'application/x-msi',
        ];

        if (in_array($mime, $executaveis, true)) {
            return true;
        }

        // Assinatura nos primeiros bytes — pega o caso em que o finfo não opina (retorna
        // octet-stream) mas o arquivo começa com o cabeçalho de um binário executável.
        $inicio = (string) file_get_contents($arquivo->getRealPath(), false, null, 0, 4);

        return str_starts_with($inicio, 'MZ')                // PE (Windows)
            || str_starts_with($inicio, "\x7FELF")            // ELF (Linux)
            || str_starts_with($inicio, "\xCA\xFE\xBA\xBE")   // Mach-O universal / class Java
            || str_starts_with($inicio, "\xFE\xED\xFA");      // Mach-O
    }

    /**
     * Extensões que o MIME real do arquivo admite. Lista vazia = não foi possível determinar
     * (arquivo sem assinatura conhecida, ex.: .txt/.csv) → não barramos por isso.
     *
     * @return list<string>
     */
    private function extensoesDoConteudo(UploadedFile $arquivo): array
    {
        $mime = $arquivo->getMimeType(); // adivinhado pelo conteúdo (finfo)

        if ($mime === null || $mime === 'application/octet-stream' || $mime === 'text/plain') {
            return [];
        }

        // As duas famílias abaixo compartilham container (ZIP/OLE): o MIME não distingue docx de
        // xlsx com segurança em todo ambiente, então aceitamos o grupo inteiro.
        $office = ['docx', 'xlsx', 'pptx', 'odt', 'ods'];
        if (in_array($mime, ['application/zip', 'application/x-zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            return $office;
        }
        if ($mime === 'application/msword' || $mime === 'application/vnd.ms-excel' || $mime === 'application/x-ole-storage') {
            return ['doc', 'xls', 'rtf'];
        }

        $porMime = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
            'image/bmp' => ['bmp'],
            'image/x-ms-bmp' => ['bmp'],
            'text/csv' => ['csv', 'txt'],
            'text/rtf' => ['rtf'],
            'application/rtf' => ['rtf'],
            'video/mp4' => ['mp4', 'm4a'],
            'video/webm' => ['webm'],
            'audio/mp4' => ['m4a', 'mp4', 'aac'],
            'audio/x-m4a' => ['m4a'],
            'audio/aac' => ['aac', 'm4a'],
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/x-wav' => ['wav'],
            'audio/wave' => ['wav'],
            'audio/ogg' => ['ogg'],
            'audio/webm' => ['webm'],
            'audio/3gpp' => ['amr'],
            'audio/amr' => ['amr'],
            'audio/x-caf' => ['caf'],
        ];

        return $porMime[$mime] ?? [];
    }

    /** @param  list<string>  $extensoes */
    private function listaLegivel(array $extensoes): string
    {
        return implode(', ', array_map(fn (string $e): string => strtoupper($e), $extensoes));
    }
}
