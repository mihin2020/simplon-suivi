<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
}

interface Formation {
    id: string
    name: string
    project: { id: string; name: string }
}

const props = defineProps<{
    formation: Formation
    learners: Learner[]
    remainingSeats: number | null
}>()

const search = ref('')
const form = useForm<{ learner_ids: string[] }>({ learner_ids: [] })

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return props.learners
    return props.learners.filter(l =>
        l.first_name.toLowerCase().includes(q) ||
        l.last_name.toLowerCase().includes(q) ||
        (l.email?.toLowerCase().includes(q) ?? false)
    )
})

const isSelected = (learner: Learner) => form.learner_ids.includes(learner.id)

const toggle = (learner: Learner) => {
    form.learner_ids = isSelected(learner)
        ? form.learner_ids.filter(id => id !== learner.id)
        : [...form.learner_ids, learner.id]
}

// « Tout sélectionner » ne porte que sur les résultats affichés (donc sur la recherche en cours).
const allFilteredSelected = computed(() =>
    filtered.value.length > 0 && filtered.value.every(isSelected)
)

const toggleAllFiltered = () => {
    const shown = new Set(filtered.value.map(l => l.id))
    form.learner_ids = allFilteredSelected.value
        ? form.learner_ids.filter(id => !shown.has(id))
        : [...new Set([...form.learner_ids, ...shown])]
}

const exceedsSeats = computed(() =>
    props.remainingSeats !== null && form.learner_ids.length > props.remainingSeats
)

const submitLabel = computed(() => {
    const count = form.learner_ids.length
    if (count === 0) return 'Inscrire à la formation'
    return count === 1 ? 'Inscrire 1 apprenant' : `Inscrire ${count} apprenants`
})

// Les erreurs peuvent porter sur la liste (learner_ids), sur un apprenant (learner_ids.N) ou sur la formation.
const errors = computed(() => [...new Set(
    Object.entries(form.errors)
        .filter(([key]) => key === 'formation_id' || key.startsWith('learner_ids'))
        .map(([, message]) => message)
)])

const submit = () => form.post(`/formations/${props.formation.id}/learners`)
</script>

<template>
    <Head title="Inscrire des apprenants" />
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/formations/${formation.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Inscrire des Apprenants</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Formation : <span class="font-semibold text-on-surface">{{ formation.name }}</span>
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-lg">

            <div class="card space-y-md">

                <!-- Recherche -->
                <div>
                    <label class="label mb-xs block">Rechercher un apprenant</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:18px">search</span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Nom, prénom ou email..."
                            class="input pl-xl"
                            autofocus
                        />
                    </div>
                </div>

                <!-- Résumé de la sélection -->
                <div v-if="learners.length > 0" class="flex items-center justify-between gap-md flex-wrap">
                    <label class="flex items-center gap-sm text-body-sm text-on-surface cursor-pointer">
                        <input
                            type="checkbox"
                            class="check"
                            :checked="allFilteredSelected"
                            :disabled="filtered.length === 0"
                            @change="toggleAllFiltered"
                        />
                        {{ search.trim() ? 'Tout sélectionner (résultats affichés)' : 'Tout sélectionner' }}
                    </label>
                    <p class="text-body-sm text-secondary">
                        <span class="font-semibold text-on-surface">{{ form.learner_ids.length }}</span> sélectionné(s)
                        <template v-if="remainingSeats !== null"> · {{ remainingSeats }} place(s) restante(s)</template>
                    </p>
                </div>

                <!-- Liste -->
                <div class="learner-list">
                    <div v-if="learners.length === 0" class="py-xl text-center text-secondary text-body-sm">
                        Tous les apprenants sont déjà inscrits à cette formation.
                    </div>
                    <div v-else-if="filtered.length === 0" class="py-md text-center text-secondary text-body-sm">
                        Aucun résultat pour « {{ search }} »
                    </div>
                    <label
                        v-for="learner in filtered"
                        :key="learner.id"
                        class="learner-row"
                        :class="{ 'learner-row-selected': isSelected(learner) }"
                    >
                        <input
                            type="checkbox"
                            class="check"
                            :checked="isSelected(learner)"
                            @change="toggle(learner)"
                        />
                        <div class="avatar-sm">
                            {{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}
                        </div>
                        <div class="text-left">
                            <p class="font-semibold text-on-surface text-body-sm">
                                {{ learner.last_name }} {{ learner.first_name }}
                            </p>
                            <p class="text-body-sm text-secondary">{{ learner.email ?? '' }}</p>
                        </div>
                    </label>
                </div>

                <p v-if="exceedsSeats" class="warning-msg">
                    Vous avez sélectionné plus d'apprenants que de places restantes ({{ remainingSeats }}).
                </p>
                <p v-for="message in errors" :key="message" class="error-msg">{{ message }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm">
                <Link :href="`/formations/${formation.id}`" class="btn-secondary">Annuler</Link>
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.learner_ids.length === 0 || form.processing"
                >
                    <span v-if="form.processing" class="spinner" />
                    <span class="material-symbols-outlined" style="font-size:16px">how_to_reg</span>
                    {{ submitLabel }}
                </button>
            </div>

        </form>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }

.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }

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

.learner-list {
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    max-height: 360px;
    overflow-y: auto;
}

.learner-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 16px;
    border-bottom: 1px solid #f2f4f6;
    transition: background 0.15s;
    cursor: pointer;
    background: transparent;
}
.learner-row:last-child { border-bottom: none; }
.learner-row:hover { background: #f9fafb; }
.learner-row-selected { background: #fff5f8; }

.check { width: 16px; height: 16px; accent-color: #E5004C; flex-shrink: 0; cursor: pointer; }

.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #E5004C;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    text-transform: uppercase;
}

.error-msg { font-size: 12px; color: #ba1a1a; }
.warning-msg { font-size: 12px; color: #b45309; }

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
