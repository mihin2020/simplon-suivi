<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    project: { id: string; name: string }
}

interface Learner {
    id: string
    first_name: string
    last_name: string
}

const props = defineProps<{
    learner: Learner
    activeFormations: Formation[]
    targetFormations: Formation[]
}>()

const form = useForm({
    source_formation_id: props.activeFormations.length === 1 ? props.activeFormations[0].id : '',
    target_formation_id: '',
    notes: '',
})

const sourceFormation = computed(() =>
    props.activeFormations.find(f => f.id === form.source_formation_id) ?? null
)

const availableTargets = computed(() => {
    if (!sourceFormation.value) return []
    return props.targetFormations.filter(f => f.project.id === sourceFormation.value!.project.id)
})

const submit = () => form.post(`/learners/${props.learner.id}/move`)
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/learners/${learner.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Déplacer l'Apprenant</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ learner.last_name }} {{ learner.first_name }}
                </p>
            </div>
        </div>

        <!-- Notice -->
        <div class="notice mb-lg">
            <span class="material-symbols-outlined" style="font-size:20px; color:#a16207">warning</span>
            <p class="text-body-sm text-amber-800">
                Le déplacement est irréversible. L'historique des présences de la formation source est conservé.
                L'apprenant peut uniquement être déplacé vers une formation du même projet.
            </p>
        </div>

        <div v-if="activeFormations.length === 0" class="card text-center py-xl">
            <span class="material-symbols-outlined text-secondary" style="font-size:48px">info</span>
            <p class="text-body-md text-secondary mt-md">Cet apprenant n'a aucune inscription active à déplacer.</p>
            <Link :href="`/learners/${learner.id}`" class="btn-secondary mt-lg inline-flex">
                Retour au profil
            </Link>
        </div>

        <form v-else @submit.prevent="submit" class="card space-y-lg">

            <!-- Formation source -->
            <div class="field">
                <label class="label">Formation de départ <span class="required">*</span></label>
                <select
                    v-model="form.source_formation_id"
                    class="input"
                    :class="{ 'input-error': form.errors.source_formation_id }"
                    @change="form.target_formation_id = ''"
                >
                    <option value="">Sélectionner</option>
                    <option v-for="f in activeFormations" :key="f.id" :value="f.id">
                        {{ f.name }} ({{ f.project.name }})
                    </option>
                </select>
                <p v-if="form.errors.source_formation_id" class="error-msg">{{ form.errors.source_formation_id }}</p>
            </div>

            <!-- Formation cible -->
            <div class="field">
                <label class="label">Formation cible <span class="required">*</span></label>
                <select
                    v-model="form.target_formation_id"
                    class="input"
                    :class="{ 'input-error': form.errors.target_formation_id }"
                    :disabled="!form.source_formation_id"
                >
                    <option value="">
                        {{ form.source_formation_id
                            ? (availableTargets.length === 0 ? 'Aucune formation disponible dans ce projet' : 'Sélectionner')
                            : ' Sélectionner d\'abord la formation de départ ' }}
                    </option>
                    <option v-for="f in availableTargets" :key="f.id" :value="f.id">
                        {{ f.name }}
                    </option>
                </select>
                <p v-if="form.errors.target_formation_id" class="error-msg">{{ form.errors.target_formation_id }}</p>
                <p v-if="form.source_formation_id && availableTargets.length === 0" class="hint">
                    Aucune autre formation active n'existe dans le projet « {{ sourceFormation?.project.name }} ».
                </p>
            </div>

            <!-- Notes -->
            <div class="field">
                <label class="label">Motif / Notes <span class="text-secondary font-normal">(facultatif)</span></label>
                <textarea
                    v-model="form.notes"
                    class="input"
                    rows="3"
                    placeholder="Raison du déplacement, informations complémentaires..."
                />
                <p v-if="form.errors.notes" class="error-msg">{{ form.errors.notes }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm pt-md border-t border-surface-container-highest">
                <Link :href="`/learners/${learner.id}`" class="btn-secondary">Annuler</Link>
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing || !form.source_formation_id || !form.target_formation_id"
                >
                    <span v-if="form.processing" class="spinner" />
                    <span class="material-symbols-outlined" style="font-size:16px">swap_horiz</span>
                    Confirmer le déplacement
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

.notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
}

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 32px; }
.field { display: flex; flex-direction: column; gap: 6px; }

.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
.required { color: #E5004C; }
.hint { font-size: 11px; color: #9aaabb; margin-top: 2px; }

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
.input:disabled { background: #f9fafb; color: #9aaabb; cursor: not-allowed; }
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
