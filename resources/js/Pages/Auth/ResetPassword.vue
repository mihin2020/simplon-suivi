<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const props = defineProps<{
    token: string
    email: string
}>()

const form = useForm({
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(`/reinitialisation/${props.token}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Réinitialisation du mot de passe" />

    <div class="form-wrap">
        <div class="heading">
            <h1>Nouveau mot de passe</h1>
            <p>Compte : <strong>{{ email }}</strong></p>
        </div>

        <form @submit.prevent="submit">
            <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="Minimum 8 caractères"
                    :class="['input', { 'input-error': form.errors.password }]"
                />
                <span v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</span>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    :class="['input', { 'input-error': form.errors.password_confirmation }]"
                />
                <span v-if="form.errors.password_confirmation" class="error-msg">{{ form.errors.password_confirmation }}</span>
            </div>

            <button type="submit" class="btn-submit" :disabled="form.processing">
                <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75" />
                </svg>
                {{ form.processing ? 'Enregistrement...' : 'Réinitialiser le mot de passe' }}
            </button>
        </form>
    </div>
</template>

<style scoped>
.form-wrap { width: 100%; }

.heading { margin-bottom: 1.5rem; }
.heading h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin: 0 0 0.25rem; }
.heading p { font-size: 0.875rem; color: #6b7280; margin: 0; }
.heading strong { color: #374151; }

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
</style>
