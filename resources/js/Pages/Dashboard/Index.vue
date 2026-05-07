<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Stats {
    projects:        { total: number; active: number }
    formations:      { total: number; active: number }
    learners:        { total: number; active: number }
    trainers:        { total: number }
    insertion_rate:  number
}

interface ActiveFormation {
    id: string
    name: string
    project_name: string
    active_learners_count: number
    started_at: string | null
    ended_at: string | null
}

interface RecentEnrollment {
    id: string
    first_name: string
    last_name: string
    photo_path: string | null
    formation_name: string
    enrolled_at: string
}

interface TrainerFormation {
    id: string
    name: string
    project_name: string
    status: string
    active_learners_count: number
    attendances_today_count: number
    started_at: string | null
    ended_at: string | null
    is_lead: boolean
}

interface TrainerStats {
    formations: number
    learners: number
    attendances_today: number
}

const props = defineProps<{
    role: 'admin' | 'trainer'
    // Admin props
    stats?: Stats
    activeFormations?: ActiveFormation[]
    recentEnrollments?: RecentEnrollment[]
    // Trainer props
    trainer?: { id: string; full_name: string; speciality: string | null } | null
    myFormations?: TrainerFormation[]
    trainerStats?: TrainerStats
}>()

const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

const fmtDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

const statusLabel: Record<string, string> = {
    active: 'Active',
    upcoming: 'À venir',
    completed: 'Terminée',
    cancelled: 'Annulée',
}
const statusClass: Record<string, string> = {
    active: 'badge-active',
    upcoming: 'badge-upcoming',
    completed: 'badge-completed',
    cancelled: 'badge-cancelled',
}
</script>

<template>
    <Head title="Tableau de Bord" />

    <!-- ===== VUE ADMIN / SUPER ADMIN ===== -->
    <div v-if="role === 'admin'" class="max-w-[1600px] mx-auto space-y-xl">

        <div>
            <h1 class="text-h1 font-bold text-on-surface">Tableau de bord</h1>
            <p class="text-body-md text-secondary mt-xs">Vue d'ensemble de l'activité Simplon Burkina Faso.</p>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-md">
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">folder_open</span>
                <div class="kpi-value">{{ stats!.projects.total }}</div>
                <div class="kpi-label">Projets</div>
                <div class="kpi-sub">{{ stats!.projects.active }} en cours</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">school</span>
                <div class="kpi-value">{{ stats!.formations.total }}</div>
                <div class="kpi-label">Formations</div>
                <div class="kpi-sub">{{ stats!.formations.active }} actives</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">groups</span>
                <div class="kpi-value">{{ stats!.learners.total.toLocaleString('fr-FR') }}</div>
                <div class="kpi-label">Apprenants</div>
                <div class="kpi-sub">{{ stats!.learners.active }} actifs</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">person_pin</span>
                <div class="kpi-value">{{ stats!.trainers.total }}</div>
                <div class="kpi-label">Formateurs</div>
                <div class="kpi-sub">&nbsp;</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">trending_up</span>
                <div class="kpi-value">{{ stats!.insertion_rate }}<span style="font-size:18px">%</span></div>
                <div class="kpi-label">Taux d'insertion</div>
                <div class="kpi-sub">Promo en cours</div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">
            <!-- Formations actives -->
            <div class="lg:col-span-2 bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                    <h2 class="text-h2 font-semibold text-on-surface">Formations actives</h2>
                    <span class="count-badge">{{ stats!.formations.active }}</span>
                </div>
                <div v-if="!activeFormations?.length" class="px-lg py-xl text-center text-secondary text-body-md">
                    Aucune formation active pour le moment.
                </div>
                <div class="divide-y divide-surface-container-highest">
                    <div v-for="f in activeFormations" :key="f.id"
                        class="px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors">
                        <div class="flex-1 min-w-0">
                            <Link :href="`/formations/${f.id}`"
                                class="font-semibold text-on-surface hover:text-primary transition-colors block truncate">
                                {{ f.name }}
                            </Link>
                            <div class="flex items-center gap-sm mt-xs text-body-sm text-secondary">
                                <span class="material-symbols-outlined" style="font-size:14px">folder_open</span>
                                {{ f.project_name }}
                                <span class="text-surface-container-highest">·</span>
                                <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                                {{ f.started_at }} → {{ f.ended_at ?? 'En cours' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-sm flex-shrink-0">
                            <div class="learner-count">
                                <span class="material-symbols-outlined" style="font-size:15px">groups</span>
                                {{ f.active_learners_count }}
                            </div>
                            <Link :href="`/formations/${f.id}/attendances`" class="icon-action" title="Présences">
                                <span class="material-symbols-outlined" style="font-size:18px">fact_check</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inscriptions récentes -->
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                <div class="px-lg py-md border-b border-surface-container-highest">
                    <h2 class="text-h2 font-semibold text-on-surface">Inscriptions récentes</h2>
                </div>
                <div v-if="!recentEnrollments?.length" class="px-lg py-xl text-center text-secondary text-body-md">
                    Aucune inscription récente.
                </div>
                <div class="divide-y divide-surface-container-highest">
                    <div v-for="e in recentEnrollments" :key="e.id + e.enrolled_at"
                        class="px-lg py-sm flex items-center gap-sm hover:bg-surface-bright transition-colors">
                        <div class="avatar-sm">
                            <img v-if="photoUrl(e.photo_path)" :src="photoUrl(e.photo_path)!" alt="" class="avatar-img" />
                            <template v-else>{{ e.first_name.charAt(0) }}{{ e.last_name.charAt(0) }}</template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <Link :href="`/learners/${e.id}`"
                                class="font-semibold text-on-surface hover:text-primary text-body-sm block truncate">
                                {{ e.last_name }} {{ e.first_name }}
                            </Link>
                            <p class="text-body-sm text-secondary truncate">{{ e.formation_name }}</p>
                        </div>
                        <span class="text-body-sm text-secondary whitespace-nowrap">{{ fmtDate(e.enrolled_at) }}</span>
                    </div>
                </div>
                <div class="px-lg py-sm border-t border-surface-container-highest">
                    <Link href="/learners" class="text-body-sm text-primary font-semibold hover:underline">
                        Voir tous les apprenants →
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== VUE FORMATEUR ===== -->
    <div v-else class="max-w-4xl mx-auto space-y-xl">

        <div>
            <h1 class="text-h1 font-bold text-on-surface">Bonjour, {{ trainer?.full_name ?? 'Formateur' }} 👋</h1>
            <p class="text-body-md text-secondary mt-xs">
                {{ trainer?.speciality ? `Spécialité : ${trainer.speciality}` : 'Voici un résumé de vos formations.' }}
            </p>
        </div>

        <!-- KPI formateur -->
        <div class="grid grid-cols-3 gap-md">
            <div class="kpi-card kpi-accent">
                <span class="kpi-icon material-symbols-outlined">school</span>
                <div class="kpi-value">{{ trainerStats!.formations }}</div>
                <div class="kpi-label">Mes formations</div>
                <div class="kpi-sub">&nbsp;</div>
            </div>
            <div class="kpi-card kpi-primary">
                <span class="kpi-icon material-symbols-outlined">groups</span>
                <div class="kpi-value">{{ trainerStats!.learners }}</div>
                <div class="kpi-label">Apprenants actifs</div>
                <div class="kpi-sub">&nbsp;</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">fact_check</span>
                <div class="kpi-value">{{ trainerStats!.attendances_today }}</div>
                <div class="kpi-label">Présences aujourd'hui</div>
                <div class="kpi-sub">&nbsp;</div>
            </div>
        </div>

        <!-- Pas de profil lié -->
        <div v-if="!trainer" class="bg-amber-50 border border-amber-200 rounded-xl p-lg flex items-start gap-md">
            <span class="material-symbols-outlined text-amber-600" style="font-size:22px">warning</span>
            <div>
                <p class="font-semibold text-amber-900">Profil formateur non configuré</p>
                <p class="text-body-sm text-amber-800 mt-xs">
                    Votre compte n'est pas encore lié à un profil formateur. Contactez l'administrateur.
                </p>
            </div>
        </div>

        <!-- Mes formations -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                <h2 class="text-h2 font-semibold text-on-surface">Mes formations</h2>
                <span class="count-badge">{{ myFormations?.length ?? 0 }}</span>
            </div>

            <div v-if="!myFormations?.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucune formation assignée pour le moment.
            </div>

            <div class="divide-y divide-surface-container-highest">
                <div v-for="f in myFormations" :key="f.id"
                    class="px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-sm">
                            <span class="font-semibold text-on-surface truncate">{{ f.name }}</span>
                            <span v-if="f.is_lead" class="lead-badge">Référent</span>
                        </div>
                        <div class="flex items-center gap-sm mt-xs text-body-sm text-secondary">
                            <span class="material-symbols-outlined" style="font-size:14px">folder_open</span>
                            {{ f.project_name }}
                            <span>·</span>
                            <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                            {{ f.started_at }} → {{ f.ended_at ?? 'En cours' }}
                        </div>
                    </div>
                    <div class="flex items-center gap-sm flex-shrink-0">
                        <span :class="['status-badge', statusClass[f.status] ?? '']">
                            {{ statusLabel[f.status] ?? f.status }}
                        </span>
                        <div class="learner-count" title="Apprenants actifs">
                            <span class="material-symbols-outlined" style="font-size:14px">groups</span>
                            {{ f.active_learners_count }}
                        </div>
                        <div v-if="f.attendances_today_count > 0" class="attendance-today" title="Présences saisies aujourd'hui">
                            <span class="material-symbols-outlined" style="font-size:14px">fact_check</span>
                            {{ f.attendances_today_count }}
                        </div>
                        <Link :href="`/formations/${f.id}/attendances`" class="btn-attendance">
                            <span class="material-symbols-outlined" style="font-size:16px">edit_note</span>
                            Présences
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* KPI Cards */
.kpi-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border-left: 4px solid #e0e3e5;
}
.kpi-icon {
    font-size: 22px;
    color: #9aaabb;
    margin-bottom: 4px;
}

.kpi-value {
    font-size: 32px;
    font-weight: 700;
    color: #191c1e;
    line-height: 1;
}
.kpi-label {
    font-size: 11px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 4px;
}
.kpi-sub {
    font-size: 12px;
    color: #9aaabb;
    margin-top: 2px;
}

/* Badges & Elements */
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

.learner-count {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: #f2f4f6;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}

.icon-action {
    display: inline-flex;
    padding: 4px;
    color: #9aaabb;
    border-radius: 4px;
    transition: color 0.15s;
}
.icon-action:hover { color: #E5004C; }

/* Avatar */
.avatar-sm {
    width: 36px;
    height: 36px;
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
    overflow: hidden;
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; }

/* Trainer — status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.badge-active    { background: #dcfce7; color: #166534; }
.badge-upcoming  { background: #dbeafe; color: #1e40af; }
.badge-completed { background: #f3f4f6; color: #374151; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

/* Lead badge */
.lead-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 8px;
    background: #fff5f8;
    border: 1px solid #fecdd3;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    color: #E5004C;
    white-space: nowrap;
}

/* Attendance today badge */
.attendance-today {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: #dcfce7;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #166534;
}

/* Btn attendance */
.btn-attendance {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s;
    white-space: nowrap;
}
.btn-attendance:hover { background: #c4003f; }
</style>
