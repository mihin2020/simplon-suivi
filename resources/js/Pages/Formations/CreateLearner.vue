<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface EducationLevel { id: number; name: string }
interface AgeRange { id: number; name: string; age_min: number; age_max: number }
interface Formation { id: string; name: string; project: { id: string; name: string } }

const props = defineProps<{
    formation: Formation
    educationLevels: EducationLevel[]
    ageRanges: AgeRange[]
}>()

const form = useForm({
    last_name:                   '',
    first_name:                  '',
    email:                       '',
    phone:                       '',
    gender:                      '',
    birth_date:                  '',
    birth_place:                 '',
    education_level_id:          '',
    age_range_id:                '' as number | '',
    organization:                '',
    talent:                      '',
    emergency_contact_name:      '',
    emergency_contact_firstname: '',
    emergency_contact_phone:     '',
    address:                     '',
    location:                    '',
    profile:                     '',
    study_field:                 '',
    photo:                       null as File | null,
    cnib:                        null as File | null,
})

const photoPreview = ref<string | null>(null)
const cnibName = ref<string | null>(null)

const onPhotoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    form.photo = file
    photoPreview.value = URL.createObjectURL(file)
}

const onCnibChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    form.cnib = file
    cnibName.value = file.name
}

// Calcul automatique de l'âge depuis la date de naissance
const computedAge = computed<number | null>(() => {
    if (!form.birth_date) return null
    const birth = new Date(form.birth_date)
    if (isNaN(birth.getTime())) return null
    const now = new Date()
    let age = now.getFullYear() - birth.getFullYear()
    const m = now.getMonth() - birth.getMonth()
    if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--
    return age >= 0 ? age : null
})

watch(computedAge, (age) => {
    if (age === null) return
    const match = props.ageRanges.find(r => age >= r.age_min && age <= r.age_max)
    if (match) form.age_range_id = match.id
})

const submit = () => form.post(`/formations/${props.formation.id}/learners/new`, {
    forceFormData: true,
})
</script>

<template>
    <div class="max-w-3xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/formations/${formation.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Créer et Inscrire un Apprenant</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Formation : <span class="font-semibold text-on-surface">{{ formation.name }}</span>
                    <span class="mx-xs">·</span>
                    {{ formation.project.name }}
                </p>
            </div>
        </div>

        <!-- Lien vers import Excel -->
        <div class="notice-import mb-lg">
            <span class="material-symbols-outlined" style="font-size:18px; color:#1d4ed8">upload_file</span>
            <p class="text-body-sm text-blue-700 flex-1">
                Pour ajouter plusieurs apprenants à la fois, utilisez l'import Excel.
            </p>
            <Link :href="`/learners/import?formation=${formation.id}`" class="btn-import">
                Importer Excel
            </Link>
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
                    <div class="flex-1">
                        <label class="upload-btn" for="photo-input">
                            <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                            Choisir une photo
                        </label>
                        <input id="photo-input" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPhotoChange" />
                        <p class="text-body-sm text-secondary mt-xs">JPEG, PNG ou WebP · 2 Mo max · Optionnel</p>
                    </div>
                </div>
            </div>

            <!-- Identité -->
            <div class="card space-y-lg">
                <h2 class="section-title">Identité</h2>

                <div class="grid grid-cols-2 gap-md">
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
                        <label class="label">Tranche d'âge
                            <span v-if="computedAge !== null" class="age-hint">({{ computedAge }} ans)</span>
                        </label>
                        <select v-model="form.age_range_id" class="input">
                            <option value="">Sélectionner</option>
                            <option v-for="r in ageRanges" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div></div>
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
                    <label class="label">Talent / Compétences</label>
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

            <!-- Informations complémentaires -->
            <div class="card space-y-lg">
                <h2 class="section-title">Informations complémentaires</h2>
                <div class="field">
                    <label class="label">Adresse</label>
                    <textarea v-model="form.address" rows="2" class="input" placeholder="Ex : Rue 123, Quartier XYZ"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Localisation</label>
                        <input v-model="form.location" type="text" class="input" placeholder="Ex : Ouagadougou, Burkina Faso" />
                    </div>
                    <div class="field">
                        <label class="label">Profil</label>
                        <input v-model="form.profile" type="text" class="input" placeholder="Ex : Développeur web junior" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Organisation</label>
                        <input v-model="form.organization" type="text" class="input" placeholder="Ex : Simplon, ONG, Entreprise..." />
                    </div>
                    <div class="field">
                        <label class="label">Domaine d'études</label>
                        <input v-model="form.study_field" type="text" class="input" placeholder="Ex : Informatique, Gestion, Marketing..." />
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card space-y-lg">
                <h2 class="section-title">Documents</h2>
                <div class="field">
                    <label class="label">CNIB / Pièce d'identité</label>
                    <div class="flex items-center gap-md">
                        <label class="upload-btn" for="cnib-input">
                            <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                            Choisir un fichier
                        </label>
                        <input id="cnib-input" type="file" accept="application/pdf,image/jpeg,image/png" class="hidden" @change="onCnibChange" />
                        <span v-if="cnibName" class="text-body-sm text-secondary">{{ cnibName }}</span>
                    </div>
                    <p class="text-body-sm text-secondary mt-xs">PDF, JPEG ou PNG · 5 Mo max · Optionnel</p>
                </div>
            </div>

            <!-- Contact d'urgence -->
            <div class="card space-y-lg">
                <h2 class="section-title">Contact d'urgence</h2>
                <div class="grid grid-cols-2 gap-md">
                    <div class="field">
                        <label class="label">Nom</label>
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

            <!-- Actions -->
            <div class="flex justify-end gap-sm">
                <Link :href="`/formations/${formation.id}`" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    <span class="material-symbols-outlined" style="font-size:16px">how_to_reg</span>
                    Créer et inscrire
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

.notice-import {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
}
.btn-import {
    padding: 6px 14px; background: #1d4ed8; color: #fff;
    border-radius: 6px; font-size: 12px; font-weight: 600;
    text-decoration: none; white-space: nowrap; transition: opacity 0.15s;
}
.btn-import:hover { opacity: 0.85; }

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
.age-hint { font-size: 11px; font-weight: 500; color: #16a34a; margin-left: 6px; }

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
