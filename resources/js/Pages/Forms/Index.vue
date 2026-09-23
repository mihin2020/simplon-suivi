<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface FormItem {
    id: string
    title: string
    status: string
    responses_count: number
    project?: { id: string; name: string }
    formation?: { id: string; name: string }
}

interface Paginated {
    data: FormItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
}

interface StatusOption {
    value: string
    label: string
    color: string
}

interface ProjectOption {
    id: string
    name: string
    formations: Array<{ id: string; project_id: string; name: string }>
}

const props = defineProps<{
    forms: Paginated
    filters: { status?: string; project_id?: string; formation_id?: string; search?: string }
    statuses: StatusOption[]
    projects: ProjectOption[]
}>()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const projectId = ref(props.filters.project_id ?? '')
const formationId = ref(props.filters.formation_id ?? '')

const formations = computed(() =>
    props.projects.find(p => p.id === projectId.value)?.formations ?? []
)

const applyFilters = () => {
    router.get('/forms', {
        search: search.value || undefined,
        status: status.value || undefined,
        project_id: projectId.value || undefined,
        formation_id: formationId.value || undefined,
    }, { preserveState: true, replace: true })
}

watch(projectId, () => {
    if (!formations.value.some(f => f.id === formationId.value)) {
        formationId.value = ''
    }
    applyFilters()
})

watch([status, formationId], applyFilters)

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const badgeClass = (color: string) => {
    const map: Record<string, string> = {
        gray: 'badge-gray',
        green: 'badge-green',
        orange: 'badge-orange',
        red: 'badge-red',
        slate: 'badge-slate',
    }
    return map[color] ?? 'badge-gray'
}

const statusMeta = (value: string) =>
    props.statuses.find(s => s.value === value) ?? { label: value, color: 'gray' }

const confirmTarget = ref<FormItem | null>(null)
const archive = () => {
    if (!confirmTarget.value) return
    router.delete(`/forms/${confirmTarget.value.id}`, {
        onFinish: () => { confirmTarget.value = null },
    })
}
</script>

<template>
    <Head title="Formulaires" />
    <div class="max-w-[1200px] mx-auto space-y-xl">

        <div class="flex items-center justify-between flex-wrap gap-sm">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined">dynamic_form</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Formulaires</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        Collecte et sélection de candidatures liées aux formations.
                    </p>
                </div>
            </div>
            <Can permission="forms.create">
                <Link href="/forms/create" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                    Nouveau formulaire
                </Link>
            </Can>
        </div>

        <div class="filter-bar">
            <div class="filter-field grow">
                <label class="filter-label" for="forms-search">Recherche</label>
                <input
                    id="forms-search"
                    v-model="search"
                    type="search"
                    class="filter-input"
                    placeholder="Titre du formulaire…"
                />
            </div>
            <div class="filter-field">
                <label class="filter-label" for="forms-status">Statut</label>
                <select id="forms-status" v-model="status" class="filter-select">
                    <option value="">Actifs</option>
                    <option v-for="s in statuses.filter(s => s.value !== 'archived')" :key="s.value" :value="s.value">{{ s.label }}</option>
                    <option value="archived">Archivés</option>
                </select>
            </div>
            <div class="filter-field">
                <label class="filter-label" for="forms-project">Projet</label>
                <select id="forms-project" v-model="projectId" class="filter-select">
                    <option value="">Tous</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="filter-field">
                <label class="filter-label" for="forms-formation">Formation</label>
                <select id="forms-formation" v-model="formationId" class="filter-select" :disabled="!projectId">
                    <option value="">Toutes</option>
                    <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
            </div>
        </div>

        <div v-if="status === 'archived'" class="archive-banner">
            <span class="material-symbols-outlined" style="font-size:20px">inventory_2</span>
            <div>
                <p class="archive-banner-title">Formulaires archivés</p>
                <p class="archive-banner-text">Cliquez sur <strong>Récupérer</strong> pour désarchiver un formulaire et le remettre en brouillon.</p>
            </div>
        </div>

        <div v-if="forms.data.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:52px;color:#e0e3e5">dynamic_form</span>
            <h2 class="font-semibold text-on-surface mt-md">
                {{ status === 'archived' ? 'Aucun formulaire archivé' : 'Aucun formulaire' }}
            </h2>
            <p class="text-body-md text-secondary mt-xs">
                {{ status === 'archived'
                    ? 'Les formulaires archivés apparaîtront ici.'
                    : 'Créez un formulaire pour collecter des candidatures.' }}
            </p>
            <Can v-if="status !== 'archived'" permission="forms.create">
                <Link href="/forms/create" class="btn-primary mt-lg">
                    <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                    Créer le premier
                </Link>
            </Can>
        </div>

        <div v-else class="table-card">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Projet / Formation</th>
                            <th>Réponses</th>
                            <th>Statut</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="form in forms.data" :key="form.id">
                            <td>
                                <Link :href="`/forms/${form.id}/edit`" class="row-title">
                                    {{ form.title }}
                                </Link>
                            </td>
                            <td>
                                <div class="row-meta">{{ form.project?.name }}</div>
                                <div class="row-sub">{{ form.formation?.name }}</div>
                            </td>
                            <td>
                                <span class="count-pill">{{ form.responses_count }}</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="badgeClass(statusMeta(form.status).color)">
                                    {{ statusMeta(form.status).label }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <Can permission="forms.stats">
                                        <Link :href="`/forms/${form.id}/stats`" class="icon-btn" title="Statistiques">
                                            <span class="material-symbols-outlined" style="font-size:18px">bar_chart</span>
                                        </Link>
                                    </Can>
                                    <Can permission="forms.responses">
                                        <Link :href="`/forms/${form.id}/responses`" class="icon-btn" title="Réponses">
                                            <span class="material-symbols-outlined" style="font-size:18px">inbox</span>
                                        </Link>
                                    </Can>
                                    <Can permission="forms.create">
                                        <button
                                            type="button"
                                            class="icon-btn"
                                            title="Dupliquer"
                                            @click="router.post(`/forms/${form.id}/duplicate`)"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:18px">content_copy</span>
                                        </button>
                                    </Can>
                                    <Link :href="`/forms/${form.id}/edit`" class="icon-btn" title="Éditer">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <Can v-if="form.status !== 'archived'" permission="forms.delete">
                                        <button type="button" class="icon-btn danger" title="Archiver" @click="confirmTarget = form">
                                            <span class="material-symbols-outlined" style="font-size:18px">inventory_2</span>
                                        </button>
                                    </Can>
                                    <Can v-else permission="forms.delete">
                                        <button
                                            type="button"
                                            class="btn-recover"
                                            title="Désarchiver et remettre en brouillon"
                                            @click="router.post(`/forms/${form.id}/unarchive`)"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:16px">unarchive</span>
                                            Récupérer
                                        </button>
                                    </Can>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="forms.links?.length > 3" class="flex items-center justify-center gap-xs">
            <template v-for="link in forms.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                <span v-else class="page-btn page-disabled" v-html="link.label" />
            </template>
        </div>

        <Teleport to="body">
            <div v-if="confirmTarget" class="modal-backdrop" @click.self="confirmTarget = null">
                <div class="modal-box">
                    <div class="modal-icon">
                        <span class="material-symbols-outlined" style="font-size:32px;color:#E5004C">inventory_2</span>
                    </div>
                    <h3 class="modal-title">Archiver le formulaire</h3>
                    <p class="modal-body">
                        Voulez-vous archiver <strong>« {{ confirmTarget.title }} »</strong> ?
                        Vous pourrez le retrouver dans le filtre « Archivés ».
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" @click="confirmTarget = null">Annuler</button>
                        <button type="button" class="btn-danger" @click="archive">Archiver</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    transition: background 0.2s; text-decoration: none; border: none; cursor: pointer;
}
.btn-primary:hover { background: #c0003e; }

.filter-bar {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
    padding: 14px 16px; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
}
.filter-field { display: flex; flex-direction: column; gap: 6px; min-width: 160px; }
.filter-field.grow { flex: 1; min-width: 220px; }
.filter-label {
    font-size: 12px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.filter-input, .filter-select {
    width: 100%; padding: 8px 12px; border: 1.5px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fafbfc; outline: none;
    font-family: inherit;
}
.filter-input:focus, .filter-select:focus {
    border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08); background: #fff;
}

.empty-state {
    display: flex; flex-direction: column; align-items: center;
    padding: 60px 40px; background: #fff; border: 1px solid #e0e3e5;
    border-radius: 16px; text-align: center;
}

.table-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table thead tr { background: #f7f9fb; border-bottom: 1px solid #e0e3e5; }
.data-table th {
    padding: 12px 16px; font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.data-table td { padding: 14px 16px; border-top: 1px solid #eef0f2; vertical-align: middle; }
.data-table tbody tr:hover { background: #fafbfc; }
.row-title {
    font-size: 14px; font-weight: 600; color: #191c1e; text-decoration: none;
}
.row-title:hover { color: #E5004C; }
.row-meta { font-size: 13px; font-weight: 500; color: #191c1e; }
.row-sub { font-size: 12px; color: #515f74; margin-top: 2px; }
.count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 28px; padding: 2px 8px; border-radius: 99px;
    background: #f0f4ff; color: #1F3A4D; font-size: 12px; font-weight: 700;
}
.status-badge {
    display: inline-flex; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.badge-gray { background: #f1f3f5; color: #515f74; }
.badge-green { background: #e6f7ed; color: #0f7a3d; }
.badge-orange { background: #fff4e5; color: #b45309; }
.badge-red { background: #fde8e8; color: #b91c1c; }
.badge-slate { background: #e2e8f0; color: #475569; }
.archive-banner {
    display: flex; align-items: flex-start; gap: 12px;
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 14px 16px; color: #475569;
}
.archive-banner-title { font-size: 13px; font-weight: 700; color: #1F3A4D; }
.archive-banner-text { font-size: 13px; margin-top: 2px; line-height: 1.4; }
.btn-recover {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 8px; border: 1.5px solid #1F3A4D;
    background: #fff; color: #1F3A4D; font-size: 12px; font-weight: 700;
    cursor: pointer; font-family: inherit; white-space: nowrap;
}
.btn-recover:hover { background: #1F3A4D; color: #fff; }
.row-actions { display: flex; justify-content: flex-end; gap: 6px; align-items: center; }
.icon-btn {
    padding: 6px; color: #515f74; border-radius: 6px; display: inline-flex;
    background: none; border: none; cursor: pointer; text-decoration: none;
    transition: color 0.15s, background 0.15s;
}
.icon-btn:hover { color: #E5004C; background: #fff5f8; }
.icon-btn.danger:hover { color: #ba1a1a; background: #fff5f5; }

.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px; padding: 0 6px; border-radius: 4px;
    font-size: 13px; font-weight: 500; color: #191c1e;
    transition: background 0.15s; cursor: pointer; text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    background: #fff; border-radius: 16px; padding: 32px 28px;
    width: 100%; max-width: 400px; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-icon { margin-bottom: 12px; }
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; margin-bottom: 8px; }
.modal-body { font-size: 14px; color: #515f74; line-height: 1.6; margin-bottom: 24px; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.btn-cancel {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    border: 1.5px solid #d0d3d5; color: #515f74; background: #fff; cursor: pointer;
}
.btn-cancel:hover { background: #f4f5f6; }
.btn-danger {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    background: #E5004C; color: #fff; border: none; cursor: pointer;
}
.btn-danger:hover { background: #c0003e; }
.text-right { text-align: right; }
</style>
