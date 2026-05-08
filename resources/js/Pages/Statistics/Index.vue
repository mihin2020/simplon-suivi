<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: AdminLayout })

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
    searching_count: number
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
        searching_count: number
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
        searching: number
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
                                        <th class="px-lg py-sm font-semibold text-center text-amber-700">Recherche</th>
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
                                        <td class="px-lg py-sm text-center font-semibold">{{ f.total_learners }}</td>
                                        <td class="px-lg py-sm text-center" style="color:#E5004C">{{ f.female_count }}</td>
                                        <td class="px-lg py-sm text-center" style="color:#1F3A4D">{{ f.male_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-700">{{ f.in_progress_count }}</td>
                                        <td class="px-lg py-sm text-center text-red-700">{{ f.withdrawn_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-700">{{ f.completed_count }}</td>
                                        <td class="px-lg py-sm text-center text-orange-700">{{ f.moved_count }}</td>
                                        <td class="px-lg py-sm text-center text-amber-700">{{ f.searching_count }}</td>
                                        <td class="px-lg py-sm text-center text-blue-600">{{ f.internship_count }}</td>
                                        <td class="px-lg py-sm text-center text-green-600">{{ f.employed_count }}</td>
                                        <td class="px-lg py-sm text-center text-gray-600">{{ f.unemployed_count }}</td>
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
                                        <td class="px-lg py-sm text-center text-amber-700">{{ project.totals.searching_count }}</td>
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
</style>
