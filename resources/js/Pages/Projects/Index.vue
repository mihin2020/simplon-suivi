<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Project {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    formations_count: number
}

interface Paginated {
    data: Project[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

defineProps<{
    projects: Paginated
    statuses: Array<{ value: string; label: string; color: string }>
}>()

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

const destroy = (p: Project) => {
    if (confirm(`Supprimer le projet « ${p.name} » ?`)) {
        router.delete(`/projects/${p.id}`)
    }
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Gestion des Projets</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Aperçu et administration de toutes les formations actives et à venir.
                </p>
            </div>
            <Link href="/projects/create" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                Ajouter un Projet
            </Link>
        </div>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Nom du Projet</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Début</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Fin</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Formations</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="projects.data.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun projet trouvé.
                            </td>
                        </tr>
                        <tr
                            v-for="project in projects.data"
                            :key="project.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <Link
                                    :href="`/projects/${project.id}`"
                                    class="font-semibold text-data-tabular text-on-surface hover:text-primary transition-colors"
                                >
                                    {{ project.name }}
                                </Link>
                                <p v-if="project.description" class="text-body-sm text-on-surface-variant mt-xs line-clamp-1">
                                    {{ project.description }}
                                </p>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(project.started_at) }}</td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(project.ended_at) }}</td>
                            <td class="px-md py-sm text-data-tabular text-on-surface text-right">{{ project.formations_count }}</td>
                            <td class="px-md py-sm">
                                <span class="status-badge" :class="`status-${project.status}`">
                                    {{ statuses.find(s => s.value === project.status)?.label ?? project.status }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link
                                        :href="`/projects/${project.id}/edit`"
                                        class="icon-btn"
                                        title="Modifier"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button @click="destroy(project)" class="icon-btn danger" title="Supprimer">
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
                    {{ projects.meta?.from }}–{{ projects.meta?.to }} sur {{ projects.meta?.total }} projets
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in projects.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="page-btn"
                            :class="{ 'page-active': link.active }"
                            v-html="link.label"
                        />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
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
