<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
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
}>()

const search = ref('')
const form = useForm({ learner_id: '' })

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return props.learners
    return props.learners.filter(l =>
        l.first_name.toLowerCase().includes(q) ||
        l.last_name.toLowerCase().includes(q) ||
        (l.email?.toLowerCase().includes(q) ?? false)
    )
})

const select = (learner: Learner) => {
    form.learner_id = learner.id
}

const selectedLearner = computed(() =>
    props.learners.find(l => l.id === form.learner_id) ?? null
)

const submit = () => form.post(`/formations/${props.formation.id}/learners`)
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link :href="`/formations/${formation.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Inscrire un Apprenant</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Formation : <span class="font-semibold text-on-surface">{{ formation.name }}</span>
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-lg">

            <div class="card space-y-md">

                <!-- Sélection -->
                <div v-if="selectedLearner" class="selected-box">
                    <div class="flex items-center gap-sm flex-1">
                        <div class="avatar-sm">
                            {{ selectedLearner.first_name.charAt(0) }}{{ selectedLearner.last_name.charAt(0) }}
                        </div>
                        <div>
                            <p class="font-semibold text-on-surface text-body-sm">
                                {{ selectedLearner.last_name }} {{ selectedLearner.first_name }}
                            </p>
                            <p class="text-body-sm text-secondary">{{ selectedLearner.email ?? '' }}</p>
                        </div>
                    </div>
                    <button type="button" class="icon-btn" @click="form.learner_id = ''">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                    </button>
                </div>

                <!-- Recherche -->
                <div v-if="!selectedLearner">
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

                <!-- Liste -->
                <div v-if="!selectedLearner" class="learner-list">
                    <div v-if="learners.length === 0" class="py-xl text-center text-secondary text-body-sm">
                        Tous les apprenants sont déjà inscrits à cette formation.
                    </div>
                    <div v-else-if="filtered.length === 0" class="py-md text-center text-secondary text-body-sm">
                        Aucun résultat pour « {{ search }} »
                    </div>
                    <button
                        v-for="learner in filtered"
                        :key="learner.id"
                        type="button"
                        class="learner-row"
                        @click="select(learner)"
                    >
                        <div class="avatar-sm">
                            {{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}
                        </div>
                        <div class="text-left">
                            <p class="font-semibold text-on-surface text-body-sm">
                                {{ learner.last_name }} {{ learner.first_name }}
                            </p>
                            <p class="text-body-sm text-secondary">{{ learner.email ?? '' }}</p>
                        </div>
                        <span class="material-symbols-outlined ml-auto text-secondary" style="font-size:18px">add_circle</span>
                    </button>
                </div>

                <p v-if="form.errors.learner_id" class="error-msg">{{ form.errors.learner_id }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm">
                <Link :href="`/formations/${formation.id}`" class="btn-secondary">Annuler</Link>
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="!form.learner_id || form.processing"
                >
                    <span v-if="form.processing" class="spinner" />
                    <span class="material-symbols-outlined" style="font-size:16px">how_to_reg</span>
                    Inscrire à la formation
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

.selected-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
}

.learner-list {
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    max-height: 320px;
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
    border-left: none;
    border-right: none;
    border-top: none;
}
.learner-row:last-child { border-bottom: none; }
.learner-row:hover { background: #f9fafb; }
.learner-row:hover .material-symbols-outlined { color: #E5004C; }

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

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
    background: transparent;
    border: none;
    cursor: pointer;
}
.icon-btn:hover { color: #ba1a1a; }

.error-msg { font-size: 12px; color: #ba1a1a; }

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
