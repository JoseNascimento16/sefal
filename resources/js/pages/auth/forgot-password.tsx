import { Form, Head } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import TextLink from '@/components/text-link';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Esqueci minha senha" />

            {status && (
                <p
                    className="selo selo-ok"
                    style={{ marginBottom: 16, display: 'inline-flex' }}
                >
                    {status}
                </p>
            )}

            <Form {...email.form()}>
                {({ processing, errors }) => (
                    <>
                        <div
                            className="form-group"
                            data-erro={errors.email ? '1' : undefined}
                        >
                            <label className="form-label" htmlFor="email">
                                E-mail cadastrado
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                className="form-control"
                                autoComplete="off"
                                autoFocus
                                required
                                placeholder="nome@salvador.ba.gov.br"
                            />
                            {errors.email && (
                                <p className="form-erro">{errors.email}</p>
                            )}
                            <p className="form-ajuda">
                                É o e-mail que a administração cadastrou na sua
                                conta. Se não souber qual é, procure o
                                administrador do sistema.
                            </p>
                        </div>

                        <BotaoAcao
                            type="submit"
                            icone={<Mail size={16} aria-hidden />}
                            carregando={processing}
                            rotuloCarregando="Enviando…"
                            className="btn btn-primary btn-block"
                            data-test="email-password-reset-link-button"
                        >
                            Enviar o link por e-mail
                        </BotaoAcao>
                    </>
                )}
            </Form>

            <p
                className="form-ajuda"
                style={{ marginTop: 18, textAlign: 'center' }}
            >
                Ou volte para <TextLink href={login()}>entrar</TextLink>.
            </p>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Esqueci minha senha',
    description:
        'Informe seu e-mail e enviaremos um link para você definir uma senha nova',
};
