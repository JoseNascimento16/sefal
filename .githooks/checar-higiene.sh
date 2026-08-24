#!/bin/sh
#
# Reprova se algum caminho PROIBIDO estiver neste repositório.
#
# A lista mora em `.higiene-proibidos` (fonte única) e é lida também pelo
# tests/Feature/HigieneDoRepositorioTest.php.
#
# Uso:
#   sh .githooks/checar-higiene.sh            # confere o que está RASTREADO (índice + HEAD)
#   sh .githooks/checar-higiene.sh --staged   # confere só o que está no índice (usado no pre-commit)
#
# Saída: 0 = limpo; 1 = achou caminho proibido (lista os caminhos e o motivo).

set -u

RAIZ=$(git rev-parse --show-toplevel 2>/dev/null) || {
    echo "checar-higiene: fora de um repositorio git." 1>&2
    exit 0
}

LISTA="$RAIZ/.higiene-proibidos"
[ -f "$LISTA" ] || {
    echo "checar-higiene: .higiene-proibidos nao encontrado — nada a conferir." 1>&2
    exit 0
}

if [ "${1:-}" = "--staged" ]; then
    ARQUIVOS=$(git diff --cached --name-only --diff-filter=ACMR)
else
    ARQUIVOS=$(git ls-files)
fi

[ -n "$ARQUIVOS" ] || exit 0

achou=0

# Lê a lista linha a linha. Formato: <caminho> | <motivo>
while IFS= read -r linha || [ -n "$linha" ]; do
    case "$linha" in
        '' | \#*) continue ;;
    esac

    caminho=$(printf '%s' "$linha" | sed -e 's/[[:space:]]*|.*$//' -e 's/^[[:space:]]*//' -e 's#/*[[:space:]]*$##')
    motivo=$(printf '%s' "$linha" | sed -n 's/^[^|]*|[[:space:]]*//p')
    [ -n "$caminho" ] || continue

    # O `for` divide por espaço: um caminho proibido COM espaço no nome escaparia daqui.
    # Hoje não existe nenhum; se passar a existir, quem pega é o teste-lei (que compara em PHP,
    # linha a linha) — mantenha a lista sem espaços nos nomes para os dois guardas concordarem.
    for arquivo in $ARQUIVOS; do
        # Casa o arquivo exato, a pasta inteira e glob — o mesmo critério do teste-lei.
        # shellcheck disable=SC2254 -- o padrao vem da lista e E para ser interpretado como glob
        case "$arquivo" in
            $caminho | $caminho/*)
                if [ "$achou" -eq 0 ]; then
                    echo "" 1>&2
                    echo "  ARQUIVO PROIBIDO NO REPOSITORIO" 1>&2
                    echo "" 1>&2
                fi
                achou=1
                echo "  x $arquivo" 1>&2
                echo "    $motivo" 1>&2
                ;;
        esac
    done
done < "$LISTA"

if [ "$achou" -eq 1 ]; then
    echo "" 1>&2
    echo "  Este repositorio e ENTREGUE ao cliente e AUDITADO pela Qualidade." 1>&2
    echo "  O ferramental de trabalho vive na branch 'ferramental', nao aqui." 1>&2
    echo "" 1>&2
    echo "  Para tirar do commit mantendo o arquivo no disco:" 1>&2
    echo "    git rm --cached <arquivo>" 1>&2
    echo "" 1>&2
    echo "  Se a decisao MUDOU e o arquivo deve mesmo entrar, ajuste .higiene-proibidos" 1>&2
    echo "  no MESMO commit, com o motivo." 1>&2
    echo "" 1>&2
    exit 1
fi

exit 0
