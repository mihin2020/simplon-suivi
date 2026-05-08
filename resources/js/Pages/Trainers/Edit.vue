<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }

interface Trainer {
    id: string
    phone: string | null
    phone2: string | null
    cv_path: string | null
    profile_id: string | null
    is_active: boolean
    user: {
        first_name: string
        last_name: string
        email: string
    }
}

const props = defineProps<{
    trainer: Trainer
    profiles: Profile[]
}>()

const form = useForm({
    profile_id: props.trainer.profile_id ?? '',
    phone:      props.trainer.phone ?? '',
    phone2:     props.trainer.phone2 ?? '',
    cv:         null as File | null,
    is_active:  props.trainer.is_active ?? true,
    remove_cv:  false,
})

const cvFileName = ref<string | null>(null)
const existingCv = ref(props.trainer.cv_path)

const onCvChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    form.cv = file
    cvFileName.value = file?.name ?? null
}

const removeCv = () => {
    form.cv = null
    cvFileName.value = null
}

const removeExistingCv = () => {
    existingCv.value = null
    form.remove_cv = true
}

const submit = () => form.post(`/trainers/${props.trainer.id}?_method=PUT`, { forceFormData: true })
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/trainers/${trainer.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Modifier le Formateur</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ trainer.user.last_name }} {{ trainer.user.first_name }}
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="card space-y-lg">
            <!-- Champ caché is_active -->
            <input type="hidden" v-model="form.is_active" />

            <!-- Champs en lecture seule -->
            <div class="readonly-section">
                <p class="text-body-sm text-secondary mb-sm">
                    Le nom, prénom et email ne peuvent pas être modifiés ici.
                </p>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Prénom</label>
                        <input type="text" class="input input-readonly" :value="trainer.user.first_name" readonly />
                    </div>
                    <div class="field">
                        <label class="label">Nom</label>
                        <input type="text" class="input input-readonly" :value="trainer.user.last_name" readonly />
                    </div>
                </div>
                <div class="field mt-md">
                    <label class="label">Email</label>
                    <input type="email" class="input input-readonly" :value="trainer.user.email" readonly />
                </div>
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

                <!-- CV existant -->
                <div v-if="existingCv && !cvFileName" class="cv-selected">
                    <span class="material-symbols-outlined cv-icon">description</span>
                    <span class="cv-name">CV existant</span>
                    <a :href="`/storage/${existingCv}`" target="_blank" class="cv-view" title="Voir">
                        <span class="material-symbols-outlined" style="font-size:16px">visibility</span>
                    </a>
                    <button type="button" class="cv-remove" @click="removeExistingCv" title="Supprimer">
                        <span class="material-symbols-outlined" style="font-size:16px">close</span>
                    </button>
                </div>

                <!-- Nouveau CV sélectionné -->
                <div v-else-if="cvFileName" class="cv-selected">
                    <span class="material-symbols-outlined cv-icon">description</span>
                    <span class="cv-name">{{ cvFileName }}</span>
                    <button type="button" class="cv-remove" @click="removeCv" title="Retirer">
                        <span class="material-symbols-outlined" style="font-size:16px">close</span>
                    </button>
                </div>

                <!-- Upload nouveau CV -->
                <label v-else class="cv-upload" :class="{ 'upload-error': form.errors.cv }">
                    <span class="material-symbols-outlined" style="font-size:28px;color:#adb5bd">upload_file</span>
                    <span class="cv-upload-text">Cliquer pour uploader un nouveau CV</span>
                    <span class="cv-upload-hint">PDF, DOC, DOCX · 5 Mo max</span>
                    <input type="file" accept=".pdf,.doc,.docx" class="sr-only" @change="onCvChange" />
                </label>

                <p v-if="form.errors.cv" class="error-msg">{{ form.errors.cv }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm pt-md border-t border-surface-container-highest">
                <Link :href="`/trainers/${trainer.id}`" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    Enregistrer
                </button>
            </div>

        </form>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: #515f74;
    transition: background 0.15s;
    flex-shrink: 0;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 32px; }
.field { display: flex; flex-direction: column; gap: 6px; }

.readonly-section {
    padding: 16px;
    background: #f9fafb;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
}

.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }

.section-label {
    font-size: 11px; font-weight: 700; color: #adb5bd;
    text-transform: uppercase; letter-spacing: 0.06em;
}

.config-link {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; font-weight: 600; color: #515f74;
    text-decoration: none; transition: color 0.15s;
}
.config-link:hover { color: #E5004C; }

select.input { appearance: none; cursor: pointer; }

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
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-readonly { background: #f3f4f6; color: #9aaabb; cursor: not-allowed; }

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
.cv-view {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(5,150,105,0.12); border: none; cursor: pointer;
    color: #059669; transition: background 0.15s; flex-shrink: 0; text-decoration: none;
}
.cv-view:hover { background: rgba(5,150,105,0.20); }
.cv-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(186,26,26,0.08); border: none; cursor: pointer;
    color: #ba1a1a; transition: background 0.15s; flex-shrink: 0;
}
.cv-remove:hover { background: rgba(186,26,26,0.16); }

.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
    border: none;
    cursor: pointer;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
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
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
