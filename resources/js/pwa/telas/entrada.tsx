import { useState, type FormEvent } from 'react';

import { classes } from '../componentes';
import { Icone } from '../icones';

/**
 * A porta do aplicativo de campo.
 *
 * No protótipo qualquer matrícula entra — não há servidor do outro lado. O que
 * esta tela está decidindo é outra coisa: o tamanho do alvo de toque de quem
 * digita em pé, no sol, segurando o celular com uma mão só.
 */
export function TelaEntrada({ aoEntrar }: { aoEntrar: () => void }) {
    const [matricula, setMatricula] = useState('F-40219');
    const [senha, setSenha] = useState('••••••••');
    const [lembrar, setLembrar] = useState(true);

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        aoEntrar();
    };

    return (
        <form className="pw-entrada-tela pw-com-faixa" onSubmit={enviar}>
            <div className="pw-marca">
                <div className="pw-marca-brasao">FA</div>
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
                    placeholder="F-00000"
                    autoComplete="username"
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

            <button type="button" className="pw-lembrar" onClick={() => setLembrar((v) => !v)}>
                <span className={classes('pw-caixa', lembrar && 'pw-caixa-marcada')}>
                    {lembrar && <Icone nome="certo" tamanho={16} />}
                </span>
                Lembrar-me neste aparelho
            </button>

            <button type="submit" className="pw-btn pw-btn-acao" style={{ minHeight: 60, fontSize: 18 }}>
                Entrar no plantão
                <Icone nome="seta" tamanho={20} />
            </button>

            <p className="pw-rodape-entrada">
                Prefeitura Municipal do Salvador · SEMOP
                <br />
                Aplicativo de campo — versão de demonstração
            </p>
        </form>
    );
}
