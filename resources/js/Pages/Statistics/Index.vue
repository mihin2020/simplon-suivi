<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: AdminLayout })

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
    gender: string | null
    education_level: string | null
    status: string
}

interface FormationStat {
    id: string
    name: string
    status: string
    total_learners: number
    male_count: number
    female_count: number
    in_progress_count: number
    withdrawn_count: number
    completed_count: number
    moved_count: number
    internship_count: number
    employed_count: number
    unemployed_count: number
}

interface ProjectStat {
    id: string
    name: string
    formations: FormationStat[]
    totals: {
        total_learners: number
        male_count: number
        female_count: number
        in_progress_count: number
        withdrawn_count: number
        completed_count: number
        moved_count: number
        internship_count: number
        employed_count: number
        unemployed_count: number
    }
}

interface GlobalStats {
    learners: {
        total: number
        male: number
        female: number
        by_status: {
            in_progress: number
            withdrawn: number
            completed: number
            moved: number
        }
    }
    insertion: {
        internship: number
        employed: number
        unemployed: number
        rate: number
    }
}

const props = defineProps<{
    globalStats: GlobalStats
    projectStats: ProjectStat[]
}>()

const expandedProjects = ref<Record<string, boolean>>({})

const toggleProject = (id: string) => {
    expandedProjects.value[id] = !expandedProjects.value[id]
}

const statusLabel: Record<string, string> = {
    active: 'Active',
    upcoming: '\u00c0 venir',
    completed: 'Termin\u00e9e',
    cancelled: 'Annul\u00e9e',
}

const statusClass: Record<string, string> = {
    active: 'badge-active',
    upcoming: 'badge-upcoming',
    completed: 'badge-completed',
    cancelled: 'badge-cancelled',
}

const pct = (part: number, total: number) =>
    total > 0 ? Math.round((part / total) * 100) : 0

// Modal drill-down
const showLearnersModal = ref(false)
const modalTitle = ref('')
const modalLearners = ref<Learner[]>([])
const modalLoading = ref(false)

const openLearnersModal = async (
    formationId: string,
    formationName: string,
    filterType: 'gender' | 'status' | 'insertion',
    filterValue: string,
    label: string
) => {
    modalLoading.value = true
    showLearnersModal.value = true
    modalTitle.value = `${formationName} - ${label}`
    modalLearners.value = []

    const params = new URLSearchParams()
    if (filterType === 'gender') {
        params.append('gender', filterValue)
    } else if (filterType === 'status') {
        params.append('status', filterValue)
    } else if (filterType === 'insertion') {
        params.append('insertion_status', filterValue)
    }

    try {
        const response = await fetch(`/api/statistics/formation/${formationId}/learners?${params}`)
        const data = await response.json()
        modalLearners.value = data.learners || []
    } catch (error) {
        console.error('Erreur lors du chargement des apprenants:', error)
    } finally {
        modalLoading.value = false
    }
}

const closeLearnersModal = () => {
    showLearnersModal.value = false
    modalTitle.value = ''
    modalLearners.value = []
}

const genderLabel = (g: string | null) => {
    return { female: 'Femme', male: 'Homme' }[g ?? ''] ?? ''
}

const statusLabelLearner: Record<string, string> = {
    in_progress: 'En cours',
    withdrawn: 'Abandonné',
    completed: 'Diplômé',
    moved: 'Transféré',
}

const statusColorLearner: Record<string, string> = {
    in_progress: 'text-green-700',
    withdrawn: 'text-red-700',
    completed: 'text-blue-700',
    moved: 'text-orange-700',
}
</script>

<template>
    <Head title="Statistiques" />

    <div class="max-w-[1600px] mx-auto space-y-xl">
        <div>
            <h1 class="text-h1 font-bold text-on-surface">Statistiques</h1>
            <p class="text-body-md text-secondary mt-xs">Vue d'ensemble des apprenants et de leur insertion.</p>
        </div>

        <!-- ===== STATS PAR PROJET ===== -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest">
                <h2 class="text-h2 font-semibold text-on-surface flex items-center gap-sm">
                    <span class="material-symbols-outlined">folder_open</span>
                    Détails par projet & formation
                </h2>
            </div>

            <div v-if="!projectStats.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucun projet disponible.
            </div>

            <div class="divide-y divide-surface-container-highest">
                <div v-for="project in projectStats" :key="project.id" class="project-section">
                    <!-- En-tête projet (cliquable) -->
                    <button
                        @click="toggleProject(project.id)"
                        class="w-full px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors text-left"
                    >
                        <div class="flex items-center gap-md">
                            <span class="material-symbols-outlined text-secondary">folder_open</span>
                            <div>
                                <span class="font-semibold text-on-surface">{{ project.name }}</span>
                                <span class="text-body-sm text-secondary ml-sm">
                                    {{ project.formations.length }} formation{{ project.formations.length > 1 ? 's' : '' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-sm flex-shrink-0">
                            <div class="stat-pill bg-green-50 text-green-700">
                                <span class="material-symbols-outlined" style="font-size:14px">work</span>
                                {{ project.totals.employed_count }}
                            </div>
                            <div class="stat-pill bg-blue-50 text-blue-700">
                                <span class="material-symbols-outlined" style="font-size:14px">business_center</span>
                                {{ project.totals.internship_count }}
                            </div>
                            <div class="stat-pill bg-gray-100 text-gray-700">
                                <span class="material-symbols-outlined" style="font-size:14px">groups</span>
                                {{ project.totals.total_learners }}
                            </div>
                            <span class="material-symbols-outlined text-secondary transition-transform" :class="expandedProjects[project.id] ? 'rotate-180' : ''">
                                expand_more
                            </span>
                        </div>
                    </button>

                    <!-- Tableau des formations -->
                    <div v-show="expandedProjects[project.id]" class="border-t border-surface-container-highest">
                        <div class="overflow-x-auto">
                            <table class="w-full text-body-sm">
                                <thead>
                                    <tr class="bg-surface-container-low text-secondary text-left">
                                        <th class="px-lg py-sm font-semibold whitespace-nowrap">Formation</th>
                                        <th class="px-lg py-sm font-semibold text-center">Apprenants</th>
                                        <th class="px-lg py-sm font-semibold text-center">
                                            <span class="material-symbols-outlined" style="font-size:16px; color:#E5004C">female</span>
                                        </th>
                                        <th class="px-lg py-sm font-semibold text-center">
                                            <span class="material-symbols-outlined" style="font-size:16px; color:#1F3A4D">male</span>
                                        </th>
                                        <th class="px-lg py-sm font-semibold text-center text-green-700">En cours</th>
                                        <th class="px-lg py-sm font-semibold text-center text-red-700">Abandons</th>
                                        <th class="px-lg py-sm font-semibold text-center text-blue-700">Diplômés</th>
                                        <th class="px-lg py-sm font-semibold text-center text-orange-700">Transférés</th>
                                        <th class="px-lg py-sm font-semibold text-center text-blue-600">Stage</th>
                                        <th class="px-lg py-sm font-semibold text-center text-green-600">Emploi</th>
                                        <th class="px-lg py-sm font-semibold text-center text-gray-600">Sans emploi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-container-highest">
                                    <tr v-for="f in project.formations" :key="f.id" class="hover:bg-surface-bright transition-colors">
                                        <td class="px-lg py-sm">
                                            <div class="flex items-center gap-sm">
                                                <span :class="['status-dot', statusClass[f.status] ?? '']" />
                                                <span class="font-medium text-on-surface">{{ f.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-lg py-sm text-center font-semibold cursor-pointer hover:underline" @click="openLearnersModal(f.id, f.name, 'gender', '', 'Tous les apprenants')">{{ f.total_learners }}</td>
                                        <td class="px-lg py-sm text-center cursor-pointer hover:underline" style="color:#E5004C" @click="f.female_count > 0 && openLearnersModal(f.id, f.name, 'gender', 'female', 'Femmes')">{{ f.female_count }}</td>
                                        <td class="px-lg py-sm text-center cursor-pointer hover:underline" style="color:#1F3A4D" @click="f.male_count > 0 && openLearnersModal(f.id, f.name, 'gender', 'male', 'Hommes')">{{ f.male_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-700 cursor-pointer hover:underline" @click="f.in_progress_count > 0 && openLearnersModal(f.id, f.name, 'status', 'in_progress', 'En cours')">{{ f.in_progress_count }}</td>
                                        <td class="px-lg py-sm text-center text-red-700 cursor-pointer hover:underline" @click="f.withdrawn_count > 0 && openLearnersModal(f.id, f.name, 'status', 'withdrawn', 'Abandons')">{{ f.withdrawn_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-700 cursor-pointer hover:underline" @click="f.completed_count > 0 && openLearnersModal(f.id, f.name, 'status', 'completed', 'Diplômés')">{{ f.completed_count }}</td>
                                        <td class="px-lg py-sm text-center text-orange-700 cursor-pointer hover:underline" @click="f.moved_count > 0 && openLearnersModal(f.id, f.name, 'status', 'moved', 'Transférés')">{{ f.moved_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-600 cursor-pointer hover:underline" @click="f.internship_count > 0 && openLearnersModal(f.id, f.name, 'insertion', 'internship', 'En stage')">{{ f.internship_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-600 cursor-pointer hover:underline" @click="f.employed_count > 0 && openLearnersModal(f.id, f.name, 'insertion', 'employed', 'En emploi')">{{ f.employed_count }}</td>
                                        <td class="px-lg py-sm text-center text-gray-600 cursor-pointer hover:underline" @click="f.unemployed_count > 0 && openLearnersModal(f.id, f.name, 'insertion', 'unemployed', 'Sans emploi')">{{ f.unemployed_count }}</td>
                                    </tr>
                                    <!-- Ligne totaux -->
                                    <tr class="bg-surface-container-low font-semibold text-on-surface">
                                        <td class="px-lg py-sm">Total {{ project.name }}</td>
                                        <td class="px-lg py-sm text-center">{{ project.totals.total_learners }}</td>
                                        <td class="px-lg py-sm text-center" style="color:#E5004C">{{ project.totals.female_count }}</td>
                                        <td class="px-lg py-sm text-center" style="color:#1F3A4D">{{ project.totals.male_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-700">{{ project.totals.in_progress_count }}</td>
                                        <td class="px-lg py-sm text-center text-red-700">{{ project.totals.withdrawn_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-700">{{ project.totals.completed_count }}</td>
                                        <td class="px-lg py-sm text-center text-orange-700">{{ project.totals.moved_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-600">{{ project.totals.internship_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-600">{{ project.totals.employed_count }}</td>
                                        <td class="px-lg py-sm text-center text-gray-600">{{ project.totals.unemployed_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Drill-down -->
        <div v-if="showLearnersModal" class="modal-overlay" @click.self="closeLearnersModal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title">{{ modalTitle }}</h3>
                        <p class="modal-subtitle">{{ modalLearners.length }} apprenant{{ modalLearners.length > 1 ? 's' : '' }}</p>
                    </div>
                    <button @click="closeLearnersModal" class="modal-close">
                        <span class="material-symbols-outlined" style="font-size:20px">close</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div v-if="modalLoading" class="text-center py-xl">
                        <span class="material-symbols-outlined spin" style="font-size:32px;color:#9aaabb">progress_activity</span>
                        <p class="mt-sm text-secondary">Chargement...</p>
                    </div>

                    <div v-else-if="modalLearners.length === 0" class="text-center py-xl text-secondary">
                        Aucun apprenant trouvé.
                    </div>

                    <div v-else class="learners-list">
                        <div
                            v-for="learner in modalLearners"
                            :key="learner.id"
                            class="learner-card"
                        >
                            <div class="learner-avatar">
                                {{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}
                            </div>
                            <div class="learner-info">
                                <p class="learner-name">{{ learner.last_name }} {{ learner.first_name }}</p>
                                <p v-if="learner.email" class="learner-email">{{ learner.email }}</p>
                                <div class="learner-meta">
                                    <span v-if="learner.gender" :class="['badge', learner.gender === 'female' ? 'badge-female' : 'badge-male']">
                                        {{ genderLabel(learner.gender) }}
                                    </span>
                                    <span v-if="learner.education_level" class="badge badge-info">
                                        {{ learner.education_level }}
                                    </span>
                                    <span :class="['badge', statusColorLearner[learner.status]]">
                                        {{ statusLabelLearner[learner.status] ?? learner.status }}
                                    </span>
                                </div>
                            </div>
                            <Link :href="`/learners/${learner.id}`" class="btn-view" title="Voir le profil">
                                <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* KPI Cards (hérités du dashboard, avec variantes) */
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
.kpi-primary { border-left-color: #E5004C; }
.kpi-accent  { border-left-color: #1F3A4D; }

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

/* Stat pill (badges dans l'en-tête projet) */
.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}

/* Status dot */
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.badge-active    { background: #dcfce7; }
.badge-upcoming  { background: #dbeafe; }
.badge-completed { background: #e5e7eb; }
.badge-cancelled { background: #fee2e2; }

/* Table */
table th,
table td {
    white-space: nowrap;
}

/* ══ Modal Drill-down ═══════════════════════════════════ */
.modal-overlay {
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
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.modal-content.modal-lg {
    max-width: 700px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}

.modal-subtitle {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

.modal-close {
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

.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

/* Learners list in modal */
.learners-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.learner-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

.learner-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #E5004C;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    flex-shrink: 0;
}

.learner-info {
    flex: 1;
    min-width: 0;
}

.learner-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.learner-email {
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}

.learner-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
}

.badge-female {
    background: #fce7f3;
    color: #be185d;
}

.badge-male {
    background: #dbeafe;
    color: #1e40af;
}

.badge-info {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-view {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    color: #6b7280;
    background: white;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}

.btn-view:hover {
    background: #f3f4f6;
    color: #374151;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Utilitaires pour le drill-down */
.cursor-pointer {
    cursor: pointer;
}

.hover\:underline:hover {
    text-decoration: underline;
}

/* ══ Responsive Statistics ══════════════════════════════ */

/* ── Mobile (<768px) ── */
@media (max-width: 767px) {
    /* Header page */
    .page-header,
    .flex.items-center.justify-between { flex-wrap: wrap; gap: 10px; }

    /* Bouton expand projet : stat pills réduits */
    .stat-pill { padding: 2px 6px; font-size: 10px; }
    .flex.items-center.gap-sm.flex-shrink-0 { gap: 4px; }

    /* Nom projet : laisser de la place */
    .font-semibold.text-on-surface { font-size: 13px; }

    /* Tableau */
    table {
        font-size: 11px;
        min-width: 700px; /* force scroll horizontal */
    }
    table th, table td {
        padding: 6px 6px !important;
        white-space: nowrap;
    }

    /* Wrapper tableau : scroll horizontal avec indicateur visuel */
    .overflow-x-auto {
        position: relative;
        -webkit-overflow-scrolling: touch;
    }

    /* Filtres */
    select, .filter-select { font-size: 12px; padding: 6px 8px; }
    .flex.items-center.gap-md { flex-wrap: wrap; gap: 8px; }

    /* KPIs si présents */
    .kpi-card { padding: 12px 14px; }
}

/* ── Tablet (768px–1023px) ── */
@media (min-width: 768px) and (max-width: 1023px) {
    table { font-size: 12px; }
    table th, table td { padding: 8px 8px !important; }
    .stat-pill { padding: 2px 8px; font-size: 11px; }
}
</style>
