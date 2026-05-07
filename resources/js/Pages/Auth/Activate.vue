<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const props = defineProps<{
    token: string
    user: {
        full_name: string
        email: string
    }
}>()

const form = useForm({
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(`/activation/${props.token}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Activer mon compte" />

    <div class="activate-form">
        <div class="activate-heading">
            <h1>Bienvenue, {{ user.full_name }} !</h1>
            <p>Choisissez un mot de passe pour activer votre compte.</p>
        </div>

        <!-- Info utilisateur -->
        <div class="user-info">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" />
            </svg>
            <span>{{ user.email }}</span>
        </div>

        <form @submit.prevent="submit">
            <!-- Mot de passe -->
            <div class="field">
                <label for="password">Mot de passe</label>
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

            <!-- Confirmation -->
            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Répétez le mot de passe"
                    :class="['input', { 'input-error': form.errors.password_confirmation }]"
                />
                <span v-if="form.errors.password_confirmation" class="error-msg">{{ form.errors.password_confirmation }}</span>
            </div>

            <button
                type="submit"
                class="btn-submit"
                :disabled="form.processing"
            >
                <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75" />
                </svg>
                {{ form.processing ? 'Activation en cours...' : 'Activer mon compte' }}
            </button>
        </form>
    </div>
</template>

<style scoped>
.activate-form {
    width: 100%;
}

.activate-heading {
    margin-bottom: 1.25rem;
}

.activate-heading h1 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.25rem;
}

.activate-heading p {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    color: #374151;
}

.user-info svg {
    width: 1rem;
    height: 1rem;
    color: #9ca3af;
    flex-shrink: 0;
}

form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.field label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    background-color: #ffffff;
    padding: 0.625rem 0.875rem;
    font-size: 0.9rem;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
}

.input::placeholder {
    color: #9ca3af;
}

.input:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgb(229 0 76 / 0.12);
}

.input-error {
    border-color: #ef4444;
}

.input-error:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgb(239 68 68 / 0.12);
}

.error-msg {
    font-size: 0.8rem;
    color: #dc2626;
}

.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.7rem 1rem;
    background-color: #E5004C;
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 600;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background-color 0.15s, opacity 0.15s;
    margin-top: 0.25rem;
}

.btn-submit:hover:not(:disabled) {
    background-color: #c4003f;
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner {
    width: 1rem;
    height: 1rem;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
