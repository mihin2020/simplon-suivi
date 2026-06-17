<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
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

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedActiveFormations {
    data: ActiveFormation[]
    current_page: number
    last_page: number
    from: number
    to: number
    total: number
    links: PaginationLink[]
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
    needs_attendance_today: boolean
    ending_soon: boolean
    started_at: string | null
    ended_at: string | null
    is_lead: boolean
}

interface TrainerStats {
    formations: number
    learners: number
    attendances_today: number
}

interface RecentAbsence {
    id: string
    first_name: string
    last_name: string
    formation_name: string
    absences_count: number
}

interface DistItem {
    key: string
    label: string
    count: number
}

const props = defineProps<{
    role: 'admin' | 'trainer'
    // Admin props
    stats?: Stats
    learnerStatus?: DistItem[]
    insertion?: DistItem[]
    insertionTracked?: number
    gender?: { male: number; female: number }
    activeFormations?: PaginatedActiveFormations
    recentEnrollments?: RecentEnrollment[]
    // Trainer props
    trainer?: { id: string; full_name: string; speciality: string | null } | null
    myFormations?: TrainerFormation[]
    trainerStats?: TrainerStats
    recentAbsences?: RecentAbsence[]
}>()

// ── Couleurs par catégorie ──
const statusColors: Record<string, string> = {
    in_progress: '#1F3A4D',
    completed:   '#16a34a',
    withdrawn:   '#ef4444',
    moved:       '#f59e0b',
}
const insertionColors: Record<string, string> = {
    employed:   '#16a34a',
    internship: '#1F3A4D',
    searching:  '#f59e0b',
    unemployed: '#94a3b8',
}

// ── Helper donut : construit le conic-gradient + légende avec % ──
function buildDonut(items: DistItem[] | undefined, colors: Record<string, string>) {
    const data = items ?? []
    const total = data.reduce((s, i) => s + i.count, 0)
    let acc = 0
    const segments: string[] = []
    const legend = data.map((i) => {
        const pct = total > 0 ? (i.count / total) * 100 : 0
        const start = acc
        acc += pct
        if (i.count > 0) {
            segments.push(`${colors[i.key] ?? '#cbd5e1'} ${start}% ${acc}%`)
        }
        return {
            ...i,
            color: colors[i.key] ?? '#cbd5e1',
            pct: total > 0 ? Math.round(pct) : 0,
        }
    })
    return {
        total,
        gradient: segments.length ? `conic-gradient(${segments.join(', ')})` : 'conic-gradient(#eef1f4 0% 100%)',
        legend,
    }
}

const statusDonut    = computed(() => buildDonut(props.learnerStatus, statusColors))
const insertionDonut = computed(() => buildDonut(props.insertion, insertionColors))

// ── Parité ──
const genderTotal = computed(() => (props.gender?.male ?? 0) + (props.gender?.female ?? 0))
const femalePct = computed(() =>
    genderTotal.value > 0 ? Math.round((props.gender!.female / genderTotal.value) * 100) : 0
)
const malePct = computed(() => genderTotal.value > 0 ? 100 - femalePct.value : 0)

const photoUrl = (path: string | null) => path ? `/storage/${path}` : null

const fmtDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

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

        <!-- Graphiques analytiques -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-md">

            <!-- Statut des apprenants -->
            <div class="chart-card">
                <div class="chart-head">
                    <h2 class="chart-title">Parcours des apprenants</h2>
                    <span class="material-symbols-outlined chart-head-icon">school</span>
                </div>
                <div v-if="statusDonut.total === 0" class="chart-empty">Aucune donnée disponible.</div>
                <div v-else class="donut-row">
                    <div class="donut" :style="{ background: statusDonut.gradient }">
                        <div class="donut-hole">
                            <span class="donut-total">{{ statusDonut.total }}</span>
                            <span class="donut-cap">apprenants</span>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div v-for="l in statusDonut.legend" :key="l.key" class="legend-row">
                            <span class="legend-dot" :style="{ background: l.color }"></span>
                            <span class="legend-label">{{ l.label }}</span>
                            <span class="legend-val">{{ l.count }}</span>
                            <span class="legend-pct">{{ l.pct }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insertion professionnelle -->
            <div class="chart-card">
                <div class="chart-head">
                    <h2 class="chart-title">Insertion professionnelle</h2>
                    <span class="material-symbols-outlined chart-head-icon">work</span>
                </div>
                <div v-if="(insertionTracked ?? 0) === 0" class="chart-empty">
                    Aucun suivi d'insertion enregistré.
                </div>
                <div v-else class="donut-row">
                    <div class="donut" :style="{ background: insertionDonut.gradient }">
                        <div class="donut-hole">
                            <span class="donut-total">{{ stats!.insertion_rate }}<small>%</small></span>
                            <span class="donut-cap">insérés</span>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div v-for="l in insertionDonut.legend" :key="l.key" class="legend-row">
                            <span class="legend-dot" :style="{ background: l.color }"></span>
                            <span class="legend-label">{{ l.label }}</span>
                            <span class="legend-val">{{ l.count }}</span>
                            <span class="legend-pct">{{ l.pct }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parité -->
            <div class="chart-card">
                <div class="chart-head">
                    <h2 class="chart-title">Parité Hommes / Femmes</h2>
                    <span class="material-symbols-outlined chart-head-icon">diversity_3</span>
                </div>
                <div v-if="genderTotal === 0" class="chart-empty">Aucune donnée disponible.</div>
                <div v-else class="parity-wrap">
                    <div class="parity-bar">
                        <div class="parity-seg parity-female" :style="{ width: femalePct + '%' }"></div>
                        <div class="parity-seg parity-male" :style="{ width: malePct + '%' }"></div>
                    </div>
                    <div class="parity-legend">
                        <div class="parity-item">
                            <span class="legend-dot" style="background:#E5004C"></span>
                            <div>
                                <div class="parity-num">{{ gender!.female }} <span class="parity-pct">· {{ femalePct }}%</span></div>
                                <div class="parity-lab">Femmes</div>
                            </div>
                        </div>
                        <div class="parity-item">
                            <span class="legend-dot" style="background:#1F3A4D"></span>
                            <div>
                                <div class="parity-num">{{ gender!.male }} <span class="parity-pct">· {{ malePct }}%</span></div>
                                <div class="parity-lab">Hommes</div>
                            </div>
                        </div>
                    </div>
                </div>
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
                <div v-if="!activeFormations?.data?.length" class="px-lg py-xl text-center text-secondary text-body-md">
                    Aucune formation active pour le moment.
                </div>
                <div class="divide-y divide-surface-container-highest">
                    <div v-for="f in activeFormations?.data" :key="f.id"
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

                <!-- Pagination -->
                <div v-if="activeFormations && activeFormations.last_page > 1" class="px-lg py-md border-t border-surface-container-highest">
                    <div class="flex items-center justify-between">
                        <span class="text-body-sm text-secondary">
                            {{ activeFormations.from }} - {{ activeFormations.to }} sur {{ activeFormations.total }}
                        </span>
                        <div class="flex items-center gap-xs">
                            <Link
                                v-for="link in activeFormations.links"
                                :key="link.label"
                                :href="link.url ?? '#'"
                                class="px-3 py-1 text-sm rounded-md transition-colors"
                                :class="link.active ? 'bg-primary text-white' : 'bg-surface-bright text-secondary hover:bg-surface-container-highest'"
                                v-html="link.label"
                            />
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
            <h1 class="text-h1 font-bold text-on-surface">Bonjour, {{ trainer?.full_name ?? 'Formateur' }}</h1>
            <p class="text-body-md text-secondary mt-xs">
                {{ trainer?.speciality ? `Spécialité : ${trainer.speciality}` : 'Voici un résumé de vos formations.' }}
            </p>
        </div>

        <!-- KPI formateur -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-md">
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
                            <span v-if="f.ending_soon" class="ending-soon-badge">
                                <span class="material-symbols-outlined" style="font-size:12px">schedule</span>
                                Se termine bientôt
                            </span>
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
                        <span v-else-if="f.needs_attendance_today" class="needs-attendance-badge" title="Présence du jour non saisie">
                            <span class="material-symbols-outlined" style="font-size:14px">warning</span>
                            À saisir
                        </span>
                        <Link :href="`/formations/${f.id}/attendances`" class="btn-attendance">
                            <span class="material-symbols-outlined" style="font-size:16px">edit_note</span>
                            Présences
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Absences répétées récentes -->
        <div v-if="recentAbsences?.length" class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                <h2 class="text-h2 font-semibold text-on-surface">Absences répétées (7 derniers jours)</h2>
                <span class="count-badge">{{ recentAbsences.length }}</span>
            </div>
            <div class="divide-y divide-surface-container-highest">
                <div v-for="a in recentAbsences" :key="a.id"
                    class="px-lg py-sm flex items-center justify-between gap-md">
                    <div class="min-w-0">
                        <span class="font-semibold text-on-surface">{{ a.first_name }} {{ a.last_name }}</span>
                        <span class="text-body-sm text-secondary ml-sm">{{ a.formation_name }}</span>
                    </div>
                    <span class="absence-count-badge">{{ a.absences_count }} absences</span>
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
    transition: box-shadow 0.15s, border-color 0.15s;
}
.kpi-card:hover { border-color: #d0d8e0; box-shadow: 0 4px 14px rgba(31,58,77,0.06); }
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

/* ── Chart cards ── */
.chart-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
}
.chart-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px;
}
.chart-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.chart-head-icon { font-size: 20px; color: #c0cad4; }
.trend-total { font-size: 12px; font-weight: 600; color: #9aaabb; }
.chart-empty { font-size: 13px; color: #9aaabb; padding: 24px 0; text-align: center; }

/* Donut */
.donut-row { display: flex; align-items: center; gap: 18px; }
.donut {
    position: relative; width: 104px; height: 104px;
    border-radius: 50%; flex-shrink: 0;
}
.donut-hole {
    position: absolute; inset: 18px;
    background: #fff; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.donut-total { font-size: 24px; font-weight: 700; color: #191c1e; line-height: 1; }
.donut-total small { font-size: 13px; font-weight: 600; }
.donut-cap { font-size: 10px; color: #9aaabb; margin-top: 2px; }
.donut-legend { flex: 1; display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.legend-row { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.legend-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.legend-label { flex: 1; color: #515f74; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.legend-val { font-weight: 700; color: #191c1e; }
.legend-pct { color: #9aaabb; min-width: 32px; text-align: right; }

/* Parité */
.parity-wrap { display: flex; flex-direction: column; gap: 16px; }
.parity-bar {
    display: flex; height: 16px; border-radius: 99px; overflow: hidden;
    background: #eef1f4;
}
.parity-seg { height: 100%; transition: width 0.4s ease; }
.parity-female { background: #E5004C; }
.parity-male { background: #1F3A4D; }
.parity-legend { display: flex; gap: 24px; }
.parity-item { display: flex; align-items: center; gap: 8px; }
.parity-num { font-size: 16px; font-weight: 700; color: #191c1e; line-height: 1; }
.parity-pct { font-size: 12px; font-weight: 500; color: #9aaabb; }
.parity-lab { font-size: 11px; color: #9aaabb; margin-top: 3px; }

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

/* Trainer · status badges */
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

/* Needs attendance badge */
.needs-attendance-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: #fef3c7;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #92400e;
}

/* Ending soon badge */
.ending-soon-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 8px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    color: #c2410c;
    white-space: nowrap;
}

/* Absence count badge */
.absence-count-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    background: #fee2e2;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #b91c1c;
    white-space: nowrap;
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

/* ── Responsive Dashboard ── */
@media (max-width: 767px) {
    .kpi-card { padding: 14px 16px; }
    .kpi-value { font-size: 24px; }
    .page-header,
    .section-header { flex-wrap: wrap; gap: 10px; }
    .formation-row { flex-wrap: wrap; gap: 8px; }
    .formation-actions { flex-wrap: wrap; gap: 6px; }
    .btn-attendance { font-size: 11px; padding: 5px 10px; }
    .status-badge, .learner-count { font-size: 11px; }
}
@media (min-width: 768px) and (max-width: 1023px) {
    .kpi-card { padding: 16px; }
    .kpi-value { font-size: 28px; }
}
</style>
