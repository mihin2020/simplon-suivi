<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface FormationOption {
    id: string
    project_id: string
    name: string
}

interface ProjectOption {
    id: string
    name: string
    formations: FormationOption[]
}

const props = defineProps<{
    projects: ProjectOption[]
}>()

const form = useForm({
    title: '',
    description: '',
    project_id: '',
    formation_id: '',
})

const formations = computed(() =>
    props.projects.find(p => p.id === form.project_id)?.formations ?? []
)

const onProjectChange = () => {
    form.formation_id = ''
}

const submit = () => form.post('/forms')
</script>

<template>
    <Head title="Nouveau formulaire" />
    <div class="page-wrapper">
        <div class="page-title-row">
            <Link href="/forms" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="page-title">Nouveau formulaire</h1>
                <p class="page-subtitle">Lié à un projet et une formation pour collecter des candidatures.</p>
            </div>
        </div>

        <form class="form-card" @submit.prevent="submit">
            <div class="field">
                <label class="label" for="title">Titre <span class="required">*</span></label>
                <input id="title" v-model="form.title" type="text" class="input" :class="{ 'input-error': form.errors.title }" required maxlength="255" />
                <p v-if="form.errors.title" class="error-msg">{{ form.errors.title }}</p>
            </div>

            <div class="field">
                <label class="label" for="description">Description du formulaire</label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    class="input"
                    maxlength="5000"
                    placeholder="Expliquez le but du formulaire aux candidats…"
                />
                <p class="help-text">Visible sous le titre sur le lien public.</p>
                <p v-if="form.errors.description" class="error-msg">{{ form.errors.description }}</p>
            </div>

            <div class="field">
                <label class="label" for="project_id">Projet <span class="required">*</span></label>
                <select id="project_id" v-model="form.project_id" class="input" required @change="onProjectChange">
                    <option value="" disabled>Sélectionner un projet</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <p v-if="form.errors.project_id" class="error-msg">{{ form.errors.project_id }}</p>
            </div>

            <div class="field">
                <label class="label" for="formation_id">Formation <span class="required">*</span></label>
                <select
                    id="formation_id"
                    v-model="form.formation_id"
                    class="input"
                    required
                    :disabled="!form.project_id || formations.length === 0"
                >
                    <option value="" disabled>
                        {{ !form.project_id ? 'Choisir d\'abord un projet' : (formations.length ? 'Sélectionner une formation' : 'Aucune formation pour ce projet') }}
                    </option>
                    <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="form.errors.formation_id" class="error-msg">{{ form.errors.formation_id }}</p>
            </div>

            <div class="form-actions">
                <Link href="/forms" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    Créer le formulaire
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.page-wrapper { max-width: 720px; margin: 0 auto; }
.page-title-row { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 28px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: #fff; border: 1px solid #e0e3e5; color: #1F3A4D;
    text-decoration: none; transition: all 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; line-height: 1.25; }
.page-subtitle { font-size: 14px; color: #515f74; margin-top: 4px; }

.form-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 16px;
    padding: 28px; display: flex; flex-direction: column; gap: 20px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.label {
    font-size: 12px; font-weight: 700; color: #191c1e;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.required { color: #E5004C; }
.input {
    width: 100%; padding: 12px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px;
    font-size: 14px; color: #191c1e; background: #fafbfc; outline: none;
    font-family: inherit; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.input:focus {
    border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229,0,76,0.08); background: #fff;
}
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }
.help-text { font-size: 12px; color: #80868b; }

.form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 8px; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    border: none; cursor: pointer; transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary {
    display: inline-flex; align-items: center; padding: 10px 18px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #e0e3e5; color: #515f74; background: #fff;
    text-decoration: none; transition: background 0.15s;
}
.btn-secondary:hover { background: #f2f4f6; border-color: #d0d3d5; }
</style>
