<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Project {
    id: string
    name: string
}

interface Formation {
    id: string
    name: string
    period: string
}

interface User {
    first_name: string
    last_name: string
    email: string
    is_active: boolean
}

interface Project {
    id: string
    name: string
}

interface Formation {
    id: string
    name: string
    project: Project
}

interface Trainer {
    id: string
    phone: string | null
    user: User
    formations: Formation[]
}

interface Paginated {
    data: Trainer[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

const props = defineProps<{
    trainers: Paginated
    filters: { search?: string }
    projects: Project[]
}>()

const search = ref(props.filters.search ?? '')

let timer: ReturnType<typeof setTimeout>
watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/trainers', { search: val }, { preserveState: true, replace: true })
    }, 400)
})

const destroy = (t: Trainer) => {
    if (confirm(`Supprimer le formateur « ${t.user.first_name} ${t.user.last_name} » ?`)) {
        router.delete(`/trainers/${t.id}`)
    }
}

const unassign = (trainer: Trainer, formation: Formation) => {
    if (confirm(`Retirer ${trainer.user.first_name} ${trainer.user.last_name} de la formation "${formation.name}" ?`)) {
        router.delete(`/trainers/${trainer.id}/unassign-formation/${formation.id}`)
    }
}

// Modal d'assignation
const showModal = ref(false)
const selectedTrainer = ref<Trainer | null>(null)
const selectedProject = ref<string>('')
const availableFormations = ref<Formation[]>([])
const loadingFormations = ref(false)

const assignForm = useForm({
    formation_ids: [] as string[],
})

const openAssignModal = (trainer: Trainer) => {
    selectedTrainer.value = trainer
    selectedProject.value = ''
    availableFormations.value = []
    assignForm.reset()
    assignForm.clearErrors()
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    selectedTrainer.value = null
    selectedProject.value = ''
    availableFormations.value = []
    assignForm.reset()
}

const onProjectChange = async () => {
    if (!selectedProject.value) {
        availableFormations.value = []
        assignForm.formation_ids = []
        return
    }

    loadingFormations.value = true
    try {
        const response = await fetch(`/api/projects/${selectedProject.value}/formations`)
        if (response.ok) {
            availableFormations.value = await response.json()

            // Pré-cocher les formations déjà assignées au formateur (pour ce projet)
            const assignedIds = selectedTrainer.value?.formations.map(f => f.id) ?? []
            assignForm.formation_ids = availableFormations.value
                .filter(f => assignedIds.includes(f.id))
                .map(f => f.id)
        }
    } catch (error) {
        console.error('Erreur lors du chargement des formations:', error)
    } finally {
        loadingFormations.value = false
    }
}

const isAlreadyAssigned = (formationId: string) => {
    return selectedTrainer.value?.formations.some(f => f.id === formationId) ?? false
}

const submitAssign = () => {
    if (!selectedTrainer.value) return

    assignForm.post(`/trainers/${selectedTrainer.value.id}/assign-formation`, {
        preserveState: true,
        onSuccess: () => {
            closeModal()
        },
    })
}

const canSubmit = computed(() => {
    return assignForm.formation_ids.length > 0 && !assignForm.processing
})

const toggleFormation = (formationId: string) => {
    const index = assignForm.formation_ids.indexOf(formationId)
    if (index === -1) {
        assignForm.formation_ids.push(formationId)
    } else {
        assignForm.formation_ids.splice(index, 1)
    }
}

const isFormationSelected = (formationId: string) => {
    return assignForm.formation_ids.includes(formationId)
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Formateurs</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ trainers.meta?.total ?? 0 }} formateur(s) enregistré(s).
                </p>
            </div>
            <Link href="/trainers/create" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                Inviter un Formateur
            </Link>
        </div>

        <!-- Barre de recherche -->
        <div class="search-bar">
            <div class="search-input-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Rechercher par nom, prénom, email..."
                    class="search-input"
                />
                <button
                    v-if="search"
                    @click="search = ''"
                    class="search-clear"
                    title="Effacer"
                >
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
            </div>
            <span v-if="search" class="search-hint">
                {{ trainers.data.length }} résultat(s)
            </span>
        </div>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formateur</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Email</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formations assignées</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="trainers.data.length === 0">
                            <td colspan="5" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun formateur trouvé.
                            </td>
                        </tr>
                        <tr
                            v-for="trainer in trainers.data"
                            :key="trainer.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <div class="flex items-center gap-sm">
                                    <div class="avatar">
                                        {{ trainer.user.first_name.charAt(0) }}{{ trainer.user.last_name.charAt(0) }}
                                    </div>
                                    <Link
                                        :href="`/trainers/${trainer.id}`"
                                        class="font-semibold text-on-surface hover:text-primary transition-colors"
                                    >
                                        {{ trainer.user.last_name }} {{ trainer.user.first_name }}
                                    </Link>
                                </div>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">{{ trainer.user.email }}</td>
                            <td class="px-md py-sm">
                                <div v-if="trainer.formations.length === 0" class="text-on-surface-variant text-body-sm"></div>
                                <div v-else class="flex flex-wrap gap-xs">
                                    <div
                                        v-for="formation in trainer.formations"
                                        :key="formation.id"
                                        class="formation-badge"
                                    >
                                        <span class="formation-project">{{ formation.project.name }}</span>
                                        <span class="formation-name">{{ formation.name }}</span>
                                        <button
                                            @click="unassign(trainer, formation)"
                                            class="formation-remove"
                                            title="Retirer de cette formation"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:12px">close</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-md py-sm">
                                <span class="active-badge" :class="trainer.user.is_active ? 'badge-active' : 'badge-pending'">
                                    {{ trainer.user.is_active ? 'Actif' : 'En attente' }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click="openAssignModal(trainer)" class="icon-btn assign" title="Assigner à une formation">
                                        <span class="material-symbols-outlined" style="font-size:18px">assignment_ind</span>
                                    </button>
                                    <Link :href="`/trainers/${trainer.id}`" class="icon-btn" title="Voir le profil">
                                        <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                                    </Link>
                                    <Link :href="`/trainers/${trainer.id}/edit`" class="icon-btn" title="Modifier">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button @click="destroy(trainer)" class="icon-btn danger" title="Supprimer">
                                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-md py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ trainers.meta?.from }}–{{ trainers.meta?.to }} sur {{ trainers.meta?.total }} formateurs
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in trainers.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal d'assignation -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <span class="material-symbols-outlined" style="font-size:20px; color:#E5004C">assignment_ind</span>
                        Assigner à une formation
                    </h3>
                    <p v-if="selectedTrainer" class="modal-subtitle">
                        {{ selectedTrainer.user.last_name }} {{ selectedTrainer.user.first_name }}
                    </p>
                    <button @click="closeModal" class="modal-close" title="Fermer">
                        <span class="material-symbols-outlined" style="font-size:20px">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitAssign" class="modal-body">
                    <!-- Projet -->
                    <div class="field">
                        <label class="label">Projet <span class="required">*</span></label>
                        <select
                            v-model="selectedProject"
                            class="input"
                            @change="onProjectChange"
                            :disabled="loadingFormations"
                        >
                            <option value="">Sélectionner un projet</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <!-- Formations (sélection multiple) -->
                    <div class="field">
                        <label class="label">Formations <span class="required">*</span></label>
                        <div v-if="!selectedProject" class="formation-placeholder">
                            Sélectionnez d'abord un projet
                        </div>
                        <div v-else-if="loadingFormations" class="formation-placeholder">
                            Chargement...
                        </div>
                        <div v-else-if="availableFormations.length === 0" class="formation-placeholder">
                            Aucune formation disponible
                        </div>
                        <div v-else class="formation-list">
                            <label
                                v-for="f in availableFormations"
                                :key="f.id"
                                class="formation-checkbox"
                                :class="{ 'formation-selected': isFormationSelected(f.id) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="f.id"
                                    :checked="isFormationSelected(f.id)"
                                    @change="toggleFormation(f.id)"
                                    class="sr-only"
                                />
                                <span class="formation-check">
                                    <span v-if="isFormationSelected(f.id)" class="material-symbols-outlined" style="font-size:14px">check</span>
                                </span>
                                <span class="formation-info">
                                    <span class="formation-info-name">{{ f.name }}</span>
                                    <span class="formation-info-period">{{ f.period }}</span>
                                </span>
                                <span v-if="isAlreadyAssigned(f.id)" class="already-assigned-badge">
                                    <span class="material-symbols-outlined" style="font-size:12px">check_circle</span>
                                    Déjà assignée
                                </span>
                            </label>
                        </div>
                        <p v-if="assignForm.errors.formation_ids" class="error-msg">{{ assignForm.errors.formation_ids }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="modal-actions">
                        <button type="button" @click="closeModal" class="btn-secondary">
                            Annuler
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="!canSubmit"
                        >
                            <span v-if="assignForm.processing" class="spinner-small" />
                            <span v-else class="material-symbols-outlined" style="font-size:16px">add</span>
                            Assigner
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>

<style scoped>
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #1F3A4D;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    flex-shrink: 0;
    text-transform: uppercase;
}

.active-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.badge-active  { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }
.icon-btn.assign:hover { color: #2563eb; }

/* Formation badges dans le tableau */
.formation-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 6px;
    font-size: 11px;
}
.formation-project {
    color: #92400e;
    font-weight: 600;
}
.formation-name {
    color: #78350f;
}
.formation-remove {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: none;
    background: rgba(146, 64, 14, 0.15);
    color: #92400e;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    margin-left: 4px;
}
.formation-remove:hover {
    background: rgba(146, 64, 14, 0.25);
    color: #78350f;
}

/* Liste de formations en checkboxes */
.formation-placeholder {
    padding: 16px;
    background: #f9fafb;
    border: 1px dashed #e0e3e5;
    border-radius: 8px;
    text-align: center;
    color: #9aaabb;
    font-size: 13px;
}
.formation-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}
.formation-checkbox {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.15s;
    border-bottom: 1px solid #f3f4f6;
}
.formation-checkbox:last-child {
    border-bottom: none;
}
.formation-checkbox:hover {
    background: #f9fafb;
}
.formation-checkbox.formation-selected {
    background: #fff5f8;
}
.formation-check {
    width: 18px;
    height: 18px;
    border: 2px solid #e0e3e5;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
    color: #fff;
}
.formation-checkbox.formation-selected .formation-check {
    background: #E5004C;
    border-color: #E5004C;
}
.formation-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}
.formation-info-name {
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
}
.formation-info-period {
    font-size: 11px;
    color: #9aaabb;
}
.already-assigned-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    background: #d1fae5;
    color: #065f46;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 600;
    flex-shrink: 0;
}

/* Barre de recherche */
.search-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.search-input-wrapper {
    position: relative;
    flex: 1;
    max-width: 400px;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: #9aaabb;
    font-size: 20px;
    pointer-events: none;
}
.search-input {
    width: 100%;
    padding: 10px 40px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    transition: all 0.15s ease;
    outline: none;
}
.search-input:focus {
    background: #fff;
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.search-input::placeholder {
    color: #9aaabb;
}
.search-clear {
    position: absolute;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #e0e3e5;
    color: #515f74;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.search-clear:hover {
    background: #d1d5db;
    color: #191c1e;
}
.search-hint {
    font-size: 13px;
    color: #9aaabb;
    font-weight: 500;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 6px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
    transition: background 0.15s;
    cursor: pointer;
    text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }

/* Modal styles */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 20px;
}
.modal-content {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e0e3e5;
    position: relative;
}
.modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
    font-weight: 600;
    color: #191c1e;
    margin: 0;
}
.modal-subtitle {
    font-size: 13px;
    color: #9aaabb;
    margin-top: 4px;
    margin-bottom: 0;
}
.modal-close {
    position: absolute;
    right: 16px;
    top: 16px;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #9aaabb;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.modal-close:hover {
    background: #f2f4f6;
    color: #191c1e;
}
.modal-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid #e0e3e5;
}

/* Form fields */
.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.field-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.label {
    font-size: 12px;
    font-weight: 600;
    color: #191c1e;
}
.required {
    color: #E5004C;
}
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
.input:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.input-error {
    border-color: #ba1a1a;
}
.input:disabled {
    background: #f3f4f6;
    color: #9aaabb;
    cursor: not-allowed;
}
select.input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    padding-right: 36px;
}
.error-msg {
    font-size: 12px;
    color: #ba1a1a;
}
.checkbox {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: #E5004C;
    cursor: pointer;
}
.checkbox-label {
    font-size: 14px;
    color: #191c1e;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.hint {
    font-size: 12px;
    color: #9aaabb;
    font-weight: 400;
}

/* Buttons */
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
    cursor: pointer;
}
.btn-secondary:hover {
    background: #f2f4f6;
}
.spinner-small {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
