<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface FormData {
    status: string
    status_changed_at: string
    status_notes: string
    internship_start_date: string
    internship_end_date: string
    internship_company: string
    internship_paid: boolean
    internship_contract_type: string
    employment_company: string
    employment_start_date: string
    employment_contract_type: string
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
    employment_company: string | null
    employment_start_date: string | null
    employment_contract_type: string | null
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
    formations: Formation[]
}

const props = defineProps<{
    learner: Learner
    insertionRecords: InsertionRecord[]
    latestInsertion: InsertionRecord | null
    insertionStatuses: InsertionStatus[]
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
    employment_company: '',
    employment_start_date: '',
    employment_contract_type: '',
    employment_position: '',
})

const deleteRecord = (recordId: string) => {
    if (confirm('Confirmer la suppression ?')) {
        router.delete(`/learners/${props.learner.id}/insertion/${recordId}`, {
            preserveScroll: true,
        })
    }
}

const editingRecord = ref<InsertionRecord | null>(null)

const editRecord = (record: InsertionRecord) => {
    editingRecord.value = record
    form.status = record.status
    form.status_changed_at = record.status_changed_at
    form.status_notes = record.status_notes ?? ''
    form.internship_start_date = record.internship_start_date ?? ''
    form.internship_end_date = record.internship_end_date ?? ''
    form.internship_company = record.internship_company ?? ''
    form.internship_paid = record.internship_paid ?? false
    form.internship_contract_type = record.internship_contract_type ?? ''
    form.employment_company = record.employment_company ?? ''
    form.employment_start_date = record.employment_start_date ?? ''
    form.employment_contract_type = record.employment_contract_type ?? ''
    form.employment_position = record.employment_position ?? ''

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
    showStageForm.value = false
    showEmploymentForm.value = false
}

const submitForm = () => {
    if (editingRecord.value) {
        // Mode update
        form.put(`/learners/${props.learner.id}/insertion/${editingRecord.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editingRecord.value = null
                form.reset()
                form.status_changed_at = today
                showStageForm.value = false
                showEmploymentForm.value = false
            },
        })
    } else {
        // Mode create
        form.post(`/learners/${props.learner.id}/insertion`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset()
                form.status_changed_at = today
                showStageForm.value = false
                showEmploymentForm.value = false
            },
        })
    }
}

const activeTab = ref<'info' | 'formations' | 'stage' | 'employment'>('info')
const showStageForm = ref(false)
const showEmploymentForm = ref(false)

const fmt = (d: string | null) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : ''
const genderLabel = (g: string | null) => ({ male: 'Masculin', female: 'Féminin' }[g ?? ''] ?? '')
const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

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
                <Link :href="`/learners/${learner.id}/edit`" class="btn-secondary"><span class="material-symbols-outlined" style="font-size:18px">edit</span>Modifier</Link>
                <Link :href="`/learners/${learner.id}/move`" class="btn-secondary"><span class="material-symbols-outlined" style="font-size:18px">swap_horiz</span>Déplacer</Link>
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
                <div class="card" v-if="learner.cnib_path">
                    <h2 class="section-title">Documents</h2>
                    <a :href="`/storage/${learner.cnib_path}`" target="_blank" rel="noopener" class="doc-link"><span class="material-symbols-outlined" style="font-size:16px">description</span>Voir CNIB / Pièce d'identité</a>
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
            <div v-if="activeTab === 'stage'" class="space-y-lg">
                <!-- Stage en cours - Carte visuelle -->
                <div v-if="latestStage" class="card stage-current-card">
                    <div class="current-header">
                        <div class="current-icon stage-icon-bg">
                            <span class="material-symbols-outlined">business_center</span>
                        </div>
                        <div class="current-info">
                            <div class="current-label">Stage en cours</div>
                            <div class="current-company">{{ latestStage.internship_company || 'Entreprise non spécifiée' }}</div>
                        </div>
                        <span v-if="latestStage.internship_paid" class="paid-badge">
                            <span class="material-symbols-outlined" style="font-size:14px">payments</span>
                            Rémunéré
                        </span>
                    </div>
                    <div v-if="latestStage.internship_start_date || latestStage.internship_end_date" class="current-dates">
                        <span class="material-symbols-outlined" style="font-size:16px">calendar_month</span>
                        <span>Du {{ fmt(latestStage.internship_start_date) }} au {{ fmt(latestStage.internship_end_date) }}</span>
                    </div>
                    <div v-if="latestStage.internship_contract_type" class="contract-type">
                        {{ latestStage.internship_contract_type }}
                    </div>
                </div>

                <!-- Empty state Stage -->
                <div v-else class="card empty-state">
                    <div class="empty-icon stage-icon-bg">
                        <span class="material-symbols-outlined">work_off</span>
                    </div>
                    <h3 class="empty-title">Aucun stage en cours</h3>
                    <p class="empty-desc">Ajoutez un stage pour suivre le parcours professionnel de l'apprenant.</p>
                </div>

                <!-- Formulaire Stage -->
                <div class="card form-card">
                    <div class="form-header" @click="showStageForm = !showStageForm">
                        <div class="form-title">
                            <span class="material-symbols-outlined form-icon">{{ editingRecord?.status === 'internship' ? 'edit' : 'add_circle' }}</span>
                            <span>{{ editingRecord?.status === 'internship' ? 'Modifier le stage' : 'Ajouter un stage' }}</span>
                        </div>
                        <span class="material-symbols-outlined toggle-icon" :class="{ 'rotated': showStageForm }">expand_more</span>
                    </div>
                    <form v-show="showStageForm" @submit.prevent="submitForm" class="form-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">business</span>
                                    Entreprise de stage
                                </label>
                                <input type="text" v-model="form.internship_company" class="form-input" placeholder="Nom de l'entreprise" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">description</span>
                                    Type de contrat
                                </label>
                                <input type="text" v-model="form.internship_contract_type" class="form-input" placeholder="Ex: Convention de stage">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">event</span>
                                    Date de début
                                </label>
                                <input type="date" v-model="form.internship_start_date" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">event_busy</span>
                                    Date de fin
                                </label>
                                <input type="date" v-model="form.internship_end_date" class="form-input">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="checkbox-label">
                                <input type="checkbox" v-model="form.internship_paid" :true-value="true" :false-value="false" class="styled-checkbox">
                                <span class="checkmark"></span>
                                <span>Stage rémunéré</span>
                            </label>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">
                                <span class="material-symbols-outlined label-icon">notes</span>
                                Notes
                            </label>
                            <textarea v-model="form.status_notes" class="form-textarea" rows="2" placeholder="Commentaires sur le stage..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" @click="cancelEdit">Annuler</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing" @click="form.status = 'internship'">
                                <span class="material-symbols-outlined">save</span>
                                {{ form.processing ? 'Enregistrement...' : (editingRecord?.status === 'internship' ? 'Modifier le stage' : 'Enregistrer le stage') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Timeline des stages -->
                <div v-if="stageRecords.length > 0" class="timeline-card">
                    <h3 class="timeline-title">
                        <span class="material-symbols-outlined">history</span>
                        Historique des stages
                        <span class="timeline-count">{{ stageRecords.length }}</span>
                    </h3>
                    <div class="timeline">
                        <div v-for="(record, index) in stageRecords" :key="record.id" class="timeline-item" :class="{ 'first': index === 0 }">
                            <div class="timeline-dot stage-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-badge stage-badge">Stage</span>
                                    <span class="timeline-date">{{ fmt(record.status_changed_at) }}</span>
                                </div>
                                <div class="timeline-company">
                                    <span class="material-symbols-outlined company-icon">business</span>
                                    {{ record.internship_company || 'Entreprise non spécifiée' }}
                                </div>
                                <div v-if="record.internship_start_date" class="timeline-dates">
                                    <span class="material-symbols-outlined">calendar_today</span>
                                    {{ fmt(record.internship_start_date) }} - {{ record.internship_end_date ? fmt(record.internship_end_date) : 'En cours' }}
                                </div>
                                <div v-if="record.internship_contract_type" class="timeline-detail">
                                    <span class="material-symbols-outlined">description</span>
                                    {{ record.internship_contract_type }}
                                </div>
                                <div v-if="record.status_notes" class="timeline-notes">{{ record.status_notes }}</div>
                                <div class="timeline-meta">
                                    <span class="recorder">
                                        <span class="material-symbols-outlined">person</span>
                                        {{ record.recorder.first_name }} {{ record.recorder.last_name }}
                                    </span>
                                    <div class="timeline-actions">
                                        <button type="button" @click="editRecord(record)" class="edit-btn" title="Modifier">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button type="button" @click="deleteRecord(record.id)" class="delete-btn" title="Supprimer">
                                            <span class="material-symbols-outlined">delete_outline</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Emploi -->
            <div v-if="activeTab === 'employment'" class="space-y-lg">
                <!-- Emploi en cours - Carte visuelle -->
                <div v-if="latestEmployment" class="card employment-current-card">
                    <div class="current-header">
                        <div class="current-icon employment-icon-bg">
                            <span class="material-symbols-outlined">work</span>
                        </div>
                        <div class="current-info">
                            <div class="current-label">Emploi actuel</div>
                            <div class="current-company">{{ latestEmployment.employment_company || 'Entreprise non spécifiée' }}</div>
                            <div v-if="latestEmployment.employment_position" class="current-position">{{ latestEmployment.employment_position }}</div>
                        </div>
                        <span v-if="latestEmployment.employment_contract_type" class="contract-badge">
                            {{ latestEmployment.employment_contract_type }}
                        </span>
                    </div>
                    <div v-if="latestEmployment.employment_start_date" class="current-dates">
                        <span class="material-symbols-outlined" style="font-size:16px">calendar_month</span>
                        <span>Début le {{ fmt(latestEmployment.employment_start_date) }}</span>
                    </div>
                </div>

                <!-- Empty state Emploi -->
                <div v-else class="card empty-state">
                    <div class="empty-icon employment-icon-bg">
                        <span class="material-symbols-outlined">person_off</span>
                    </div>
                    <h3 class="empty-title">Aucun emploi en cours</h3>
                    <p class="empty-desc">Ajoutez un emploi pour suivre le parcours professionnel de l'apprenant.</p>
                </div>

                <!-- Formulaire Emploi -->
                <div class="card form-card">
                    <div class="form-header" @click="showEmploymentForm = !showEmploymentForm">
                        <div class="form-title">
                            <span class="material-symbols-outlined form-icon">{{ editingRecord?.status === 'employed' ? 'edit' : 'add_circle' }}</span>
                            <span>{{ editingRecord?.status === 'employed' ? 'Modifier l\'emploi' : 'Ajouter un emploi' }}</span>
                        </div>
                        <span class="material-symbols-outlined toggle-icon" :class="{ 'rotated': showEmploymentForm }">expand_more</span>
                    </div>
                    <form v-show="showEmploymentForm" @submit.prevent="submitForm" class="form-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">business</span>
                                    Entreprise
                                </label>
                                <input type="text" v-model="form.employment_company" class="form-input" placeholder="Nom de l'entreprise" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">badge</span>
                                    Poste
                                </label>
                                <input type="text" v-model="form.employment_position" class="form-input" placeholder="Titre du poste">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">contract</span>
                                    Type de contrat
                                </label>
                                <select v-model="form.employment_contract_type" class="form-input">
                                    <option value="">Choisir...</option>
                                    <option value="CDI">CDI</option>
                                    <option value="CDD">CDD</option>
                                    <option value="freelance">Freelance</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="material-symbols-outlined label-icon">event</span>
                                    Date de début
                                </label>
                                <input type="date" v-model="form.employment_start_date" class="form-input">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">
                                <span class="material-symbols-outlined label-icon">notes</span>
                                Notes
                            </label>
                            <textarea v-model="form.status_notes" class="form-textarea" rows="2" placeholder="Commentaires sur l'emploi..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" @click="cancelEdit">Annuler</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing" @click="form.status = 'employed'">
                                <span class="material-symbols-outlined">save</span>
                                {{ form.processing ? 'Enregistrement...' : (editingRecord?.status === 'employed' ? 'Modifier l\'emploi' : 'Enregistrer l\'emploi') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Timeline des emplois -->
                <div v-if="employmentRecords.length > 0" class="timeline-card">
                    <h3 class="timeline-title">
                        <span class="material-symbols-outlined">history</span>
                        Historique des emplois
                        <span class="timeline-count">{{ employmentRecords.length }}</span>
                    </h3>
                    <div class="timeline">
                        <div v-for="(record, index) in employmentRecords" :key="record.id" class="timeline-item" :class="{ 'first': index === 0 }">
                            <div class="timeline-dot employment-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-badge employment-badge">Emploi</span>
                                    <span class="timeline-date">{{ fmt(record.status_changed_at) }}</span>
                                </div>
                                <div class="timeline-company">
                                    <span class="material-symbols-outlined company-icon">business</span>
                                    {{ record.employment_company || 'Entreprise non spécifiée' }}
                                </div>
                                <div v-if="record.employment_position" class="timeline-position">
                                    <span class="material-symbols-outlined">badge</span>
                                    {{ record.employment_position }}
                                </div>
                                <div v-if="record.employment_start_date" class="timeline-dates">
                                    <span class="material-symbols-outlined">calendar_today</span>
                                    Début le {{ fmt(record.employment_start_date) }}
                                </div>
                                <div v-if="record.employment_contract_type" class="timeline-detail">
                                    <span class="material-symbols-outlined">contract</span>
                                    Contrat : {{ record.employment_contract_type }}
                                </div>
                                <div v-if="record.status_notes" class="timeline-notes">{{ record.status_notes }}</div>
                                <div class="timeline-meta">
                                    <span class="recorder">
                                        <span class="material-symbols-outlined">person</span>
                                        {{ record.recorder.first_name }} {{ record.recorder.last_name }}
                                    </span>
                                    <div class="timeline-actions">
                                        <button type="button" @click="editRecord(record)" class="edit-btn" title="Modifier">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button type="button" @click="deleteRecord(record.id)" class="delete-btn" title="Supprimer">
                                            <span class="material-symbols-outlined">delete_outline</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ===== BASE STYLES ===== */
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; color: #515f74; transition: background 0.15s; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #eceef0; color: #191c1e; }
.avatar-lg { width: 56px; height: 56px; border-radius: 50%; background: #E5004C; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; flex-shrink: 0; text-transform: uppercase; overflow: hidden; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }
.section-title { font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 16px; }
.info-list { display: flex; flex-direction: column; gap: 12px; }
.info-row { display: flex; flex-direction: column; gap: 2px; }
.info-row dt { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.info-row dd { font-size: 14px; color: #191c1e; }
.doc-link { display: inline-flex; align-items: center; gap: 4px; color: #1d4ed8; text-decoration: none; font-weight: 500; font-size: 13px; }
.doc-link:hover { text-decoration: underline; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #515f74; border-radius: 8px; font-size: 13px; font-weight: 500; border: 1px solid #e0e3e5; transition: all 0.15s; text-decoration: none; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
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
.stage-current-card, .employment-current-card {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 1px solid #bfdbfe;
    padding: 20px;
}
.employment-current-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
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
.stage-icon-bg {
    background: #3b82f6;
    color: white;
}
.employment-icon-bg {
    background: #22c55e;
    color: white;
}
.current-icon .material-symbols-outlined {
    font-size: 24px;
}
.current-info {
    flex: 1;
}
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
.current-position {
    font-size: 14px;
    color: #4b5563;
    margin-top: 4px;
}
.paid-badge, .contract-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.paid-badge {
    background: #fef3c7;
    color: #92400e;
}
.contract-badge {
    background: #e0e7ff;
    color: #4338ca;
}
.current-dates {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #4b5563;
    margin-top: 8px;
}
.contract-type {
    display: inline-block;
    font-size: 13px;
    color: #4b5563;
    background: rgba(255,255,255,0.6);
    padding: 4px 10px;
    border-radius: 6px;
    margin-top: 8px;
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
    overflow: hidden;
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
.form-content {
    padding: 20px;
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
.timeline-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
}
.timeline-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 20px;
}
.timeline-title .material-symbols-outlined {
    color: #9ca3af;
}
.timeline-count {
    margin-left: auto;
    background: #f3f4f6;
    color: #6b7280;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}
.timeline {
    position: relative;
    padding-left: 24px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.timeline-item {
    position: relative;
    padding-bottom: 24px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-dot {
    position: absolute;
    left: -20px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px currentColor;
}
.stage-dot {
    background: #3b82f6;
    color: #3b82f6;
}
.employment-dot {
    background: #22c55e;
    color: #22c55e;
}
.timeline-item.first .timeline-dot {
    box-shadow: 0 0 0 3px currentColor;
    transform: scale(1.1);
}
.timeline-content {
    background: #f9fafb;
    border-radius: 10px;
    padding: 16px;
}
.timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.timeline-badge {
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.stage-badge {
    background: #dbeafe;
    color: #1e40af;
}
.employment-badge {
    background: #d1fae5;
    color: #065f46;
}
.timeline-date {
    font-size: 12px;
    color: #9ca3af;
}
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
    color: #3b82f6;
    background: #dbeafe;
}
</style>
