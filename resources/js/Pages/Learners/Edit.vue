<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface EducationLevel {
    id: number
    name: string
}

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
    phone: string | null
    gender: string | null
    birth_date: string | null
    birth_place: string | null
    talent: string | null
    education_level_id: number | null
    photo_path: string | null
    cnib_path: string | null
    emergency_contact_name: string | null
    emergency_contact_firstname: string | null
    emergency_contact_phone: string | null
}

const props = defineProps<{
    learner: Learner
    educationLevels: EducationLevel[]
}>()

// Normalize ISO date string to YYYY-MM-DD for <input type="date">
const toDateInput = (d: string | null) => d ? d.split('T')[0] : ''

// Method spoofing: Inertia sends FormData as POST but Laravel reads _method=PUT.
// This is required because PHP does not natively parse multipart/form-data for PUT.
const form = useForm({
    _method:                     'PUT' as string,
    last_name:                   props.learner.last_name,
    first_name:                  props.learner.first_name,
    email:                       props.learner.email ?? '',
    phone:                       props.learner.phone ?? '',
    gender:                      props.learner.gender ?? '',
    birth_date:                  toDateInput(props.learner.birth_date),
    birth_place:                 props.learner.birth_place ?? '',
    education_level_id:          props.learner.education_level_id ?? '',
    talent:                      props.learner.talent ?? '',
    emergency_contact_name:      props.learner.emergency_contact_name ?? '',
    emergency_contact_firstname: props.learner.emergency_contact_firstname ?? '',
    emergency_contact_phone:     props.learner.emergency_contact_phone ?? '',
    photo:                       null as File | null,
    cnib:                        null as File | null,
})

const photoPreview = ref<string | null>(
    props.learner.photo_path ? `/storage/${props.learner.photo_path}` : null
)
const cnibName = ref<string | null>(
    props.learner.cnib_path ? props.learner.cnib_path.split('/').pop() ?? null : null
)
const hasCnib = ref(!!props.learner.cnib_path)

const onPhotoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    form.photo = file
    photoPreview.value = URL.createObjectURL(file)
}

const removePhoto = () => {
    form.photo = null
    photoPreview.value = null
}

const onCnibChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    form.cnib = file
    cnibName.value = file.name
    hasCnib.value = true
}

const submit = () => form.post(`/learners/${props.learner.id}`, {
    forceFormData: true,
})
</script>

<template>
    <div class="max-w-3xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/learners/${learner.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Modifier l'Apprenant</h1>
                <p class="text-body-md text-secondary mt-xs">{{ learner.last_name }} {{ learner.first_name }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-lg">

            <!-- Photo -->
            <div class="card">
                <h2 class="section-title">Photo</h2>
                <div class="flex items-center gap-lg">
                    <div class="photo-preview">
                        <img v-if="photoPreview" :src="photoPreview" alt="" class="photo-img" />
                        <span v-else class="material-symbols-outlined text-secondary" style="font-size:40px">person</span>
                    </div>
                    <div class="flex-1 flex flex-col gap-xs">
                        <div class="flex items-center gap-sm">
                            <label class="upload-btn" for="photo-input">
                                <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                                {{ photoPreview ? 'Changer la photo' : 'Choisir une photo' }}
                            </label>
                            <button v-if="photoPreview && form.photo" type="button" @click="removePhoto" class="remove-btn">
                                <span class="material-symbols-outlined" style="font-size:16px">close</span>
                                Annuler
                            </button>
                        </div>
                        <input id="photo-input" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPhotoChange" />
                        <p class="text-body-sm text-secondary">JPEG, PNG ou WebP · 2 Mo max · Optionnel</p>
                        <p v-if="form.errors.photo" class="error-msg">{{ form.errors.photo }}</p>
                    </div>
                </div>
            </div>

            <!-- Identité -->
            <div class="card space-y-lg">
                <h2 class="section-title">Identité</h2>

                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Nom <span class="required">*</span></label>
                        <input v-model="form.last_name" type="text" class="input" :class="{ 'input-error': form.errors.last_name }" />
                        <p v-if="form.errors.last_name" class="error-msg">{{ form.errors.last_name }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Prénom <span class="required">*</span></label>
                        <input v-model="form.first_name" type="text" class="input" :class="{ 'input-error': form.errors.first_name }" />
                        <p v-if="form.errors.first_name" class="error-msg">{{ form.errors.first_name }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Genre</label>
                        <div class="gender-group">
                            <label class="gender-option" :class="{ active: form.gender === 'male' }">
                                <input type="radio" v-model="form.gender" value="male" class="hidden" />
                                <span class="gender-dot gender-m">M</span>
                                Masculin
                            </label>
                            <label class="gender-option" :class="{ active: form.gender === 'female' }">
                                <input type="radio" v-model="form.gender" value="female" class="hidden" />
                                <span class="gender-dot gender-f">F</span>
                                Féminin
                            </label>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Date de naissance</label>
                        <input v-model="form.birth_date" type="date" class="input" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Lieu de naissance</label>
                        <input v-model="form.birth_place" type="text" class="input" />
                    </div>
                    <div class="field">
                        <label class="label">Niveau d'études</label>
                        <select v-model="form.education_level_id" class="input">
                            <option value="">Sélectionner</option>
                            <option v-for="lvl in educationLevels" :key="lvl.id" :value="lvl.id">{{ lvl.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Talent / Compétences particulières</label>
                    <input v-model="form.talent" type="text" class="input" />
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="card space-y-lg">
                <h2 class="section-title">Coordonnées</h2>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" class="input" :class="{ 'input-error': form.errors.email }" />
                        <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Téléphone</label>
                        <input v-model="form.phone" type="tel" class="input" />
                    </div>
                </div>
            </div>

            <!-- Contact d'urgence -->
            <div class="card space-y-lg">
                <h2 class="section-title">Contact d'urgence</h2>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Nom de famille</label>
                        <input v-model="form.emergency_contact_name" type="text" class="input" />
                    </div>
                    <div class="field">
                        <label class="label">Prénom</label>
                        <input v-model="form.emergency_contact_firstname" type="text" class="input" />
                    </div>
                </div>
                <div class="field">
                    <label class="label">Téléphone</label>
                    <input v-model="form.emergency_contact_phone" type="tel" class="input" />
                </div>
            </div>

            <!-- Documents -->
            <div class="card space-y-lg">
                <h2 class="section-title">Documents</h2>
                <div class="field">
                    <label class="label">CNIB / Pièce d'identité</label>
                    <div class="file-upload-row">
                        <label class="upload-btn" for="cnib-input">
                            <span class="material-symbols-outlined" style="font-size:18px">upload_file</span>
                            {{ hasCnib ? 'Remplacer le document' : 'Choisir un fichier' }}
                        </label>
                        <span v-if="cnibName" class="file-name">{{ cnibName }}</span>
                    </div>
                    <input id="cnib-input" type="file" accept=".pdf,image/jpeg,image/png" class="hidden" @change="onCnibChange" />
                    <p class="text-body-sm text-secondary">PDF, JPEG ou PNG · 5 Mo max · Optionnel</p>
                    <p v-if="form.errors.cnib" class="error-msg">{{ form.errors.cnib }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm">
                <Link :href="`/learners/${learner.id}`" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    Enregistrer les modifications
                </button>
            </div>

        </form>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%; color: #515f74;
    transition: background 0.15s; flex-shrink: 0;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 28px; }

.section-title {
    font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.06em;
    padding-bottom: 12px; border-bottom: 1px solid #f2f4f6; margin-bottom: 4px;
}

.photo-preview {
    width: 88px; height: 88px; border-radius: 50%;
    background: #f2f4f6; border: 2px dashed #e0e3e5;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.photo-img { width: 100%; height: 100%; object-fit: cover; }

.upload-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 600; color: #515f74;
    background: #f9fafb; cursor: pointer; transition: background 0.15s;
}
.upload-btn:hover { background: #eceef0; }

.remove-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 8px 12px; border: 1px solid #fecaca; border-radius: 8px;
    font-size: 12px; color: #ba1a1a; background: #fff5f5;
    cursor: pointer; transition: background 0.15s;
}
.remove-btn:hover { background: #fee2e2; }

.file-upload-row { display: flex; align-items: center; gap: 12px; }
.file-name { font-size: 13px; color: #515f74; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.gender-group { display: flex; gap: 10px; margin-top: 4px; }
.gender-option {
    flex: 1; display: flex; align-items: center; gap: 8px;
    padding: 10px 12px; border: 1.5px solid #e0e3e5;
    border-radius: 8px; cursor: pointer; transition: all 0.15s; font-size: 14px; color: #515f74;
}
.gender-option.active { border-color: #E5004C; background: #fff0f4; color: #191c1e; }
.gender-dot {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%; font-size: 11px; font-weight: 700;
}
.gender-m { background: #dbeafe; color: #1d4ed8; }
.gender-f { background: #fce7f3; color: #be185d; }

.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
.required { color: #E5004C; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s; width: 100%; outline: none;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
select.input { appearance: none; cursor: pointer; }
.error-msg { font-size: 12px; color: #ba1a1a; }

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
