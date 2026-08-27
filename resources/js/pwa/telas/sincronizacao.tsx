import { useEffect, useState } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, atalhoDoPerfil } from '../componentes';
import { FISCAL, nomeRegiao } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   ENVIO — o aplicativo trabalha primeiro, conversa depois.
   ----------------------------------------------------------------------------
   Rua não tem sinal garantido: praia, subsolo de galeria, aglomeração de festa.
   O registro é gravado no aparelho e sobe quando dá — e o fiscal PRECISA ver
   isso, senão ele fica refazendo registro achando que perdeu. Esta tela existe
   para dar essa certeza: o que já subiu, o que está na fila e o que falhou.
   ============================================================================ */

export function TelaSincronizacao() {
    const { registros, pendentes, esvaziarFila } = useApp();
    const [enviando, setEnviando] = useState(false);
    const [progresso, setProgresso] = useState(0);

    useEffect(() => {
        if (!enviando) {
            return;
        }

        const relogio = window.setInterval(() => {
            setProgresso((p) => {
                if (p >= 100) {
                    window.clearInterval(relogio);
                    setEnviando(false);
                    esvaziarFila();

                    return 100;
                }

                return p + 8;
            });
        }, 90);

        return () => window.clearInterval(relogio);
    }, [enviando, esvaziarFila]);

    const fila = registros.filter((r) => r.envio !== 'enviado');
    const enviados = registros.length - fila.length;

    return (
        <div className="pw-tela">
            <Topo
                titulo="Envio"
                subtitulo={pendentes === 0 ? 'Tudo em dia' : `${pendentes} aguardando sinal`}
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            <div className="pw-corpo">
                <div className="pw-card">
                    <div className="pw-linha-espalha">
                        <div>
                            <p className="pw-forte" style={{ margin: 0, fontSize: 17 }}>
                                {pendentes === 0
                                    ? 'Nada na fila'
                                    : pendentes === 1
                                      ? '1 registro na fila'
                                      : `${pendentes} registros na fila`}
                            </p>
                            <p className="pw-fraco" style={{ margin: 0 }}>
                                {enviados === 1 ? '1 já no servidor' : `${enviados} já no servidor`}
                            </p>
                        </div>
                        <Selo tom={pendentes === 0 ? 'ok' : 'alerta'}>
                            <Icone nome="nuvem" tamanho={13} />
                            {pendentes === 0 ? 'Sincronizado' : 'Pendente'}
                        </Selo>
                    </div>

                    {enviando && (
                        <div className="pw-progresso">
                            <div style={{ width: `${progresso}%` }} />
                        </div>
                    )}

                    <button
                        type="button"
                        className="pw-btn pw-btn-acao"
                        style={{ marginTop: 14 }}
                        onClick={() => {
                            setProgresso(0);
                            setEnviando(true);
                        }}
                        disabled={enviando || pendentes === 0}
                    >
                        <Icone
                            nome="atualizar"
                            tamanho={19}
                            className={enviando ? 'pw-girando' : undefined}
                        />
                        {enviando ? 'Enviando…' : 'Sincronizar agora'}
                    </button>
                </div>

                <p className="pw-titulo-secao">Na fila</p>

                {fila.length === 0 ? (
                    <div className="pw-fila-item">
                        <span
                            className="pw-fila-marca"
                            style={{ background: 'var(--pw-ok-suave)', color: 'var(--pw-ok)' }}
                        >
                            <Icone nome="certo" tamanho={20} />
                        </span>
                        <span>
                            <span className="pw-forte" style={{ display: 'block' }}>
                                Nenhum registro esperando
                            </span>
                            <span className="pw-fraco">Tudo o que você registrou já chegou à Retaguarda.</span>
                        </span>
                    </div>
                ) : (
                    fila.map((r) => (
                        <div key={r.id} className="pw-fila-item">
                            <span
                                className="pw-fila-marca"
                                style={{
                                    background:
                                        r.envio === 'erro' ? 'var(--pw-perigo-suave)' : 'var(--pw-info-suave)',
                                    color: r.envio === 'erro' ? 'var(--pw-perigo)' : 'var(--pw-info)',
                                }}
                            >
                                <Icone nome={r.envio === 'erro' ? 'erro' : 'relogio'} tamanho={20} />
                            </span>
                            <span style={{ flex: 1, minWidth: 0 }}>
                                <span className="pw-forte" style={{ display: 'block' }}>
                                    {r.hora} · {nomeRegiao(r.regiao)}
                                </span>
                                <span className="pw-fraco">
                                    {r.endereco} ·{' '}
                                    {r.fotos === 1 ? '1 foto' : `${r.fotos} fotos`}
                                </span>
                                {r.envio === 'erro' && (
                                    <span
                                        className="pw-fraco"
                                        style={{ display: 'block', color: 'var(--pw-perigo)' }}
                                    >
                                        Falhou no envio anterior — será tentado de novo.
                                    </span>
                                )}
                            </span>
                        </div>
                    ))
                )}

                <p className="pw-titulo-secao">Como funciona sem sinal</p>

                <div className="pw-card">
                    <ol style={{ margin: 0, paddingLeft: 20, fontSize: 14.5, lineHeight: 1.7 }}>
                        <li>Você registra: fica gravado no aparelho, com foto e coordenada.</li>
                        <li>Sem rede, o registro espera aqui — nada se perde e você segue trabalhando.</li>
                        <li>Voltou o sinal, ele sobe sozinho e o selo vira “Enviado”.</li>
                        <li>Se o envio falhar, o aplicativo tenta de novo e avisa nesta tela.</li>
                    </ol>
                </div>
            </div>
        </div>
    );
}
