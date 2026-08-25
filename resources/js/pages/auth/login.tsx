import { Form, Head } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import PasswordInput from '@/components/password-input';
import { BotaoAcao } from '@/components/retaguarda/acao';
import TextLink from '@/components/text-link';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            {status && (
                <p
                    className="selo selo-ok"
                    style={{ marginBottom: 16, display: 'inline-flex' }}
                >
                    {status}
                </p>
            )}

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <>
                        <div
                            className="form-group"
                            data-erro={errors.login ? '1' : undefined}
                        >
                            <label className="form-label" htmlFor="login">
                                Matrícula
                            </label>
                            <input
                                id="login"
                                name="login"
                                type="text"
                                className="form-control"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="username"
                                placeholder="Sua matrícula"
                            />
                            {errors.login && (
                                <p className="form-erro">{errors.login}</p>
                            )}
                        </div>

                        <div
                            className="form-group"
                            data-erro={errors.password ? '1' : undefined}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'baseline',
                                    justifyContent: 'space-between',
                                    gap: 10,
                                }}
                            >
                                <label
                                    className="form-label"
                                    htmlFor="password"
                                >
                                    Senha
                                </label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                        tabIndex={5}
                                    >
                                        Esqueci minha senha
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Senha"
                            />
                            {errors.password && (
                                <p className="form-erro">{errors.password}</p>
                            )}
                        </div>

                        <label
                            className="form-group"
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 9,
                                fontSize: 13.5,
                                color: 'var(--sm-texto-corpo)',
                                cursor: 'pointer',
                            }}
                        >
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            Continuar conectado
                        </label>

                        <BotaoAcao
                            type="submit"
                            icone={<LogIn size={16} aria-hidden />}
                            carregando={processing}
                            rotuloCarregando="Entrando…"
                            className="btn btn-primary btn-block"
                            tabIndex={4}
                            data-test="login-button"
                        >
                            Entrar
                        </BotaoAcao>

                        {/*
                            Não há link de cadastro: a conta do servidor é criada
                            pela administração do sistema. Quem entra pela
                            primeira vez define a senha por "Esqueci minha senha".
                        */}
                        {canResetPassword && (
                            <p
                                className="form-ajuda"
                                style={{ marginTop: 18, textAlign: 'center' }}
                            >
                                Primeiro acesso? Use{' '}
                                <TextLink href={request()} tabIndex={5}>
                                    Esqueci minha senha
                                </TextLink>{' '}
                                para definir a sua.
                            </p>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Acesso à Retaguarda',
    description: 'Informe sua matrícula e senha para entrar',
};
