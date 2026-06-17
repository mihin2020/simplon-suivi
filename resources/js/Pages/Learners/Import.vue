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
    if (file) { form.file = file; fileName.value = file.name }
}

const onDrop = (e: DragEvent) => {
    isDragging.value = false
    const file = e.dataTransfer?.files?.[0]
    if (file) { form.file = file; fileName.value = file.name }
}

const clearFile = () => { form.file = null; fileName.value = null }

const submit = () => form.post('/learners/import', { forceFormData: true })

const requiredColumns = ['prenom', 'nom']
const optionalColumns = [
    { key: 'email',                      label: 'Email' },
    { key: 'telephone',                  label: 'Téléphone' },
    { key: 'genre',                      label: 'Genre (M/F)' },
    { key: 'date_naissance',             label: 'Date de naissance (YYYY-MM-DD)' },
    { key: 'lieu_naissance',             label: 'Lieu de naissance' },
    { key: 'niveau_etudes',              label: 'Niveau d\'études' },
    { key: 'talent',                     label: 'Talent / compétences' },
    { key: 'contact_urgence_nom',        label: 'Contact urgence · Nom' },
    { key: 'contact_urgence_prenom',     label: 'Contact urgence · Prénom' },
    { key: 'contact_urgence_telephone',  label: 'Contact urgence · Téléphone' },
    { key: 'adresse',                    label: 'Adresse' },
    { key: 'localisation',               label: 'Localisation' },
    { key: 'profil',                     label: 'Profil / métier visé' },
    { key: 'organisation',               label: 'Organisation' },
    { key: 'tranche_age',                label: 'Tranche d\'âge' },
    { key: 'domaine_etudes',             label: 'Domaine d\'études' },
]
</script>

<template>
    <div class="import-page">

        <!-- ── En-tête ── -->
        <div class="page-header">
            <Link :href="formation ? `/formations/${formation.id}` : '/learners'" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="page-title">Importer des Apprenants</h1>
                <p class="page-subtitle">
                    <template v-if="formation">
                        {{ formation.name }}<template v-if="formation.project"> · {{ formation.project.name }}</template>
                    </template>
                    <template v-else>
                        Import général — les apprenants ne seront pas inscrits dans une formation.
                    </template>
                </p>
            </div>
        </div>

        <div class="content">

            <!-- ── Étape 1 : Télécharger le modèle ── -->
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-body">
                    <div class="step-head">
                        <div>
                            <p class="step-title">Téléchargez le modèle Excel</p>
                            <p class="step-desc">Remplissez les données dans le fichier, puis importez-le ci-dessous.</p>
                        </div>
                        <a href="/learners/import/template" class="btn-download">
                            <span class="material-symbols-outlined" style="font-size:16px">download</span>
                            Modèle .xlsx
                        </a>
                    </div>
                </div>
            </div>

            <!-- ── Étape 2 : Structure du fichier ── -->
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-body">
                    <p class="step-title">Structure du fichier</p>
                    <p class="step-desc" style="margin-bottom:16px">La première ligne doit contenir les noms de colonnes exacts.</p>

                    <div class="cols-grid">
                        <!-- Obligatoires -->
                        <div class="cols-section">
                            <p class="cols-label required-label">Obligatoires</p>
                            <div class="cols-list">
                                <div v-for="col in requiredColumns" :key="col" class="col-row col-row-required">
                                    <span class="material-symbols-outlined" style="font-size:14px;color:#E5004C">check_circle</span>
                                    <code class="col-code">{{ col }}</code>
                                </div>
                            </div>
                        </div>

                        <!-- Optionnelles -->
                        <div class="cols-section">
                            <p class="cols-label optional-label">Optionnelles</p>
                            <div class="cols-list">
                                <div v-for="col in optionalColumns" :key="col.key" class="col-row">
                                    <span class="material-symbols-outlined" style="font-size:13px;color:#9aaabb">circle</span>
                                    <span class="col-label-text">{{ col.label }}</span>
                                    <code class="col-code col-code-sm">{{ col.key }}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="notice-info">
                        <span class="material-symbols-outlined" style="font-size:15px">info</span>
                        Les apprenants dont l'email existe déjà en base seront ignorés (pas de doublon).
                    </div>
                </div>
            </div>

            <!-- ── Étape 3 : Upload ── -->
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-body">
                    <p class="step-title">Importez votre fichier</p>
                    <p class="step-desc" style="margin-bottom:16px">Formats acceptés : XLSX, XLS, CSV · 5 Mo max</p>

                    <form @submit.prevent="submit">
                        <!-- Zone de dépôt -->
                        <div
                            class="drop-zone"
                            :class="{ 'drop-active': isDragging, 'drop-success': !!fileName }"
                            @dragover.prevent="isDragging = true"
                            @dragleave="isDragging = false"
                            @drop.prevent="onDrop"
                        >
                            <template v-if="fileName">
                                <span class="material-symbols-outlined" style="font-size:36px;color:#16a34a">check_circle</span>
                                <p class="drop-filename">{{ fileName }}</p>
                                <button type="button" class="drop-change" @click="clearFile">Changer de fichier</button>
                            </template>
                            <template v-else>
                                <span class="material-symbols-outlined drop-icon">upload_file</span>
                                <p class="drop-main">Glissez-déposez votre fichier ici</p>
                                <p class="drop-or">ou</p>
                                <label class="btn-browse" for="file-input">Parcourir les fichiers</label>
                            </template>
                            <input id="file-input" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileChange" />
                        </div>

                        <p v-if="form.errors.file" class="error-msg">{{ form.errors.file }}</p>

                        <!-- Actions -->
                        <div class="form-actions">
                            <Link :href="formation ? `/formations/${formation.id}` : '/learners'" class="btn-cancel">
                                Annuler
                            </Link>
                            <button type="submit" class="btn-submit" :disabled="!form.file || form.processing">
                                <span v-if="form.processing" class="spinner" />
                                <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                                Lancer l'import
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.import-page { max-width: 780px; margin: 0 auto; }

/* ── Header ── */
.page-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 28px;
}
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.header-icon .material-symbols-outlined { font-size: 24px; }
.page-title { font-size: 22px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #515f74; margin-top: 3px; }

/* ── Content ── */
.content { display: flex; flex-direction: column; gap: 16px; }

/* ── Step cards ── */
.step-card {
    display: flex; gap: 16px;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 14px;
    padding: 24px;
}
.step-number {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    background: #1F3A4D; color: #fff;
    font-size: 13px; font-weight: 700; margin-top: 2px;
}
.step-body { flex: 1; min-width: 0; }
.step-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.step-title { font-size: 15px; font-weight: 700; color: #191c1e; margin-bottom: 4px; }
.step-desc { font-size: 13px; color: #515f74; line-height: 1.5; }

.btn-download {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #1F3A4D; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap; transition: background 0.15s; flex-shrink: 0;
}
.btn-download:hover { background: #2d5a7b; }

/* ── Colonnes ── */
.cols-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 16px; }
.cols-section { display: flex; flex-direction: column; gap: 8px; }
.cols-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    padding-bottom: 6px; border-bottom: 1px solid #f2f4f6;
}
.required-label { color: #E5004C; }
.optional-label { color: #9aaabb; }
.cols-list { display: flex; flex-direction: column; gap: 4px; }
.col-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #515f74; line-height: 1.4;
}
.col-row-required { color: #191c1e; }
.col-code {
    background: #f2f4f6; padding: 1px 6px; border-radius: 4px;
    font-size: 11px; font-family: monospace; color: #1F3A4D; flex-shrink: 0;
}
.col-label-text { flex: 1; }
.col-code-sm { font-size: 10px; }

.notice-info {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px; background: #f0f3f6; border: 1px solid #d0d8e0;
    border-radius: 8px; font-size: 12px; color: #1F3A4D;
}

/* ── Drop zone ── */
.drop-zone {
    border: 2px dashed #d0d8e0; border-radius: 12px;
    padding: 36px 24px; margin-bottom: 16px;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    transition: all 0.2s; text-align: center;
}
.drop-zone:hover, .drop-active { border-color: #E5004C; background: #fff0f4; }
.drop-success { border-color: #16a34a; background: #f0fdf4; border-style: solid; }
.drop-icon { font-size: 40px; color: #9aaabb; }
.drop-main { font-size: 14px; font-weight: 600; color: #191c1e; }
.drop-or { font-size: 12px; color: #9aaabb; }
.drop-filename { font-size: 14px; font-weight: 600; color: #191c1e; }
.drop-change {
    font-size: 12px; color: #9aaabb; background: none; border: none;
    cursor: pointer; text-decoration: underline;
}
.drop-change:hover { color: #515f74; }

.btn-browse {
    display: inline-flex; align-items: center;
    padding: 8px 18px; border: 1.5px solid #E5004C; border-radius: 8px;
    font-size: 13px; font-weight: 600; color: #E5004C;
    cursor: pointer; transition: background 0.15s;
}
.btn-browse:hover { background: #fff0f4; }

.error-msg { font-size: 12px; color: #ba1a1a; margin-bottom: 12px; }

/* ── Actions ── */
.form-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    padding-top: 16px; border-top: 1px solid #f2f4f6;
}
.btn-cancel {
    display: inline-flex; align-items: center; padding: 10px 20px;
    background: transparent; color: #515f74; border-radius: 8px;
    font-size: 14px; font-weight: 500; border: 1px solid #e0e3e5;
    transition: background 0.15s; text-decoration: none;
}
.btn-cancel:hover { background: #f2f4f6; }
.btn-submit {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 24px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; transition: background 0.2s;
}
.btn-submit:hover:not(:disabled) { background: #c0003e; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
