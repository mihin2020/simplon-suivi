<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/connexion', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Connexion" />

    <div class="login-form">
        <!-- Titre -->
        <div class="login-heading">
            <h1>Connexion</h1>
            <p>Accès réservé aux administrateurs et formateurs.</p>
        </div>

        <form @submit.prevent="submit">
            <!-- Email -->
            <div class="field">
                <label for="email">Adresse email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    placeholder="admin@simplon.bf"
                    :class="['input', { 'input-error': form.errors.email }]"
                />
                <span v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</span>
            </div>

            <!-- Mot de passe -->
            <div class="field">
                <label for="password">Mot de passe</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    :class="['input', { 'input-error': form.errors.password }]"
                />
                <span v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</span>
            </div>

            <!-- Se souvenir de moi -->
            <div class="remember">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                />
                <label for="remember">Se souvenir de moi</label>
            </div>

            <!-- Bouton -->
            <button
                type="submit"
                class="btn-submit"
                :disabled="form.processing"
            >
                <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75" />
                </svg>
                {{ form.processing ? 'Connexion en cours...' : 'Se connecter' }}
            </button>
        </form>
    </div>
</template>

<style scoped>
.login-form {
    width: 100%;
}

.login-heading {
    margin-bottom: 1.5rem;
}

.login-heading h1 {
    font-size: 1.375rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.25rem;
}

.login-heading p {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
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

.remember {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.remember input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    accent-color: #E5004C;
    cursor: pointer;
}

.remember label {
    font-size: 0.875rem;
    color: #6b7280;
    cursor: pointer;
    user-select: none;
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
