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

Quem não tem setor definido vê isso dito em tela, e não um espaço vazio: é a pista de que falta
alguém lhe conceder acesso.

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
