<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string | null
    phone: string | null
    photo_path: string | null
    formations: Array<{
        id: string
        name: string
        project: { id: string; name: string }
    }>
}

interface Paginated {
    data: Learner[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

const props = defineProps<{
    learners: Paginated
    filters: {
        search?: string
        project_id?: string
        formation_id?: string
    }
    projects: Array<{ id: string; name: string }>
    formations: Array<{ id: string; name: string; project_id: string; project: { id: string; name: string } }>
}>()

const search = ref(props.filters.search ?? '')
const projectId = ref(props.filters.project_id ?? '')
const formationId = ref(props.filters.formation_id ?? '')

const formationsForProject = computed(() => {
    if (!projectId.value) return props.formations
    return props.formations.filter(f => f.project_id === projectId.value)
})

const applyFilters = () => {
    router.get('/learners', {
        search: search.value || undefined,
        project_id: projectId.value || undefined,
        formation_id: formationId.value || undefined,
    }, { preserveState: true, replace: true })
}

let timer: ReturnType<typeof setTimeout>
watch([search, projectId, formationId], () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        applyFilters()
    }, 400)
})

watch(projectId, () => {
    formationId.value = ''
})

const destroy = (l: Learner) => {
    if (confirm(`Supprimer l'apprenant « ${l.last_name} ${l.first_name} » ?`)) {
        router.delete(`/learners/${l.id}`)
    }
}

const photoUrl = (path: string | null) =>
    path ? `/storage/${path}` : null
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Annuaire des Apprenants</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ learners.meta?.total ?? 0 }} apprenants enregistrés.
                </p>
            </div>
            <div class="flex items-center gap-sm">
                <Link href="/learners/import" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">upload_file</span>
                    Importer Excel
                </Link>
                <Link href="/learners/create" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                    Ajouter un Apprenant
                </Link>
            </div>
        </div>

        <!-- Recherche + filtres -->
        <form @submit.prevent="applyFilters" class="bg-surface-container-lowest border border-surface-container-highest rounded-xl p-md flex flex-wrap gap-md items-end shadow-sm">
            <div class="relative flex-1 min-w-[260px]">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:18px">search</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Rechercher par nom, prénom, email..."
                    class="w-full pl-xl py-sm bg-surface border border-surface-container-highest rounded-lg text-body-sm text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                />
            </div>
            <div class="min-w-[220px]">
                <label class="filter-label">Projet</label>
                <select v-model="projectId" class="filter-input">
                    <option value="">Tous les projets</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>

            <div class="min-w-[240px]">
                <label class="filter-label">Formation</label>
                <select v-model="formationId" class="filter-input" :disabled="formationsForProject.length === 0">
                    <option value="">Toutes les formations</option>
                    <option v-for="f in formationsForProject" :key="f.id" :value="f.id">
                        {{ f.name }} <template v-if="!projectId">— {{ f.project.name }}</template>
                    </option>
                </select>
            </div>

            <button type="submit" class="btn-secondary">
                Filtrer
            </button>

            <span class="text-body-sm text-secondary ml-auto">{{ learners.meta?.total ?? 0 }} résultat(s)</span>
        </form>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Apprenant</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Email</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Téléphone</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Projet</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formation</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="learners.data.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun apprenant trouvé.
                            </td>
                        </tr>
                        <tr
                            v-for="learner in learners.data"
                            :key="learner.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <div class="flex items-center gap-sm">
                                    <!-- Photo ou initiales -->
                                    <div class="avatar-wrap">
                                        <img
                                            v-if="photoUrl(learner.photo_path)"
                                            :src="photoUrl(learner.photo_path)!"
                                            :alt="`${learner.first_name} ${learner.last_name}`"
                                            class="avatar-img"
                                        />
                                        <div v-else class="avatar-initials">
                                            {{ learner.first_name.charAt(0) }}{{ learner.last_name.charAt(0) }}
                                        </div>
                                    </div>
                                    <div>
                                        <Link
                                            :href="`/learners/${learner.id}`"
                                            class="font-semibold text-data-tabular text-on-surface hover:text-primary transition-colors"
                                        >
                                            {{ learner.last_name }} {{ learner.first_name }}
                                        </Link>
                                    </div>
                                </div>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">
                                {{ learner.email ?? '—' }}
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">
                                {{ learner.phone ?? '—' }}
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">
                                {{ learner.formations?.[0]?.project?.name ?? '—' }}
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">
                                {{ learner.formations?.[0]?.name ?? '—' }}
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/learners/${learner.id}`" class="icon-btn" title="Voir le profil">
                                        <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                                    </Link>
                                    <Link :href="`/learners/${learner.id}/edit`" class="icon-btn" title="Modifier">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button @click="destroy(learner)" class="icon-btn danger" title="Supprimer">
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
                    {{ learners.meta?.from }}–{{ learners.meta?.to }} sur {{ learners.meta?.total }} apprenants
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in learners.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
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

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    border: 1px solid #e0e3e5;
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

/* Avatar */
.avatar-wrap { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-initials {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #E5004C;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
}

/* Filters */
.filter-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}
.filter-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 13px;
    color: #191c1e;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.filter-input:disabled { opacity: 0.6; background: #f9fafb; cursor: not-allowed; }

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
