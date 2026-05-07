<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface EducationLevel {
    name: string
}

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

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : '—'

const genderLabel = (g: string | null) => ({ male: 'Masculin', female: 'Féminin' }[g ?? ''] ?? '—')
const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

const enrollmentStatusLabels: Record<string, string> = {
    in_progress: 'En cours',
    withdrawn:   'Retiré',
    completed:   'Terminé',
    moved:       'Déplacé',
}
</script>

<template>
    <div class="max-w-[1400px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <Link href="/learners" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="flex items-center gap-md">
                    <div class="avatar-lg">
                        <img v-if="photoUrl(learner.photo_path)" :src="photoUrl(learner.photo_path)!" alt="" class="avatar-img" />
                        <template v-else>{{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}</template>
                    </div>
                    <div>
                        <h1 class="text-h1 font-bold text-on-surface">{{ learner.last_name }} {{ learner.first_name }}</h1>
                        <p class="text-body-md text-secondary mt-xs">
                            {{ genderLabel(learner.gender) }}
                            <template v-if="learner.education_level"> · {{ learner.education_level.name }}</template>
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <Link :href="`/learners/${learner.id}/edit`" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Modifier
                </Link>
                <Link :href="`/learners/${learner.id}/move`" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">swap_horiz</span>
                    Déplacer
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">

            <!-- Infos personnelles -->
            <div class="space-y-xl">
                <div class="card">
                    <h2 class="section-title">Informations personnelles</h2>
                    <dl class="info-list">
                        <div class="info-row">
                            <dt>Email</dt>
                            <dd>{{ learner.email ?? '—' }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Téléphone</dt>
                            <dd>{{ learner.phone ?? '—' }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Date de naissance</dt>
                            <dd>{{ fmt(learner.birth_date) }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Lieu de naissance</dt>
                            <dd>{{ learner.birth_place ?? '—' }}</dd>
                        </div>
                        <div v-if="learner.talent" class="info-row">
                            <dt>Talent</dt>
                            <dd>{{ learner.talent }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card" v-if="learner.emergency_contact_name || learner.emergency_contact_phone">
                    <h2 class="section-title">Contact d'urgence</h2>
                    <dl class="info-list">
                        <div class="info-row" v-if="learner.emergency_contact_name">
                            <dt>Nom de famille</dt>
                            <dd>{{ learner.emergency_contact_name }}</dd>
                        </div>
                        <div class="info-row" v-if="learner.emergency_contact_firstname">
                            <dt>Prénom</dt>
                            <dd>{{ learner.emergency_contact_firstname }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Téléphone</dt>
                            <dd>
                                <template v-if="learner.emergency_contact_phone">
                                    <a
                                        :href="`https://wa.me/${learner.emergency_contact_phone.replace(/\D/g,'')}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="whatsapp-link"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:16px">chat</span>
                                        {{ learner.emergency_contact_phone }}
                                    </a>
                                </template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="card" v-if="learner.cnib_path">
                    <h2 class="section-title">Documents</h2>
                    <dl class="info-list">
                        <div class="info-row">
                            <dt>CNIB / Pièce d'identité</dt>
                            <dd>
                                <a :href="`/storage/${learner.cnib_path}`" target="_blank" rel="noopener" class="doc-link">
                                    <span class="material-symbols-outlined" style="font-size:16px">description</span>
                                    Voir le document
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Formations -->
            <div class="lg:col-span-2">
                <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                    <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                        <h2 class="text-h2 font-semibold text-on-surface">
                            Parcours de formation
                            <span class="count-badge ml-sm">{{ learner.formations.length }}</span>
                        </h2>
                    </div>
                    <div class="divide-y divide-surface-container-highest">
                        <div v-if="learner.formations.length === 0" class="px-lg py-xl text-center text-secondary text-body-md">
                            Cet apprenant n'est inscrit dans aucune formation.
                        </div>
                        <div
                            v-for="formation in learner.formations"
                            :key="formation.id"
                            class="px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors"
                        >
                            <div>
                                <Link
                                    :href="`/formations/${formation.id}`"
                                    class="font-semibold text-on-surface hover:text-primary transition-colors"
                                >
                                    {{ formation.name }}
                                </Link>
                                <div class="flex items-center gap-sm mt-xs text-body-sm text-secondary">
                                    <Link :href="`/projects/${formation.project.id}`" class="hover:text-primary">
                                        {{ formation.project.name }}
                                    </Link>
                                    <span>·</span>
                                    <span>Inscrit le {{ fmt(formation.pivot.enrolled_at) }}</span>
                                </div>
                            </div>
                            <span class="enrollment-badge" :class="`enrollment-${formation.pivot.status}`">
                                {{ enrollmentStatusLabels[formation.pivot.status] ?? formation.pivot.status }}
                            </span>
                        </div>
                    </div>
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
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.avatar-lg {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #E5004C;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
    overflow: hidden;
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }

.section-title {
    font-size: 11px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 16px;
}

.info-list { display: flex; flex-direction: column; gap: 12px; }
.info-row { display: flex; flex-direction: column; gap: 2px; }
.info-row dt { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.info-row dd { font-size: 14px; color: #191c1e; }

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

.enrollment-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.enrollment-in_progress { background: #d1fae5; color: #065f46; }
.enrollment-completed   { background: #dbeafe; color: #1e40af; }
.enrollment-withdrawn   { background: #fee2e2; color: #991b1b; }
.enrollment-moved       { background: #fef3c7; color: #92400e; }

.whatsapp-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #15803d;
    text-decoration: none;
    font-weight: 500;
}
.whatsapp-link:hover { text-decoration: underline; }

.doc-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #1d4ed8;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
}
.doc-link:hover { text-decoration: underline; }

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
</style>
