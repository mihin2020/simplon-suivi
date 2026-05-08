<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const page = usePage()

const form = useForm({
    email: '',
})

const submit = () => {
    form.post('/mot-de-passe-oublie')
}
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div class="form-wrap">
        <div class="heading">
            <h1>Mot de passe oublié</h1>
            <p>Saisissez votre email pour recevoir un lien de réinitialisation.</p>
        </div>

        <div v-if="(page.props.flash as any)?.success" class="alert-success">
            <span class="material-symbols-outlined">check_circle</span>
            {{ (page.props.flash as any).success }}
        </div>

        <form @submit.prevent="submit">
            <div class="field">
                <label for="email">Adresse email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    placeholder="votre@email.com"
                    :class="['input', { 'input-error': form.errors.email }]"
                />
                <span v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</span>
            </div>

            <button type="submit" class="btn-submit" :disabled="form.processing">
                <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75" />
                </svg>
                {{ form.processing ? 'Envoi en cours...' : 'Envoyer le lien' }}
            </button>
        </form>

        <div class="back-link">
            <Link href="/connexion">← Retour à la connexion</Link>
        </div>
    </div>
</template>

<style scoped>
.form-wrap { width: 100%; }

.heading { margin-bottom: 1.5rem; }
.heading h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin: 0 0 0.25rem; }
.heading p { font-size: 0.875rem; color: #6b7280; margin: 0; }

.alert-success {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
}

form { display: flex; flex-direction: column; gap: 1.25rem; }

.field { display: flex; flex-direction: column; gap: 0.375rem; }
.field label { font-size: 0.875rem; font-weight: 500; color: #374151; }

.input {
    width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem;
    background: #fff; padding: 0.625rem 0.875rem; font-size: 0.9rem;
    color: #111827; outline: none; box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgb(229 0 76 / 0.12); }
.input-error { border-color: #ef4444; }
.error-msg { font-size: 0.8rem; color: #dc2626; }

.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%; padding: 0.7rem 1rem; background: #E5004C; color: #fff;
    font-size: 0.9rem; font-weight: 600; border: none; border-radius: 0.5rem;
    cursor: pointer; transition: background 0.15s; margin-top: 0.25rem;
}
.btn-submit:hover:not(:disabled) { background: #c4003f; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner { width: 1rem; height: 1rem; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.back-link { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; }
.back-link a { color: #E5004C; text-decoration: none; font-weight: 500; }
.back-link a:hover { text-decoration: underline; }
</style>
