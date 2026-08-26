import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import PasswordInput from '@/components/password-input';
import { BotaoAcao } from '@/components/retaguarda/acao';
import TextLink from '@/components/text-link';
import { request as pedirRedefinicao } from '@/routes/password';
import { store } from '@/routes/password/confirm';
import { inicio } from '@/routes/retaguarda';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar a senha" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <>
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
                                <label className="form-label" htmlFor="password">
                                    Senha
                                </label>
                                {/* Quem não lembra a senha precisa poder pedir a
                                    redefinição de DENTRO do fluxo que a exige —
                                    como na tela de entrar. */}
                                <TextLink
                                    href={pedirRedefinicao()}
                                    className="text-sm"
                                >
                                    Esqueci minha senha
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Sua senha"
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            {errors.password && (
                                <p className="form-erro">{errors.password}</p>
                            )}
                        </div>

                        <BotaoAcao
                            type="submit"
                            icone={<LockKeyhole size={16} aria-hidden />}
                            carregando={processing}
                            rotuloCarregando="Confirmando…"
                            className="btn btn-primary btn-block"
                            data-test="confirm-password-button"
                        >
                            Confirmar
                        </BotaoAcao>

                        {/* A saída. Esta tela não tem menu nem barra: sem um
                            caminho de volta, quem cai aqui por engano só sai
                            pelo botão do navegador — e a sessão segue aberta,
                            então há para onde voltar. */}
                        <p
                            className="form-ajuda"
                            style={{ marginTop: 18, textAlign: 'center' }}
                        >
                            <TextLink href={inicio()}>
                                Voltar ao sistema
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmar a senha',
    description:
        'Esta é uma área protegida do sistema. Confirme a sua senha para continuar.',
};
