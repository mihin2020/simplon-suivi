<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Project {
    id: string
    name: string
}

const props = defineProps<{
    project: Project
    statuses: Array<{ value: string; label: string }>
    referentiels: Array<{ id: string; name: string }>
}>()

const form = useForm({
    name:           '',
    description:    '',
    started_at:     '',
    ended_at:       '',
    status:         'active',
    referentiel_id: '',
})

const submit = () => form.post(`/projects/${props.project.id}/formations`)
</script>

<template>
    <Head title="Créer une formation" />
    <div class="page-wrap">

        <!-- En-tête -->
        <div class="page-header">
            <Link :href="`/projects/${project.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="page-title">Nouvelle Formation</h1>
                <p class="page-subtitle">Projet : <strong>{{ project.name }}</strong></p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-lg">

            <!-- Informations principales -->
            <div class="card">
                <p class="section-label">Informations</p>

                <div class="field">
                    <label class="label">Nom de la formation <span class="required">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': form.errors.name }"
                        placeholder="Ex : Développement Web & Mobile · Cohorte 3"
                        autofocus
                    />
                    <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
                </div>

                <div class="field mt-md">
                    <label class="label">Description</label>
                    <textarea
                        v-model="form.description"
                        class="input"
                        rows="3"
                        placeholder="Objectifs et contenu de la formation..."
                    />
                </div>
            </div>

            <!-- Dates & statut -->
            <div class="card">
                <p class="section-label">Planification</p>

                <div class="grid-2">
                    <div class="field">
                        <label class="label">Date de début <span class="required">*</span></label>
                        <input
                            v-model="form.started_at"
                            type="date"
                            class="input"
                            :class="{ 'input-error': form.errors.started_at }"
                        />
                        <p v-if="form.errors.started_at" class="error-msg">{{ form.errors.started_at }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Date de fin prévue</label>
                        <input v-model="form.ended_at" type="date" class="input" />
                    </div>
                </div>

                <div class="field mt-md">
                    <label class="label">Statut <span class="required">*</span></label>
                    <select v-model="form.status" class="input select-input">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <p v-if="form.errors.status" class="error-msg">{{ form.errors.status }}</p>
                </div>
            </div>

            <!-- Référentiel -->
            <div class="card">
                <p class="section-label">Référentiel de compétences <span class="section-label-opt">(optionnel)</span></p>

                <div class="ref-list">
                    <button
                        type="button"
                        class="ref-row"
                        :class="{ 'ref-row-active': form.referentiel_id === '' }"
                        @click="form.referentiel_id = ''"
                    >
                        <span class="ref-row-name ref-none">Aucun référentiel</span>
                        <span v-if="form.referentiel_id === ''" class="material-symbols-outlined ref-check">check_circle</span>
                    </button>
                    <button
                        v-for="r in referentiels"
                        :key="r.id"
                        type="button"
                        class="ref-row"
                        :class="{ 'ref-row-active': form.referentiel_id === r.id }"
                        @click="form.referentiel_id = r.id"
                    >
                        <span class="material-symbols-outlined ref-icon">menu_book</span>
                        <span class="ref-row-name">{{ r.name }}</span>
                        <span v-if="form.referentiel_id === r.id" class="material-symbols-outlined ref-check">check_circle</span>
                    </button>
                </div>

                <p class="ref-hint">
                    Vous pouvez créer de nouveaux référentiels depuis
                    <a href="/referentiels" class="ref-link">la section Référentiels</a>.
                </p>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <Link :href="`/projects/${project.id}`" class="btn-cancel">Annuler</Link>
                <button type="submit" class="btn-submit" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    Créer la formation
                </button>
            </div>

        </form>
    </div>
</template>

<style scoped>
.page-wrap { max-width: 640px; margin: 0 auto; }

/* ── Header ── */
.page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.page-title { font-size: 22px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #515f74; margin-top: 3px; }
.page-subtitle strong { color: #191c1e; font-weight: 600; }

/* ── Cards ── */
.card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 14px; padding: 24px;
}
.section-label {
    font-size: 10px; font-weight: 700; color: #9aaabb;
    text-transform: uppercase; letter-spacing: 0.08em;
    margin-bottom: 16px;
}
.section-label-opt { font-size: 10px; font-weight: 500; color: #c8d0d8; text-transform: none; letter-spacing: 0; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* ── Fields ── */
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
textarea.input { resize: vertical; }
.select-input { appearance: none; cursor: pointer; }

.error-msg { font-size: 12px; color: #ba1a1a; margin-top: 2px; }

/* ── Référentiel list ── */
.ref-list {
    display: flex; flex-direction: column; gap: 4px;
    border: 1px solid #e0e3e5; border-radius: 10px; padding: 6px;
    max-height: 240px; overflow-y: auto; margin-bottom: 10px;
}
.ref-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 8px; width: 100%;
    border: 1.5px solid transparent; background: transparent;
    cursor: pointer; text-align: left; transition: background 0.12s, border-color 0.12s;
}
.ref-row:hover { background: #f6f8fa; }
.ref-row-active { background: #fff0f4 !important; border-color: #ffc0d0; }
.ref-icon { font-size: 18px; color: #9aaabb; flex-shrink: 0; }
.ref-row-active .ref-icon { color: #E5004C; }
.ref-row-name { flex: 1; font-size: 14px; font-weight: 500; color: #191c1e; }
.ref-none { color: #9aaabb; font-style: italic; font-weight: 400; }
.ref-check { font-size: 18px; color: #E5004C; flex-shrink: 0; }
.ref-hint { font-size: 11px; color: #9aaabb; }
.ref-link { color: #E5004C; text-decoration: none; }
.ref-link:hover { text-decoration: underline; }

/* ── Actions ── */
.form-actions { display: flex; justify-content: flex-end; gap: 10px; }
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
