<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface EducationLevel { name: string }

interface Formation {
    id: string
    name: string
    status: string
    project: { id: string; name: string }
    pivot: { status: string; enrolled_at: string; withdrawn_at: string | null }
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
    education_level: EducationLevel | null
    formations: Formation[]
}

interface InsertionRecord {
    id: string
    status: string
    status_changed_at: string | null
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
    recorded_by: { id: string; first_name: string; last_name: string } | null
    created_at: string
}

interface InsertionStatus {
    value: string
    label: string
    color: string
    is_stage: boolean
    is_employment: boolean
}

const props = defineProps<{
    learner: Learner
    insertionRecords: InsertionRecord[]
    latestInsertion: InsertionRecord | null
    insertionStatuses: InsertionStatus[]
}>()

const activeTab = ref<'info' | 'formations' | 'stage' | 'insertion'>('info')

const insertionStatusLabels: Record<string, string> = {
    searching: 'En recherche', internship: 'En stage', employed: 'En emploi', unemployed: 'Sans emploi'
}

const insertionStatusColors: Record<string, string> = {
    searching: '#f97316', internship: '#3b82f6', employed: '#22c55e', unemployed: '#6b7280'
}

const contractTypeOptions = ['CDI', 'CDD', 'freelance', 'autre']

const fmt = (d: string | null) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : '—'
const genderLabel = (g: string | null) => ({ male: 'Masculin', female: 'Féminin' }[g ?? ''] ?? '—')
const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

const enrollmentStatusLabels: Record<string, string> = {
    in_progress: 'En cours', withdrawn: 'Retiré', completed: 'Terminé', moved: 'Déplacé'
}

// Computed
const stageRecords = computed(() => props.insertionRecords.filter(r => r.internship_company))
const employmentRecords = computed(() => props.insertionRecords.filter(r => r.employment_company))
const currentFormation = computed(() => props.learner.formations.find(f => f.pivot.status === 'in_progress') ?? props.learner.formations[0] ?? null)

// Statut rapide
const statusForm = ref({ status: props.latestInsertion?.status ?? 'searching', status_notes: '' })
const submitStatus = () => {
    router.post(`/learners/${props.learner.id}/insertion`, {
        status: statusForm.value.status,
        status_notes: statusForm.value.status_notes,
        status_changed_at: new Date().toISOString().split('T')[0],
    }, { preserveScroll: true, onSuccess: () => { statusForm.value.status_notes = '' } })
}

// Stage
const stageForm = ref({ internship_company: '', internship_start_date: '', internship_end_date: '', internship_paid: '', internship_contract_type: '', status_notes: '' })
const submitStage = () => {
    router.post(`/learners/${props.learner.id}/insertion`, {
        status: 'internship',
        status_changed_at: stageForm.value.internship_start_date || new Date().toISOString().split('T')[0],
        status_notes: stageForm.value.status_notes,
        internship_company: stageForm.value.internship_company,
        internship_start_date: stageForm.value.internship_start_date,
        internship_end_date: stageForm.value.internship_end_date,
        internship_paid: stageForm.value.internship_paid === 'yes',
        internship_contract_type: stageForm.value.internship_contract_type,
    }, {
        preserveScroll: true,
        onSuccess: () => { stageForm.value = { internship_company: '', internship_start_date: '', internship_end_date: '', internship_paid: '', internship_contract_type: '', status_notes: '' } }
    })
}

// Emploi
const employmentForm = ref({ employment_company: '', employment_position: '', employment_start_date: '', employment_contract_type: '', status_notes: '' })
const submitEmployment = () => {
    router.post(`/learners/${props.learner.id}/insertion`, {
        status: 'employed',
        status_changed_at: employmentForm.value.employment_start_date || new Date().toISOString().split('T')[0],
        status_notes: employmentForm.value.status_notes,
        employment_company: employmentForm.value.employment_company,
        employment_position: employmentForm.value.employment_position,
        employment_start_date: employmentForm.value.employment_start_date,
        employment_contract_type: employmentForm.value.employment_contract_type,
    }, {
        preserveScroll: true,
        onSuccess: () => { employmentForm.value = { employment_company: '', employment_position: '', employment_start_date: '', employment_contract_type: '', status_notes: '' } }
    })
}

const deleteRecord = (recordId: string) => {
    if (confirm('Supprimer cette entrée d\'historique ?')) {
        router.delete(`/learners/${props.learner.id}/insertion/${recordId}`, { preserveScroll: true })
    }
}
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

        <!-- Statut courant -->
        <div v-if="latestInsertion" class="status-banner" :style="{ background: insertionStatusColors[latestInsertion.status] + '12', borderLeftColor: insertionStatusColors[latestInsertion.status] }">
            <div class="flex items-center gap-md">
                <span class="status-dot-lg" :style="{ background: insertionStatusColors[latestInsertion.status] }"></span>
                <div>
                    <div class="text-body-xs text-secondary">Statut d'insertion actuel</div>
                    <div class="text-base font-semibold" :style="{ color: insertionStatusColors[latestInsertion.status] }">{{ insertionStatusLabels[latestInsertion.status] ?? latestInsertion.status }}</div>
                </div>
            </div>
            <div class="text-body-xs text-secondary">Mis à jour le {{ fmt(latestInsertion.status_changed_at ?? latestInsertion.created_at) }}</div>
        </div>

        <!-- Tabs -->
        <div class="tabs-bar">
            <button @click="activeTab = 'info'" class="tab-btn" :class="{ 'tab-active': activeTab === 'info' }"><span class="material-symbols-outlined" style="font-size:18px">person</span>Profil</button>
            <button @click="activeTab = 'formations'" class="tab-btn" :class="{ 'tab-active': activeTab === 'formations' }"><span class="material-symbols-outlined" style="font-size:18px">school</span>Formations<span class="tab-badge">{{ learner.formations.length }}</span></button>
            <button @click="activeTab = 'stage'" class="tab-btn" :class="{ 'tab-active': activeTab === 'stage' }"><span class="material-symbols-outlined" style="font-size:18px">badge</span>Stage<span class="tab-badge">{{ stageRecords.length }}</span></button>
            <button @click="activeTab = 'insertion'" class="tab-btn" :class="{ 'tab-active': activeTab === 'insertion' }"><span class="material-symbols-outlined" style="font-size:18px">work</span>Insertion<span class="tab-badge">{{ employmentRecords.length }}</span></button>
        </div>

        <div class="tab-content">

            <!-- Tab: Profil -->
            <div v-if="activeTab === 'info'" class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                <div class="card">
                    <h2 class="section-title">Informations personnelles</h2>
                    <dl class="info-list">
                        <div class="info-row"><dt>Email</dt><dd>{{ learner.email ?? '—' }}</dd></div>
                        <div class="info-row"><dt>Téléphone</dt><dd>{{ learner.phone ?? '—' }}</dd></div>
                        <div class="info-row"><dt>Date de naissance</dt><dd>{{ fmt(learner.birth_date) }}</dd></div>
                        <div class="info-row"><dt>Lieu de naissance</dt><dd>{{ learner.birth_place ?? '—' }}</dd></div>
                        <div v-if="learner.talent" class="info-row"><dt>Talent</dt><dd>{{ learner.talent }}</dd></div>
                    </dl>
                </div>
                <div class="space-y-lg">
                    <div class="card" v-if="learner.emergency_contact_name">
                        <h2 class="section-title">Contact d'urgence</h2>
                        <dl class="info-list">
                            <div class="info-row"><dt>Nom complet</dt><dd>{{ learner.emergency_contact_name }} {{ learner.emergency_contact_firstname }}</dd></div>
                            <div class="info-row" v-if="learner.emergency_contact_phone"><dt>Téléphone</dt><dd>{{ learner.emergency_contact_phone }}</dd></div>
                        </dl>
                    </div>
                    <div class="card" v-if="learner.cnib_path">
                        <h2 class="section-title">Documents</h2>
                        <a :href="`/storage/${learner.cnib_path}`" target="_blank" rel="noopener" class="doc-link"><span class="material-symbols-outlined" style="font-size:16px">description</span>Voir CNIB / Pièce d'identité</a>
                    </div>
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
            <div v-if="activeTab === 'stage'" class="grid grid-cols-1 lg:grid-cols-5 gap-lg">
                <div class="card lg:col-span-2 self-start">
                    <h2 class="section-title flex items-center gap-xs"><span class="material-symbols-outlined" style="font-size:18px">add_circle</span> Ajouter un stage</h2>
                    <form @submit.prevent="submitStage" class="space-y-md">
                        <div>
                            <label class="form-label">Entreprise d'accueil *</label>
                            <input v-model="stageForm.internship_company" type="text" class="form-input" placeholder="Ex: Orange BF" required />
                        </div>
                        <div class="grid grid-cols-2 gap-md">
                            <div>
                                <label class="form-label">Date de début *</label>
                                <input v-model="stageForm.internship_start_date" type="date" class="form-input" required />
                            </div>
                            <div>
                                <label class="form-label">Date de fin</label>
                                <input v-model="stageForm.internship_end_date" type="date" class="form-input" />
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Stage rémunéré *</label>
                            <select v-model="stageForm.internship_paid" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="yes">Oui</option>
                                <option value="no">Non</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Type de convention</label>
                            <input v-model="stageForm.internship_contract_type" type="text" class="form-input" placeholder="Ex: Convention de stage" />
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea v-model="stageForm.status_notes" rows="2" class="form-textarea" placeholder="Missions, contexte..."></textarea>
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer le stage</button>
                    </form>
                </div>

                <div class="lg:col-span-3 space-y-md">
                    <div v-if="stageRecords.length === 0" class="empty-state">
                        <span class="material-symbols-outlined" style="font-size:48px;color:#cbd5e1">badge</span>
                        <p class="text-secondary mt-sm">Aucun stage enregistré pour le moment.</p>
                    </div>
                    <div v-for="record in stageRecords" :key="record.id" class="entry-card stage-card">
                        <div class="flex items-start justify-between gap-md">
                            <div class="flex-1">
                                <div class="entry-company"><span class="material-symbols-outlined" style="font-size:20px;color:#3b82f6">business</span>{{ record.internship_company }}</div>
                                <div class="text-body-sm text-secondary mt-xs flex items-center gap-xs">
                                    <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                                    Du {{ fmt(record.internship_start_date) }}
                                    <template v-if="record.internship_end_date"> au {{ fmt(record.internship_end_date) }}</template>
                                    <template v-else> (en cours)</template>
                                </div>
                                <div class="flex items-center gap-sm mt-md flex-wrap">
                                    <span v-if="record.internship_paid === true" class="chip chip-success"><span class="material-symbols-outlined" style="font-size:14px">payments</span>Rémunéré</span>
                                    <span v-else-if="record.internship_paid === false" class="chip chip-neutral">Non rémunéré</span>
                                    <span v-if="record.internship_contract_type" class="chip chip-neutral">{{ record.internship_contract_type }}</span>
                                </div>
                                <p v-if="record.status_notes" class="mt-md text-body-sm text-secondary italic">"{{ record.status_notes }}"</p>
                            </div>
                            <button @click="deleteRecord(record.id)" class="icon-btn danger" title="Supprimer"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Insertion -->
            <div v-if="activeTab === 'insertion'" class="space-y-lg">
                <!-- Statut rapide -->
                <div class="card">
                    <h2 class="section-title flex items-center gap-xs"><span class="material-symbols-outlined" style="font-size:18px">flag</span> Mettre à jour le statut d'insertion</h2>
                    <form @submit.prevent="submitStatus" class="flex flex-wrap items-end gap-md">
                        <div style="flex: 1; min-width: 200px;">
                            <label class="form-label">Statut courant *</label>
                            <select v-model="statusForm.status" class="form-select" required>
                                <option v-for="s in insertionStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>
                        <div style="flex: 2; min-width: 240px;">
                            <label class="form-label">Note (optionnel)</label>
                            <input v-model="statusForm.status_notes" type="text" class="form-input" placeholder="Précision..." />
                        </div>
                        <button type="submit" class="btn-primary"><span class="material-symbols-outlined" style="font-size:18px">check</span>Valider</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-lg">
                    <div class="card lg:col-span-2 self-start">
                        <h2 class="section-title flex items-center gap-xs"><span class="material-symbols-outlined" style="font-size:18px">add_circle</span> Ajouter un emploi</h2>
                        <form @submit.prevent="submitEmployment" class="space-y-md">
                            <div>
                                <label class="form-label">Entreprise employeuse *</label>
                                <input v-model="employmentForm.employment_company" type="text" class="form-input" placeholder="Ex: Onatel" required />
                            </div>
                            <div>
                                <label class="form-label">Poste / Intitulé</label>
                                <input v-model="employmentForm.employment_position" type="text" class="form-input" placeholder="Ex: Développeur web" />
                            </div>
                            <div>
                                <label class="form-label">Date de début *</label>
                                <input v-model="employmentForm.employment_start_date" type="date" class="form-input" required />
                            </div>
                            <div>
                                <label class="form-label">Type de contrat</label>
                                <select v-model="employmentForm.employment_contract_type" class="form-select">
                                    <option value="">Sélectionner...</option>
                                    <option v-for="c in contractTypeOptions" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea v-model="employmentForm.status_notes" rows="2" class="form-textarea"></textarea>
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center"><span class="material-symbols-outlined" style="font-size:18px">save</span>Enregistrer l'emploi</button>
                        </form>
                    </div>

                    <div class="lg:col-span-3 space-y-md">
                        <div v-if="employmentRecords.length === 0" class="empty-state">
                            <span class="material-symbols-outlined" style="font-size:48px;color:#cbd5e1">work</span>
                            <p class="text-secondary mt-sm">Aucun emploi enregistré.</p>
                        </div>
                        <div v-for="record in employmentRecords" :key="record.id" class="entry-card employment-card">
                            <div class="flex items-start justify-between gap-md">
                                <div class="flex-1">
                                    <div class="entry-company" style="color:#22c55e"><span class="material-symbols-outlined" style="font-size:20px">work</span>{{ record.employment_company }}</div>
                                    <div v-if="record.employment_position" class="font-medium mt-xs">{{ record.employment_position }}</div>
                                    <div class="text-body-sm text-secondary mt-xs flex items-center gap-xs">
                                        <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                                        Depuis le {{ fmt(record.employment_start_date) }}
                                    </div>
                                    <div class="flex items-center gap-sm mt-md flex-wrap">
                                        <span v-if="record.employment_contract_type" class="chip chip-success">{{ record.employment_contract_type }}</span>
                                    </div>
                                    <p v-if="record.status_notes" class="mt-md text-body-sm text-secondary italic">"{{ record.status_notes }}"</p>
                                </div>
                                <button @click="deleteRecord(record.id)" class="icon-btn danger" title="Supprimer"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historique des changements de statut -->
                <div class="card" v-if="insertionRecords.length > 0">
                    <h2 class="section-title flex items-center gap-xs"><span class="material-symbols-outlined" style="font-size:18px">history</span> Historique du parcours</h2>
                    <div class="timeline">
                        <div v-for="record in insertionRecords" :key="record.id" class="timeline-item">
                            <span class="timeline-dot" :style="{ background: insertionStatusColors[record.status] }"></span>
                            <div class="timeline-content">
                                <div class="flex items-center gap-sm">
                                    <span class="font-semibold">{{ insertionStatusLabels[record.status] ?? record.status }}</span>
                                    <span class="text-body-xs text-secondary">{{ fmt(record.status_changed_at ?? record.created_at) }}</span>
                                </div>
                                <div v-if="record.internship_company" class="text-body-sm text-secondary mt-xs">Stage chez {{ record.internship_company }}</div>
                                <div v-if="record.employment_company" class="text-body-sm text-secondary mt-xs">Emploi chez {{ record.employment_company }}<template v-if="record.employment_position"> — {{ record.employment_position }}</template></div>
                                <p v-if="record.status_notes" class="text-body-xs text-secondary italic mt-xs">"{{ record.status_notes }}"</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; color: #515f74; transition: background 0.15s; flex-shrink: 0; }
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
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #515f74; border-radius: 8px; font-size: 13px; font-weight: 500; border: 1px solid #e0e3e5; transition: background 0.15s; text-decoration: none; }
.btn-secondary:hover { background: #f2f4f6; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #E5004C; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: background 0.15s; }
.btn-primary:hover { background: #c4003f; }
.count-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 6px; background: #f2f4f6; border-radius: 99px; font-size: 12px; font-weight: 600; color: #515f74; }
.enrollment-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
.enrollment-in_progress { background: #d1fae5; color: #065f46; }
.enrollment-completed { background: #dbeafe; color: #1e40af; }
.enrollment-withdrawn { background: #fee2e2; color: #991b1b; }
.enrollment-moved { background: #fef3c7; color: #92400e; }

/* Tabs */
.tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; font-size: 14px; font-weight: 500; color: #515f74; background: transparent; border: none; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.15s; }
.tab-btn:hover { color: #191c1e; background: #f9fafb; }
.tab-active { color: #E5004C; border-bottom-color: #E5004C; background: #fff5f8; }
.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; background: #f2f4f6; border-radius: 99px; font-size: 11px; font-weight: 600; color: #515f74; margin-left: 6px; }
.tab-active .tab-badge { background: #E5004C; color: #fff; }

/* Form */
.form-label { display: block; font-size: 13px; font-weight: 600; color: #515f74; margin-bottom: 6px; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; color: #191c1e; background: #fafafa; outline: none; transition: all 0.15s; }
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08); }
.form-textarea { resize: vertical; min-height: 80px; }
.form-checkbox { width: 18px; height: 18px; accent-color: #E5004C; cursor: pointer; }

/* Insertion */
.status-badge-large { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 99px; font-size: 14px; font-weight: 600; border: 1px solid; }
.status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.insertion-history-item { padding: 16px; background: #fafafa; border: 1px solid #f0f0f0; border-radius: 10px; }
.insertion-history-item:hover { background: #f5f5f5; }
.icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: none; background: transparent; color: #515f74; cursor: pointer; transition: all 0.15s; }
.icon-btn:hover { background: #f2f4f6; color: #191c1e; }
.icon-btn.danger { color: #dc2626; }
.icon-btn.danger:hover { background: #fee2e2; }
.text-h3 { font-size: 16px; }

/* === Layout === */
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
.text-body-xs { font-size: 11px; }
.text-body-sm { font-size: 13px; }
.text-body-md { font-size: 14px; }
.text-base { font-size: 15px; }

/* Status banner */
.status-banner { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-radius: 12px; border-left: 4px solid; }
.status-dot-lg { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 4px rgba(255,255,255,0.6); }

/* Tabs bar */
.tabs-bar { display: flex; gap: 0; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 4px; overflow-x: auto; }
.tabs-bar .tab-btn { border-radius: 8px; border: none; padding: 10px 18px; }
.tabs-bar .tab-btn:hover { background: #f9fafb; }
.tabs-bar .tab-active { background: #fff5f8; color: #E5004C; border: none; }

.tab-content { display: flex; flex-direction: column; gap: 20px; }

/* Formation rows */
.formation-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
.formation-row:last-child { border-bottom: none; }
.formation-row:hover { background: #fafbfc; }
.formation-name { font-weight: 600; color: #191c1e; text-decoration: none; }
.formation-name:hover { color: #E5004C; }
.link { color: #E5004C; text-decoration: none; font-weight: 500; }
.link:hover { text-decoration: underline; }

/* Entry cards (stage / employment) */
.entry-card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 20px; transition: all 0.15s; }
.entry-card:hover { border-color: #cbd5e1; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.stage-card { border-left: 3px solid #3b82f6; }
.employment-card { border-left: 3px solid #22c55e; }
.entry-company { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; color: #3b82f6; }

/* Chips */
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.chip-success { background: #d1fae5; color: #065f46; }
.chip-neutral { background: #f2f4f6; color: #515f74; }

/* Empty state */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; background: #fafbfc; border: 1px dashed #e0e3e5; border-radius: 12px; text-align: center; }

/* Timeline */
.timeline { display: flex; flex-direction: column; gap: 0; position: relative; padding-left: 8px; }
.timeline-item { display: flex; gap: 14px; padding-bottom: 18px; position: relative; }
.timeline-item:not(:last-child)::before { content: ''; position: absolute; left: 5px; top: 18px; bottom: 0; width: 2px; background: #e5e7eb; }
.timeline-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; box-shadow: 0 0 0 3px #fff, 0 0 0 4px #e5e7eb; z-index: 1; }
.timeline-content { flex: 1; padding-bottom: 4px; }

/* Utility */
.gap-xs { gap: 4px; }
.gap-sm { gap: 8px; }
.gap-md { gap: 16px; }
.gap-lg { gap: 24px; }
.mb-md { margin-bottom: 16px; }
.mt-xs { margin-top: 4px; }
.mt-sm { margin-top: 8px; }
.mt-md { margin-top: 16px; }
.mx-xs { margin-left: 4px; margin-right: 4px; }
.ml-sm { margin-left: 8px; }
.px-lg { padding-left: 20px; padding-right: 20px; }
.py-md { padding-top: 12px; padding-bottom: 12px; }
.py-xl { padding-top: 32px; padding-bottom: 32px; }
.space-y-md > * + * { margin-top: 16px; }
.space-y-lg > * + * { margin-top: 24px; }
.space-y-xl > * + * { margin-top: 32px; }
.flex { display: flex; }
.flex-1 { flex: 1; }
.flex-wrap { flex-wrap: wrap; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.items-end { align-items: flex-end; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.self-start { align-self: flex-start; }
.w-full { width: 100%; }
.text-center { text-align: center; }
.font-semibold { font-weight: 600; }
.font-medium { font-weight: 500; }
.italic { font-style: italic; }
.grid { display: grid; }
.grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
@media (min-width: 768px) {
    .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1024px) {
    .lg\:grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .lg\:col-span-2 { grid-column: span 2 / span 2; }
    .lg\:col-span-3 { grid-column: span 3 / span 3; }
}
</style>
