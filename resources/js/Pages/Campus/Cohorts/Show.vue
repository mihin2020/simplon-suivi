<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineOptions({ layout: AdminLayout })

interface Formation { id: string; name: string; total_cost?: number }
interface StatusOpt { value: string; label: string; color: string }
interface EducationLevel { id: number; name: string }
interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
    phone: string | null
    gender: string | null
    birth_date: string | null
    photo_path: string | null
    emergency_contact_name: string | null
    emergency_contact_firstname: string | null
    emergency_contact_phone: string | null
    education_level?: { id: number; name: string } | null
    pivot?: { enrolled_at: string; status: string }
}

interface Cohort {
    id: string
    name: string
    started_at: string
    ended_at: string
    status: string
    campus_formation: Formation
}

interface CohortOption {
    id: string
    name: string
    formation_name: string
    total_cost: number
}

interface PaymentStats {
    total_collected: number
    total_expected: number
    total_remaining: number
    paid_count: number
    overdue_count: number
}

interface PaginatedLearners {
    data: Learner[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    from: number | null
    to: number | null
    total: number
    current_page: number
    last_page: number
}

const props = defineProps<{
    cohort: Cohort
    learners: PaginatedLearners
    paymentStats: PaymentStats
    statuses: StatusOpt[]
    availableLearners?: Learner[]
    educationLevels?: EducationLevel[]
    availableCohorts?: CohortOption[]
}>()

const fmt = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const fmtCost = (n: number) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

const statusObj = (val: string) => props.statuses.find(s => s.value === val)
const statusClass: Record<string, string> = {
    planifiee: 'badge-blue',
    en_cours:  'badge-green',
    cloturee:  'badge-gray',
}
const learnerStatusClass: Record<string, string> = {
    actif:    'badge-green',
    retrait:  'badge-gray',
    diplome:  'badge-blue',
    deplace:  'badge-purple',
}
const learnerStatusLabel: Record<string, string> = {
    actif:   'Actif',
    retrait: 'Retiré',
    diplome: 'Diplômé',
    deplace: 'Déplacé',
}

// ── Inscription apprenants ────────────────────────────────────────────────
const showEnrollModal = ref(false)
const enrollSearch    = ref('')
const enrollForm = useForm({ learner_ids: [] as string[] })

const filteredAvailable = computed(() => {
    const enrolled = new Set(props.learners.data.map(l => l.id))
    return (props.availableLearners ?? []).filter(l => {
        if (enrolled.has(l.id)) return false
        const q = enrollSearch.value.toLowerCase()
        return !q || `${l.first_name} ${l.last_name} ${l.email}`.toLowerCase().includes(q)
    })
})

const toggleLearner = (id: string) => {
    const i = enrollForm.learner_ids.indexOf(id)
    if (i === -1) enrollForm.learner_ids.push(id)
    else enrollForm.learner_ids.splice(i, 1)
}

const submitEnroll = () => {
    enrollForm.post(`/campus/cohorts/${props.cohort.id}/enroll`, {
        onSuccess: () => {
            showEnrollModal.value = false
            enrollForm.reset()
            enrollSearch.value = ''
        },
    })
}

// ── Ajouter un apprenant ─────────────────────────────────────────────────
const showAddModal  = ref(false)
const photoPreview  = ref<string | null>(null)

const addForm = useForm({
    last_name:                   '',
    first_name:                  '',
    email:                       '',
    phone:                       '',
    gender:                      '',
    birth_date:                  '',
    education_level_id:          '',
    emergency_contact_name:      '',
    emergency_contact_firstname: '',
    emergency_contact_phone:     '',
    photo:                       null as File | null,
})

const onPhotoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    addForm.photo = file
    photoPreview.value = URL.createObjectURL(file)
}

const submitAdd = () => {
    addForm.post(`/campus/cohorts/${props.cohort.id}/learners`, {
        forceFormData: true,
        onSuccess: () => {
            showAddModal.value = false
            addForm.reset()
            photoPreview.value = null
        },
    })
}

// ── Importer ─────────────────────────────────────────────────────────────
const showImportModal = ref(false)
const isDragging      = ref(false)
const importFileName  = ref<string | null>(null)

const importForm = useForm({ file: null as File | null })

const onImportFile = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (file) { importForm.file = file; importFileName.value = file.name }
}

const onImportDrop = (e: DragEvent) => {
    isDragging.value = false
    const file = e.dataTransfer?.files?.[0]
    if (file) { importForm.file = file; importFileName.value = file.name }
}

const submitImport = () => {
    importForm.post(`/campus/cohorts/${props.cohort.id}/import`, {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
            importFileName.value = null
        },
    })
}

// ── Détail / Édition apprenant ───────────────────────────────────────────
const showLearnerModal  = ref(false)
const learnerTab        = ref<'detail' | 'edit'>('detail')
const selectedLearner   = ref<Learner | null>(null)
const editPhotoPreview  = ref<string | null>(null)

const openLearner = (l: Learner) => {
    selectedLearner.value  = l
    learnerTab.value       = 'detail'
    editPhotoPreview.value = null
    showLearnerModal.value = true
    editForm.reset()
    editForm.last_name                   = l.last_name
    editForm.first_name                  = l.first_name
    editForm.email                       = l.email ?? ''
    editForm.phone                       = l.phone ?? ''
    editForm.gender                      = l.gender ?? ''
    editForm.birth_date                  = l.birth_date ? l.birth_date.slice(0, 10) : ''
    editForm.education_level_id          = l.education_level?.id ?? ''
    editForm.emergency_contact_name      = l.emergency_contact_name ?? ''
    editForm.emergency_contact_firstname = l.emergency_contact_firstname ?? ''
    editForm.emergency_contact_phone     = l.emergency_contact_phone ?? ''
}

const editForm = useForm({
    last_name:                   '',
    first_name:                  '',
    email:                       '',
    phone:                       '',
    gender:                      '',
    birth_date:                  '',
    education_level_id:          '' as number | '',
    emergency_contact_name:      '',
    emergency_contact_firstname: '',
    emergency_contact_phone:     '',
    photo:                       null as File | null,
})

const onEditPhotoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    editForm.photo        = file
    editPhotoPreview.value = URL.createObjectURL(file)
}

const submitEdit = () => {
    if (!selectedLearner.value) return
    editForm.post(`/campus/cohorts/${props.cohort.id}/learners/${selectedLearner.value.id}`, {
        forceFormData: true,
        onSuccess: () => {
            showLearnerModal.value = false
            editPhotoPreview.value = null
        },
    })
}

const genderLabel: Record<string, string> = { male: 'Homme', female: 'Femme' }

// ── Retirer apprenant ────────────────────────────────────────────────────
const showRemoveModal   = ref(false)
const removingLearner   = ref(false)
const targetRemove      = ref<Learner | null>(null)

const askRemove = (l: Learner) => { targetRemove.value = l; showRemoveModal.value = true }
const confirmRemove = () => {
    if (!targetRemove.value) return
    removingLearner.value = true
    router.delete(`/campus/cohorts/${props.cohort.id}/learners/${targetRemove.value.id}`, {
        onFinish: () => {
            removingLearner.value = false
            showRemoveModal.value = false
            targetRemove.value = null
            showLearnerModal.value = false
        },
    })
}

// ── Sélection multiple ───────────────────────────────────────────────────
const selectedIds = ref<Set<string>>(new Set())

const allSelected = computed(() =>
    props.learners.data.length > 0 && props.learners.data.every(l => selectedIds.value.has(l.id))
)
const someSelected = computed(() => selectedIds.value.size > 0)

const toggleSelect = (id: string) => {
    const s = new Set(selectedIds.value)
    s.has(id) ? s.delete(id) : s.add(id)
    selectedIds.value = s
}
const toggleAll = () => {
    if (allSelected.value) {
        selectedIds.value = new Set()
    } else {
        selectedIds.value = new Set(props.learners.data.map(l => l.id))
    }
}

// Reset selection when learners list changes (after remove or page change)
watch(() => props.learners.data, () => { selectedIds.value = new Set() })

const showBulkRemoveModal = ref(false)
const bulkRemoving        = ref(false)

const confirmBulkRemove = () => {
    bulkRemoving.value = true
    router.delete(`/campus/cohorts/${props.cohort.id}/learners`, {
        data: { learner_ids: [...selectedIds.value] },
        onFinish: () => {
            bulkRemoving.value    = false
            showBulkRemoveModal.value = false
            selectedIds.value     = new Set()
        },
    })
}

// ── Déplacer apprenant ───────────────────────────────────────────────────
const showMoveModal  = ref(false)
const moveTarget     = ref<Learner | null>(null)
const moveForm       = useForm({ target_cohort_id: '' })

const openMove = (l: Learner) => {
    moveTarget.value           = l
    moveForm.target_cohort_id  = ''
    showMoveModal.value        = true
    showLearnerModal.value     = false
}
const submitMove = () => {
    if (!moveTarget.value) return
    moveForm.post(`/campus/cohorts/${props.cohort.id}/learners/${moveTarget.value.id}/move`, {
        preserveScroll: true,
        onSuccess: () => { showMoveModal.value = false },
    })
}

const selectedTargetCohort = computed<CohortOption | null>(() =>
    (props.availableCohorts ?? []).find(c => c.id === moveForm.target_cohort_id) ?? null
)
const sourceCost = computed(() => props.cohort.campus_formation.total_cost ?? 0)
const targetCostChanged = computed(() =>
    selectedTargetCohort.value !== null &&
    selectedTargetCohort.value.total_cost !== sourceCost.value
)

// ── Clôturer ─────────────────────────────────────────────────────────────
const showCloseModal = ref(false)
const closing        = ref(false)

const confirmClose = () => {
    closing.value = true
    router.patch(`/campus/cohorts/${props.cohort.id}/close`, {}, {
        onFinish: () => { closing.value = false; showCloseModal.value = false },
    })
}
</script>

<template>
    <Head :title="cohort.name" />
    <div class="max-w-[1400px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-start justify-between gap-md">
            <div class="flex items-center gap-md">
                <Link href="/campus/cohorts" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <p class="text-body-sm text-secondary mb-xs">{{ cohort.campus_formation.name }}</p>
                    <div class="flex items-center gap-sm">
                        <h1 class="text-h1 font-bold text-on-surface">{{ cohort.name }}</h1>
                        <span :class="['status-badge', statusClass[cohort.status]]">
                            {{ statusObj(cohort.status)?.label ?? cohort.status }}
                        </span>
                    </div>
                    <p class="text-body-sm text-secondary mt-xs">
                        {{ fmt(cohort.started_at) }} → {{ fmt(cohort.ended_at) }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <Link :href="`/campus/cohorts/${cohort.id}/edit`" class="btn-navy">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Modifier
                </Link>
                <button
                    v-if="cohort.status !== 'cloturee'"
                    @click="showCloseModal = true"
                    class="btn-warn"
                    type="button"
                >
                    <span class="material-symbols-outlined" style="font-size:18px">lock</span>
                    Clôturer
                </button>
                <Link :href="`/campus/cohorts/${cohort.id}/payments`" class="btn-brand">
                    <span class="material-symbols-outlined" style="font-size:18px">payments</span>
                    Paiements
                </Link>
            </div>
        </div>

        <!-- Stats financières -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="sic sic-navy"><span class="material-symbols-outlined">groups</span></span>
                <div>
                    <p class="stat-label">Apprenants</p>
                    <p class="stat-val">{{ learners.total }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic sic-green"><span class="material-symbols-outlined">payments</span></span>
                <div>
                    <p class="stat-label">Encaissé</p>
                    <p class="stat-val green">{{ fmtCost(paymentStats.total_collected) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic sic-blue"><span class="material-symbols-outlined">account_balance</span></span>
                <div>
                    <p class="stat-label">Attendu</p>
                    <p class="stat-val">{{ fmtCost(paymentStats.total_expected) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic sic-amber"><span class="material-symbols-outlined">hourglass_empty</span></span>
                <div>
                    <p class="stat-label">Reste à payer</p>
                    <p class="stat-val amber">{{ fmtCost(paymentStats.total_remaining) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic sic-emerald"><span class="material-symbols-outlined">verified</span></span>
                <div>
                    <p class="stat-label">Soldés</p>
                    <p class="stat-val">{{ paymentStats.paid_count }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic sic-red"><span class="material-symbols-outlined">warning</span></span>
                <div>
                    <p class="stat-label">En retard</p>
                    <p class="stat-val red">{{ paymentStats.overdue_count }}</p>
                </div>
            </div>
        </div>

        <!-- Apprenants -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                <h2 class="text-h2 font-semibold text-on-surface">
                    Apprenants
                    <span class="count-badge ml-sm">{{ learners.total }}</span>
                </h2>
                <div v-if="cohort.status !== 'cloturee'" class="action-group">
                    <button @click="showEnrollModal = true" class="action-btn" type="button" title="Inscrire un apprenant existant">
                        <span class="material-symbols-outlined" style="font-size:16px">person_add</span>
                        Inscrire
                    </button>
                    <div class="action-sep"></div>
                    <button @click="showAddModal = true" class="action-btn" type="button" title="Créer et inscrire un nouvel apprenant">
                        <span class="material-symbols-outlined" style="font-size:16px">add</span>
                        Ajouter
                    </button>
                    <div class="action-sep"></div>
                    <button @click="showImportModal = true" class="action-btn" type="button" title="Importer depuis Excel">
                        <span class="material-symbols-outlined" style="font-size:16px">upload_file</span>
                        Importer
                    </button>
                </div>
            </div>

            <!-- Barre de sélection -->
            <Transition name="sel-bar">
                <div v-if="someSelected" class="sel-bar">
                    <span class="sel-count">
                        <span class="material-symbols-outlined" style="font-size:16px">check_box</span>
                        {{ selectedIds.size }} sélectionné(s)
                    </span>
                    <div class="flex items-center gap-sm">
                        <button @click="selectedIds = new Set()" class="btn-cancel" type="button" style="padding:6px 12px;font-size:12px">
                            Annuler
                        </button>
                        <button @click="showBulkRemoveModal = true" class="btn-bulk-remove" type="button">
                            <span class="material-symbols-outlined" style="font-size:15px">group_remove</span>
                            Retirer la sélection
                        </button>
                    </div>
                </div>
            </Transition>

            <div class="divide-y divide-surface-container-highest">
                <div v-if="learners.data.length === 0" class="px-lg py-xl text-center text-secondary text-body-md">
                    Aucun apprenant inscrit dans cette cohorte.
                </div>

                <!-- En-tête "tout sélectionner" -->
                <div v-if="learners.data.length > 0" class="px-lg py-xs flex items-center gap-md bg-surface border-b border-surface-container-highest">
                    <label class="sel-checkbox-wrap" title="Tout sélectionner">
                        <input
                            type="checkbox"
                            class="sr-only"
                            :checked="allSelected"
                            :indeterminate="someSelected && !allSelected"
                            @change="toggleAll"
                        />
                        <span class="sel-box" :class="{ 'sel-box-checked': allSelected, 'sel-box-indeterminate': someSelected && !allSelected }">
                            <span v-if="allSelected" class="material-symbols-outlined" style="font-size:13px">check</span>
                            <span v-else-if="someSelected && !allSelected" class="sel-dash"></span>
                        </span>
                    </label>
                    <span class="text-body-sm text-on-surface-variant">Tout sélectionner</span>
                </div>

                <div
                    v-for="l in learners.data"
                    :key="l.id"
                    class="px-lg py-sm flex items-center justify-between hover:bg-surface-bright transition-colors group"
                    :class="{ 'bg-rose-50': selectedIds.has(l.id) }"
                >
                    <div class="flex items-center gap-md">
                        <label class="sel-checkbox-wrap">
                            <input type="checkbox" class="sr-only" :checked="selectedIds.has(l.id)" @change="toggleSelect(l.id)" />
                            <span class="sel-box" :class="{ 'sel-box-checked': selectedIds.has(l.id) }">
                                <span v-if="selectedIds.has(l.id)" class="material-symbols-outlined" style="font-size:13px">check</span>
                            </span>
                        </label>
                        <div class="avatar">
                            {{ l.first_name.charAt(0) }}{{ l.last_name.charAt(0) }}
                        </div>
                        <div>
                            <button
                                @click="openLearner(l)"
                                class="font-semibold text-on-surface hover:text-primary transition-colors text-left"
                                type="button"
                            >
                                {{ l.last_name }} {{ l.first_name }}
                            </button>
                            <p class="text-body-sm text-on-surface-variant">{{ l.email ?? '' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-sm">
                        <span v-if="l.pivot" :class="['status-badge', learnerStatusClass[l.pivot.status]]">
                            {{ learnerStatusLabel[l.pivot.status] ?? l.pivot.status }}
                        </span>
                        <span v-if="l.pivot" class="text-body-sm text-on-surface-variant">
                            Inscrit le {{ fmt(l.pivot.enrolled_at) }}
                        </span>
                        <template v-if="cohort.status !== 'cloturee' && l.pivot?.status === 'actif'">
                            <button
                                @click="openMove(l)"
                                class="move-btn opacity-0 group-hover:opacity-100"
                                title="Déplacer vers une autre cohorte"
                                type="button"
                            >
                                <span class="material-symbols-outlined" style="font-size:16px">swap_horiz</span>
                            </button>
                            <button
                                @click="askRemove(l)"
                                class="remove-btn opacity-0 group-hover:opacity-100"
                                title="Retirer de la cohorte"
                                type="button"
                            >
                                <span class="material-symbols-outlined" style="font-size:16px">person_remove</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="learners.last_page > 1" class="px-lg py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ learners.from }}–{{ learners.to }} sur {{ learners.total }} apprenants
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in learners.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="page-btn"
                            :class="{ 'page-active': link.active }"
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal détail apprenant -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showLearnerModal && selectedLearner" class="backdrop" @click.self="showLearnerModal = false">
                <div class="enroll-modal" style="max-width:480px">
                    <!-- En-tête identité -->
                    <div class="learner-modal-header">
                        <div class="flex items-center gap-md">
                            <div class="learner-avatar-lg">
                                <img
                                    v-if="selectedLearner.photo_path"
                                    :src="`/storage/${selectedLearner.photo_path}`"
                                    class="learner-avatar-img"
                                    alt="photo"
                                />
                                <span v-else class="learner-avatar-initials">
                                    {{ selectedLearner.first_name.charAt(0) }}{{ selectedLearner.last_name.charAt(0) }}
                                </span>
                            </div>
                            <div>
                                <h3 class="enroll-title">{{ selectedLearner.last_name }} {{ selectedLearner.first_name }}</h3>
                                <p class="enroll-sub" v-if="selectedLearner.pivot">
                                    Inscrit le {{ fmt(selectedLearner.pivot.enrolled_at) }} ·
                                    <span :class="['status-badge', learnerStatusClass[selectedLearner.pivot.status]]">
                                        {{ learnerStatusLabel[selectedLearner.pivot.status] ?? selectedLearner.pivot.status }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <button @click="showLearnerModal = false" class="close-btn" type="button">
                            <span class="material-symbols-outlined" style="font-size:20px">close</span>
                        </button>
                    </div>

                    <!-- Onglets -->
                    <div class="learner-tabs">
                        <button
                            @click="learnerTab = 'detail'"
                            :class="['learner-tab', learnerTab === 'detail' ? 'learner-tab-active' : '']"
                            type="button"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">info</span>
                            Détails
                        </button>
                        <button
                            v-if="cohort.status !== 'cloturee'"
                            @click="learnerTab = 'edit'"
                            :class="['learner-tab', learnerTab === 'edit' ? 'learner-tab-active' : '']"
                            type="button"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                            Modifier
                        </button>
                    </div>

                    <!-- Onglet Détail -->
                    <div v-if="learnerTab === 'detail'" class="learner-detail-body">
                        <div class="detail-row">
                            <span class="detail-icon material-symbols-outlined">mail</span>
                            <div><p class="detail-label">Email</p><p class="detail-val">{{ selectedLearner.email ?? '' }}</p></div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-icon material-symbols-outlined">phone</span>
                            <div><p class="detail-label">Téléphone</p><p class="detail-val">{{ selectedLearner.phone ?? '' }}</p></div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-icon material-symbols-outlined">person</span>
                            <div><p class="detail-label">Genre</p><p class="detail-val">{{ selectedLearner.gender ? genderLabel[selectedLearner.gender] : '' }}</p></div>
                        </div>
                        <div class="detail-row" v-if="selectedLearner.birth_date">
                            <span class="detail-icon material-symbols-outlined">cake</span>
                            <div><p class="detail-label">Date de naissance</p><p class="detail-val">{{ fmt(selectedLearner.birth_date) }}</p></div>
                        </div>
                        <div class="detail-row" v-if="selectedLearner.education_level">
                            <span class="detail-icon material-symbols-outlined">school</span>
                            <div><p class="detail-label">Niveau d'étude</p><p class="detail-val">{{ selectedLearner.education_level?.name }}</p></div>
                        </div>
                        <div class="detail-row" v-if="selectedLearner.emergency_contact_name || selectedLearner.emergency_contact_phone">
                            <span class="detail-icon material-symbols-outlined">emergency</span>
                            <div>
                                <p class="detail-label">Contact d'urgence</p>
                                <p class="detail-val">{{ selectedLearner.emergency_contact_firstname }} {{ selectedLearner.emergency_contact_name }}</p>
                                <p class="detail-val" style="font-size:12px;color:#9aaabb">{{ selectedLearner.emergency_contact_phone }}</p>
                            </div>
                        </div>
                        <div class="enroll-actions" style="padding:16px 0 0">
                            <template v-if="cohort.status !== 'cloturee' && selectedLearner.pivot?.status === 'actif'">
                                <button
                                    @click="openMove(selectedLearner)"
                                    class="btn-move-outline"
                                    type="button"
                                >
                                    <span class="material-symbols-outlined" style="font-size:16px">swap_horiz</span>
                                    Déplacer
                                </button>
                                <button
                                    @click="askRemove(selectedLearner)"
                                    class="btn-danger-outline"
                                    type="button"
                                >
                                    <span class="material-symbols-outlined" style="font-size:16px">person_remove</span>
                                    Retirer
                                </button>
                            </template>
                            <button @click="showLearnerModal = false" class="btn-cancel" type="button">Fermer</button>
                        </div>
                    </div>

                    <!-- Onglet Modifier -->
                    <form v-if="learnerTab === 'edit'" @submit.prevent="submitEdit" class="add-form-body" enctype="multipart/form-data">
                        <!-- Photo -->
                        <div class="photo-row">
                            <label class="photo-pick" for="edit-photo">
                                <img
                                    v-if="editPhotoPreview || selectedLearner.photo_path"
                                    :src="editPhotoPreview ?? `/storage/${selectedLearner.photo_path}`"
                                    class="photo-preview"
                                    alt="photo"
                                />
                                <span v-else class="material-symbols-outlined" style="font-size:28px;color:#9aaabb">add_a_photo</span>
                            </label>
                            <input id="edit-photo" type="file" accept="image/*" class="sr-only" @change="onEditPhotoChange" />
                            <span class="text-body-sm text-on-surface-variant">Changer la photo</span>
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Nom <span class="req">*</span></label>
                                <input v-model="editForm.last_name" type="text" class="form-input" required />
                                <p v-if="editForm.errors.last_name" class="error-msg">{{ editForm.errors.last_name }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prénom <span class="req">*</span></label>
                                <input v-model="editForm.first_name" type="text" class="form-input" required />
                                <p v-if="editForm.errors.first_name" class="error-msg">{{ editForm.errors.first_name }}</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Genre</label>
                            <div class="gender-row">
                                <label class="gender-opt" :class="{ 'gender-sel': editForm.gender === 'male' }">
                                    <input v-model="editForm.gender" type="radio" value="male" class="sr-only" />
                                    <span class="material-symbols-outlined" style="font-size:16px">male</span> Homme
                                </label>
                                <label class="gender-opt" :class="{ 'gender-sel': editForm.gender === 'female' }">
                                    <input v-model="editForm.gender" type="radio" value="female" class="sr-only" />
                                    <span class="material-symbols-outlined" style="font-size:16px">female</span> Femme
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date de naissance</label>
                            <input v-model="editForm.birth_date" type="date" class="form-input" />
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input v-model="editForm.email" type="email" class="form-input" />
                                <p v-if="editForm.errors.email" class="error-msg">{{ editForm.errors.email }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Téléphone</label>
                                <input v-model="editForm.phone" type="tel" class="form-input" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Niveau d'étude</label>
                            <select v-model="editForm.education_level_id" class="form-input">
                                <option value="">— Sélectionner —</option>
                                <option v-for="el in educationLevels" :key="el.id" :value="el.id">{{ el.name }}</option>
                            </select>
                        </div>

                        <div class="section-sep">Contact d'urgence</div>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input v-model="editForm.emergency_contact_name" type="text" class="form-input" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prénom</label>
                                <input v-model="editForm.emergency_contact_firstname" type="text" class="form-input" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone d'urgence</label>
                            <input v-model="editForm.emergency_contact_phone" type="tel" class="form-input" />
                        </div>

                        <div class="enroll-actions">
                            <button type="button" @click="learnerTab = 'detail'" class="btn-cancel">Annuler</button>
                            <button type="submit" class="btn-confirm-enroll" :disabled="editForm.processing">
                                <span v-if="editForm.processing" class="spinner" />
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal inscription apprenants -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showEnrollModal" class="backdrop" @click.self="showEnrollModal = false">
                <div class="enroll-modal">
                    <div class="enroll-header">
                        <div>
                            <h3 class="enroll-title">Inscrire des apprenants</h3>
                            <p class="enroll-sub">{{ cohort.name }}</p>
                        </div>
                        <button @click="showEnrollModal = false" class="close-btn" type="button">
                            <span class="material-symbols-outlined" style="font-size:20px">close</span>
                        </button>
                    </div>

                    <div class="enroll-search">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#9aaabb">search</span>
                        <input
                            v-model="enrollSearch"
                            type="text"
                            placeholder="Rechercher un apprenant..."
                            class="enroll-input"
                        />
                    </div>

                    <form @submit.prevent="submitEnroll" class="enroll-form">
                        <div class="learner-list">
                            <div v-if="filteredAvailable.length === 0" class="learner-empty">
                                Aucun apprenant disponible
                            </div>
                            <label
                                v-for="l in filteredAvailable"
                                :key="l.id"
                                :class="['learner-item', enrollForm.learner_ids.includes(l.id) ? 'learner-selected' : '']"
                            >
                                <input
                                    type="checkbox"
                                    :value="l.id"
                                    :checked="enrollForm.learner_ids.includes(l.id)"
                                    @change="toggleLearner(l.id)"
                                    class="sr-only"
                                />
                                <span class="learner-check">
                                    <span v-if="enrollForm.learner_ids.includes(l.id)" class="material-symbols-outlined" style="font-size:14px">check</span>
                                </span>
                                <div class="avatar-sm">{{ l.first_name.charAt(0) }}{{ l.last_name.charAt(0) }}</div>
                                <div>
                                    <p class="learner-name">{{ l.last_name }} {{ l.first_name }}</p>
                                    <p class="learner-email">{{ l.email }}</p>
                                </div>
                            </label>
                        </div>

                        <div class="enroll-actions">
                            <span class="text-body-sm text-on-surface-variant">
                                {{ enrollForm.learner_ids.length }} sélectionné(s)
                            </span>
                            <div class="flex gap-sm">
                                <button type="button" @click="showEnrollModal = false" class="btn-cancel">Annuler</button>
                                <button
                                    type="submit"
                                    class="btn-confirm-enroll"
                                    :disabled="enrollForm.learner_ids.length === 0 || enrollForm.processing"
                                >
                                    <span v-if="enrollForm.processing" class="spinner" />
                                    Inscrire
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal ajouter un apprenant -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showAddModal" class="backdrop" @click.self="showAddModal = false">
                <div class="add-modal">
                    <div class="enroll-header">
                        <div>
                            <h3 class="enroll-title">Ajouter un apprenant</h3>
                            <p class="enroll-sub">Créer et inscrire dans « {{ cohort.name }} »</p>
                        </div>
                        <button @click="showAddModal = false" class="close-btn" type="button">
                            <span class="material-symbols-outlined" style="font-size:20px">close</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitAdd" class="add-form-body" enctype="multipart/form-data">
                        <!-- Photo -->
                        <div class="photo-row">
                            <label class="photo-pick" for="add-photo">
                                <img v-if="photoPreview" :src="photoPreview" class="photo-preview" alt="photo" />
                                <span v-else class="material-symbols-outlined" style="font-size:28px;color:#9aaabb">add_a_photo</span>
                            </label>
                            <input id="add-photo" type="file" accept="image/*" class="sr-only" @change="onPhotoChange" />
                            <span class="text-body-sm text-on-surface-variant">Photo (optionnelle)</span>
                        </div>

                        <!-- Identité -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Nom <span class="req">*</span></label>
                                <input v-model="addForm.last_name" type="text" class="form-input" :class="{ 'input-error': addForm.errors.last_name }" required />
                                <p v-if="addForm.errors.last_name" class="error-msg">{{ addForm.errors.last_name }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prénom <span class="req">*</span></label>
                                <input v-model="addForm.first_name" type="text" class="form-input" :class="{ 'input-error': addForm.errors.first_name }" required />
                                <p v-if="addForm.errors.first_name" class="error-msg">{{ addForm.errors.first_name }}</p>
                            </div>
                        </div>

                        <!-- Genre -->
                        <div class="form-group">
                            <label class="form-label">Genre</label>
                            <div class="gender-row">
                                <label class="gender-opt" :class="{ 'gender-sel': addForm.gender === 'male' }">
                                    <input v-model="addForm.gender" type="radio" value="male" class="sr-only" />
                                    <span class="material-symbols-outlined" style="font-size:16px">male</span> Homme
                                </label>
                                <label class="gender-opt" :class="{ 'gender-sel': addForm.gender === 'female' }">
                                    <input v-model="addForm.gender" type="radio" value="female" class="sr-only" />
                                    <span class="material-symbols-outlined" style="font-size:16px">female</span> Femme
                                </label>
                            </div>
                        </div>

                        <!-- Date de naissance -->
                        <div class="form-group">
                            <label class="form-label">Date de naissance</label>
                            <input v-model="addForm.birth_date" type="date" class="form-input" />
                        </div>

                        <!-- Contact -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input v-model="addForm.email" type="email" class="form-input" :class="{ 'input-error': addForm.errors.email }" />
                                <p v-if="addForm.errors.email" class="error-msg">{{ addForm.errors.email }}</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Téléphone</label>
                                <input v-model="addForm.phone" type="tel" class="form-input" />
                            </div>
                        </div>

                        <!-- Niveau d'étude -->
                        <div class="form-group">
                            <label class="form-label">Niveau d'étude</label>
                            <select v-model="addForm.education_level_id" class="form-input">
                                <option value="">— Sélectionner —</option>
                                <option v-for="el in educationLevels" :key="el.id" :value="el.id">{{ el.name }}</option>
                            </select>
                        </div>

                        <!-- Contact urgence -->
                        <div class="section-sep">Contact d'urgence</div>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input v-model="addForm.emergency_contact_name" type="text" class="form-input" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prénom</label>
                                <input v-model="addForm.emergency_contact_firstname" type="text" class="form-input" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone d'urgence</label>
                            <input v-model="addForm.emergency_contact_phone" type="tel" class="form-input" />
                        </div>

                        <div class="enroll-actions">
                            <button type="button" @click="showAddModal = false" class="btn-cancel">Annuler</button>
                            <button type="submit" class="btn-confirm-enroll" :disabled="addForm.processing">
                                <span v-if="addForm.processing" class="spinner" />
                                Ajouter et inscrire
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal importer -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showImportModal" class="backdrop" @click.self="showImportModal = false">
                <div class="enroll-modal">
                    <div class="enroll-header">
                        <div>
                            <h3 class="enroll-title">Importer des apprenants</h3>
                            <p class="enroll-sub">Fichier Excel ou CSV · max 5 Mo</p>
                        </div>
                        <button @click="showImportModal = false" class="close-btn" type="button">
                            <span class="material-symbols-outlined" style="font-size:20px">close</span>
                        </button>
                    </div>

                    <form @submit.prevent="submitImport" class="import-body">
                        <a href="/campus/cohorts/import/template" class="template-dl" download>
                            <span class="material-symbols-outlined" style="font-size:16px">download</span>
                            Télécharger le modèle Excel
                        </a>
                        <label
                            class="drop-zone"
                            :class="{ 'drop-active': isDragging, 'drop-has-file': !!importFileName }"
                            for="import-file"
                            @dragover.prevent="isDragging = true"
                            @dragleave="isDragging = false"
                            @drop.prevent="onImportDrop"
                        >
                            <span
                                class="material-symbols-outlined"
                                style="font-size:40px"
                                :style="{ color: importFileName ? '#059669' : '#9aaabb' }"
                            >{{ importFileName ? 'check_circle' : 'upload_file' }}</span>
                            <p class="drop-label">
                                <span v-if="importFileName" class="drop-filename-ok">{{ importFileName }}</span>
                                <span v-else>Glissez votre fichier ici ou <span class="drop-link">parcourir</span></span>
                            </p>
                            <p class="drop-hint" :style="{ color: importFileName ? '#059669' : '' }">
                                {{ importFileName ? 'Fichier prêt à être importé' : 'Format : .xlsx, .xls ou .csv' }}
                            </p>
                            <input id="import-file" type="file" accept=".xlsx,.xls,.csv" class="sr-only" @change="onImportFile" />
                        </label>

                        <p v-if="importForm.errors.file" class="error-msg mt-sm">{{ importForm.errors.file }}</p>

                        <div class="enroll-actions">
                            <button type="button" @click="showImportModal = false" class="btn-cancel">Annuler</button>
                            <button type="submit" class="btn-confirm-enroll" :disabled="!importForm.file || importForm.processing">
                                <span v-if="importForm.processing" class="spinner" />
                                Importer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal retirer sélection -->
    <ConfirmModal
        :show="showBulkRemoveModal"
        title="Retirer les apprenants sélectionnés"
        :message="`Vous êtes sur le point de retirer ${selectedIds.size} apprenant(s) de cette cohorte. Cette action est irréversible.`"
        confirm-label="Retirer"
        :loading="bulkRemoving"
        @confirm="confirmBulkRemove"
        @cancel="showBulkRemoveModal = false"
    />

    <!-- Modal retirer apprenant -->
    <ConfirmModal
        :show="showRemoveModal"
        title="Retirer l'apprenant"
        :message="targetRemove ? `Retirer « ${targetRemove.last_name} ${targetRemove.first_name} » de cette cohorte ?` : ''"
        confirm-label="Retirer"
        :loading="removingLearner"
        @confirm="confirmRemove"
        @cancel="showRemoveModal = false"
    />

    <!-- Modal déplacer apprenant -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showMoveModal && moveTarget" class="backdrop" @click.self="showMoveModal = false">
                <div class="enroll-modal" style="max-width:440px">
                    <div class="enroll-header">
                        <div>
                            <h3 class="enroll-title">Déplacer l'apprenant</h3>
                            <p class="enroll-sub">
                                {{ moveTarget.last_name }} {{ moveTarget.first_name }} —
                                ses paiements suivront dans la nouvelle cohorte
                            </p>
                        </div>
                        <button @click="showMoveModal = false" class="close-btn" type="button">
                            <span class="material-symbols-outlined" style="font-size:20px">close</span>
                        </button>
                    </div>
                    <form @submit.prevent="submitMove" class="add-form-body">

                        <!-- Frais actuels -->
                        <div class="move-cost-row">
                            <span class="move-cost-label">Formation actuelle</span>
                            <span class="move-cost-val">
                                {{ cohort.campus_formation.name }}
                                <strong v-if="sourceCost"> — {{ fmtCost(sourceCost) }}</strong>
                            </span>
                        </div>

                        <!-- Sélecteur cohorte cible -->
                        <div class="form-group">
                            <label class="form-label">Cohorte de destination <span class="req">*</span></label>
                            <select v-model="moveForm.target_cohort_id" class="form-input" required>
                                <option value="">— Sélectionner une cohorte —</option>
                                <optgroup
                                    v-for="formationName in [...new Set((availableCohorts ?? []).map(c => c.formation_name))]"
                                    :key="formationName"
                                    :label="formationName"
                                >
                                    <option
                                        v-for="c in (availableCohorts ?? []).filter(c => c.formation_name === formationName)"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }} — {{ fmtCost(c.total_cost) }}
                                    </option>
                                </optgroup>
                            </select>
                            <p v-if="moveForm.errors.target_cohort_id" class="error-msg">
                                {{ moveForm.errors.target_cohort_id }}
                            </p>
                            <p v-if="(availableCohorts ?? []).length === 0" class="error-msg">
                                Aucune autre cohorte ouverte disponible.
                            </p>
                        </div>

                        <!-- Aperçu frais cible + avertissements -->
                        <template v-if="selectedTargetCohort">
                            <!-- Changement de montant -->
                            <div v-if="targetCostChanged" class="move-alert move-alert-warn">
                                <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0">info</span>
                                <div>
                                    <strong>Les frais changent :</strong>
                                    {{ fmtCost(sourceCost) }} → {{ fmtCost(selectedTargetCohort.total_cost) }}.
                                    Le nouveau restant sera recalculé automatiquement.
                                </div>
                            </div>
                            <div v-else class="move-alert move-alert-ok">
                                <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0">check_circle</span>
                                Même montant de frais — aucun recalcul nécessaire.
                            </div>

                            <!-- Tranches en attente toujours annulées -->
                            <div class="move-alert move-alert-info">
                                <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0">swap_horiz</span>
                                <div>
                                    Les <strong>versements encaissés</strong> suivront l'apprenant.
                                    Les <strong>tranches en attente</strong> seront annulées
                                    (à replanifier dans la nouvelle cohorte selon les nouveaux frais).
                                </div>
                            </div>
                        </template>

                        <div class="enroll-actions">
                            <button type="button" @click="showMoveModal = false" class="btn-cancel">Annuler</button>
                            <button
                                type="submit"
                                class="btn-confirm-enroll"
                                :disabled="!moveForm.target_cohort_id || moveForm.processing"
                            >
                                <span v-if="moveForm.processing" class="spinner" />
                                <span v-else class="material-symbols-outlined" style="font-size:15px">swap_horiz</span>
                                Déplacer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal clôturer -->
    <ConfirmModal
        :show="showCloseModal"
        title="Clôturer la cohorte"
        :message="`Clôturer « ${cohort.name} » ? Tous les apprenants encore actifs seront automatiquement diplômés. Cette action est irréversible.`"
        confirm-label="Clôturer"
        :loading="closing"
        @confirm="confirmClose"
        @cancel="showCloseModal = false"
    />
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    transition: background 0.15s, color 0.15s; flex-shrink: 0; text-decoration: none;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

.status-badge {
    display: inline-flex; align-items: center;
    padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
}
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-green  { background: #d1fae5; color: #065f46; }
.badge-gray   { background: #f3f4f6; color: #6b7280; }
.badge-purple { background: #ede9fe; color: #6d28d9; }

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    padding: 14px 16px; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sic {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.sic .material-symbols-outlined { font-size: 20px; }
.sic-navy    { background: #e8edf2; color: #1F3A4D; }
.sic-green   { background: #d1fae5; color: #059669; }
.sic-blue    { background: #dbeafe; color: #2563eb; }
.sic-amber   { background: #fef3c7; color: #d97706; }
.sic-emerald { background: #d1fae5; color: #059669; }
.sic-red     { background: #ffe4e6; color: #e11d48; }
.stat-label { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-val   { font-size: 16px; font-weight: 700; color: #191c1e; margin-top: 3px; }
.stat-val.green { color: #059669; }
.stat-val.amber { color: #d97706; }
.stat-val.red   { color: #e11d48; }

.count-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 24px; height: 24px; padding: 0 6px;
    background: #f2f4f6; border-radius: 99px;
    font-size: 12px; font-weight: 600; color: #515f74;
}

.avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: #1F3A4D; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; flex-shrink: 0; text-transform: uppercase;
}

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    transition: background 0.2s; border: none; cursor: pointer;
}
.btn-primary:hover { background: #c0003e; }
.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; background: transparent; color: #515f74;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    border: 1px solid #e0e3e5; transition: background 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

/* Modifier — navy Simplon */
.btn-navy {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; background: transparent; color: #1F3A4D;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none;
}
.btn-navy:hover { background: #1F3A4D; color: #fff; }

/* Paiements — rose Simplon */
.btn-brand {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: none; transition: background 0.2s; text-decoration: none; cursor: pointer;
}
.btn-brand:hover { background: #c0003e; }

/* Clôturer — ambre (action irréversible) */
.btn-warn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; background: transparent; color: #d97706;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #fbbf24; transition: background 0.15s, color 0.15s; cursor: pointer;
}
.btn-warn:hover { background: #d97706; color: #fff; border-color: #d97706; }

/* Modal inscription */
.backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 20px;
}
.enroll-modal {
    background: #fff; border-radius: 14px; width: 100%; max-width: 500px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;
    display: flex; flex-direction: column; max-height: 85vh;
}
.enroll-header {
    padding: 20px 24px; border-bottom: 1px solid #e0e3e5;
    display: flex; align-items: flex-start; justify-content: space-between;
}
.enroll-title { font-size: 17px; font-weight: 700; color: #191c1e; margin: 0; }
.enroll-sub   { font-size: 13px; color: #9aaabb; margin: 4px 0 0; }
.close-btn {
    width: 32px; height: 32px; border-radius: 8px; border: none;
    background: transparent; color: #9aaabb; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0;
}
.close-btn:hover { background: #f2f4f6; color: #191c1e; }

.enroll-search {
    padding: 12px 16px; border-bottom: 1px solid #e0e3e5;
    display: flex; align-items: center; gap: 10px;
}
.enroll-input {
    flex: 1; border: none; outline: none; font-size: 14px; color: #191c1e;
    background: transparent;
}
.enroll-input::placeholder { color: #9aaabb; }

.enroll-form {
    display: flex; flex-direction: column; flex: 1; overflow: hidden;
}
.learner-list {
    overflow-y: auto; flex: 1;
}
.learner-empty {
    padding: 32px; text-align: center; color: #9aaabb; font-size: 14px;
}
.learner-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; cursor: pointer; transition: background 0.15s;
    border-bottom: 1px solid #f3f4f6;
}
.learner-item:hover { background: #fafafa; }
.learner-item.learner-selected { background: #fff5f8; }
.learner-check {
    width: 18px; height: 18px; border: 2px solid #e0e3e5; border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.15s; color: #fff;
}
.learner-selected .learner-check { background: #E5004C; border-color: #E5004C; }
.avatar-sm {
    width: 32px; height: 32px; border-radius: 50%;
    background: #1F3A4D; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; flex-shrink: 0; text-transform: uppercase;
}
.learner-name  { font-size: 13px; font-weight: 600; color: #191c1e; }
.learner-email { font-size: 11px; color: #9aaabb; }

.enroll-actions {
    padding: 16px; border-top: 1px solid #e0e3e5;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.btn-cancel {
    padding: 9px 18px; border: 1px solid #e0e3e5; border-radius: 8px;
    background: transparent; color: #515f74; font-size: 14px; cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: #f2f4f6; }
.btn-confirm-enroll {
    padding: 9px 20px; border: none; border-radius: 8px;
    background: #E5004C; color: #fff; font-size: 14px; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-confirm-enroll:hover:not(:disabled) { background: #c0003e; }
.btn-confirm-enroll:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Sélection multiple */
.sel-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 24px; background: #fff5f8; border-bottom: 1px solid #fecdd3;
}
.sel-count {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; color: #9f1239;
}
.btn-bulk-remove {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border: 1px solid #fca5a5; border-radius: 8px;
    background: #fff; color: #ba1a1a; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
}
.btn-bulk-remove:hover { background: #fef2f2; }

.sel-checkbox-wrap { display: inline-flex; align-items: center; cursor: pointer; flex-shrink: 0; }
.sel-box {
    width: 18px; height: 18px; border: 2px solid #d1d5db; border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s; color: #fff; flex-shrink: 0;
}
.sel-box:hover { border-color: #E5004C; }
.sel-box-checked { background: #E5004C; border-color: #E5004C; }
.sel-box-indeterminate { background: #E5004C; border-color: #E5004C; }
.sel-dash { width: 8px; height: 2px; background: #fff; border-radius: 1px; }

.sel-bar-enter-active, .sel-bar-leave-active { transition: all 0.2s ease; }
.sel-bar-enter-from, .sel-bar-leave-to { opacity: 0; transform: translateY(-6px); }

.remove-btn {
    padding: 4px; color: #9aaabb; border-radius: 4px; border: none;
    background: transparent; cursor: pointer; display: inline-flex;
    transition: color 0.15s;
}
.remove-btn:hover { color: #ba1a1a; }

.move-btn {
    padding: 4px; color: #9aaabb; border-radius: 4px; border: none;
    background: transparent; cursor: pointer; display: inline-flex;
    transition: color 0.15s;
}
.move-btn:hover { color: #6d28d9; }

.btn-move-outline {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border: 1px solid #ddd6fe; border-radius: 8px;
    background: transparent; color: #6d28d9; font-size: 13px; font-weight: 500;
    cursor: pointer; transition: background 0.15s;
}
.btn-move-outline:hover { background: #f5f3ff; }

.move-cost-row {
    display: flex; flex-direction: column; gap: 2px;
    padding: 10px 14px; background: #f8f9fa; border-radius: 8px;
}
.move-cost-label { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.move-cost-val   { font-size: 13px; color: #191c1e; }

.move-alert {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px; border-radius: 8px; font-size: 12px; line-height: 1.5;
}
.move-alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.move-alert-ok   { background: #f0fdf4; color: #065f46; border: 1px solid #a7f3d0; }
.move-alert-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

.learner-modal-header {
    padding: 20px 24px; border-bottom: 1px solid #e0e3e5;
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
}
.learner-avatar-lg {
    width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
    background: #1F3A4D; overflow: hidden;
    display: flex; align-items: center; justify-content: center;
}
.learner-avatar-img  { width: 100%; height: 100%; object-fit: cover; }
.learner-avatar-initials { color: #fff; font-size: 18px; font-weight: 700; text-transform: uppercase; }

.learner-tabs {
    display: flex; border-bottom: 1px solid #e0e3e5;
    padding: 0 16px;
}
.learner-tab {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 10px 14px; font-size: 13px; font-weight: 500; color: #9aaabb;
    border: none; background: transparent; cursor: pointer;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: color 0.15s;
}
.learner-tab:hover { color: #515f74; }
.learner-tab-active { color: #E5004C; border-bottom-color: #E5004C; font-weight: 600; }

.learner-detail-body {
    padding: 16px 24px; display: flex; flex-direction: column; gap: 12px;
    overflow-y: auto;
}
.detail-row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 10px 0; border-bottom: 1px solid #f3f4f6;
}
.detail-row:last-child { border-bottom: none; }
.detail-icon { font-size: 20px; color: #E5004C; flex-shrink: 0; margin-top: 2px; }
.detail-label { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.detail-val   { font-size: 14px; font-weight: 500; color: #191c1e; margin-top: 2px; }

.btn-danger-outline {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border: 1px solid #fecaca; border-radius: 8px;
    background: transparent; color: #ba1a1a; font-size: 13px; font-weight: 500;
    cursor: pointer; transition: background 0.15s;
}
.btn-danger-outline:hover { background: #fff5f5; }

/* Action group */
.action-group {
    display: inline-flex; align-items: stretch;
    border: 1px solid #e0e3e5; border-radius: 8px; overflow: hidden;
}
.action-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 13px; background: #fff; color: #515f74;
    font-size: 12px; font-weight: 600; border: none; cursor: pointer;
    transition: background 0.15s; white-space: nowrap;
}
.action-btn:hover { background: #f2f4f6; color: #191c1e; }
.action-sep { width: 1px; background: #e0e3e5; flex-shrink: 0; }

/* Add learner modal */
.add-modal {
    background: #fff; border-radius: 14px; width: 100%; max-width: 640px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;
    display: flex; flex-direction: column; max-height: 90vh;
}
.add-form-body {
    overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 14px;
}
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group  { display: flex; flex-direction: column; gap: 5px; }
.form-label  { font-size: 12px; font-weight: 600; color: #515f74; }
.req { color: #E5004C; }
.form-input {
    padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fafafa; outline: none;
    transition: border-color 0.15s;
}
.form-input:focus { border-color: #E5004C; background: #fff; }
.input-error { border-color: #dc2626; }
.error-msg { font-size: 11px; color: #dc2626; margin: 0; }

.gender-row { display: flex; gap: 10px; }
.gender-opt {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 8px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 500; color: #515f74; cursor: pointer;
    transition: all 0.15s;
}
.gender-opt:hover { background: #f2f4f6; }
.gender-sel { border-color: #E5004C; background: #fff5f8; color: #E5004C; }

.age-display {
    padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #515f74; background: #f2f4f6;
}

.section-sep {
    font-size: 11px; font-weight: 700; color: #9aaabb; text-transform: uppercase;
    letter-spacing: 0.06em; padding: 4px 0; border-top: 1px solid #f3f4f6; margin-top: 4px;
}

/* Photo pick */
.photo-row { display: flex; align-items: center; gap: 14px; }
.photo-pick {
    width: 64px; height: 64px; border-radius: 50%; border: 2px dashed #e0e3e5;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden; flex-shrink: 0; transition: border-color 0.15s;
}
.photo-pick:hover { border-color: #E5004C; }
.photo-preview { width: 100%; height: 100%; object-fit: cover; }

/* Import modal */
.import-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.drop-zone {
    border: 2px dashed #e0e3e5; border-radius: 12px;
    padding: 36px 20px; display: flex; flex-direction: column; align-items: center;
    gap: 8px; cursor: pointer; transition: all 0.15s; background: #fafafa;
}
.drop-zone:hover, .drop-active { border-color: #E5004C; background: #fff5f8; }
.drop-has-file { border-color: #059669 !important; background: #f0fdf4 !important; border-style: solid !important; }
.drop-label { font-size: 14px; color: #515f74; text-align: center; margin: 0; }
.drop-filename { font-weight: 600; color: #191c1e; }
.drop-filename-ok { font-weight: 600; color: #059669; }
.drop-link { color: #E5004C; font-weight: 600; }
.drop-hint { font-size: 11px; color: #9aaabb; margin: 0; }
.mt-sm { margin-top: 6px; }

.template-dl {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 500; color: #515f74;
    background: #fafafa; text-decoration: none; transition: all 0.15s; align-self: flex-start;
}
.template-dl:hover { border-color: #E5004C; color: #E5004C; background: #fff5f8; }

/* Pagination */
.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px; padding: 0 6px; border-radius: 4px;
    font-size: 13px; font-weight: 500; color: #191c1e;
    transition: background 0.15s; cursor: pointer; text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }

/* Transition */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
