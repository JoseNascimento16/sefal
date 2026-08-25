import { Form, Head, usePage } from '@inertiajs/react';
import { Save } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;
    // Numa cópia local: dentro do corpo do formulário, o TypeScript já não sabe
    // que a checagem acima aconteceu.
    const usuario = auth.user;

    if (!usuario) {
        return null;
    }

    return (
        <>
            <Head title="Meus dados" />

            <div style={{ marginBottom: 20 }}>
                <h2 className="card-titulo">Meus dados</h2>
                <p className="card-sub">
                    Nome e e-mail. A matrícula é definida pela administração e
                    não muda por aqui.
                </p>
            </div>

            <Form
                {...ProfileController.update.form()}
                options={{ preserveScroll: true }}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="form-group">
                            <label className="form-label" htmlFor="matricula">
                                Matrícula
                            </label>
                            {/* Só leitura: aparece porque é o identificador de
                                quem está logado, em MAIÚSCULA como no crachá. */}
                            <input
                                id="matricula"
                                className="form-control"
                                value={usuario.login.toUpperCase()}
                                readOnly
                                disabled
                            />
                        </div>

                        <div
                            className="form-group"
                            data-erro={errors.name ? '1' : undefined}
                        >
                            <label className="form-label" htmlFor="name">
                                Nome
                            </label>
                            <input
                                id="name"
                                name="name"
                                className="form-control"
                                defaultValue={usuario.name}
                                required
                                autoComplete="name"
                                placeholder="Nome completo"
                            />
                            {errors.name && (
                                <p className="form-erro">{errors.name}</p>
                            )}
                        </div>

                        <div
                            className="form-group"
                            data-erro={errors.email ? '1' : undefined}
                        >
                            <label className="form-label" htmlFor="email">
                                E-mail
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                className="form-control"
                                defaultValue={usuario.email}
                                required
                                autoComplete="email"
                                placeholder="nome@salvador.ba.gov.br"
                            />
                            {errors.email && (
                                <p className="form-erro">{errors.email}</p>
                            )}
                            <p className="form-ajuda">
                                É por este e-mail que chega o link para definir
                                uma senha nova.
                            </p>
                        </div>

                        <BotaoAcao
                            type="submit"
                            icone={<Save size={16} aria-hidden />}
                            carregando={processing}
                            rotuloCarregando="Salvando…"
                            className="btn btn-primary"
                            data-test="update-profile-button"
                        >
                            Salvar
                        </BotaoAcao>
                    </>
                )}
            </Form>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Meu Perfil',
            href: edit(),
        },
    ],
};
