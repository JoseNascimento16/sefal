# Início — a tela para onde o login leva

**Onde fica:** Menu → Painel → Início (`/retaguarda/inicio`).
**Quem usa:** todo mundo que entra na Retaguarda.

É a primeira coisa que a pessoa vê depois de entrar — e é também para onde volta, **com o motivo**,
quem foi barrado em outra tela. Por isso ela é a única fora do controle de acesso: barrá-la fecharia
um laço de redirecionamento (a própria negativa manda para cá).

A tela tem duas partes: a **saudação** (com a matrícula e o papel de quem entrou) e os **atalhos** —
os caminhos curtos para o trabalho.

---

## Regras vigentes

### RN-01 — Os atalhos são decididos pelo SERVIDOR, com as duas perguntas do menu

Cada atalho passa pelas mesmas duas perguntas que o menu lateral faz:

1. **a rota existe?** Se não, a tela ainda não foi construída: o cartão fica à vista, esmaecido,
   dizendo **"Em construção"**. É informação útil — quem usa o sistema enxerga o caminho que está
   sendo aberto;
2. **esta pessoa pode entrar?** Quem responde é o `PermissaoService`, o **mesmo** que o menu e as
   guardas de acesso consultam. Sem permissão, o cartão **não aparece**: oferecer atalho para uma
   tela que a guarda barra é convite para uma recusa que ninguém entende.

São três estados, portanto: **leva à tela**, **em construção** e **ausente**. Confundir os dois
últimos é o defeito que esta regra existe para impedir.

⚠️ **Por que no servidor.** Os atalhos eram quatro cartões escritos na própria tela, e o resultado foi
o defeito que essa forma sempre produz: o cartão de Permissionários continuou anunciando
"Em construção" depois de a tela ficar pronta e entrar no menu. A primeira coisa que o usuário
enxergava **desmentia a entrega principal da fase** — e quem lê "não existe" não vai procurar no menu
ao lado. Mesma informação, dois donos: um dia divergem.

### RN-02 — A saudação é pelo relógio de quem olha, e o nome é o primeiro

"Bom dia" / "Boa tarde" / "Boa noite" pela hora do navegador, e só o **primeiro nome**: "Bom dia,
Maria" soa com gente; o nome completo soa com cadastro. A matrícula aparece em **maiúscula** (é como
ela vem no crachá), embora seja guardada em minúscula — a forma canônica é do banco, a legível é da
tela.

As faixas são **06:00–11:59 bom dia · 12:00–17:59 boa tarde · 18:00–05:59 boa noite**, e a
**madrugada é NOITE**. Neste sistema isso não é detalhe: fiscalização de ambulante acontece de
madrugada, em Carnaval e em festa de largo, e quem abre o sistema às 3h não está começando o dia.

A regra tem **um dono só** (`resources/js/lib/saudacao.ts`), porque **duas** telas cumprimentam quem
entra ao mesmo tempo — esta e o splash de boas-vindas (RN-03). Com uma cópia em cada uma, elas se
contradiziam: o splash dizia "Boa noite" e a tela por baixo, "Bom dia".

Quem não tem setor definido vê isso dito em tela, e não um espaço vazio: é a pista de que falta
alguém lhe conceder acesso.

### RN-03 — O splash de boas-vindas aparece UMA vez, e nunca atrapalha

Logo depois do login, a primeira tela renderizada recebe por cima um splash com a saudação, o nome
de quem entrou e a marca — a cidade vista de cima, com a malha de ruas e os pontos de fiscalização.
Ele fica ~3,2s em tela e sai sozinho num fade de ~900ms.

Três garantias, e cada uma existe por um motivo:

1. **Uma vez por entrada.** A marca de "acabou de entrar" é gravada na sessão pelo evento de
   autenticação e **consumida na entrega** dos dados da tela — não por flash de sessão. A diferença
   importa: entre o login e a primeira tela pode haver redirecionamento (a guarda de permissão
   devolvendo alguém à tela inicial), e flash morre nesse salto. Assim o splash aparece na primeira
   tela DE VERDADE, quantos saltos houver, e não reaparece nas seguintes.
2. **Nunca bloqueia.** O splash não captura clique (`pointer-events: none`) e **se desmonta** ao fim
   do fade: quem já quer trabalhar clica através dele. Splash que fica, ou que engole o clique,
   deixa de ser recepção e vira porta trancada.
3. **É recepção, não informação.** Nada nele é dado do sistema: as ruas, os pontos e as manchas são
   desenho. Quem precisa de número abre a tela, não o splash.

Quem pede menos movimento (`prefers-reduced-motion`) recebe o **mesmo** splash, parado — não um
splash a menos: a informação (quem entrou, que horas são) é a mesma.

---

## Fora de escopo (por ora)

- **Indicadores e contagens** (quantos cadastros, quantas fiscalizações no mês). Painel de números
  sem o fluxo que os alimenta é decoração; entra quando houver o que contar.
- **Atalho configurável por pessoa.** A lista é curta e igual para todos de propósito.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 26/08/2026 | José Nascimento | Início | Os atalhos passam a vir do servidor, com endereço resolvido e filtrados por permissão (RN-01); o cartão de Permissionários passa a levar à tela. | O cartão dizia "Em construção" para uma tela pronta, no menu e no Acompanhamento de Requisitos: a primeira tela do sistema desmentia a entrega principal da fase. Escrito na tela, o cartão não tinha como saber que a rota nasceu. |
| 27/08/2026 | José Nascimento | Início | Entra o splash de boas-vindas na primeira tela depois do login (RN-03), e a saudação passa a ter **um dono só** — com as faixas 6/12/18 e a madrugada como NOITE (RN-02). | Esta tela cortava só em `hora < 12`: às 3h da manhã ela dizia "Bom dia", e fiscalização de ambulante acontece de madrugada. Com o splash aparecendo por cima dela e trazendo a própria cópia da regra, as duas se contradiriam na mesma tela, no mesmo segundo. |
