import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import PasswordInput from '@/components/password-input';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Definir a senha" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="form-group">
                            <label className="form-label" htmlFor="email">
                                E-mail
                            </label>
                            {/* Só leitura: o e-mail vem do link recebido, e mudá-lo
                                aqui invalidaria o próprio link. */}
                            <input
                                id="email"
                                name="email"
                                type="email"
                                className="form-control"
                                autoComplete="email"
                                value={email}
                                readOnly
                            />
                            {errors.email && (
                                <p className="form-erro">{errors.email}</p>
                            )}
                        </div>

                        <div
                            className="form-group"
                            data-erro={errors.password ? '1' : undefined}
                        >
                            <label className="form-label" htmlFor="password">
                                Senha nova
                            </label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                placeholder="Senha nova"
                                passwordrules={passwordRules}
                            />
                            {errors.password && (
                                <p className="form-erro">{errors.password}</p>
                            )}
                        </div>

                        <div
                            className="form-group"
                            data-erro={
                                errors.password_confirmation ? '1' : undefined
                            }
                        >
                            <label
                                className="form-label"
                                htmlFor="password_confirmation"
                            >
                                Repita a senha nova
                            </label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                placeholder="Repita a senha nova"
                                passwordrules={passwordRules}
                            />
                            {errors.password_confirmation && (
                                <p className="form-erro">
                                    {errors.password_confirmation}
                                </p>
                            )}
                        </div>

                        <BotaoAcao
                            type="submit"
                            icone={<KeyRound size={16} aria-hidden />}
                            carregando={processing}
                            rotuloCarregando="Salvando…"
                            className="btn btn-primary btn-block"
                            data-test="reset-password-button"
                        >
                            Salvar a senha e entrar
                        </BotaoAcao>
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Definir a senha',
    description: 'Escolha a senha que você vai usar para entrar no sistema',
};
