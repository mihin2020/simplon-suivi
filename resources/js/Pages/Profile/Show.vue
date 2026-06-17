<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    user: {
        id: string
        first_name: string
        last_name: string
        email: string
        role: string
        role_label: string
    }
}>()

const infoForm = useForm({
    first_name: props.user.first_name,
    last_name:  props.user.last_name,
    email:      props.user.email,
})

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
})

const submitInfo = () => {
    infoForm.put('/profil/informations')
}

const submitPassword = () => {
    passwordForm.put('/profil/mot-de-passe', {
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <Head title="Mon profil" />

    <div class="max-w-2xl mx-auto space-y-xl">

        <div class="flex items-center gap-md">
            <div class="page-header-icon">
                <span class="material-symbols-outlined">account_circle</span>
            </div>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Mon profil</h1>
                <p class="text-body-md text-secondary mt-xs">Gérez vos informations personnelles et votre mot de passe.</p>
            </div>
        </div>

        <!-- Avatar + rôle -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl p-lg flex items-center gap-lg shadow-sm">
            <div class="avatar-lg">
                {{ user.first_name.charAt(0) }}{{ user.last_name.charAt(0) }}
            </div>
            <div>
                <p class="text-h2 font-semibold text-on-surface">{{ user.first_name }} {{ user.last_name }}</p>
                <span class="role-badge">{{ user.role_label }}</span>
            </div>
        </div>

        <!-- Informations personnelles -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest">
                <h2 class="text-h2 font-semibold text-on-surface">Informations personnelles</h2>
            </div>
            <form @submit.prevent="submitInfo" class="p-lg space-y-md">
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label>Prénom</label>
                        <input v-model="infoForm.first_name" type="text" :class="['input', { 'input-error': infoForm.errors.first_name }]" />
                        <span v-if="infoForm.errors.first_name" class="error-msg">{{ infoForm.errors.first_name }}</span>
                    </div>
                    <div class="field">
                        <label>Nom</label>
                        <input v-model="infoForm.last_name" type="text" :class="['input', { 'input-error': infoForm.errors.last_name }]" />
                        <span v-if="infoForm.errors.last_name" class="error-msg">{{ infoForm.errors.last_name }}</span>
                    </div>
                </div>
                <div class="field">
                    <label>Adresse email</label>
                    <input v-model="infoForm.email" type="email" :class="['input', { 'input-error': infoForm.errors.email }]" />
                    <span v-if="infoForm.errors.email" class="error-msg">{{ infoForm.errors.email }}</span>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="infoForm.processing">
                        {{ infoForm.processing ? 'Enregistrement...' : 'Enregistrer' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Changer le mot de passe -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest">
                <h2 class="text-h2 font-semibold text-on-surface">Changer le mot de passe</h2>
            </div>
            <form @submit.prevent="submitPassword" class="p-lg space-y-md">
                <div class="field">
                    <label>Mot de passe actuel</label>
                    <input v-model="passwordForm.current_password" type="password" :class="['input', { 'input-error': passwordForm.errors.current_password }]" placeholder="••••••••" />
                    <span v-if="passwordForm.errors.current_password" class="error-msg">{{ passwordForm.errors.current_password }}</span>
                </div>
                <div class="field">
                    <label>Nouveau mot de passe</label>
                    <input v-model="passwordForm.password" type="password" :class="['input', { 'input-error': passwordForm.errors.password }]" placeholder="Minimum 8 caractères" />
                    <span v-if="passwordForm.errors.password" class="error-msg">{{ passwordForm.errors.password }}</span>
                </div>
                <div class="field">
                    <label>Confirmer le nouveau mot de passe</label>
                    <input v-model="passwordForm.password_confirmation" type="password" :class="['input', { 'input-error': passwordForm.errors.password_confirmation }]" placeholder="••••••••" />
                    <span v-if="passwordForm.errors.password_confirmation" class="error-msg">{{ passwordForm.errors.password_confirmation }}</span>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="passwordForm.processing">
                        {{ passwordForm.processing ? 'Enregistrement...' : 'Changer le mot de passe' }}
                    </button>
                </div>
            </form>
        </div>

    </div>
</template>

<style scoped>
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

.avatar-lg {
    width: 64px; height: 64px; border-radius: 50%;
    background: #E5004C; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 700; flex-shrink: 0;
    text-transform: uppercase;
}

.role-badge {
    display: inline-flex; align-items: center;
    padding: 2px 12px; border-radius: 99px;
    background: #f0f4ff; color: #1e40af;
    font-size: 12px; font-weight: 600; margin-top: 4px;
}

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

.btn-primary {
    padding: 0.55rem 1.25rem; background: #E5004C; color: #fff;
    font-size: 0.875rem; font-weight: 600; border: none; border-radius: 0.5rem;
    cursor: pointer; transition: background 0.15s;
}
.btn-primary:hover:not(:disabled) { background: #c4003f; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
