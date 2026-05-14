<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    project: { name: string }
}

const props = defineProps<{
    formation: Formation | null
}>()

const form = useForm({
    file:         null as File | null,
    formation_id: props.formation?.id ?? '',
})

const fileName = ref<string | null>(null)
const isDragging = ref(false)

const onFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (file) {
        form.file = file
        fileName.value = file.name
    }
}

const onDrop = (e: DragEvent) => {
    isDragging.value = false
    const file = e.dataTransfer?.files?.[0]
    if (file) {
        form.file = file
        fileName.value = file.name
    }
}

const clearFile = () => {
    form.file = null
    fileName.value = null
}

const submit = () => form.post('/learners/import', { forceFormData: true })

const requiredColumns = ['prenom', 'nom']
const optionalColumns = [
    { key: 'email', label: 'Email' },
    { key: 'telephone', label: 'Téléphone' },
    { key: 'genre', label: 'Genre (M/F ou Masculin/Féminin)' },
    { key: 'date_naissance', label: 'Date de naissance (YYYY-MM-DD)' },
    { key: 'lieu_naissance', label: 'Lieu de naissance' },
    { key: 'niveau_etudes', label: 'Niveau d\'études (Infrabac | Bac+2 | Bac+3 et plus)' },
    { key: 'talent', label: 'Talent / compétences' },
    { key: 'contact_urgence_nom', label: 'Contact urgence · Nom' },
    { key: 'contact_urgence_prenom', label: 'Contact urgence · Prénom' },
    { key: 'contact_urgence_telephone', label: 'Contact urgence · Téléphone' },
    { key: 'adresse', label: 'Adresse' },
    { key: 'localisation', label: 'Localisation (ville, pays)' },
    { key: 'profil', label: 'Profil / métier visé' },
    { key: 'organisation', label: 'Organisation' },
    { key: 'tranche_age', label: 'Tranche d\'âge (auto-calculée si vide)' },
    { key: 'domaine_etudes', label: 'Domaine d\'études' },
]
</script>

<template>
    <div class="max-w-3xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="formation ? `/formations/${formation.id}` : '/learners'" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Importer des Apprenants</h1>
                <p class="text-body-md text-secondary mt-xs">
                    <template v-if="formation">
                        Formation : <span class="font-semibold text-on-surface">{{ formation.name }}</span>
                    </template>
                    <template v-else>Import général · les apprenants ne seront pas inscrits dans une formation.</template>
                </p>
            </div>
        </div>

        <div class="space-y-lg">

            <!-- Télécharger le modèle -->
            <div class="card flex items-center justify-between gap-md">
                <div>
                    <h2 class="font-semibold text-on-surface">Modèle Excel</h2>
                    <p class="text-body-sm text-secondary mt-xs">
                        Téléchargez le modèle, remplissez les données dans Excel, puis importez-le.
                    </p>
                    <p class="text-body-sm text-secondary">
                        <span class="badge-xlsx">XLSX</span> Format Excel · pas de problème d'encodage ou de séparateur.
                    </p>
                </div>
                <a href="/learners/import/template" class="btn-download">
                    <span class="material-symbols-outlined" style="font-size:18px">download</span>
                    Télécharger le modèle .xlsx
                </a>
            </div>

            <!-- Colonnes -->
            <div class="card space-y-md">
                <h2 class="section-title">Structure du fichier</h2>
                <div class="grid grid-cols-2 gap-lg">
                    <div>
                        <p class="text-label-caps text-on-surface-variant uppercase tracking-wide mb-sm" style="font-size:11px">Colonnes obligatoires</p>
                        <ul class="space-y-xs">
                            <li v-for="col in requiredColumns" :key="col" class="col-item col-required">
                                <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
                                <code>{{ col }}</code>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-label-caps text-on-surface-variant uppercase tracking-wide mb-sm" style="font-size:11px">Colonnes optionnelles</p>
                        <ul class="space-y-xs">
                            <li v-for="col in optionalColumns" :key="col.key" class="col-item col-optional">
                                <span class="material-symbols-outlined" style="font-size:14px">radio_button_unchecked</span>
                                <span>{{ col.label }} <code class="text-secondary">{{ col.key }}</code></span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="notice-info">
                    <span class="material-symbols-outlined" style="font-size:16px">info</span>
                    Les apprenants dont l'email existe déjà en base seront ignorés (pas de doublon).
                </div>
            </div>

            <!-- Upload -->
            <form @submit.prevent="submit" class="card space-y-lg">
                <h2 class="section-title">Fichier à importer</h2>

                <div
                    class="drop-zone"
                    :class="{ 'drop-active': isDragging, 'drop-success': !!fileName }"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <template v-if="fileName">
                        <span class="material-symbols-outlined text-primary" style="font-size:36px">check_circle</span>
                        <p class="font-semibold text-on-surface">{{ fileName }}</p>
                        <button type="button" class="text-secondary text-body-sm hover:text-primary" @click="clearFile">
                            Changer de fichier
                        </button>
                    </template>
                    <template v-else>
                        <span class="material-symbols-outlined text-secondary" style="font-size:40px">upload_file</span>
                        <p class="text-on-surface font-medium">Glissez-déposez votre fichier ici</p>
                        <p class="text-body-sm text-secondary">ou</p>
                        <label class="upload-btn" for="file-input">
                            Parcourir les fichiers
                        </label>
                        <p class="text-body-sm text-secondary">XLSX, XLS ou CSV · 5 Mo max</p>
                        <p class="text-body-sm text-secondary" style="font-size:11px; margin-top:-4px">Utilisez le modèle .xlsx ci-dessus pour éviter les problèmes d'encodage</p>
                    </template>
                    <input id="file-input" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileChange" />
                </div>

                <p v-if="form.errors.file" class="error-msg">{{ form.errors.file }}</p>

                <div class="flex justify-end gap-sm pt-sm border-t border-surface-container-highest">
                    <Link :href="formation ? `/formations/${formation.id}` : '/learners'" class="btn-secondary">
                        Annuler
                    </Link>
                    <button type="submit" class="btn-primary" :disabled="!form.file || form.processing">
                        <span v-if="form.processing" class="spinner" />
                        <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                        Lancer l'import
                    </button>
                </div>
            </form>

        </div>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%; color: #515f74;
    transition: background 0.15s; flex-shrink: 0;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }

.section-title {
    font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.06em;
    padding-bottom: 12px; border-bottom: 1px solid #f2f4f6;
}

.btn-download {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #1F3A4D; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; transition: opacity 0.15s; white-space: nowrap;
}
.btn-download:hover { opacity: 0.85; }

.col-item {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 13px; color: #515f74; line-height: 1.5;
}
.col-required { color: #065f46; }
.col-optional { color: #515f74; }
code { background: #f2f4f6; padding: 1px 5px; border-radius: 4px; font-size: 12px; }

.badge-xlsx {
    display: inline-block; padding: 1px 6px; background: #d1fae5; color: #065f46;
    border-radius: 4px; font-size: 10px; font-weight: 700; margin-right: 4px;
    vertical-align: middle;
}

.notice-info {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 8px; font-size: 13px; color: #1e40af;
}

.drop-zone {
    border: 2px dashed #e0e3e5; border-radius: 12px;
    padding: 40px 24px;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    transition: all 0.2s; cursor: pointer;
}
.drop-zone:hover, .drop-active { border-color: #E5004C; background: #fff0f4; }
.drop-success { border-color: #16a34a; background: #f0fdf4; }

.upload-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1.5px solid #E5004C; border-radius: 8px;
    font-size: 13px; font-weight: 600; color: #E5004C;
    cursor: pointer; transition: background 0.15s;
}
.upload-btn:hover { background: #fff0f4; }

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
