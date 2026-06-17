<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PermissionPicker from '@/Components/PermissionPicker.vue'

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

interface TrainerProfile {
    id: string
    name: string
}

const props = defineProps<{
    roles: RoleOption[]
    permissions: Permission[]
    trainerProfiles: TrainerProfile[]
}>()

const form = useForm({
    first_name:  '',
    last_name:   '',
    email:       '',
    role:        '',
    permissions: [] as number[],
    profile_id:  '',
    phone:       '',
    phone2:      '',
    cv:          null as File | null,
})

const selectedRole    = ref('')
const showPermissions = computed(() => selectedRole.value === 'admin')
const showTrainerFields = computed(() => selectedRole.value === 'trainer')

const cvFileName = ref<string | null>(null)

const onCvChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    form.cv = file
    cvFileName.value = file?.name ?? null
}

const removeCv = () => {
    form.cv = null
    cvFileName.value = null
}

watch(selectedRole, (val) => {
    form.role = val
    if (val !== 'admin') form.permissions = []
    if (val !== 'trainer') {
        form.profile_id = ''
        form.phone = ''
        form.phone2 = ''
        form.cv = null
        cvFileName.value = null
    }
})

const submit = () => {
    form.post('/users', {
        forceFormData: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Inviter un utilisateur" />
    <div class="max-w-3xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center gap-md">
            <Link href="/users" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Inviter un utilisateur</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Créez un compte Administrateur ou Formateur. Un email d'activation sera envoyé.
                </p>
            </div>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="card">
            <!-- Identité -->
            <div class="section">
                <h2 class="section-title">Identité</h2>
                <div class="grid-2">
                    <div class="field">
                        <label class="label">Nom <span class="required">*</span></label>
                        <input v-model="form.last_name" type="text" class="input" :class="{ 'input-error': form.errors.last_name }" autofocus />
                        <p v-if="form.errors.last_name" class="error-msg">{{ form.errors.last_name }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Prénom <span class="required">*</span></label>
                        <input v-model="form.first_name" type="text" class="input" :class="{ 'input-error': form.errors.first_name }" />
                        <p v-if="form.errors.first_name" class="error-msg">{{ form.errors.first_name }}</p>
                    </div>
                </div>
                <div class="field mt-md">
                    <label class="label">Email <span class="required">*</span></label>
                    <input v-model="form.email" type="email" class="input" :class="{ 'input-error': form.errors.email }" />
                    <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
                </div>
            </div>

            <!-- Rôle -->
            <div class="section">
                <h2 class="section-title">Rôle</h2>
                <div class="field">
                    <label class="label">Type de compte <span class="required">*</span></label>
                    <select v-model="selectedRole" class="input" :class="{ 'input-error': form.errors.role }">
                        <option value="">Sélectionner</option>
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <p v-if="form.errors.role" class="error-msg">{{ form.errors.role }}</p>
                </div>

                <div v-if="selectedRole === 'admin'" class="hint mt-sm">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">info</span>
                    L'Administrateur nécessite des permissions spécifiques. Sélectionnez-les ci-dessous.
                </div>
            </div>

            <!-- Champs Formateur -->
            <div v-if="showTrainerFields" class="section space-y-md">
                <h2 class="section-title">Informations du formateur</h2>

                <!-- Profil -->
                <div class="field">
                    <div class="flex items-center justify-between">
                        <label class="label">Profil</label>
                        <Link href="/trainer-profiles" class="config-link" target="_blank">
                            <span class="material-symbols-outlined" style="font-size:14px">settings</span>
                            Configurer les profils
                        </Link>
                    </div>
                    <select v-model="form.profile_id" class="input" :class="{ 'input-error': form.errors.profile_id }">
                        <option value="">Sélectionner un profil</option>
                        <option v-for="p in props.trainerProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <p v-if="form.errors.profile_id" class="error-msg">{{ form.errors.profile_id }}</p>
                    <p v-if="props.trainerProfiles.length === 0" class="hint-text">
                        Aucun profil configuré.
                        <Link href="/trainer-profiles" class="text-primary underline">Ajouter des profils →</Link>
                    </p>
                </div>

                <!-- Téléphones -->
                <div>
                    <p class="section-label">Contacts</p>
                    <div class="grid-2 mt-sm">
                        <div class="field">
                            <label class="label">Téléphone 1</label>
                            <input v-model="form.phone" type="tel" class="input" :class="{ 'input-error': form.errors.phone }" placeholder="+226 XX XX XX XX" />
                            <p v-if="form.errors.phone" class="error-msg">{{ form.errors.phone }}</p>
                        </div>
                        <div class="field">
                            <label class="label">Téléphone 2</label>
                            <input v-model="form.phone2" type="tel" class="input" :class="{ 'input-error': form.errors.phone2 }" placeholder="+226 XX XX XX XX" />
                            <p v-if="form.errors.phone2" class="error-msg">{{ form.errors.phone2 }}</p>
                        </div>
                    </div>
                </div>

                <!-- CV -->
                <div class="field">
                    <label class="label">CV</label>
                    <div v-if="cvFileName" class="cv-selected">
                        <span class="material-symbols-outlined cv-icon">description</span>
                        <span class="cv-name">{{ cvFileName }}</span>
                        <button type="button" class="cv-remove" @click="removeCv" title="Retirer">
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>
                    <label v-else class="cv-upload" :class="{ 'upload-error': form.errors.cv }">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#adb5bd">upload_file</span>
                        <span class="cv-upload-text">Cliquer pour uploader le CV</span>
                        <span class="cv-upload-hint">PDF, DOC, DOCX · 5 Mo max</span>
                        <input type="file" accept=".pdf,.doc,.docx" class="sr-only" @change="onCvChange" />
                    </label>
                    <p v-if="form.errors.cv" class="error-msg">{{ form.errors.cv }}</p>
                </div>

                <!-- Notice -->
                <div class="notice">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#2563eb">info</span>
                    <p class="text-body-sm" style="color:#1d4ed8">
                        Le formateur recevra un email avec un lien d'activation valable 72h pour définir son mot de passe.
                        Il n'aura accès qu'à la saisie des présences pour les formations qui lui sont assignées.
                    </p>
                </div>
            </div>

            <!-- Permissions (Admin uniquement) -->
            <div v-if="showPermissions" class="section">
                <PermissionPicker v-model="form.permissions" :permissions="permissions" />
                <p v-if="form.errors.permissions" class="error-msg mt-sm">{{ form.errors.permissions }}</p>
            </div>

            <!-- Actions -->
            <div class="actions">
                <Link href="/users" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-small" />
                    <span v-else class="material-symbols-outlined" style="font-size:18px">send</span>
                    Envoyer l'invitation
                </button>
            </div>
        </form>

    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

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
.input-error {
    border-color: #ba1a1a;
}
.error-msg {
    font-size: 12px;
    color: #ba1a1a;
}

.hint {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #f0f3f6;
    border-radius: 8px;
    font-size: 13px;
    color: #1F3A4D;
}

select.input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    padding-right: 36px;
}

.perm-panel { border: 1px solid #e0e3e5; border-radius: 12px; overflow: hidden; }
.perm-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}
.perm-count { font-size: 13px; color: #6b7280; margin: 4px 0 0; }
.perm-bulk-actions { display: flex; gap: 8px; }
.perm-bulk-btn {
    font-size: 12px;
    font-weight: 600;
    color: #E5004C;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    cursor: pointer;
    padding: 6px 12px;
    transition: all 0.15s;
}
.perm-bulk-btn:hover { background: #fff5f8; border-color: #E5004C; }

.perm-groups { display: grid; gap: 16px; padding: 20px 24px; }
.perm-group-card { border: 1px solid #f3f4f6; border-radius: 10px; padding: 16px; background: #fff; }
.perm-group-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.perm-group-card-title { font-size: 13px; font-weight: 700; color: #191c1e; text-transform: uppercase; letter-spacing: 0.03em; margin: 0; }
.perm-group-badge { font-size: 11px; font-weight: 600; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 99px; }
.perm-chips { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
.perm-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    background: #fff;
}
.perm-chip:hover { border-color: #16a34a; background: #f0fdf4; }
.perm-chip-selected { border-color: #16a34a; background: #f0fdf4; box-shadow: 0 1px 2px rgba(22,163,74,0.08); }
.perm-chip-check {
    width: 18px; height: 18px;
    border: 2px solid #e0e3e5; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.15s;
    color: #fff; font-size: 12px;
}
.perm-chip-selected .perm-chip-check { background: #16a34a; border-color: #16a34a; }
.perm-chip-name { font-size: 13px; font-weight: 500; color: #374151; }
.perm-chip-selected .perm-chip-name { color: #16a34a; font-weight: 600; }

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
.space-y-md > * + * { margin-top: 16px; }

.section-label {
    font-size: 11px; font-weight: 700; color: #adb5bd;
    text-transform: uppercase; letter-spacing: 0.06em;
}
.hint-text { font-size: 11px; color: #9aaabb; margin-top: 2px; }

.config-link {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; font-weight: 600; color: #515f74;
    text-decoration: none; transition: color 0.15s;
}
.config-link:hover { color: #E5004C; }

.cv-upload {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 4px; padding: 24px 20px;
    border: 2px dashed #e0e3e5; border-radius: 10px;
    cursor: pointer; transition: border-color 0.2s, background 0.2s; background: #fafafa;
}
.cv-upload:hover { border-color: #E5004C; background: #fff5f8; }
.upload-error { border-color: #ba1a1a; }
.cv-upload-text { font-size: 14px; font-weight: 500; color: #515f74; }
.cv-upload-hint { font-size: 12px; color: #adb5bd; }

.cv-selected {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; border: 1px solid #d1fae5;
    border-radius: 8px; background: #f0fdf4;
}
.cv-icon { font-size: 22px; color: #059669; flex-shrink: 0; }
.cv-name { flex: 1; font-size: 13px; font-weight: 500; color: #065f46; word-break: break-all; }
.cv-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(186,26,26,0.08); border: none; cursor: pointer;
    color: #ba1a1a; transition: background 0.15s; flex-shrink: 0;
}
.cv-remove:hover { background: rgba(186,26,26,0.16); }

.notice {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px; background: #eff6ff;
    border: 1px solid #bfdbfe; border-radius: 8px;
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
