<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Permission {
    id: number
    name: string
    slug: string
    group: string | null
}

interface RoleOption {
    value: string
    label: string
}

interface UserData {
    id: string
    first_name: string
    last_name: string
    email: string
    role: string
    is_active: boolean
    permissions: Permission[]
}

interface TrainerProfile {
    id: string
    name: string
}

interface TrainerData {
    profile_id: string | null
    phone: string | null
    phone2: string | null
    cv_path: string | null
}

const props = defineProps<{
    user: UserData
    roles: RoleOption[]
    permissions: Permission[]
    userPermissions: number[]
    trainerProfiles: TrainerProfile[]
    trainerData: TrainerData | null
}>()

const form = useForm({
    first_name:  props.user.first_name,
    last_name:   props.user.last_name,
    email:       props.user.email,
    role:        props.user.role,
    is_active:   props.user.is_active,
    permissions: [...props.userPermissions],
    profile_id:  props.trainerData?.profile_id ?? '',
    phone:       props.trainerData?.phone ?? '',
    phone2:      props.trainerData?.phone2 ?? '',
    cv:          null as File | null,
})

const showPermissions = computed(() => form.role === 'admin')
const showTrainer     = computed(() => form.role === 'trainer')

const onCvChange = (e: Event) => {
    form.cv = (e.target as HTMLInputElement).files?.[0] ?? null
}

const submit = () => {
    form.transform(data => ({ ...data, _method: 'PUT' }))
        .post(`/users/${props.user.id}`, { forceFormData: true })
}

const groupedPermissions = computed(() => {
    const groups: Record<string, Permission[]> = {}
    for (const p of props.permissions) {
        const g = p.group ?? 'Autres'
        if (!groups[g]) groups[g] = []
        groups[g].push(p)
    }
    return groups
})

const isSelected = (id: number) => form.permissions.includes(id)

const togglePermission = (id: number) => {
    const idx = form.permissions.indexOf(id)
    if (idx === -1) {
        form.permissions = [...form.permissions, id]
    } else {
        form.permissions = form.permissions.filter(p => p !== id)
    }
}

</script>

<template>
    <div class="max-w-3xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div>
            <Link href="/users" class="back-link">
                <span class="material-symbols-outlined" style="font-size:16px">arrow_back</span>
                Retour à la liste
            </Link>
            <h1 class="text-h1 font-bold text-on-surface mt-sm">Modifier l'utilisateur</h1>
            <p class="text-body-md text-secondary mt-xs">
                {{ user.last_name }} {{ user.first_name }} · {{ user.email }}
            </p>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="card">
            <!-- Identité -->
            <div class="section">
                <h2 class="section-title">Identité</h2>
                <div class="grid-2">
                    <div class="field">
                        <label class="label">Prénom <span class="required">*</span></label>
                        <input v-model="form.first_name" type="text" class="input" :class="{ 'input-error': form.errors.first_name }" />
                        <p v-if="form.errors.first_name" class="error-msg">{{ form.errors.first_name }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Nom <span class="required">*</span></label>
                        <input v-model="form.last_name" type="text" class="input" :class="{ 'input-error': form.errors.last_name }" />
                        <p v-if="form.errors.last_name" class="error-msg">{{ form.errors.last_name }}</p>
                    </div>
                </div>
                <div class="field mt-md">
                    <label class="label">Email <span class="required">*</span></label>
                    <input v-model="form.email" type="email" class="input" :class="{ 'input-error': form.errors.email }" />
                    <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
                </div>
            </div>

            <!-- Rôle & Statut -->
            <div class="section">
                <h2 class="section-title">Rôle et statut</h2>
                <div class="grid-2">
                    <div class="field">
                        <label class="label">Type de compte <span class="required">*</span></label>
                        <select v-model="form.role" class="input" :class="{ 'input-error': form.errors.role }">
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <p v-if="form.errors.role" class="error-msg">{{ form.errors.role }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Statut du compte</label>
                        <div class="toggle-row">
                            <label class="toggle">
                                <input type="checkbox" v-model="form.is_active" />
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label">{{ form.is_active ? 'Actif' : 'Inactif' }}</span>
                        </div>
                        <p v-if="form.errors.is_active" class="error-msg">{{ form.errors.is_active }}</p>
                    </div>
                </div>

                <div v-if="form.role === 'admin'" class="hint mt-sm">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">info</span>
                    Définissez les permissions de cet Administrateur ci-dessous.
                </div>
                <div v-if="form.role === 'trainer'" class="hint mt-sm">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">info</span>
                    Complétez les informations du formateur ci-dessous.
                </div>
            </div>

            <!-- Informations Formateur -->
            <div v-if="showTrainer" class="section">
                <h2 class="section-title">Informations Formateur</h2>
                <div class="grid-2">
                    <div class="field">
                        <label class="label">Téléphone principal</label>
                        <input v-model="form.phone" type="text" class="input" placeholder="+226 XX XX XX XX"
                            :class="{ 'input-error': form.errors.phone }" />
                        <p v-if="form.errors.phone" class="error-msg">{{ form.errors.phone }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Téléphone secondaire</label>
                        <input v-model="form.phone2" type="text" class="input" placeholder="+226 XX XX XX XX" />
                    </div>
                </div>
                <div class="grid-2 mt-md">
                    <div class="field">
                        <div class="flex items-center justify-between">
                            <label class="label">Profil / Spécialité</label>
                            <a href="/configuration" target="_blank" class="config-link">
                                <span class="material-symbols-outlined" style="font-size:13px">settings</span>
                                Configurer
                            </a>
                        </div>
                        <select v-model="form.profile_id" class="input" :class="{ 'input-error': form.errors.profile_id }">
                            <option value="">Sélectionner</option>
                            <option v-for="p in trainerProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.profile_id" class="error-msg">{{ form.errors.profile_id }}</p>
                    </div>
                    <div class="field">
                        <label class="label">CV (PDF, Word)</label>
                        <div v-if="trainerData?.cv_path" class="cv-existing">
                            <span class="material-symbols-outlined" style="font-size:16px;color:#E5004C">picture_as_pdf</span>
                            <a :href="`/storage/${trainerData.cv_path}`" target="_blank" class="cv-link">
                                Voir le CV actuel
                            </a>
                            <span class="cv-replace-hint"> pour remplacer, sélectionnez un nouveau fichier ci-dessous</span>
                        </div>
                        <input type="file" accept=".pdf,.doc,.docx" @change="onCvChange" class="input file-input" />
                        <p v-if="form.errors.cv" class="error-msg">{{ form.errors.cv }}</p>
                    </div>
                </div>
            </div>

            <!-- Permissions (Admin uniquement) -->
            <div v-if="showPermissions" class="section">
                <h2 class="section-title">Permissions</h2>
                <div v-for="(perms, group) in groupedPermissions" :key="group" class="perm-group">
                    <h3 class="perm-group-title">{{ group }}</h3>
                    <div class="perm-list">
                        <div
                            v-for="p in perms"
                            :key="p.id"
                            class="perm-checkbox"
                            :class="{ 'perm-selected': isSelected(p.id) }"
                            @click.prevent="togglePermission(p.id)"
                        >
                            <span class="perm-check">
                                <span v-if="isSelected(p.id)" class="material-symbols-outlined" style="font-size:14px">check</span>
                            </span>
                            <span class="perm-name">{{ p.name }}</span>
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.permissions" class="error-msg">{{ form.errors.permissions }}</p>
            </div>

            <!-- Actions -->
            <div class="actions">
                <Link href="/users" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-small" />
                    <span v-else class="material-symbols-outlined" style="font-size:18px">save</span>
                    Enregistrer
                </button>
            </div>
        </form>

    </div>
</template>

<style scoped>
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #515f74;
    text-decoration: none;
    transition: color 0.15s;
}
.back-link:hover { color: #E5004C; }

.card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    overflow: hidden;
}

.section {
    padding: 24px;
    border-bottom: 1px solid #f3f4f6;
}
.section:last-child { border-bottom: none; }

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #191c1e;
    margin-bottom: 16px;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.label {
    font-size: 12px;
    font-weight: 600;
    color: #191c1e;
}
.required { color: #E5004C; }

.input {
    padding: 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
    outline: none;
}
.input:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.input-error { border-color: #ba1a1a; }
.error-msg {
    font-size: 12px;
    color: #ba1a1a;
}

.hint {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #eff6ff;
    border-radius: 8px;
    font-size: 13px;
    color: #1e40af;
}

select.input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    padding-right: 36px;
}

.toggle-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}
.toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    border-radius: 24px;
    transition: 0.3s;
}
.toggle-slider::before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: 0.3s;
}
.toggle input:checked + .toggle-slider {
    background-color: #E5004C;
}
.toggle input:checked + .toggle-slider::before {
    transform: translateX(20px);
}
.toggle-label {
    font-size: 14px;
    font-weight: 500;
    color: #191c1e;
}

.perm-group { margin-bottom: 16px; }
.perm-group-title {
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.perm-list {
    display: flex;
    flex-direction: column;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    overflow: hidden;
}
.perm-checkbox {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.15s;
    border-bottom: 1px solid #f3f4f6;
}
.perm-checkbox:last-child { border-bottom: none; }
.perm-checkbox:hover { background: #f9fafb; }
.perm-checkbox.perm-selected { background: #fff5f8; }
.perm-check {
    width: 18px;
    height: 18px;
    border: 2px solid #e0e3e5;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
    color: #fff;
}
.perm-checkbox.perm-selected .perm-check {
    background: #E5004C;
    border-color: #E5004C;
}
.perm-name {
    font-size: 14px;
    color: #191c1e;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px;
    border-top: 1px solid #f3f4f6;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-primary:hover { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid #e0e3e5;
    text-decoration: none;
    transition: background 0.15s;
}
.btn-secondary:hover { background: #f2f4f6; }

.spinner-small {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

.mt-sm { margin-top: 8px; }
.mt-md { margin-top: 16px; }

.file-input {
    padding: 7px 14px;
    cursor: pointer;
    color: #515f74;
}

.config-link {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 600;
    color: #515f74;
    text-decoration: none;
    transition: color 0.15s;
}
.config-link:hover { color: #E5004C; }

.cv-existing {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    background: #fff5f7;
    border: 1px solid #fcd5e0;
    padding: 6px 10px;
    border-radius: 6px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}
.cv-link {
    color: #E5004C;
    font-weight: 600;
    text-decoration: none;
    font-size: 12px;
}
.cv-link:hover { text-decoration: underline; }
.cv-replace-hint {
    color: #6b7280;
    font-size: 12px;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
</style>
