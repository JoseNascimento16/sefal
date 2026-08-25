import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import PasswordInput from '@/components/password-input';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { store } from '@/routes/password/confirm';

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
                            <label className="form-label" htmlFor="password">
                                Senha
                            </label>
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
