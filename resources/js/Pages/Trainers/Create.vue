<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }

defineProps<{ profiles: Profile[] }>()

const form = useForm({
    last_name:  '',
    first_name: '',
    email:      '',
    profile_id: '',
    phone:      '',
    phone2:     '',
    cv:         null as File | null,
})

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

const submit = () => form.post('/trainers', { forceFormData: true })
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link href="/trainers" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Inviter un Formateur</h1>
                <p class="text-body-md text-secondary mt-xs">Un email d'activation sera envoyé automatiquement.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="card space-y-lg">

            <!-- Identité -->
            <div>
                <p class="section-label">Identité</p>
                <div class="grid grid-cols-2 gap-md mt-sm">
                    <div class="field">
                        <label class="label">Nom <span class="required">*</span></label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            class="input"
                            :class="{ 'input-error': form.errors.last_name }"
                            autofocus
                        />
                        <p v-if="form.errors.last_name" class="error-msg">{{ form.errors.last_name }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Prénom <span class="required">*</span></label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            class="input"
                            :class="{ 'input-error': form.errors.first_name }"
                        />
                        <p v-if="form.errors.first_name" class="error-msg">{{ form.errors.first_name }}</p>
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div class="field">
                <label class="label">Adresse email <span class="required">*</span></label>
                <input
                    v-model="form.email"
                    type="email"
                    class="input"
                    :class="{ 'input-error': form.errors.email }"
                    placeholder="formateur@example.com"
                />
                <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
                <p class="hint">L'invitation sera envoyée à cette adresse.</p>
            </div>

            <!-- Profil -->
            <div class="field">
                <div class="flex items-center justify-between">
                    <label class="label">Profil</label>
                    <Link href="/trainer-profiles" class="config-link" target="_blank">
                        <span class="material-symbols-outlined" style="font-size:14px">settings</span>
                        Configurer les profils
                    </Link>
                </div>
                <select
                    v-model="form.profile_id"
                    class="input"
                    :class="{ 'input-error': form.errors.profile_id }"
                >
                    <option value="">Sélectionner un profil</option>
                    <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p v-if="form.errors.profile_id" class="error-msg">{{ form.errors.profile_id }}</p>
                <p v-if="profiles.length === 0" class="hint">
                    Aucun profil configuré.
                    <Link href="/trainer-profiles" class="text-primary underline">Ajouter des profils →</Link>
                </p>
            </div>

            <!-- Téléphones -->
            <div>
                <p class="section-label">Contacts</p>
                <div class="grid grid-cols-2 gap-md mt-sm">
                    <div class="field">
                        <label class="label">Téléphone 1</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="input"
                            :class="{ 'input-error': form.errors.phone }"
                            placeholder="+226 XX XX XX XX"
                        />
                        <p v-if="form.errors.phone" class="error-msg">{{ form.errors.phone }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Téléphone 2</label>
                        <input
                            v-model="form.phone2"
                            type="tel"
                            class="input"
                            :class="{ 'input-error': form.errors.phone2 }"
                            placeholder="+226 XX XX XX XX"
                        />
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
                    Le formateur recevra un email avec un lien d'activation valable 24h pour définir son mot de passe.
                    Il n'aura accès qu'à la saisie des présences pour les formations qui lui sont assignées.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm pt-md border-t border-surface-container-highest">
                <Link href="/trainers" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    <span v-else class="material-symbols-outlined" style="font-size:16px">send</span>
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
    color: #515f74; transition: background 0.15s; flex-shrink: 0;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 32px; }

.section-label {
    font-size: 11px; font-weight: 700; color: #adb5bd;
    text-transform: uppercase; letter-spacing: 0.06em;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
.required { color: #E5004C; }
.hint { font-size: 11px; color: #9aaabb; margin-top: 2px; }

.config-link {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; font-weight: 600; color: #515f74;
    text-decoration: none; transition: color 0.15s;
}
.config-link:hover { color: #E5004C; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s; width: 100%; outline: none;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
select.input { appearance: none; cursor: pointer; }
.error-msg { font-size: 12px; color: #ba1a1a; margin-top: 2px; }

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

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 24px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    transition: background 0.2s; border: none; cursor: pointer;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
    display: inline-flex; align-items: center; padding: 10px 20px;
    background: transparent; color: #515f74; border-radius: 8px;
    font-size: 14px; font-weight: 500; border: 1px solid #e0e3e5;
    transition: background 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
