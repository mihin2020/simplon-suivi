<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'
import ContractTypeModal from '@/Components/ContractTypeModal.vue'
import InsertionRecordForm from '@/Components/InsertionRecordForm.vue'

interface FormData {
    status: string
    status_changed_at: string
    status_notes: string
    internship_start_date: string
    internship_end_date: string
    internship_company: string
    internship_paid: boolean
    internship_contract_type: string
    internship_work_mode: string
    internship_contract_file: File | null
    remove_internship_contract: boolean
    employment_company: string
    employment_start_date: string
    employment_contract_type: string
    employment_work_mode: string
    employment_contract_file: File | null
    remove_employment_contract: boolean
    employment_position: string
}

defineOptions({ layout: AdminLayout })

interface EducationLevel { name: string }

interface Formation {
    id: string
    name: string
    status: string
    project: { id: string; name: string }
    pivot: { status: string; enrolled_at: string; withdrawn_at: string | null }
}

interface Recorder {
    id: string
    first_name: string
    last_name: string
}

interface InsertionRecord {
    id: string
    status: string
    status_label: string
    status_color: string
    status_changed_at: string
    status_notes: string | null
    internship_start_date: string | null
    internship_end_date: string | null
    internship_company: string | null
    internship_paid: boolean | null
    internship_contract_type: string | null
    internship_work_mode: string | null
    internship_contract_path: string | null
    internship_contract_original_name: string | null
    employment_company: string | null
    employment_start_date: string | null
    employment_contract_type: string | null
    employment_work_mode: string | null
    employment_contract_path: string | null
    employment_contract_original_name: string | null
    employment_position: string | null
    recorder: Recorder
}

interface InsertionStatus {
    value: string
    label: string
    color: string
    is_stage: boolean
    is_employment: boolean
}

interface Learner {
    id: string
    first_name: string
    last_name: string
    full_name: string
    email: string | null
    phone: string | null
    gender: string | null
    birth_date: string | null
    birth_place: string | null
    talent: string | null
    photo_path: string | null
    cnib_path: string | null
    emergency_contact_name: string | null
    emergency_contact_firstname: string | null
    emergency_contact_phone: string | null
    address: string | null
    location: string | null
    profile: string | null
    organization: string | null
    study_field: string | null
    education_level: EducationLevel | null
    age_range: { id: number; name: string; age_min: number; age_max: number } | null
    cnib_number: string | null
    marital_status: string | null
    children_count: number | null
    vulnerability: { id: string; name: string } | null
    last_diploma: { id: string; name: string } | null
    cv_path: string | null
    cv_original_name: string | null
    formations: Formation[]
}

interface ContractTypeItem { id: string; name: string }
interface WorkModeOption { value: string; label: string; icon: string }

const props = defineProps<{
    learner: Learner
    insertionRecords: InsertionRecord[]
    latestInsertion: InsertionRecord | null
    insertionStatuses: InsertionStatus[]
    internshipContractTypes: ContractTypeItem[]
    employmentContractTypes: ContractTypeItem[]
    workModes: WorkModeOption[]
    canManageContractTypes: boolean
}>()

const today = new Date().toISOString().split('T')[0]

const form = useForm<FormData>({
    status: '',
    status_changed_at: today,
    status_notes: '',
    internship_start_date: '',
    internship_end_date: '',
    internship_company: '',
    internship_paid: false,
    internship_contract_type: '',
    internship_work_mode: '',
    internship_contract_file: null as File | null,
    remove_internship_contract: false,
    employment_company: '',
    employment_start_date: '',
    employment_contract_type: '',
    employment_work_mode: '',
    employment_contract_file: null as File | null,
    remove_employment_contract: false,
    employment_position: '',
})

const deleteTargetId = ref<string | null>(null)
const confirmDeleteRecord = () => {
    if (!deleteTargetId.value) return
    router.delete(`/learners/${props.learner.id}/insertion/${deleteTargetId.value}`, {
        preserveScroll: true,
        onFinish: () => { deleteTargetId.value = null },
    })
}

const editingRecord = ref<InsertionRecord | null>(null)

const resetFileInputs = () => {
    form.internship_contract_file = null
    form.employment_contract_file = null
    form.remove_internship_contract = false
    form.remove_employment_contract = false
}

const contractUrl = (path: string | null) => path ? `/storage/${path}` : null

const showExistingInternshipContract = computed(() =>
    editingRecord.value?.internship_contract_path
    && !form.remove_internship_contract
    && !form.internship_contract_file,
)

const showExistingEmploymentContract = computed(() =>
    editingRecord.value?.employment_contract_path
    && !form.remove_employment_contract
    && !form.employment_contract_file,
)

watch(() => form.internship_contract_file, (file) => {
    if (file) form.remove_internship_contract = false
})
watch(() => form.employment_contract_file, (file) => {
    if (file) form.remove_employment_contract = false
})

// Convertir date ISO en YYYY-MM-DD pour input type="date"
const toDateInput = (d: string | null) => d ? d.split('T')[0] : ''

const editRecord = (record: InsertionRecord) => {
    editingRecord.value = record
    form.status = record.status
    form.status_changed_at = record.status_changed_at
    form.status_notes = record.status_notes ?? ''
    form.internship_start_date = toDateInput(record.internship_start_date)
    form.internship_end_date = toDateInput(record.internship_end_date)
    form.internship_company = record.internship_company ?? ''
    form.internship_paid = record.internship_paid ?? false
    form.internship_contract_type = record.internship_contract_type ?? ''
    form.internship_work_mode = record.internship_work_mode ?? ''
    form.employment_company = record.employment_company ?? ''
    form.employment_start_date = toDateInput(record.employment_start_date)
    form.employment_contract_type = record.employment_contract_type ?? ''
    form.employment_work_mode = record.employment_work_mode ?? ''
    form.employment_position = record.employment_position ?? ''
    resetFileInputs()

    if (record.status === 'internship') {
        showStageForm.value = true
        activeTab.value = 'stage'
    } else if (record.status === 'employed') {
        showEmploymentForm.value = true
        activeTab.value = 'employment'
    }
}

const cancelEdit = () => {
    editingRecord.value = null
    form.reset()
    form.status_changed_at = today
    resetFileInputs()
    showStageForm.value = false
    showEmploymentForm.value = false
}

const openStageForm = () => {
    if (editingRecord.value?.status !== 'internship') {
        editingRecord.value = null
        form.reset()
        form.status_changed_at = today
        resetFileInputs()
    }
    showEmploymentForm.value = false
    showStageForm.value = true
}

const openEmploymentForm = () => {
    if (editingRecord.value?.status !== 'employed') {
        editingRecord.value = null
        form.reset()
        form.status_changed_at = today
        resetFileInputs()
    }
    showStageForm.value = false
    showEmploymentForm.value = true
}

const submitStageForm = () => {
    form.status = 'internship'
    submitForm()
}

const submitEmploymentForm = () => {
    form.status = 'employed'
    submitForm()
}

const submitForm = () => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editingRecord.value = null
            form.reset()
            form.status_changed_at = today
            resetFileInputs()
            showStageForm.value = false
            showEmploymentForm.value = false
        },
    }

    if (editingRecord.value) {
        form.post(`/learners/${props.learner.id}/insertion/${editingRecord.value.id}?_method=PUT`, options)
    } else {
        form.post(`/learners/${props.learner.id}/insertion`, options)
    }
}

const activeTab = ref<'info' | 'formations' | 'stage' | 'employment'>('info')
const showStageForm = ref(false)
const showEmploymentForm = ref(false)
const contractTypeModal = ref<'internship' | 'employment' | null>(null)

const workModeLabel = (value: string | null) =>
    props.workModes.find(m => m.value === value)?.label ?? value ?? ''

const workModeIcon = (value: string | null) =>
    props.workModes.find(m => m.value === value)?.icon ?? 'location_on'

const fmt = (d: string | null) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : ''
const genderLabel = (g: string | null) => ({ male: 'Masculin', female: 'Féminin' }[g ?? ''] ?? '')
const photoUrl = (path: string | null) => path ? `/storage/${path}` : null
const maritalStatusLabel = (s: string | null) => {
    const labels: Record<string, string> = {
        single: 'Célibataire',
        married: 'Marié(e)',
        divorced: 'Divorcé(e)',
        widowed: 'Veuf / Veuve'
    }
    return labels[s ?? ''] ?? ''
}

const enrollmentStatusLabels: Record<string, string> = {
    in_progress: 'En cours', withdrawn: 'Retiré', completed: 'Terminé', moved: 'Déplacé'
}

const currentFormation = computed(() => props.learner.formations.find(f => f.pivot.status === 'in_progress') ?? props.learner.formations[0] ?? null)

const stageRecords = computed(() => props.insertionRecords.filter(r => r.status === 'internship'))
const employmentRecords = computed(() => props.insertionRecords.filter(r => r.status === 'employed'))
const latestStage = computed(() => stageRecords.value[0] ?? null)
const latestEmployment = computed(() => employmentRecords.value[0] ?? null)
</script>

<template>
    <Head :title="`${learner.first_name} ${learner.last_name}`" />
    <div class="page-wrap">
        <!-- En-tête -->
        <div class="page-header">
            <div class="header-left">
                <Link href="/learners" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="avatar-lg">
                    <img v-if="photoUrl(learner.photo_path)" :src="photoUrl(learner.photo_path)!" alt="" class="avatar-img" />
                    <template v-else>{{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}</template>
                </div>
                <div>
                    <h1 class="page-title">{{ learner.last_name }} {{ learner.first_name }}</h1>
                    <div class="page-meta">
                        <span v-if="currentFormation" class="formation-pill">
                            <span class="material-symbols-outlined" style="font-size:14px">school</span>
                            <Link :href="`/projects/${currentFormation.project.id}`" class="link">{{ currentFormation.project.name }}</Link>
                            <span class="sep-arrow">›</span>
                            <Link :href="`/formations/${currentFormation.id}`" class="link">{{ currentFormation.name }}</Link>
                        </span>
                        <span v-else class="text-secondary">Aucune formation active</span>
                        <span class="text-secondary">·</span>
                        <span class="text-secondary">{{ genderLabel(learner.gender) }}<template v-if="learner.education_level"> · {{ learner.education_level.name }}</template></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <Can permission="learners.move">
                    <Link :href="`/learners/${learner.id}/move`" class="btn-move"><span class="material-symbols-outlined" style="font-size:18px">swap_horiz</span>Déplacer</Link>
                </Can>
                <Can permission="learners.update">
                    <Link :href="`/learners/${learner.id}/edit`" class="btn-navy"><span class="material-symbols-outlined" style="font-size:18px">edit</span>Modifier</Link>
                </Can>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-bar">
            <button @click="activeTab = 'info'" class="tab-btn" :class="{ 'tab-active': activeTab === 'info' }"><span class="material-symbols-outlined" style="font-size:18px">person</span>Profil</button>
            <button @click="activeTab = 'formations'" class="tab-btn" :class="{ 'tab-active': activeTab === 'formations' }"><span class="material-symbols-outlined" style="font-size:18px">school</span>Formations<span class="tab-badge">{{ learner.formations.length }}</span></button>
            <button @click="activeTab = 'stage'" class="tab-btn" :class="{ 'tab-active': activeTab === 'stage' }"><span class="material-symbols-outlined" style="font-size:18px">business_center</span>Stage<span class="tab-badge">{{ stageRecords.length }}</span></button>
            <button @click="activeTab = 'employment'" class="tab-btn" :class="{ 'tab-active': activeTab === 'employment' }"><span class="material-symbols-outlined" style="font-size:18px">work</span>Emploi<span class="tab-badge">{{ employmentRecords.length }}</span></button>
        </div>

        <div class="tab-content">
            <!-- Tab: Profil -->
            <div v-if="activeTab === 'info'" class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                <div class="card">
                    <h2 class="section-title">Informations personnelles</h2>
                    <dl class="info-list">
                        <div class="info-row"><dt>Date de naissance</dt><dd>{{ fmt(learner.birth_date) }}</dd></div>
                        <div class="info-row"><dt>Lieu de naissance</dt><dd>{{ learner.birth_place ?? '' }}</dd></div>
                        <div v-if="learner.education_level" class="info-row"><dt>Niveau d'études</dt><dd>{{ learner.education_level.name }}</dd></div>
                        <div v-if="learner.age_range" class="info-row"><dt>Tranche d'âge</dt><dd>{{ learner.age_range.name }}</dd></div>
                        <div v-if="learner.talent" class="info-row"><dt>Talent</dt><dd>{{ learner.talent }}</dd></div>
                        <div v-if="learner.last_diploma" class="info-row"><dt>Dernier diplôme</dt><dd>{{ learner.last_diploma.name }}</dd></div>
                        <div v-if="learner.cnib_number" class="info-row"><dt>N° CNIB</dt><dd>{{ learner.cnib_number }}</dd></div>
                        <div v-if="learner.marital_status" class="info-row"><dt>Situation matrimoniale</dt><dd>{{ maritalStatusLabel(learner.marital_status) }}</dd></div>
                        <div v-if="learner.children_count !== null && learner.children_count > 0" class="info-row"><dt>Nombre d'enfants</dt><dd>{{ learner.children_count }}</dd></div>
                        <div v-if="learner.vulnerability" class="info-row"><dt>Vulnérabilité</dt><dd>{{ learner.vulnerability.name }}</dd></div>
                    </dl>
                </div>
                <div class="card" v-if="learner.email || learner.phone">
                    <h2 class="section-title">Coordonnées</h2>
                    <dl class="info-list">
                        <div v-if="learner.email" class="info-row"><dt>Email</dt><dd>{{ learner.email }}</dd></div>
                        <div v-if="learner.phone" class="info-row"><dt>Téléphone</dt><dd>{{ learner.phone }}</dd></div>
                    </dl>
                </div>
                <div class="card" v-if="learner.address || learner.location || learner.profile || learner.organization || learner.study_field">
                    <h2 class="section-title">Informations complémentaires</h2>
                    <dl class="info-list">
                        <div v-if="learner.address" class="info-row"><dt>Adresse</dt><dd>{{ learner.address }}</dd></div>
                        <div v-if="learner.location" class="info-row"><dt>Localisation</dt><dd>{{ learner.location }}</dd></div>
                        <div v-if="learner.profile" class="info-row"><dt>Profil</dt><dd>{{ learner.profile }}</dd></div>
                        <div v-if="learner.organization" class="info-row"><dt>Organisation</dt><dd>{{ learner.organization }}</dd></div>
                        <div v-if="learner.study_field" class="info-row"><dt>Domaine d'études</dt><dd>{{ learner.study_field }}</dd></div>
                    </dl>
                </div>
                <div class="card" v-if="learner.cnib_path || learner.cv_path">
                    <h2 class="section-title">Documents</h2>
                    <div class="docs-grid">
                        <a v-if="learner.cnib_path" :href="`/storage/${learner.cnib_path}`" target="_blank" rel="noopener" class="doc-card">
                            <div class="doc-icon doc-id">
                                <span class="material-symbols-outlined">badge</span>
                            </div>
                            <div class="doc-info">
                                <span class="doc-title">Pièce d'identité</span>
                                <span class="doc-meta">CNIB · {{ learner.cnib_original_name || 'Document' }}</span>
                            </div>
                            <span class="material-symbols-outlined doc-arrow">open_in_new</span>
                        </a>
                        <a v-if="learner.cv_path" :href="`/storage/${learner.cv_path}`" target="_blank" rel="noopener" class="doc-card">
                            <div class="doc-icon doc-cv">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                            <div class="doc-info">
                                <span class="doc-title">Curriculum Vitae</span>
                                <span class="doc-meta">CV · {{ learner.cv_original_name || 'Document' }}</span>
                            </div>
                            <span class="material-symbols-outlined doc-arrow">open_in_new</span>
                        </a>
                    </div>
                </div>
                <div class="card" v-if="learner.emergency_contact_name">
                    <h2 class="section-title">Contact d'urgence</h2>
                    <dl class="info-list">
                        <div class="info-row"><dt>Nom complet</dt><dd>{{ learner.emergency_contact_name }} {{ learner.emergency_contact_firstname }}</dd></div>
                        <div class="info-row" v-if="learner.emergency_contact_phone"><dt>Téléphone</dt><dd>{{ learner.emergency_contact_phone }}</dd></div>
                    </dl>
                </div>
            </div>

            <!-- Tab: Formations -->
            <div v-if="activeTab === 'formations'" class="card" style="padding: 0; overflow: hidden;">
                <div class="px-lg py-md" style="border-bottom: 1px solid #e0e3e5">
                    <h2 class="text-base font-semibold">Parcours de formation <span class="count-badge ml-sm">{{ learner.formations.length }}</span></h2>
                </div>
                <div v-if="learner.formations.length === 0" class="px-lg py-xl text-center text-secondary">Aucune formation.</div>
                <div v-for="formation in learner.formations" :key="formation.id" class="formation-row">
                    <div class="flex-1">
                        <Link :href="`/formations/${formation.id}`" class="formation-name">{{ formation.name }}</Link>
                        <div class="text-body-sm text-secondary mt-xs">
                            <Link :href="`/projects/${formation.project.id}`" class="link">{{ formation.project.name }}</Link>
                            <span class="mx-xs">·</span>
                            <span>Inscrit le {{ fmt(formation.pivot.enrolled_at) }}</span>
                        </div>
                    </div>
                    <span class="enrollment-badge" :class="`enrollment-${formation.pivot.status}`">{{ enrollmentStatusLabels[formation.pivot.status] ?? formation.pivot.status }}</span>
                </div>
            </div>

            <!-- Tab: Stage -->
            <div v-if="activeTab === 'stage'" class="insertion-tab">

                <div class="tab-toolbar">
                    <div class="tab-toolbar-text">
                        <h2 class="tab-toolbar-title">Suivi des stages</h2>
                        <p class="tab-toolbar-desc">{{ stageRecords.length }} enregistrement(s) · stage en cours affiché en premier</p>
                    </div>
                    <button
                        v-if="!showStageForm"
                        type="button"
                        class="btn-add-record btn-add-stage"
                        @click="openStageForm"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter un stage
                    </button>
                </div>

                <!-- Stage en cours -->
                <div v-if="latestStage" class="stage-active-card">
                    <div class="stage-active-accent"></div>
                    <div class="stage-active-body">
                        <div class="stage-active-top">
                            <div class="stage-active-icon">
                                <span class="material-symbols-outlined" style="font-size:22px">business_center</span>
                            </div>
                            <div class="stage-active-info">
                                <p class="stage-active-label">Stage en cours</p>
                                <p class="stage-active-company">{{ latestStage.internship_company || 'Entreprise non spécifiée' }}</p>
                            </div>
                            <Can permission="learners.update">
                                <div class="stage-active-actions">
                                    <button type="button" class="card-action-btn" @click="editRecord(latestStage)">
                                        <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                                        Modifier
                                    </button>
                                </div>
                            </Can>
                        </div>
                        <div class="stage-active-chips">
                            <span v-if="latestStage.internship_paid" class="chip chip-paid">
                                <span class="material-symbols-outlined" style="font-size:13px">payments</span> Rémunéré
                            </span>
                            <span v-if="latestStage.internship_contract_type" class="chip chip-contract">{{ latestStage.internship_contract_type }}</span>
                            <span v-if="latestStage.internship_work_mode" class="chip chip-mode">
                                <span class="material-symbols-outlined" style="font-size:14px">{{ workModeIcon(latestStage.internship_work_mode) }}</span>
                                {{ workModeLabel(latestStage.internship_work_mode) }}
                            </span>
                        </div>
                        <div v-if="latestStage.internship_start_date" class="stage-active-dates">
                            <span class="material-symbols-outlined" style="font-size:15px;color:#E5004C">calendar_month</span>
                            Du {{ fmt(latestStage.internship_start_date) }}
                            <template v-if="latestStage.internship_end_date"> au {{ fmt(latestStage.internship_end_date) }}</template>
                        </div>
                        <div v-if="latestStage.status_notes" class="stage-active-notes">{{ latestStage.status_notes }}</div>
                        <a
                            v-if="latestStage.internship_contract_path"
                            :href="contractUrl(latestStage.internship_contract_path)!"
                            target="_blank"
                            class="contract-link"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">description</span>
                            {{ latestStage.internship_contract_original_name || 'Contrat / convention de stage' }}
                        </a>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="!showStageForm" class="insertion-empty">
                    <div class="insertion-empty-icon stage-empty-icon">
                        <span class="material-symbols-outlined">work_off</span>
                    </div>
                    <h3 class="insertion-empty-title">Aucun stage enregistré</h3>
                    <p class="insertion-empty-desc">Documentez le stage de l'apprenant : entreprise, dates, modalité et convention.</p>
                    <button type="button" class="btn-add-record btn-add-stage" @click="openStageForm">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter un stage
                    </button>
                </div>

                <!-- Formulaire Stage -->
                <InsertionRecordForm
                    kind="internship"
                    :form="form"
                    :visible="showStageForm"
                    :is-editing="editingRecord?.status === 'internship'"
                    :contract-types="internshipContractTypes"
                    :work-modes="workModes"
                    :can-manage-contract-types="canManageContractTypes"
                    :existing-contract-url="contractUrl(editingRecord?.internship_contract_path ?? null)"
                    :existing-contract-name="editingRecord?.internship_contract_original_name"
                    :show-existing-contract="showExistingInternshipContract"
                    @submit="submitStageForm"
                    @cancel="cancelEdit"
                    @manage-types="contractTypeModal = 'internship'"
                    @remove-existing="form.remove_internship_contract = true; form.internship_contract_file = null"
                />

                <!-- Historique des stages -->
                <div v-if="stageRecords.length > 0" class="history-card">
                    <div class="history-header">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#9aaabb">history</span>
                        <span class="history-title">Historique des stages</span>
                        <span class="history-count">{{ stageRecords.length }}</span>
                    </div>
                    <div class="history-list">
                        <div v-for="record in stageRecords" :key="record.id" class="history-item">
                            <div class="history-item-left">
                                <div class="history-dot stage-dot-sm"></div>
                            </div>
                            <div class="history-item-body">
                                <div class="history-item-top">
                                    <span class="history-company">{{ record.internship_company || 'Entreprise non spécifiée' }}</span>
                                    <span class="history-item-date">{{ fmt(record.status_changed_at) }}</span>
                                </div>
                                <div class="history-item-meta">
                                    <span v-if="record.internship_start_date" class="history-chip chip-date">
                                        <span class="material-symbols-outlined" style="font-size:12px">calendar_today</span>
                                        {{ fmt(record.internship_start_date) }}{{ record.internship_end_date ? ' → ' + fmt(record.internship_end_date) : ' · En cours' }}
                                    </span>
                                    <span v-if="record.internship_contract_type" class="history-chip chip-contract-sm">{{ record.internship_contract_type }}</span>
                                    <span v-if="record.internship_work_mode" class="history-chip chip-mode-sm">{{ workModeLabel(record.internship_work_mode) }}</span>
                                    <a
                                        v-if="record.internship_contract_path"
                                        :href="contractUrl(record.internship_contract_path)!"
                                        target="_blank"
                                        class="history-chip chip-file-sm"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:12px">description</span>
                                        Contrat
                                    </a>
                                    <span v-if="record.internship_paid" class="history-chip chip-paid-sm">Rémunéré</span>
                                </div>
                                <div v-if="record.status_notes" class="history-notes">{{ record.status_notes }}</div>
                                <div class="history-item-footer">
                                    <span class="history-recorder">
                                        <span class="material-symbols-outlined" style="font-size:13px">person</span>
                                        {{ record.recorder.first_name }} {{ record.recorder.last_name }}
                                    </span>
                                    <div class="history-actions">
                                        <Can permission="learners.update">
                                            <button type="button" @click="editRecord(record)" class="edit-btn" title="Modifier">
                                                <span class="material-symbols-outlined">edit</span>
                                            </button>
                                        </Can>
                                        <Can permission="learners.delete">
                                            <button type="button" @click="deleteTargetId = record.id" class="delete-btn" title="Supprimer">
                                                <span class="material-symbols-outlined">delete_outline</span>
                                            </button>
                                        </Can>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Emploi -->
            <div v-if="activeTab === 'employment'" class="insertion-tab">

                <div class="tab-toolbar">
                    <div class="tab-toolbar-text">
                        <h2 class="tab-toolbar-title">Suivi de l'emploi</h2>
                        <p class="tab-toolbar-desc">{{ employmentRecords.length }} enregistrement(s) · emploi actuel affiché en premier</p>
                    </div>
                    <button
                        v-if="!showEmploymentForm"
                        type="button"
                        class="btn-add-record btn-add-employment"
                        @click="openEmploymentForm"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter un emploi
                    </button>
                </div>

                <!-- Emploi en cours -->
                <div v-if="latestEmployment" class="stage-active-card employment-active-card">
                    <div class="stage-active-body">
                        <div class="stage-active-top">
                            <div class="stage-active-icon employment-active-icon">
                                <span class="material-symbols-outlined" style="font-size:22px">work</span>
                            </div>
                            <div class="stage-active-info">
                                <p class="stage-active-label employment-active-label">Emploi actuel</p>
                                <p class="stage-active-company">{{ latestEmployment.employment_company || 'Entreprise non spécifiée' }}</p>
                                <p v-if="latestEmployment.employment_position" class="stage-active-position">{{ latestEmployment.employment_position }}</p>
                            </div>
                            <Can permission="learners.update">
                                <div class="stage-active-actions">
                                    <button type="button" class="card-action-btn card-action-btn-navy" @click="editRecord(latestEmployment)">
                                        <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                                        Modifier
                                    </button>
                                </div>
                            </Can>
                        </div>
                        <div class="stage-active-chips">
                            <span v-if="latestEmployment.employment_contract_type" class="chip chip-contract">{{ latestEmployment.employment_contract_type }}</span>
                            <span v-if="latestEmployment.employment_work_mode" class="chip chip-mode">
                                <span class="material-symbols-outlined" style="font-size:14px">{{ workModeIcon(latestEmployment.employment_work_mode) }}</span>
                                {{ workModeLabel(latestEmployment.employment_work_mode) }}
                            </span>
                        </div>
                        <div v-if="latestEmployment.employment_start_date" class="stage-active-dates">
                            <span class="material-symbols-outlined" style="font-size:15px;color:#1F3A4D">calendar_month</span>
                            Début le {{ fmt(latestEmployment.employment_start_date) }}
                        </div>
                        <div v-if="latestEmployment.status_notes" class="stage-active-notes">{{ latestEmployment.status_notes }}</div>
                        <a
                            v-if="latestEmployment.employment_contract_path"
                            :href="contractUrl(latestEmployment.employment_contract_path)!"
                            target="_blank"
                            class="contract-link"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">description</span>
                            {{ latestEmployment.employment_contract_original_name || 'Contrat d\'emploi' }}
                        </a>
                    </div>
                </div>

                <!-- Empty state Emploi -->
                <div v-else-if="!showEmploymentForm" class="insertion-empty">
                    <div class="insertion-empty-icon employment-empty-icon">
                        <span class="material-symbols-outlined">person_off</span>
                    </div>
                    <h3 class="insertion-empty-title">Aucun emploi enregistré</h3>
                    <p class="insertion-empty-desc">Documentez l'insertion professionnelle : entreprise, poste, contrat et modalité.</p>
                    <button type="button" class="btn-add-record btn-add-employment" @click="openEmploymentForm">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter un emploi
                    </button>
                </div>

                <!-- Formulaire Emploi -->
                <InsertionRecordForm
                    kind="employment"
                    :form="form"
                    :visible="showEmploymentForm"
                    :is-editing="editingRecord?.status === 'employed'"
                    :contract-types="employmentContractTypes"
                    :work-modes="workModes"
                    :can-manage-contract-types="canManageContractTypes"
                    :existing-contract-url="contractUrl(editingRecord?.employment_contract_path ?? null)"
                    :existing-contract-name="editingRecord?.employment_contract_original_name"
                    :show-existing-contract="showExistingEmploymentContract"
                    @submit="submitEmploymentForm"
                    @cancel="cancelEdit"
                    @manage-types="contractTypeModal = 'employment'"
                    @remove-existing="form.remove_employment_contract = true; form.employment_contract_file = null"
                />

                <!-- Historique des emplois -->
                <div v-if="employmentRecords.length > 0" class="history-card">
                    <div class="history-header">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#9aaabb">history</span>
                        <span class="history-title">Historique des emplois</span>
                        <span class="history-count">{{ employmentRecords.length }}</span>
                    </div>
                    <div class="history-list">
                        <div v-for="record in employmentRecords" :key="record.id" class="history-item">
                            <div class="history-item-left">
                                <div class="history-dot employment-dot-sm"></div>
                            </div>
                            <div class="history-item-body">
                                <div class="history-item-top">
                                    <span class="history-company">{{ record.employment_company || 'Entreprise non spécifiée' }}</span>
                                    <span class="history-item-date">{{ fmt(record.status_changed_at) }}</span>
                                </div>
                                <div v-if="record.employment_position" class="history-position">{{ record.employment_position }}</div>
                                <div class="history-item-meta">
                                    <span v-if="record.employment_start_date" class="history-chip chip-date">
                                        <span class="material-symbols-outlined" style="font-size:12px">calendar_today</span>
                                        Début le {{ fmt(record.employment_start_date) }}
                                    </span>
                                    <span v-if="record.employment_contract_type" class="history-chip chip-contract-sm">{{ record.employment_contract_type }}</span>
                                    <span v-if="record.employment_work_mode" class="history-chip chip-mode-sm">{{ workModeLabel(record.employment_work_mode) }}</span>
                                    <a
                                        v-if="record.employment_contract_path"
                                        :href="contractUrl(record.employment_contract_path)!"
                                        target="_blank"
                                        class="history-chip chip-file-sm"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:12px">description</span>
                                        Contrat
                                    </a>
                                </div>
                                <div v-if="record.status_notes" class="history-notes">{{ record.status_notes }}</div>
                                <div class="history-item-footer">
                                    <span class="history-recorder">
                                        <span class="material-symbols-outlined" style="font-size:13px">person</span>
                                        {{ record.recorder.first_name }} {{ record.recorder.last_name }}
                                    </span>
                                    <div class="history-actions">
                                        <Can permission="learners.update">
                                            <button type="button" @click="editRecord(record)" class="edit-btn" title="Modifier">
                                                <span class="material-symbols-outlined">edit</span>
                                            </button>
                                        </Can>
                                        <Can permission="learners.delete">
                                            <button type="button" @click="deleteTargetId = record.id" class="delete-btn" title="Supprimer">
                                                <span class="material-symbols-outlined">delete_outline</span>
                                            </button>
                                        </Can>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal suppression enregistrement insertion -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="deleteTargetId" class="modal-overlay" @click.self="deleteTargetId = null">
                <div class="modal-box">
                    <div class="modal-box-head">
                        <div class="modal-warn-icon">
                            <span class="material-symbols-outlined" style="font-size:20px">warning</span>
                        </div>
                        <h3 class="modal-box-title">Supprimer cet enregistrement ?</h3>
                    </div>
                    <p class="modal-box-msg">Cette action est irréversible. L'enregistrement sera définitivement supprimé.</p>
                    <div class="modal-box-foot">
                        <button class="modal-cancel" @click="deleteTargetId = null">Annuler</button>
                        <button class="modal-delete" @click="confirmDeleteRecord">Supprimer</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <ContractTypeModal
        :visible="contractTypeModal === 'internship'"
        context="internship"
        :types="internshipContractTypes"
        :can-manage="canManageContractTypes"
        @close="contractTypeModal = null"
    />
    <ContractTypeModal
        :visible="contractTypeModal === 'employment'"
        context="employment"
        :types="employmentContractTypes"
        :can-manage="canManageContractTypes"
        @close="contractTypeModal = null"
    />
</template>

<style scoped>
/* ===== BASE STYLES ===== */
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent; flex-shrink: 0; text-decoration: none; transition: background 0.15s, color 0.15s; }
.icon-back:hover { background: #1F3A4D; color: #fff; }
.avatar-lg { width: 56px; height: 56px; border-radius: 50%; background: #E5004C; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; flex-shrink: 0; text-transform: uppercase; overflow: hidden; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }
.section-title { font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 16px; }
.info-list { display: flex; flex-direction: column; gap: 12px; }
.info-row { display: flex; flex-direction: column; gap: 2px; }
.info-row dt { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.info-row dd { font-size: 14px; color: #191c1e; }
/* ===== DOCUMENTS CARDS ===== */
.docs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.doc-card { display: flex; align-items: center; gap: 12px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; transition: all 0.2s; }
.doc-card:hover { background: #f1f5f9; border-color: #cbd5e1; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.doc-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0; }
.doc-id { background: #fff0f4; color: #E5004C; }
.doc-cv { background: #fff0f4; color: #E5004C; }
.doc-icon .material-symbols-outlined { font-size: 22px; }
.doc-info { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.doc-title { font-size: 13px; font-weight: 600; color: #1e293b; }
.doc-meta { font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-arrow { color: #94a3b8; font-size: 18px; }
.doc-card:hover .doc-arrow { color: #64748b; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #515f74; border-radius: 8px; font-size: 13px; font-weight: 500; border: 1px solid #e0e3e5; transition: all 0.15s; text-decoration: none; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
.btn-navy { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #1F3A4D; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none; }
.btn-navy:hover { background: #1F3A4D; color: #fff; }
.btn-move { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #E5004C; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1.5px solid #E5004C; transition: background 0.15s, color 0.15s; text-decoration: none; }
.btn-move:hover { background: #E5004C; color: #fff; }
.count-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 6px; background: #f2f4f6; border-radius: 99px; font-size: 12px; font-weight: 600; color: #515f74; }
.enrollment-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
.enrollment-in_progress { background: #d1fae5; color: #065f46; }
.enrollment-completed { background: #dbeafe; color: #1e40af; }
.enrollment-withdrawn { background: #fee2e2; color: #991b1b; }
.enrollment-moved { background: #fef3c7; color: #92400e; }
.tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; font-size: 14px; font-weight: 500; color: #515f74; background: transparent; border: none; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.15s; }
.tab-btn:hover { color: #191c1e; background: #f9fafb; }
.tab-active { color: #E5004C; border-bottom-color: #E5004C; background: #fff5f8; }
.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; background: #f2f4f6; border-radius: 99px; font-size: 11px; font-weight: 600; color: #515f74; margin-left: 6px; }
.tab-active .tab-badge { background: #E5004C; color: #fff; }
.page-wrap { max-width: 1200px; margin: 0 auto; padding: 0 16px; display: flex; flex-direction: column; gap: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.header-left { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; margin: 0; line-height: 1.2; }
.page-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; font-size: 13px; }
.formation-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #fff5f8; border: 1px solid #ffd1de; color: #E5004C; border-radius: 99px; font-size: 13px; font-weight: 500; }
.formation-pill .link { color: #E5004C; text-decoration: none; }
.formation-pill .link:hover { text-decoration: underline; }
.formation-pill .sep-arrow { color: #cbd5e1; font-weight: 400; }
.text-secondary { color: #6b7280; }
.tabs-bar { display: flex; gap: 0; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 4px; overflow-x: auto; }
.tabs-bar .tab-btn { border-radius: 8px; border: none; padding: 10px 18px; }
.tabs-bar .tab-btn:hover { background: #f9fafb; }
.tabs-bar .tab-active { background: #fff5f8; color: #E5004C; border: none; }
.tab-content { display: flex; flex-direction: column; gap: 20px; }
.formation-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
.formation-row:last-child { border-bottom: none; }
.formation-row:hover { background: #fafbfc; }
.formation-name { font-weight: 600; color: #191c1e; text-decoration: none; }
.formation-name:hover { color: #E5004C; }
.link { color: #E5004C; text-decoration: none; font-weight: 500; }
.link:hover { text-decoration: underline; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-sm { gap: 8px; }
.gap-md { gap: 16px; }
.gap-lg { gap: 24px; }
.ml-sm { margin-left: 8px; }
.mx-xs { margin-left: 4px; margin-right: 4px; }
.mt-xs { margin-top: 4px; }
.px-lg { padding-left: 20px; padding-right: 20px; }
.py-md { padding-top: 12px; padding-bottom: 12px; }
.py-xl { padding-top: 32px; padding-bottom: 32px; }
.grid { display: grid; }
@media (min-width: 768px) {
    .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

/* ===== CURRENT STATUS CARDS ===== */
/* ===== STAGE ACTIF ===== */
.stage-active-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
}
.stage-active-accent { display: none; }
.stage-active-body {
    padding: 18px 20px;
}
.stage-active-top {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 10px;
}
.stage-active-actions { flex-shrink: 0; margin-left: auto; }
.card-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 7px 12px;
    border: 1.5px solid #E5004C;
    border-radius: 8px;
    background: #fff;
    color: #E5004C;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.card-action-btn:hover { background: #E5004C; color: #fff; }
.card-action-btn-navy { border-color: #1F3A4D; color: #1F3A4D; }
.card-action-btn-navy:hover { background: #1F3A4D; color: #fff; }
.employment-active-card { border-top: 3px solid #1F3A4D; }
.employment-active-icon { background: #e8edf2 !important; color: #1F3A4D !important; }
.employment-active-label { color: #1F3A4D !important; }

/* ===== INSERTION TABS ===== */
.insertion-tab {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.tab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.tab-toolbar-title {
    font-size: 16px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
}
.tab-toolbar-desc {
    font-size: 12px;
    color: #9aaabb;
    margin: 2px 0 0;
}
.btn-add-record {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    transition: filter 0.15s, transform 0.1s;
    white-space: nowrap;
}
.btn-add-record:hover { filter: brightness(1.06); }
.btn-add-record:active { transform: scale(0.98); }
.btn-add-stage { background: #E5004C; }
.btn-add-employment { background: #1F3A4D; }

.insertion-empty {
    text-align: center;
    padding: 48px 28px;
    background: #fafbfc;
    border: 1.5px dashed #e0e3e5;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.insertion-empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.insertion-empty-icon .material-symbols-outlined { font-size: 28px; }
.stage-empty-icon { background: #fff0f4; color: #E5004C; }
.employment-empty-icon { background: #e8edf2; color: #1F3A4D; }
.insertion-empty-title {
    font-size: 16px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
}
.insertion-empty-desc {
    font-size: 13px;
    color: #6b7280;
    max-width: 360px;
    margin: 0;
    line-height: 1.5;
}
.stage-active-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.stage-active-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #fff0f4;
    color: #E5004C;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stage-active-info { flex: 1; min-width: 0; }
.stage-active-label {
    font-size: 11px;
    font-weight: 700;
    color: #E5004C;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.stage-active-company {
    font-size: 17px;
    font-weight: 700;
    color: #191c1e;
    margin-top: 2px;
}
.chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
}
.chip-paid { background: #fff0f4; color: #E5004C; }
.chip-contract { background: #e8edf2; color: #1F3A4D; }
.chip-mode {
    display: inline-flex; align-items: center; gap: 4px;
    background: #dbeafe; color: #1d4ed8;
}
.label-row {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.manage-types-link {
    border: none; background: none; padding: 0;
    font-size: 11px; font-weight: 600; color: #E5004C;
    cursor: pointer; text-decoration: underline;
}
.manage-types-link:hover { color: #c0003e; }

.mode-options {
    display: flex; flex-wrap: wrap; gap: 10px;
}
.mode-option {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 16px; border: 1.5px solid #e0e3e5; border-radius: 10px;
    background: #fafbfc; font-size: 13px; font-weight: 600; color: #515f74;
    cursor: pointer; transition: all 0.15s;
}
.mode-option:hover { border-color: #c8cdd3; background: #fff; }
.mode-option.selected {
    border-color: #E5004C; background: #fff5f8; color: #E5004C;
}
.mode-icon { font-size: 18px; }

.contract-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #1F3A4D;
    text-decoration: none;
}
.contract-link:hover { color: #E5004C; text-decoration: underline; }
.contract-existing {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e8edf2;
    border-radius: 8px;
}
.contract-remove {
    border: none;
    background: none;
    color: #ba1a1a;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.form-file {
    width: 100%;
    font-size: 13px;
    color: #515f74;
}
.file-hint { font-size: 11px; color: #9aaabb; margin-top: 4px; }
.chip-file-sm {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f0fdf4;
    color: #166534;
    text-decoration: none;
}
.chip-file-sm:hover { background: #dcfce7; }

.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}
.stage-active-dates {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #515f74;
    margin-bottom: 6px;
}
.stage-active-position {
    font-size: 13px;
    color: #515f74;
    margin-top: 2px;
}
.stage-active-notes {
    font-size: 12px;
    color: #9aaabb;
    font-style: italic;
    margin-top: 4px;
}
.history-position {
    font-size: 12px;
    color: #515f74;
    margin-bottom: 6px;
}

/* ===== EMPLOI ACTIF (conservé) ===== */
.employment-current-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    padding: 20px;
}
.current-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}
.current-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stage-icon-bg { background: #E5004C; color: white; }
.employment-icon-bg { background: #22c55e; color: white; }
.current-icon .material-symbols-outlined { font-size: 24px; }
.current-info { flex: 1; }
.current-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.current-company {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin-top: 2px;
}
.current-position { font-size: 14px; color: #4b5563; margin-top: 4px; }
.paid-badge, .contract-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.paid-badge { background: #fef3c7; color: #92400e; }
.contract-badge { background: #e0e7ff; color: #4338ca; }
.current-dates {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; color: #4b5563; margin-top: 8px;
}
.contract-type {
    display: inline-block; font-size: 13px; color: #4b5563;
    background: rgba(255,255,255,0.6); padding: 4px 10px; border-radius: 6px; margin-top: 8px;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    background: #f9fafb;
    border: 2px dashed #e5e7eb;
}
.empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.empty-icon .material-symbols-outlined {
    font-size: 32px;
}
.empty-title {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
.empty-desc {
    font-size: 14px;
    color: #6b7280;
    max-width: 300px;
    margin: 0 auto;
}

/* ===== FORM CARD ===== */
.form-card {
    padding: 0;
}
.form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid transparent;
}
.form-mode-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.form-mode-contract-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    align-items: start;
}
@media (min-width: 768px) {
    .form-mode-contract-row {
        grid-template-columns: 1fr 1fr;
    }
}
.mode-options-stack {
    flex-direction: column;
    align-items: stretch;
}
.mode-options-stack .mode-option {
    justify-content: flex-start;
}
.form-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-header:hover {
    background: #f9fafb;
}
.form-header:has(+ form[style*="display: block"]), .form-header:has(+ form:not([style*="display: none"])) {
    border-bottom-color: #e5e7eb;
}
.form-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}
.form-icon {
    color: #E5004C;
    font-size: 20px;
}
.toggle-icon {
    color: #9ca3af;
    transition: transform 0.2s;
}
.toggle-icon.rotated {
    transform: rotate(180deg);
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}
@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-group.full-width {
    grid-column: 1 / -1;
}
.form-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
}
.label-icon {
    font-size: 16px;
    color: #9ca3af;
}
.form-input, .form-textarea {
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    color: #1f2937;
    background: #fff;
    transition: all 0.15s;
}
.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.1);
}
.form-textarea {
    resize: vertical;
    min-height: 60px;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-primary:hover:not(:disabled) {
    background: #c40042;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(229, 0, 76, 0.3);
}
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ===== CUSTOM CHECKBOX ===== */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    color: #4b5563;
}
.styled-checkbox {
    display: none;
}
.checkmark {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.styled-checkbox:checked + .checkmark {
    background: #E5004C;
    border-color: #E5004C;
}
.checkmark::after {
    content: '';
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    display: none;
}
.styled-checkbox:checked + .checkmark::after {
    display: block;
}

/* ===== TIMELINE ===== */
/* ===== HISTORIQUE (remplace timeline) ===== */
.history-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
}
.history-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.history-title {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    flex: 1;
}
.history-count {
    background: #f3f4f6;
    color: #6b7280;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}
.history-list { display: flex; flex-direction: column; gap: 0; }
.history-item {
    display: flex;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid #f2f4f6;
}
.history-item:last-child { border-bottom: none; padding-bottom: 0; }
.history-item-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 5px;
}
.history-dot-sm, .stage-dot-sm {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.stage-dot-sm { background: #E5004C; }
.employment-dot-sm { background: #1F3A4D; }
.history-item-body { flex: 1; min-width: 0; }
.history-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}
.history-company {
    font-size: 14px;
    font-weight: 700;
    color: #191c1e;
}
.history-item-date {
    font-size: 11px;
    color: #9aaabb;
    white-space: nowrap;
    flex-shrink: 0;
}
.history-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;
}
.history-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 99px;
    font-size: 11px; font-weight: 500;
}
.chip-date { background: #eef2f5; color: #1F3A4D; }
.chip-contract-sm { background: #e8edf2; color: #1F3A4D; }
.chip-mode-sm { background: #dbeafe; color: #1d4ed8; }
.chip-paid-sm { background: #fff0f4; color: #E5004C; }
.history-notes {
    font-size: 12px;
    color: #9aaabb;
    font-style: italic;
    margin-top: 4px;
    margin-bottom: 6px;
}
.history-item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 6px;
}
.history-recorder {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #9aaabb;
}
.history-actions {
    display: flex;
    gap: 4px;
}

/* conservé pour emploi timeline */
.timeline-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
}
.timeline-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 20px;
}
.timeline-title .material-symbols-outlined { color: #9ca3af; }
.timeline-count {
    margin-left: auto; background: #f3f4f6; color: #6b7280;
    padding: 2px 10px; border-radius: 99px; font-size: 12px; font-weight: 600;
}
.timeline { position: relative; padding-left: 24px; }
.timeline::before {
    content: ''; position: absolute; left: 8px; top: 0; bottom: 0;
    width: 2px; background: #e5e7eb;
}
.timeline-item { position: relative; padding-bottom: 24px; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-dot {
    position: absolute; left: -20px; top: 4px;
    width: 12px; height: 12px; border-radius: 50%;
    border: 2px solid #fff; box-shadow: 0 0 0 2px currentColor;
}
.stage-dot { background: #E5004C; color: #E5004C; }
.employment-dot { background: #22c55e; color: #22c55e; }
.timeline-item.first .timeline-dot { box-shadow: 0 0 0 3px currentColor; transform: scale(1.1); }
.timeline-content { background: #f9fafb; border-radius: 10px; padding: 16px; }
.timeline-header {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 10px;
}
.timeline-badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.stage-badge { background: #fff0f4; color: #E5004C; }
.employment-badge { background: #d1fae5; color: #065f46; }
.timeline-date { font-size: 12px; color: #9ca3af; }
.timeline-company {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}
.company-icon {
    color: #9ca3af;
    font-size: 18px;
}
.timeline-position {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #4b5563;
    margin-bottom: 8px;
}
.timeline-position .material-symbols-outlined {
    font-size: 16px;
    color: #9ca3af;
}
.timeline-dates, .timeline-detail {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 6px;
}
.timeline-dates .material-symbols-outlined, .timeline-detail .material-symbols-outlined {
    font-size: 14px;
}
.timeline-notes {
    font-size: 13px;
    color: #6b7280;
    font-style: italic;
    margin-top: 8px;
    padding: 8px 12px;
    background: #fff;
    border-radius: 6px;
    border-left: 3px solid #e5e7eb;
}
.timeline-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}
.recorder {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #9ca3af;
}
.delete-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: #9ca3af;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.delete-btn:hover {
    color: #ef4444;
    background: #fee2e2;
}

.timeline-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: #9ca3af;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.edit-btn:hover {
    color: #1F3A4D;
    background: #e8edf2;
}

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.modal-box {
    background: #fff; border-radius: 14px; padding: 28px 28px 22px;
    width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.18);
}
.modal-box-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.modal-warn-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: #fff0f4; color: #E5004C;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-box-title { font-size: 15px; font-weight: 700; color: #191c1e; }
.modal-box-msg { font-size: 13px; color: #515f74; margin-bottom: 20px; line-height: 1.5; }
.modal-box-foot { display: flex; justify-content: flex-end; gap: 10px; }
.modal-cancel {
    padding: 8px 18px; background: #f2f4f6; color: #515f74;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.modal-cancel:hover { background: #e0e3e5; }
.modal-delete {
    padding: 8px 18px; background: #E5004C; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.modal-delete:hover { background: #c0003e; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
