<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    active_learners_count: number
}

interface Project {
    id: string
    name: string
}

interface Paginated {
    data: Formation[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

const props = defineProps<{
    project: Project
    formations: Paginated
}>()

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

const statusLabels: Record<string, string> = {
    active: 'Active',
    completed: 'Terminée',
    archived: 'Archivée',
}

const destroyFormation = (f: Formation) => {
    if (confirm(`Supprimer la formation « ${f.name} » ?`)) {
        router.delete(`/formations/${f.id}`)
    }
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div class="flex items-start gap-md">
                <Link :href="`/projects/${project.id}`" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Formations</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        Projet : <span class="font-semibold text-on-surface">{{ project.name }}</span>
                        · {{ formations.meta?.total ?? 0 }} formation(s)
                    </p>
                </div>
            </div>
            <Link :href="`/projects/${project.id}/formations/create`" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                Ajouter une Formation
            </Link>
        </div>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formation</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Début</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Fin</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-center">Apprenants</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="formations.data.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucune formation pour ce projet.
                            </td>
                        </tr>
                        <tr
                            v-for="formation in formations.data"
                            :key="formation.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <Link
                                    :href="`/formations/${formation.id}`"
                                    class="font-semibold text-on-surface hover:text-primary transition-colors"
                                >
                                    {{ formation.name }}
                                </Link>
                                <p v-if="formation.description" class="text-body-sm text-on-surface-variant mt-xs line-clamp-1">
                                    {{ formation.description }}
                                </p>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(formation.started_at) }}</td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(formation.ended_at) }}</td>
                            <td class="px-md py-sm text-center">
                                <span class="learner-count">{{ formation.active_learners_count }}</span>
                            </td>
                            <td class="px-md py-sm">
                                <span class="status-badge" :class="`status-${formation.status}`">
                                    {{ statusLabels[formation.status] ?? formation.status }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/formations/${formation.id}/attendances`" class="icon-btn" title="Présences">
                                        <span class="material-symbols-outlined" style="font-size:18px">fact_check</span>
                                    </Link>
                                    <Link :href="`/formations/${formation.id}/edit`" class="icon-btn" title="Modifier">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button @click="destroyFormation(formation)" class="icon-btn danger" title="Supprimer">
                                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-md py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ formations.meta?.from }}–{{ formations.meta?.to }} sur {{ formations.meta?.total }} formations
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in formations.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
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
    margin-top: 2px;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.learner-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 6px;
    background: #f2f4f6;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 600;
    color: #191c1e;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}
.status-active    { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-archived  { background: #f3f4f6; color: #6b7280; }

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 6px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
    transition: background 0.15s;
    cursor: pointer;
    text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }
</style>
