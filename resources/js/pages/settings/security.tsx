import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import PasswordInput from '@/components/password-input';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
};

export default function Security({ passwordRules }: Props) {
    const senhaNova = useRef<HTMLInputElement>(null);
    const senhaAtual = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Minha senha" />

            <div style={{ marginBottom: 20 }}>
                <h2 className="card-titulo">Minha senha</h2>
                <p className="card-sub">
                    Use uma senha longa e que você não use em outro lugar.
                </p>
            </div>

            <Form
                {...SecurityController.update.form()}
                options={{ preserveScroll: true }}
                resetOnError={[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]}
                resetOnSuccess
                onError={(errors) => {
                    // O foco volta para o campo que errou: sem isso, quem digitou
                    // errado tem de caçar na tela onde está o problema.
                    if (errors.password) {
                        senhaNova.current?.focus();
                    }

                    if (errors.current_password) {
                        senhaAtual.current?.focus();
                    }
                }}
            >
                {({ errors, processing }) => (
                    <>
                        <div
                            className="form-group"
                            data-erro={
                                errors.current_password ? '1' : undefined
                            }
                        >
                            <label
                                className="form-label"
                                htmlFor="current_password"
                            >
                                Senha atual
                            </label>
                            <PasswordInput
                                id="current_password"
                                ref={senhaAtual}
                                name="current_password"
                                autoComplete="current-password"
                                placeholder="Senha atual"
                            />
                            {errors.current_password && (
                                <p className="form-erro">
                                    {errors.current_password}
                                </p>
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
                                ref={senhaNova}
                                name="password"
                                autoComplete="new-password"
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
                            rotuloCarregando="Trocando…"
                            className="btn btn-primary"
                            data-test="update-password-button"
                        >
                            Trocar a senha
                        </BotaoAcao>
                    </>
                )}
            </Form>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Meu Perfil',
            href: edit(),
        },
    ],
};
