<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    referentiel_id: string | null
    project: { id: string; name: string }
}

const props = defineProps<{
    formation: Formation
    statuses: Array<{ value: string; label: string }>
    referentiels: Array<{ id: string; name: string }>
}>()

const toDateInput = (d: string | null) => d ? d.split('T')[0] : ''

const form = useForm({
    name:           props.formation.name,
    description:    props.formation.description ?? '',
    started_at:     toDateInput(props.formation.started_at),
    ended_at:       toDateInput(props.formation.ended_at),
    status:         props.formation.status,
    referentiel_id: props.formation.referentiel_id ?? '',
})

const submit = () => form.put(`/formations/${props.formation.id}`)
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/formations/${formation.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Modifier la Formation</h1>
                <p class="text-body-md text-secondary mt-xs">{{ formation.name }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="card space-y-lg">

            <!-- Nom -->
            <div class="field">
                <label class="label">Nom de la formation <span class="required">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    :class="{ 'input-error': form.errors.name }"
                />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>

            <!-- Description -->
            <div class="field">
                <label class="label">Description</label>
                <textarea v-model="form.description" class="input" rows="3" />
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-md">
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

            <!-- Statut -->
            <div class="field">
                <label class="label">Statut <span class="required">*</span></label>
                <select v-model="form.status" class="input">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <p v-if="form.errors.status" class="error-msg">{{ form.errors.status }}</p>
            </div>

            <!-- Référentiel -->
            <div class="field">
                <label class="label">Référentiel de compétences</label>
                <select v-model="form.referentiel_id" class="input">
                    <option value="">— Aucun référentiel —</option>
                    <option v-for="r in referentiels" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
                <p style="font-size:11px; color:#9aaabb; margin-top:4px;">
                    Optionnel. Gérez les référentiels depuis
                    <a href="/referentiels" style="color:#E5004C;">la section Référentiels</a>.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm pt-md border-t border-surface-container-highest">
                <Link :href="`/formations/${formation.id}`" class="btn-secondary">Annuler</Link>
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

.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
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
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
textarea.input { resize: vertical; }
select.input { appearance: none; cursor: pointer; }

.error-msg { font-size: 12px; color: #ba1a1a; margin-top: 2px; }

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
