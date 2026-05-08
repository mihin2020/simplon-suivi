<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface EducationLevel {
    name: string
}

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
    phone: string | null
    education_level: EducationLevel | null
    pivot: { status: string; enrolled_at: string; withdrawn_at: string | null; notes: string | null }
}

interface User {
    first_name: string
    last_name: string
    email: string
}

interface Trainer {
    id: string
    specialty: string | null
    user: User
    pivot?: { is_lead: boolean }
}

interface Project {
    id: string
    name: string
}

interface Formation {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    project: Project
    trainers: Trainer[]
    referentiel: { id: string; name: string } | null
}

const props = defineProps<{
    formation: Formation
    activeLearners: {
        data: Learner[]
        links: Array<{ url: string | null; label: string; active: boolean }>
        total: number
        per_page: number
        current_page: number
        last_page: number
        from: number | null
        to: number | null
    }
    inactiveLearners: Learner[]
    availableTrainers: Trainer[]
}>()

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

const statusLabels: Record<string, string> = {
    active: 'Active',
    completed: 'Terminée',
    archived: 'Archivée',
}

const withdrawLearner = (learner: Learner) => {
    if (confirm(`Retirer ${learner.first_name} ${learner.last_name} de cette formation ?`)) {
        router.delete(`/formations/${props.formation.id}/learners/${learner.id}`)
    }
}

// Modal abandon apprenant
const showAbandonModal = ref(false)
const abandonLearnerData = ref<Learner | null>(null)
const abandonNotes = ref('')

const openAbandonModal = (learner: Learner) => {
    abandonLearnerData.value = learner
    abandonNotes.value = ''
    showAbandonModal.value = true
}

const closeAbandonModal = () => {
    showAbandonModal.value = false
    abandonLearnerData.value = null
    abandonNotes.value = ''
}

const confirmAbandon = () => {
    if (!abandonLearnerData.value) return
    router.post(`/formations/${props.formation.id}/learners/${abandonLearnerData.value.id}/abandon`, {
        notes: abandonNotes.value,
    }, {
        preserveState: true,
        onSuccess: () => {
            closeAbandonModal()
        },
    })
}

// Onglet apprenants
const activeTab = ref<'actifs' | 'inactifs'>('actifs')

const statusLabelsLearner: Record<string, string> = {
    in_progress: 'En cours',
    withdrawn: 'Abandonné',
    completed: 'Diplômé',
    moved: 'Transféré',
}

const statusColors: Record<string, string> = {
    in_progress: '#22c55e',
    withdrawn: '#ef4444',
    completed: '#3b82f6',
    moved: '#f97316',
}

// Modal assignation formateurs
const showTrainerModal = ref(false)
const selectedTrainerIds = ref<string[]>([])
const trainerSearch = ref('')

const filteredAvailableTrainers = computed(() => {
    const q = trainerSearch.value.toLowerCase().trim()
    if (!q) return props.availableTrainers
    return props.availableTrainers.filter(t =>
        `${t.user.first_name} ${t.user.last_name}`.toLowerCase().includes(q) ||
        t.user.last_name.toLowerCase().includes(q) ||
        t.user.first_name.toLowerCase().includes(q)
    )
})

const openTrainerModal = () => {
    selectedTrainerIds.value = []
    trainerSearch.value = ''
    showTrainerModal.value = true
}

const closeTrainerModal = () => {
    showTrainerModal.value = false
    selectedTrainerIds.value = []
    trainerSearch.value = ''
}

const toggleTrainer = (id: string) => {
    const idx = selectedTrainerIds.value.indexOf(id)
    if (idx === -1) selectedTrainerIds.value.push(id)
    else selectedTrainerIds.value.splice(idx, 1)
}

const isTrainerSelected = (id: string) => selectedTrainerIds.value.includes(id)

const assignTrainer = () => {
    if (selectedTrainerIds.value.length === 0) return

    router.post(`/formations/${props.formation.id}/trainers`, {
        trainer_ids: selectedTrainerIds.value,
    }, {
        preserveState: true,
        onSuccess: () => {
            closeTrainerModal()
        },
    })
}

const unassignTrainer = (trainer: Trainer) => {
    if (confirm(`Retirer ${trainer.user.first_name} ${trainer.user.last_name} de cette formation ?`)) {
        router.delete(`/formations/${props.formation.id}/trainers/${trainer.id}`)
    }
}

// Recherche apprenants actifs
const learnerSearch = ref('')
const filteredLearners = computed(() => {
    const q = learnerSearch.value.toLowerCase().trim()
    if (!q) return props.activeLearners.data
    return props.activeLearners.data.filter(l =>
        `${l.first_name} ${l.last_name}`.toLowerCase().includes(q) ||
        l.last_name.toLowerCase().includes(q) ||
        l.first_name.toLowerCase().includes(q) ||
        (l.email && l.email.toLowerCase().includes(q))
    )
})

// Recherche apprenants inactifs
const inactiveSearch = ref('')
const filteredInactiveLearners = computed(() => {
    const q = inactiveSearch.value.toLowerCase().trim()
    if (!q) return props.inactiveLearners
    return props.inactiveLearners.filter(l =>
        `${l.first_name} ${l.last_name}`.toLowerCase().includes(q) ||
        l.last_name.toLowerCase().includes(q) ||
        l.first_name.toLowerCase().includes(q) ||
        (l.email && l.email.toLowerCase().includes(q))
    )
})
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="bg-surface-container-lowest p-lg rounded-xl border border-surface-container-highest space-y-md">
            <!-- Ligne 1 : retour + titre complet -->
            <div class="flex items-center gap-md">
                <Link :href="`/projects/${formation.project.id}`" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="flex items-center gap-sm flex-wrap flex-1">
                    <h1 class="text-h1 font-bold text-on-surface">{{ formation.name }}</h1>
                    <span class="status-badge" :class="`status-${formation.status}`">
                        {{ statusLabels[formation.status] ?? formation.status }}
                    </span>
                </div>
            </div>
            <!-- Ligne 2 : meta + boutons -->
            <div class="flex flex-wrap items-center justify-between gap-md pl-[56px]">
                <div class="flex flex-wrap items-center gap-md text-body-sm text-secondary">
                    <span class="flex items-center gap-xs">
                        <span class="material-symbols-outlined" style="font-size:16px">folder_open</span>
                        {{ formation.project.name }}
                    </span>
                    <span class="flex items-center gap-xs">
                        <span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>
                        {{ fmt(formation.started_at) }} → {{ fmt(formation.ended_at) }}
                    </span>
                    <span v-if="formation.description" class="text-secondary">· {{ formation.description }}</span>
                </div>
                <div class="flex items-center gap-sm flex-shrink-0">
                <Link :href="`/formations/${formation.id}/attendances`" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">fact_check</span>
                    Présences
                </Link>
                <Link
                    :href="formation.referentiel ? `/referentiels/${formation.referentiel.id}` : `/formations/${formation.id}/edit`"
                    class="btn-secondary"
                    :title="formation.referentiel ? formation.referentiel.name : 'Aucun référentiel · cliquez pour en assigner un'"
                >
                    <span class="material-symbols-outlined" style="font-size:18px">menu_book</span>
                    {{ formation.referentiel ? 'Référentiel' : 'Assigner un référentiel' }}
                </Link>
                <Link :href="`/formations/${formation.id}/edit`" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Modifier
                </Link>
                <Link :href="`/formations/${formation.id}/learners/enroll`" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">search</span>
                    Apprenant existant
                </Link>
                <Link :href="`/formations/${formation.id}/learners/new`" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                    Créer &amp; Inscrire
                </Link>
                </div><!-- /boutons -->
            </div><!-- /ligne 2 -->
        </div><!-- /en-tête -->

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl items-start">

            <!-- Formateurs -->
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                    <div class="flex items-center gap-sm">
                        <h2 class="text-h2 font-semibold text-on-surface">Formateurs</h2>
                        <span class="count-badge">{{ formation.trainers.length }}</span>
                    </div>
                    <button
                        @click="openTrainerModal"
                        class="btn-assign"
                        title="Assigner un formateur"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    </button>
                </div>
                <div class="divide-y divide-surface-container-highest">
                    <div v-if="formation.trainers.length === 0" class="px-lg py-xl text-center text-secondary text-body-sm">
                        Aucun formateur assigné.
                    </div>
                    <div
                        v-for="trainer in formation.trainers"
                        :key="trainer.id"
                        class="px-lg py-sm flex items-center gap-sm group"
                    >
                        <div class="avatar-sm">
                            {{ trainer.user.first_name.charAt(0) }}{{ trainer.user.last_name.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-on-surface text-body-sm truncate">
                                {{ trainer.user.last_name }} {{ trainer.user.first_name }}
                                <span v-if="trainer.pivot?.is_lead" class="lead-badge">Principal</span>
                            </p>
                            <p v-if="trainer.specialty" class="text-body-sm text-secondary truncate">{{ trainer.specialty }}</p>
                        </div>
                        <button
                            @click="unassignTrainer(trainer)"
                            class="btn-unassign opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Retirer de la formation"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Apprenants avec onglets -->
            <div class="lg:col-span-2 bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                <!-- Onglets -->
                <div class="flex border-b border-surface-container-highest">
                    <button
                        @click="activeTab = 'actifs'"
                        class="tab-btn"
                        :class="{ 'tab-active': activeTab === 'actifs' }"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">groups</span>
                        Apprenants actifs
                        <span class="tab-badge">{{ activeLearners.total }}</span>
                    </button>
                    <button
                        @click="activeTab = 'inactifs'"
                        class="tab-btn"
                        :class="{ 'tab-active': activeTab === 'inactifs' }"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">person_off</span>
                        Inactifs
                        <span class="tab-badge">{{ inactiveLearners.length }}</span>
                    </button>
                </div>

                <!-- Contenu Actifs -->
                <div v-if="activeTab === 'actifs'">
                    <div class="px-lg py-md border-b border-surface-container-highest">
                        <div class="search-bar">
                            <span class="material-symbols-outlined search-icon">search</span>
                            <input
                                v-model="learnerSearch"
                                type="text"
                                placeholder="Rechercher un apprenant par nom ou email..."
                                class="search-input"
                            />
                            <button v-if="learnerSearch" @click="learnerSearch = ''" class="search-clear">
                                <span class="material-symbols-outlined" style="font-size:16px">close</span>
                            </button>
                            <span v-if="learnerSearch" class="search-count">
                                {{ filteredLearners.length }} trouvé{{ filteredLearners.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface border-b border-surface-container-highest">
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide w-10 text-center">N°</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Apprenant</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Niveau</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Inscrit le</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container-highest">
                                <tr v-if="filteredLearners.length === 0">
                                    <td colspan="5" class="px-md py-xl text-center text-secondary text-body-md">
                                        {{ learnerSearch ? 'Aucun apprenant correspond à cette recherche.' : 'Aucun apprenant inscrit.' }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(learner, idx) in filteredLearners"
                                    :key="learner.id"
                                    class="hover:bg-surface-bright transition-colors group"
                                >
                                    <td class="px-md py-sm text-secondary text-center text-body-sm">
                                        {{ String((activeLearners.from ?? 1) + activeLearners.data.findIndex(l => l.id === learner.id)).padStart(2, '0') }}
                                    </td>
                                    <td class="px-md py-sm">
                                        <Link
                                            :href="`/learners/${learner.id}`"
                                            class="font-semibold text-on-surface hover:text-primary transition-colors"
                                        >
                                            {{ learner.last_name }} {{ learner.first_name }}
                                        </Link>
                                        <p v-if="learner.email" class="text-body-sm text-secondary">{{ learner.email }}</p>
                                    </td>
                                    <td class="px-md py-sm text-on-surface-variant text-body-sm">
                                        {{ learner.education_level?.name ?? '' }}
                                    </td>
                                    <td class="px-md py-sm text-on-surface-variant text-body-sm whitespace-nowrap">
                                        {{ fmt(learner.pivot.enrolled_at) }}
                                    </td>
                                    <td class="px-md py-sm text-right">
                                        <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                            <Link :href="`/learners/${learner.id}`" class="icon-btn" title="Voir le profil">
                                                <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                                            </Link>
                                            <button @click="openAbandonModal(learner)" class="icon-btn warning" title="Marquer comme abandonné">
                                                <span class="material-symbols-outlined" style="font-size:18px">logout</span>
                                            </button>
                                            <button @click="withdrawLearner(learner)" class="icon-btn danger" title="Retirer de la formation">
                                                <span class="material-symbols-outlined" style="font-size:18px">person_remove</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="activeLearners.links?.length" class="px-lg py-md border-t border-surface-container-highest flex items-center justify-between gap-md flex-wrap">
                        <p class="text-body-sm text-secondary">
                            Page {{ activeLearners.current_page }} / {{ activeLearners.last_page }}
                        </p>
                        <nav class="flex items-center gap-xs flex-wrap">
                            <template v-for="(l, i) in activeLearners.links" :key="i">
                                <Link
                                    v-if="l.url"
                                    :href="l.url"
                                    class="pager-btn"
                                    :class="{ 'pager-active': l.active }"
                                    preserve-scroll
                                >
                                    <span v-html="l.label"></span>
                                </Link>
                                <span
                                    v-else
                                    class="pager-btn pager-disabled"
                                    aria-disabled="true"
                                >
                                    <span v-html="l.label"></span>
                                </span>
                            </template>
                        </nav>
                    </div>
                </div>

                <!-- Contenu Inactifs -->
                <div v-else>
                    <div class="px-lg py-md border-b border-surface-container-highest">
                        <div class="search-bar">
                            <span class="material-symbols-outlined search-icon">search</span>
                            <input
                                v-model="inactiveSearch"
                                type="text"
                                placeholder="Rechercher parmi les apprenants inactifs..."
                                class="search-input"
                            />
                            <button v-if="inactiveSearch" @click="inactiveSearch = ''" class="search-clear">
                                <span class="material-symbols-outlined" style="font-size:16px">close</span>
                            </button>
                            <span v-if="inactiveSearch" class="search-count">
                                {{ filteredInactiveLearners.length }} trouvé{{ filteredInactiveLearners.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface border-b border-surface-container-highest">
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide w-10 text-center">N°</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Apprenant</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Date</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Notes</th>
                                    <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-container-highest">
                                <tr v-if="filteredInactiveLearners.length === 0">
                                    <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                        {{ inactiveSearch ? 'Aucun résultat pour cette recherche.' : 'Aucun apprenant inactif.' }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="(learner, idx) in filteredInactiveLearners"
                                    :key="learner.id"
                                    class="hover:bg-surface-bright transition-colors"
                                >
                                    <td class="px-md py-sm text-secondary text-center text-body-sm">
                                        {{ String(idx + 1).padStart(2, '0') }}
                                    </td>
                                    <td class="px-md py-sm">
                                        <Link
                                            :href="`/learners/${learner.id}`"
                                            class="font-semibold text-on-surface hover:text-primary transition-colors"
                                        >
                                            {{ learner.last_name }} {{ learner.first_name }}
                                        </Link>
                                        <p v-if="learner.email" class="text-body-sm text-secondary">{{ learner.email }}</p>
                                    </td>
                                    <td class="px-md py-sm">
                                        <span
                                            class="status-badge-learner"
                                            :style="{ background: statusColors[learner.pivot.status] + '20', color: statusColors[learner.pivot.status] }"
                                        >
                                            {{ statusLabelsLearner[learner.pivot.status] ?? learner.pivot.status }}
                                        </span>
                                    </td>
                                    <td class="px-md py-sm text-on-surface-variant text-body-sm whitespace-nowrap">
                                        {{ fmt(learner.pivot.withdrawn_at) }}
                                    </td>
                                    <td class="px-md py-sm text-on-surface-variant text-body-sm max-w-[200px] truncate" :title="learner.pivot.notes ?? ''">
                                        {{ learner.pivot.notes || '' }}
                                    </td>
                                    <td class="px-md py-sm text-right">
                                        <Link :href="`/learners/${learner.id}`" class="icon-btn" title="Voir le profil">
                                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Assigner un formateur -->
        <div v-if="showTrainerModal" class="modal-overlay" @click.self="closeTrainerModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title">Assigner des formateurs</h3>
                        <p v-if="selectedTrainerIds.length > 0" class="modal-subtitle">
                            {{ selectedTrainerIds.length }} sélectionné(s)
                        </p>
                    </div>
                    <button @click="closeTrainerModal" class="modal-close">
                        <span class="material-symbols-outlined" style="font-size:20px">close</span>
                    </button>
                </div>

                <div v-if="availableTrainers.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:48px;color:#9aaabb">school</span>
                    <p>Aucun formateur disponible</p>
                    <p class="empty-hint">Tous les formateurs actifs sont déjà assignés.</p>
                </div>

                <template v-else>
                    <!-- Barre de recherche -->
                    <div class="modal-search">
                        <span class="material-symbols-outlined modal-search-icon">search</span>
                        <input
                            v-model="trainerSearch"
                            type="text"
                            placeholder="Rechercher un formateur..."
                            class="modal-search-input"
                            autofocus
                        />
                        <button v-if="trainerSearch" @click="trainerSearch = ''" class="modal-search-clear">
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div v-if="filteredAvailableTrainers.length === 0" class="no-result">
                            Aucun résultat pour "{{ trainerSearch }}"
                        </div>
                        <div v-else class="trainer-list">
                            <label
                                v-for="trainer in filteredAvailableTrainers"
                                :key="trainer.id"
                                class="trainer-option"
                                :class="{ 'trainer-selected': isTrainerSelected(trainer.id) }"
                                @click="toggleTrainer(trainer.id)"
                            >
                                <div class="trainer-avatar">
                                    {{ trainer.user.first_name.charAt(0) }}{{ trainer.user.last_name.charAt(0) }}
                                </div>
                                <div class="trainer-info">
                                    <span class="trainer-name">{{ trainer.user.last_name }} {{ trainer.user.first_name }}</span>
                                    <span v-if="trainer.specialty" class="trainer-specialty">{{ trainer.specialty }}</span>
                                </div>
                                <span class="trainer-checkbox" :class="{ 'trainer-checkbox-checked': isTrainerSelected(trainer.id) }">
                                    <span v-if="isTrainerSelected(trainer.id)" class="material-symbols-outlined" style="font-size:14px">check</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </template>

                <div class="modal-actions">
                    <button @click="closeTrainerModal" class="btn-secondary">Annuler</button>
                    <button
                        @click="assignTrainer"
                        class="btn-primary"
                        :disabled="selectedTrainerIds.length === 0"
                    >
                        Assigner
                        <span v-if="selectedTrainerIds.length > 0" class="btn-count">{{ selectedTrainerIds.length }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Abandon -->
        <div v-if="showAbandonModal" class="modal-overlay" @click.self="closeAbandonModal">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title">Marquer comme abandonné</h3>
                        <p class="modal-subtitle" v-if="abandonLearnerData">
                            {{ abandonLearnerData.first_name }} {{ abandonLearnerData.last_name }}
                        </p>
                    </div>
                    <button @click="closeAbandonModal" class="modal-close">
                        <span class="material-symbols-outlined" style="font-size:20px">close</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Motif / Notes (optionnel)</label>
                        <textarea
                            v-model="abandonNotes"
                            rows="3"
                            class="form-textarea"
                            placeholder="Raison de l'abandon, date effective, etc."
                        ></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button @click="closeAbandonModal" class="btn-secondary">Annuler</button>
                    <button @click="confirmAbandon" class="btn-primary btn-danger">
                        Confirmer l'abandon
                    </button>
                </div>
            </div>
        </div>

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
    margin-top: 2px;
}
.icon-back:hover { background: rgba(255,255,255,0.1); color: #191c1e; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}
.status-active    { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-archived  { background: #f3f4f6; color: #6b7280; }

.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    background: #f2f4f6;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #1F3A4D;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    text-transform: uppercase;
}

.lead-badge {
    display: inline-block;
    margin-left: 4px;
    padding: 1px 6px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    vertical-align: middle;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #e0e3e5;
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }

.pager-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 13px;
    color: #515f74;
    text-decoration: none;
    background: #fff;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.pager-btn:hover { background: #f2f4f6; }
.pager-active { border-color: #E5004C; color: #E5004C; background: #fff0f4; }
.pager-disabled { opacity: 0.5; pointer-events: none; }

/* Bouton assigner formateur */
.btn-assign {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px dashed #c7cdd4;
    background: transparent;
    color: #515f74;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-assign:hover {
    border-color: #E5004C;
    color: #E5004C;
    background: #fff0f4;
}

/* Bouton retirer formateur */
.btn-unassign {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #fee2e2;
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.btn-unassign:hover {
    background: #fecaca;
}

/* Modal overlay */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 16px;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f0f2f4;
}
.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #191c1e;
}
.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: #515f74;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
}
.modal-close:hover { background: #f2f4f6; }

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid #f0f2f4;
}

/* État vide */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 32px 16px;
    text-align: center;
    font-size: 14px;
    color: #515f74;
    font-weight: 500;
}
.empty-hint {
    font-size: 12px;
    color: #9aaabb;
    font-weight: 400;
}

/* Modal search */
.modal-subtitle {
    font-size: 12px;
    color: #E5004C;
    font-weight: 500;
    margin-top: 2px;
}
.modal-search {
    position: relative;
    display: flex;
    align-items: center;
    padding: 0 24px 12px;
    border-bottom: 1px solid #f0f2f4;
}
.modal-search-icon {
    position: absolute;
    left: 36px;
    color: #9aaabb;
    font-size: 18px;
    pointer-events: none;
}
.modal-search-input {
    width: 100%;
    padding: 9px 36px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 13px;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    transition: all 0.15s;
}
.modal-search-input:focus {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.modal-search-input::placeholder { color: #9aaabb; }
.modal-search-clear {
    position: absolute;
    right: 32px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: none;
    background: #e0e3e5;
    color: #515f74;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.modal-search-clear:hover { background: #d1d5db; }

.no-result {
    padding: 24px;
    text-align: center;
    font-size: 13px;
    color: #9aaabb;
}

/* Liste de sélection des formateurs */
.trainer-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.trainer-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
}
.trainer-option:hover {
    background: #f9fafb;
}
.trainer-selected {
    background: #fff5f8 !important;
    border-color: #E5004C;
}
.trainer-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #1F3A4D;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
    transition: background 0.15s;
}
.trainer-selected .trainer-avatar {
    background: #E5004C;
}
.trainer-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.trainer-name {
    font-size: 14px;
    font-weight: 600;
    color: #191c1e;
}
.trainer-specialty {
    font-size: 12px;
    color: #9aaabb;
}
.trainer-checkbox {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
    color: #fff;
}
.trainer-checkbox-checked {
    background: #E5004C;
    border-color: #E5004C;
}

/* Bouton Assigner avec compteur */
.btn-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: rgba(255,255,255,0.25);
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Barre de recherche apprenants */
.search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    max-width: 480px;
}
.search-icon {
    font-size: 18px;
    color: #9aaabb;
    flex-shrink: 0;
}
.search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #191c1e;
    background: transparent;
    padding: 4px 0;
}
.search-input::placeholder { color: #9aaabb; }
.search-clear {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    background: #f5f7f9;
    color: #515f74;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.search-clear:hover { background: #eceef0; }
.search-count {
    font-size: 11px;
    font-weight: 600;
    color: #9aaabb;
    background: #f5f7f9;
    padding: 2px 8px;
    border-radius: 99px;
    flex-shrink: 0;
}

/* Onglets */
.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 500;
    color: #515f74;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
}
.tab-btn:hover { color: #191c1e; background: #f9fafb; }
.tab-active {
    color: #E5004C;
    border-bottom-color: #E5004C;
    background: #fff5f8;
}
.tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: #f2f4f6;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    color: #515f74;
}
.tab-active .tab-badge { background: #E5004C; color: #fff; }

/* Badge statut apprenant */
.status-badge-learner {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}

/* Bouton warning */
.icon-btn.warning {
    color: #d97706;
}
.icon-btn.warning:hover {
    color: #b45309;
    background: #fef3c7;
}

/* Bouton danger */
.btn-danger {
    background: #dc2626;
}
.btn-danger:hover {
    background: #b91c1c;
}

/* Formulaire modal */
.form-group {
    margin-bottom: 16px;
}
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #515f74;
    margin-bottom: 6px;
}
.form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    resize: vertical;
    transition: all 0.15s;
}
.form-textarea:focus {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
</style>
