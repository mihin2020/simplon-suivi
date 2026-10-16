<script setup lang="ts">
import ListLoading from '@/Components/UI/ListLoading.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ContractsTabs from '@/Components/Contracts/ContractsTabs.vue'
import Can from '@/Components/Can.vue'
import ContractBulkBar from '@/Components/Contracts/ContractBulkBar.vue'
import RecipientCell from '@/Components/Contracts/RecipientCell.vue'
import EmailStatusBadge from '@/Components/Contracts/EmailStatusBadge.vue'
import Pagination from '@/Components/UI/Pagination.vue'

defineOptions({ layout: AdminLayout })

interface ContractItem {
    id: string
    reference: string
    title: string
    target: string
    target_label: string
    status: string
    status_label: string
    status_color: string
    subject_name: string
    recipient_email: string | null
    is_nominative: boolean
    emailed_at: string | null
    emailed_to: string | null
    email_count: number
    email_status: 'queued' | 'sent' | 'failed' | null
    created_at: string | null
}
interface Paginated {
    data: ContractItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
    from: number | null
    to: number | null
    per_page: number
}
interface Option { value: string; label: string; color: string }

const props = defineProps<{
    contracts: Paginated
    filters: { search?: string; target?: string; status?: string; email?: string }
    targets: Option[]
    statuses: Option[]
}>()

const search = ref(props.filters.search ?? '')
const target = ref(props.filters.target ?? '')
const status = ref(props.filters.status ?? '')
const email = ref(props.filters.email ?? '')

const applyFilters = () => {
    router.get('/contracts', {
        search: search.value.trim() || undefined,
        target: target.value || undefined,
        status: status.value || undefined,
        email: email.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['contracts', 'filters'] })
}
watch([target, status, email], applyFilters)

// Search as you type, without a request per keystroke.
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

// ── Sélection (kept across pages and filters) ─────────────────────────────
const selected = ref<Map<string, ContractItem>>(new Map())
const selectedList = computed(() => [...selected.value.values()])
const isSelected = (c: ContractItem) => selected.value.has(c.id)
const toggle = (c: ContractItem) => {
    const next = new Map(selected.value)
    if (next.has(c.id)) next.delete(c.id)
    else next.set(c.id, c)
    selected.value = next
}
const pageAllSelected = computed(() => props.contracts.data.length > 0 && props.contracts.data.every(isSelected))
const togglePage = () => {
    const next = new Map(selected.value)
    for (const c of props.contracts.data) {
        if (pageAllSelected.value) next.delete(c.id)
        else next.set(c.id, c)
    }
    selected.value = next
}
const clearSelection = () => { selected.value = new Map() }

// While emails leave in the background, refresh the list so each badge moves
// from "being sent" to "sent". Stops by itself after a few minutes (no worker?).
const sending = computed(() => props.contracts.data.some(c => c.email_status === 'queued'))
let pollTimer: ReturnType<typeof setInterval> | undefined
let pollStarted = 0
const stopPolling = () => { clearInterval(pollTimer); pollTimer = undefined }
watch(sending, active => {
    if (active && !pollTimer) {
        pollStarted = Date.now()
        pollTimer = setInterval(() => {
            if (Date.now() - pollStarted > 5 * 60_000) return stopPolling()
            router.reload({ only: ['contracts'] })
        }, 4000)
    } else if (!active) {
        stopPolling()
    }
}, { immediate: true })
onBeforeUnmount(stopPolling)


const statusColors: Record<string, string> = {
    amber: 'c-amber', blue: 'c-blue', emerald: 'c-emerald', gray: 'c-gray',
}
</script>

<template>
    <Head title="Contrats & conventions" />
    <div class="wrap">
        <ContractsTabs />
        <div class="head">
            <div class="head-left">
                <div class="head-icon"><span class="material-symbols-outlined">contract</span></div>
                <div>
                    <h1 class="title">Contrats & conventions</h1>
                    <p class="subtitle">{{ contracts.total ?? 0 }} document(s) généré(s).</p>
                </div>
            </div>
            <div class="head-actions">
                <Can permission="contracts.create">
                    <Link href="/contracts/create" prefetch class="btn-primary">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Générer un contrat
                    </Link>
                </Can>
            </div>
        </div>

        <div class="filters">
            <div class="search">
                <span class="material-symbols-outlined">search</span>
                <input v-model="search" type="search" placeholder="Rechercher par référence, titre ou destinataire…" />
            </div>
            <select v-model="target" class="filter-select">
                <option value="">Tous les types</option>
                <option v-for="t in targets" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <select v-model="status" class="filter-select">
                <option value="">Tous les statuts</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="email" class="filter-select">
                <option value="">Envoi : tous</option>
                <option value="sent">Envoyés par email</option>
                <option value="none">Jamais envoyés</option>
                <option value="queued">Envoi en cours</option>
                <option value="failed">Échec d'envoi</option>
            </select>
        </div>

        <ContractBulkBar v-if="selectedList.length" :selected="selectedList" @clear="clearSelection" />

        <div v-if="contracts.data.length === 0" class="empty">
            <span class="material-symbols-outlined" style="font-size:48px;color:#ddd">contract</span>
            <p>{{ search || target || status ? 'Aucun contrat ne correspond à la recherche.' : 'Aucun contrat généré.' }}</p>
        </div>

        <div v-else class="card">
            <ListLoading :columns="8" with-checkbox>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="check"><input type="checkbox" :checked="pageAllSelected" title="Tout sélectionner sur cette page" @change="togglePage" /></th>
                            <th>Référence</th><th>Document</th><th>Destinataire</th><th>Type</th><th>Statut</th><th>Email</th><th class="open-col" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in contracts.data" :key="c.id" class="row" :class="{ 'row-selected': isSelected(c) }" @click="router.visit(`/contracts/${c.id}`)">
                            <td class="check" @click.stop="toggle(c)"><input type="checkbox" :checked="isSelected(c)" @click.stop="toggle(c)" /></td>
                            <td class="mono">{{ c.reference }}</td>
                            <td class="fw">
                                <Link :href="`/contracts/${c.id}`" prefetch class="doc-link" @click.stop>{{ c.title }}</Link>
                            </td>
                            <td><RecipientCell :name="c.subject_name" :email="c.recipient_email" :nominative="c.is_nominative" /></td>
                            <td>{{ c.target_label }}</td>
                            <td><span class="badge" :class="statusColors[c.status_color]">{{ c.status_label }}</span></td>
                            <td><EmailStatusBadge :status="c.email_status" :emailed-at="c.emailed_at" :emailed-to="c.emailed_to" :count="c.email_count" /></td>
                            <td class="open-col">
                                <span class="open-hint">Ouvrir<span class="material-symbols-outlined">chevron_right</span></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </ListLoading>
            <Pagination
                :from="contracts.from"
                :to="contracts.to"
                :total="contracts.total"
                :per-page="contracts.per_page"
                :links="contracts.links"
                :only="['contracts', 'filters']"
            />
        </div>

    </div>
</template>

<style scoped>
.wrap { max-width: 1040px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.head-left { display: flex; align-items: center; gap: 14px; }
.head-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(229,0,76,0.08); color: #E5004C; display: flex; align-items: center; justify-content: center; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 14px; color: #515f74; margin-top: 2px; }
.head-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; }
.btn-primary:hover { background: #c0003e; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; color: #515f74; font-size: 13px; font-weight: 500; text-decoration: none; background: #fff; }
.btn-secondary:hover { background: #f2f4f6; }
.filters { display: flex; gap: 10px; }
.filter-select { padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 10px; font-size: 14px; background: #fff; color: #374151; }
.card { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; overflow: hidden; }
.tbl { width: 100%; border-collapse: collapse; font-size: 14px; }
.tbl th { text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #9aaabb; border-bottom: 1px solid #f0f2f5; }
.tbl td { padding: 12px 16px; border-bottom: 1px solid #f5f6f8; color: #374151; }
.tbl tr:last-child td { border-bottom: none; }
.row { cursor: pointer; transition: background 0.12s; }
.row td:first-child { box-shadow: inset 3px 0 0 transparent; transition: box-shadow 0.12s; }
.row:hover td { background: #f6f8fa; }
.row:hover td:first-child { box-shadow: inset 3px 0 0 #E5004C; }
.doc-link { color: #191c1e; font-weight: 600; text-decoration: none; }
.row:hover .doc-link { color: #E5004C; text-decoration: underline; text-underline-offset: 3px; }
.open-col { width: 90px; text-align: right; }
.open-hint { display: inline-flex; align-items: center; gap: 2px; font-size: 12px; font-weight: 600; color: #c3ccd6; transition: color 0.12s; }
.open-hint .material-symbols-outlined { font-size: 20px; }
.row:hover .open-hint { color: #E5004C; }
.mono { font-family: monospace; font-size: 12px; color: #515f74; }
.fw { font-weight: 600; color: #1F3A4D; }
.badge { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.c-amber { background: #fff4e0; color: #b45309; }
.c-blue { background: #e4edff; color: #1d4ed8; }
.c-emerald { background: #e7f8ef; color: #0a7b43; }
.c-gray { background: #f1f3f5; color: #868e96; }
.empty { text-align: center; padding: 60px 20px; color: #868e96; background: #fff; border: 1px solid #e8edf2; border-radius: 16px; }
.search { flex: 1 1 280px; display: flex; align-items: center; gap: 6px; padding: 0 12px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; }
.search:focus-within { border-color: #E5004C; }
.search .material-symbols-outlined { font-size: 20px; color: #9aaabb; }
.search input { flex: 1; min-width: 0; padding: 10px 0; border: none; outline: none; font-size: 14px; background: transparent; }
.check { width: 40px; text-align: center; cursor: pointer; }
.check input { width: 16px; height: 16px; accent-color: #E5004C; cursor: pointer; }
.row-selected td { background: #fff5f8; }
</style>
