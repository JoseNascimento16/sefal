# Usuários — quem tem conta, e em que setor

**Onde fica:** Menu → Sistema → Usuários (`/retaguarda/usuarios`).
**Quem usa:** administrador (slug `usuarios`, semeado só para ele no Modo Gerente).

A tela de Usuários do Codecon, trazida para o SEFAL por pedido do dono em
25/09/2026. É onde se cria a conta de quem trabalha na Retaguarda, se diz em que
**setor** a pessoa está (o setor decide o que ela vê e faz — RN-01 de
[papéis e setores](papeis-e-setores.md)) e se acompanha quem ainda não entrou.

Três abas: **Localizar** (as contas), **Excluídos** (a lixeira) e o **registro**
aberto — em modo navegação primeiro; alterar é um clique em "Editar".

---

## Regras vigentes

### RN-01 — A conta nasce SEM senha conhecida, e o convite chega por e-mail

Ao incluir, a conta recebe uma senha aleatória que ninguém conhece, e a pessoa
recebe no e-mail o **convite** para escolher a dela (o mesmo link e a mesma tela
do "Esqueci minha senha", com o texto de boas-vindas e a matrícula). Por isso o
**e-mail é obrigatório** e único. O botão **Enviar convite** reenvia enquanto o
primeiro acesso estiver pendente; para quem já tem senha, o caminho é o
"Esqueci minha senha".

A coluna **1º acesso** diz se a pessoa já escolheu a senha (`users.senha_definida_em`,
carimbada toda vez que a senha muda de verdade — convite, redefinição, troca no
perfil ou comando). O "Esqueci minha senha" também passou a chegar em português.

### RN-02 — Quem não definiu a senha é AVISADO no login

Tentar entrar antes de escolher a senha mostra "Sua senha ainda não foi
definida…", e não "matrícula ou senha inválida" — a pessoa saberia que errou, mas
não o que falta fazer.

### RN-03 — A matrícula não muda

A matrícula só é informada na inclusão (letras, números, ponto, hífen e
sublinhado; até 30 caracteres; guardada em minúsculo). Ela identifica quem fez
cada registro — trocá-la seria trocar a assinatura do que já foi feito. Nome e
e-mail se corrigem.

### RN-04 — O Chefe de Setor é UM só

Marcar uma conta como Chefe de Setor tira o setor de quem o tinha. A tela avisa
**quem sai** antes de salvar, e o recado depois de salvar repete. Desmarcar o
único chefe avisa que a Caixa de Entrada fica sem dono.

### RN-05 — Administrador só se dá entre administradores

A tela é só do administrador, mas o Modo Gerente pode concedê-la a outro setor.
Mesmo assim, **dar ou tirar o setor Administrador, alterar ou excluir a conta de
um administrador** continua só de administrador — a recusa vem do servidor, com o
motivo. Sem isso, conceder a tela seria conceder tudo.

A conta de administrador antiga (pela marca na conta, sem o setor) aparece com o
setor Administrador marcado: são duas portas para o mesmo papel, e salvar deixa as
duas iguais.

### RN-06 — Ninguém se tranca do lado de fora

Não se tira o setor Administrador de si mesmo, não se desativa a própria conta e
não se exclui a própria conta. Só outro administrador desfaria.

### RN-07 — Excluir vai para a LIXEIRA

A conta excluída some da lista e do login, e fica na aba **Excluídos** por
**3 dias**, restaurável (volta com os mesmos setores e equipes). Depois disso, a
limpeza diária (`sefal:purgar-usuarios-excluidos`, 03:00) a remove de vez.

**Exceção:** a conta com **histórico** — autoria de trâmite, vistoria, decisão,
operação ou concessão de acesso (`User::AUTORIA`) — **nunca é removida**: fica na
lixeira, sem acesso, para o nome continuar no que ela fez. A aba diz isso na
coluna de remoção.

Excluir quem lidera equipe avisa que a equipe fica sem líder enquanto a conta
estiver excluída.

### RN-08 — Equipe se vincula em Áreas e Equipes

A tela mostra de quais equipes a conta é líder e em quais é fiscal, mas o vínculo
é feito em [Áreas e Equipes](estrutura/areas-e-equipes.md). A tela avisa quando a
conta é Líder de Equipe sem equipe (as Fiscalizações não chegam a ela) e quando
deixa de ser Líder de Equipe continuando líder de uma equipe.

### RN-09 — Busca e exportação

A busca é a barra única: nome, matrícula, e-mail, setor ou equipe, e reconhece
"ativos", "inativos", "sem setor", "acesso pendente" e "senha definida". As duas
abas exportam o recorte visível (PDF, Excel e Word), com matrícula e equipes em
coluna própria.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/09/2026 | José Nascimento | Usuários | Nasce a tela (RN-01 a RN-09): inclusão com convite de primeiro acesso, setores, situação, reenvio do convite, lixeira com restauração e remoção definitiva em 3 dias (menos quem tem histórico), Chefe de Setor único, administrador só entre administradores, travas contra se trancar do lado de fora. Migration `2026_09_25_090000_controle_de_usuarios` (`deleted_at` e `senha_definida_em`). E-mail de redefinição em português. | Pedido do dono: "Estou sentindo falta de controle de usuários. Implemente a funcionalidade da tela de usuários igual à do sistema Codecon". Cargo, Diretor e Modo Gerente por pessoa não existem no SEFAL; o posto único daqui é o Chefe de Setor. |
| 25/09/2026 | José Nascimento | Usuários | O atalho da tela sai do menu lateral e vai para o **menu da conta**, no canto superior direito, junto de Meu Perfil e Sair (como no Codecon). A tela continua na matriz do Modo Gerente (`oculto` tira o atalho, não o acesso); o atalho aparece para quem pode abrir a tela (`auth.user.administra_usuarios`). | Pedido do dono. |
