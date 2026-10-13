import { useState, type FormEvent } from 'react';

import { Icone } from '../icones';

/**
 * A porta do aplicativo de campo.
 *
 * Matrícula e senha são as MESMAS da Retaguarda — é a mesma conta. Quem confere
 * é o servidor, e a recusa vem com a frase dele (senha errada, senha ainda não
 * definida, conta inativa, conta fora de equipe): ninguém fica parado na porta
 * sem saber por quê.
 *
 * A sessão fica lembrada no aparelho: o fiscal não digita senha a cada vez que
 * o celular bloqueia. Quem sai é quem toca em "Encerrar plantão", no perfil.
 *
 * A outra coisa que esta tela decide é o tamanho do alvo de toque de quem
 * digita em pé, no sol, segurando o celular com uma mão só.
 */
export function TelaEntrada({
    aviso,
    aoEntrar,
}: {
    aviso: string | null;
    aoEntrar: (login: string, senha: string) => Promise<string | null>;
}) {
    const [matricula, setMatricula] = useState('');
    const [senha, setSenha] = useState('');
    const [entrando, setEntrando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);

    const enviar = async (evento: FormEvent) => {
        evento.preventDefault();

        if (entrando) {
            return;
        }

        if (!matricula.trim() || !senha) {
            setErro('Informe a matrícula e a senha.');

            return;
        }

        setEntrando(true);
        setErro(null);
        const recusa = await aoEntrar(matricula.trim(), senha);
        setEntrando(false);

        if (recusa) {
            setErro(recusa);
        }
    };

    const mensagem = erro ?? aviso;

    return (
        <form className="pw-entrada-tela pw-com-faixa" onSubmit={enviar}>
            {/* Ao lado do nome do produto vai só o escudo: aqui quem precisa ser
                lido de longe é "SEFAL". O lockup completo desce para o rodapé. */}
            <div className="pw-marca">
                <img
                    className="pw-marca-escudo"
                    src="/images/marca/brasao-salvador-branco.svg"
                    alt=""
                    width={58}
                    height={54}
                />
                <div>
                    <h1>SEFAL</h1>
                    <p>Fiscalização de ambulantes</p>
                </div>
            </div>

            <label className="pw-campo">
                <span>Matrícula</span>
                <input
                    className="pw-entrada"
                    value={matricula}
                    onChange={(e) => setMatricula(e.target.value)}
                    autoComplete="username"
                    autoCapitalize="none"
                />
            </label>

            <label className="pw-campo">
                <span>Senha</span>
                <input
                    className="pw-entrada"
                    type="password"
                    value={senha}
                    onChange={(e) => setSenha(e.target.value)}
                    autoComplete="current-password"
                />
            </label>

            {mensagem && (
                <p className="pw-dica-entrada" role="alert">
                    {mensagem}
                </p>
            )}

            <button
                type="submit"
                className="pw-btn pw-btn-acao"
                style={{ minHeight: 60, fontSize: 18 }}
                disabled={entrando}
                aria-busy={entrando}
            >
                {entrando ? 'Entrando…' : 'Entrar no plantão'}
                <Icone nome={entrando ? 'atualizar' : 'seta'} tamanho={20} className={entrando ? 'pw-girando' : undefined} />
            </button>

            <p className="pw-dica-entrada">
                Use a mesma matrícula e senha da Retaguarda. Primeiro acesso? Defina a senha pelo convite que chegou
                no seu e-mail.
            </p>

            <footer className="pw-rodape-entrada">
                <img
                    className="pw-marca-oficial"
                    src="/images/marca/salvador-horizontal-branco.svg"
                    alt="Prefeitura Municipal do Salvador"
                    width={186}
                    height={75}
                />
                Prefeitura Municipal do Salvador · SEMOP
            </footer>
        </form>
    );
}
