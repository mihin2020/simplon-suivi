<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface Formation { id: string; name: string }
interface Status { value: string; label: string; color: string }

interface Cohort {
    id: string
    name: string
    started_at: string
    ended_at: string
    capacity: number
    status: string
    learners_count: number
    campus_formation: Formation
}

interface Paginated {
    data: Cohort[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    from: number | null
    to: number | null
    total: number
    current_page: number
    last_page: number
}

interface Filters { formation: string; status: string }

const props = defineProps<{
    cohorts: Paginated
    formations: Formation[]
    statuses: Status[]
    filters: Filters
}>()

const filterFormation = ref(props.filters.formation)
const filterStatus    = ref(props.filters.status)

watch([filterFormation, filterStatus], ([f, s]) => {
    router.get('/campus/cohorts', { formation: f, status: s }, { preserveState: true, replace: true })
})

const statusObj = (val: string) => props.statuses.find(s => s.value === val)

const statusClass: Record<string, string> = {
    planifiee: 'badge-blue',
    en_cours:  'badge-green',
    cloturee:  'badge-gray',
}

const fmt = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })

// ── Clôturer via modal ───────────────────────────────────────────────────
const showCloseModal  = ref(false)
const closing         = ref(false)
const targetClose     = ref<Cohort | null>(null)

const askClose = (c: Cohort) => { targetClose.value = c; showCloseModal.value = true }
const confirmClose = () => {
    if (!targetClose.value) return
    closing.value = true
    router.patch(`/campus/cohorts/${targetClose.value.id}/close`, {}, {
        onFinish: () => { closing.value = false; showCloseModal.value = false; targetClose.value = null },
    })
}

// ── Suppression via modal ─────────────────────────────────────────────────
const showDeleteModal = ref(false)
const deleting        = ref(false)
const targetCohort    = ref<Cohort | null>(null)

const askDelete = (c: Cohort) => {
    targetCohort.value = c
    showDeleteModal.value = true
}

const confirmDelete = () => {
    if (!targetCohort.value) return
    deleting.value = true
    router.delete(`/campus/cohorts/${targetCohort.value.id}`, {
        onFinish: () => {
            deleting.value = false
            showDeleteModal.value = false
            targetCohort.value = null
        },
    })
}

const cancelDelete = () => {
    showDeleteModal.value = false
    targetCohort.value = null
}
</script>

<template>
    <Head title="Cohortes" />
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined" style="font-size:24px">class</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Cohortes</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        {{ cohorts.total }} cohorte(s) enregistrée(s).
                    </p>
                </div>
            </div>
            <Can permission="campus.cohorts.create">
                <Link href="/campus/cohorts/create" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Nouvelle cohorte
                </Link>
            </Can>
        </div>

        <!-- Filtres -->
        <div class="filter-bar">
            <select v-model="filterFormation" class="filter-select">
                <option value="">Toutes les formations</option>
                <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
            <select v-model="filterStatus" class="filter-select">
                <option value="">Tous les statuts</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <span class="filter-count">
                <strong>{{ cohorts.total }}</strong> cohorte(s)
            </span>
        </div>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Cohorte</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formation</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Période</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-center">Apprenants</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-center">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="cohorts.data.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucune cohorte trouvée.
                            </td>
                        </tr>
                        <tr
                            v-for="c in cohorts.data"
                            :key="c.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <Link :href="`/campus/cohorts/${c.id}`" class="font-semibold text-on-surface hover:text-primary transition-colors">
                                    {{ c.name }}
                                </Link>
                            </td>
                            <td class="px-md py-sm text-body-sm text-on-surface-variant">
                                {{ c.campus_formation?.name ?? '' }}
                            </td>
                            <td class="px-md py-sm">
                                <span class="text-data-tabular text-on-surface">{{ fmt(c.started_at) }}</span>
                                <span class="text-on-surface-variant text-body-sm"> → {{ fmt(c.ended_at) }}</span>
                            </td>
                            <td class="px-md py-sm text-center">
                                <span class="text-data-tabular font-semibold">{{ c.learners_count }}</span>
                            </td>
                            <td class="px-md py-sm text-center">
                                <span :class="['status-badge', statusClass[c.status]]">
                                    {{ statusObj(c.status)?.label ?? c.status }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/campus/cohorts/${c.id}`" class="icon-btn" title="Voir">
                                        <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                                    </Link>
                                    <Can permission="campus.cohorts.update">
                                        <Link :href="`/campus/cohorts/${c.id}/edit`" class="icon-btn" title="Modifier">
                                            <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                        </Link>
                                    </Can>
                                    <Can :any="['campus.finance.view', 'campus.finance.collect', 'campus.finance.manage']">
                                        <Link :href="`/campus/cohorts/${c.id}/payments`" class="icon-btn" title="Paiements">
                                            <span class="material-symbols-outlined" style="font-size:18px">payments</span>
                                        </Link>
                                    </Can>
                                    <Can permission="campus.cohorts.close">
                                        <button
                                            v-if="c.status !== 'cloturee'"
                                            @click="askClose(c)"
                                            class="icon-btn warn"
                                            title="Clôturer"
                                            type="button"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:18px">lock</span>
                                        </button>
                                    </Can>
                                    <Can permission="campus.cohorts.delete">
                                        <button
                                            @click="askDelete(c)"
                                            class="icon-btn danger"
                                            title="Supprimer"
                                            type="button"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                        </button>
                                    </Can>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-md py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ cohorts.from }}–{{ cohorts.to }} sur {{ cohorts.total }} cohortes
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in cohorts.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal suppression -->
    <ConfirmModal
        :show="showDeleteModal"
        title="Supprimer la cohorte"
        :message="targetCohort ? `Vous êtes sur le point de supprimer « ${targetCohort.name} ». Cette action est irréversible.` : ''"
        confirm-label="Supprimer"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
    />

    <!-- Modal clôturer -->
    <ConfirmModal
        :show="showCloseModal"
        title="Clôturer la cohorte"
        :message="targetClose ? `Clôturer « ${targetClose.name} » ? Cette action est irréversible.` : ''"
        confirm-label="Clôturer"
        :loading="closing"
        @confirm="confirmClose"
        @cancel="showCloseModal = false"
    />
</template>

<style scoped>
.page-header-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

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

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
    background: transparent;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.warn:hover   { color: #d97706; }
.icon-btn.danger:hover { color: #ba1a1a; }

.filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.filter-select {
    padding: 8px 32px 8px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 13px;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 16px;
    transition: border-color 0.15s;
}
.filter-select:focus { border-color: #E5004C; }
.filter-count { margin-left: auto; font-size: 13px; color: #9aaabb; }
.filter-count strong { color: #191c1e; }

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.badge-blue  { background: #dbeafe; color: #1d4ed8; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-gray  { background: #f3f4f6; color: #6b7280; }

.fill-bar {
    width: 72px;
    height: 5px;
    border-radius: 99px;
    background: #e0e3e5;
    overflow: hidden;
}
.fill-inner { height: 100%; border-radius: 99px; transition: width 0.4s; }
.fill-green { background: #10b981; }
.fill-amber { background: #f59e0b; }
.fill-red   { background: #ef4444; }

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
