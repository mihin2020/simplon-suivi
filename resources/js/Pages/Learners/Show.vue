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

const props = defineProps<{
    learner: Learner
}>()

const activeTab = ref<'info' | 'formations'>('info')

const fmt = (d: string | null) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : ''
const genderLabel = (g: string | null) => ({ male: 'Masculin', female: 'Féminin' }[g ?? ''] ?? '')
const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

const enrollmentStatusLabels: Record<string, string> = {
    in_progress: 'En cours', withdrawn: 'Retiré', completed: 'Terminé', moved: 'Déplacé'
}

const currentFormation = computed(() => props.learner.formations.find(f => f.pivot.status === 'in_progress') ?? props.learner.formations[0] ?? null)
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
        </div>

        <div class="tab-content">
            <!-- Tab: Profil -->
            <div v-if="activeTab === 'info'" class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                <div class="card">
                    <h2 class="section-title">Informations personnelles</h2>
                    <dl class="info-list">
                        <div class="info-row"><dt>Email</dt><dd>{{ learner.email ?? '' }}</dd></div>
                        <div class="info-row"><dt>Téléphone</dt><dd>{{ learner.phone ?? '' }}</dd></div>
                        <div class="info-row"><dt>Date de naissance</dt><dd>{{ fmt(learner.birth_date) }}</dd></div>
                        <div class="info-row"><dt>Lieu de naissance</dt><dd>{{ learner.birth_place ?? '' }}</dd></div>
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
        </div>
    </div>
</template>

<style scoped>
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
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: transparent; color: #515f74; border-radius: 8px; font-size: 13px; font-weight: 500; border: 1px solid #e0e3e5; transition: background 0.15s; text-decoration: none; }
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
</style>
