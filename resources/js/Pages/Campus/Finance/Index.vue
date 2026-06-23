<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface CohortStat {
    id: string
    name: string
    started_at: string
    ended_at: string
    status: string
    learners_count: number
    total_expected: number
    total_collected: number
    total_remaining: number
    overdue_count: number
    collect_rate: number
    formation: { id: string; name: string }
}

interface FormationGroup {
    id: string
    name: string
    cohorts: CohortStat[]
    totals: {
        total_expected: number
        total_collected: number
        total_remaining: number
        overdue_count: number
        collect_rate: number
    }
}

interface GlobalStats {
    total_expected: number
    total_collected: number
    total_remaining: number
    overdue_count: number
    paid_this_month: number
}

interface OverduePayment {
    id: string
    amount: number
    due_date: string
    learner: { id: string; first_name: string; last_name: string }
    cohort: { id: string; name: string; campus_formation: { name: string } }
}

const props = defineProps<{
    byFormation: FormationGroup[]
    globalStats: GlobalStats
    recentOverdue: OverduePayment[]
    years: number[]
    selectedYear: number
}>()

const fmt = (n: number) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'
const fmtShort = (n: number) => {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + ' M'
    if (n >= 1_000) return Math.round(n / 1_000) + ' k'
    return String(n)
}
const fmtDate = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const fmtMonth = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' })

const changeYear = (e: Event) => {
    const y = (e.target as HTMLSelectElement).value
    router.get('/campus/finance', { year: y }, { preserveState: false })
}

const cohortStatusLabel: Record<string, string> = {
    planifiee: 'Planifiée',
    en_cours: 'En cours',
    cloturee: 'Clôturée',
}
const cohortStatusClass: Record<string, string> = {
    planifiee: 'badge-blue',
    en_cours: 'badge-green',
    cloturee: 'badge-gray',
}

const progressColor = (rate: number) => {
    if (rate >= 80) return '#10b981'
    if (rate >= 40) return '#f59e0b'
    return '#ef4444'
}

const totalExpected  = props.globalStats.total_expected
const totalCollected = props.globalStats.total_collected
</script>

<template>
    <Head title="Finance" />
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined" style="font-size:24px">account_balance</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Finance</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        Suivi des encaissements par cohorte, session {{ selectedYear }}.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <span class="text-body-sm text-on-surface-variant">Année :</span>
                <select class="year-select" :value="selectedYear" @change="changeYear">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon kpi-blue">
                    <span class="material-symbols-outlined" style="font-size:22px">calculate</span>
                </div>
                <div>
                    <p class="kpi-label">Total planifié</p>
                    <p class="kpi-val">{{ fmt(globalStats.total_expected) }}</p>
                    <p class="kpi-sub">coût × apprenants actifs</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-green">
                    <span class="material-symbols-outlined" style="font-size:22px">account_balance_wallet</span>
                </div>
                <div>
                    <p class="kpi-label">Total encaissé</p>
                    <p class="kpi-val" style="color:#065f46">{{ fmt(globalStats.total_collected) }}</p>
                    <p class="kpi-sub" v-if="globalStats.total_expected > 0">
                        {{ Math.min(100, Math.round(globalStats.total_collected / globalStats.total_expected * 100)) }}% collecté
                    </p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-amber">
                    <span class="material-symbols-outlined" style="font-size:22px">pending_actions</span>
                </div>
                <div>
                    <p class="kpi-label">Reste à percevoir</p>
                    <p class="kpi-val" :style="globalStats.total_remaining > 0 ? 'color:#92400e' : 'color:#065f46'">
                        {{ fmt(globalStats.total_remaining) }}
                    </p>
                    <p class="kpi-sub">sur le total planifié</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-red">
                    <span class="material-symbols-outlined" style="font-size:22px">warning</span>
                </div>
                <div>
                    <p class="kpi-label">Apprenants en retard</p>
                    <p class="kpi-val" style="color:#dc2626">{{ globalStats.overdue_count }}</p>
                    <p class="kpi-sub">avec ≥ 1 échéance dépassée</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-primary">
                    <span class="material-symbols-outlined" style="font-size:22px">trending_up</span>
                </div>
                <div>
                    <p class="kpi-label">Encaissé ce mois</p>
                    <p class="kpi-val" style="color:#E5004C">{{ fmt(globalStats.paid_this_month) }}</p>
                </div>
            </div>
        </div>

        <!-- Barre de progression globale -->
        <div v-if="totalExpected > 0" class="global-progress-card">
            <div class="flex items-center justify-between mb-sm">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#E5004C">donut_large</span>
                    <span class="font-semibold text-on-surface text-body-md">Taux de collecte global {{ selectedYear }}</span>
                </div>
                <span class="text-body-sm text-on-surface-variant">
                    {{ fmt(totalCollected) }} / {{ fmt(totalExpected) }}
                </span>
            </div>
            <div class="progress-track" style="height:10px">
                <div
                    class="progress-fill"
                    :style="`width:${Math.round(totalCollected/totalExpected*100)}%;background:${progressColor(Math.round(totalCollected/totalExpected*100))}`"
                />
            </div>
            <p class="text-body-sm text-on-surface-variant mt-xs text-right font-semibold">
                {{ Math.round(totalCollected/totalExpected*100) }}% encaissé
            </p>
        </div>

        <!-- Formations + cohortes -->
        <div v-if="byFormation.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:56px;color:#b9c7e0">payments</span>
            <p class="empty-title">Aucune cohorte pour {{ selectedYear }}</p>
            <p class="empty-sub">Choisissez une autre année ou créez des cohortes.</p>
        </div>

        <div v-for="formation in byFormation" :key="formation.id" class="formation-block">

            <!-- Formation header -->
            <div class="formation-header">
                <div class="flex items-center gap-md flex-1 min-w-0">
                    <span class="material-symbols-outlined" style="font-size:20px;color:#E5004C">local_library</span>
                    <div class="min-w-0">
                        <h2 class="font-bold text-on-surface text-body-lg truncate">{{ formation.name }}</h2>
                        <p class="text-body-sm text-on-surface-variant">{{ formation.cohorts.length }} cohorte(s)</p>
                    </div>
                </div>
                <div class="formation-totals">
                    <div class="total-chip chip-green">
                        <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
                        {{ fmt(formation.totals.total_collected) }} encaissé
                    </div>
                    <div class="total-chip chip-amber">
                        <span class="material-symbols-outlined" style="font-size:14px">schedule</span>
                        {{ fmt(formation.totals.total_remaining) }} restant
                    </div>
                    <div v-if="formation.totals.overdue_count > 0" class="total-chip chip-red">
                        <span class="material-symbols-outlined" style="font-size:14px">warning</span>
                        {{ formation.totals.overdue_count }} retard(s)
                    </div>
                    <div class="total-chip chip-gray">
                        {{ formation.totals.collect_rate }}%
                    </div>
                </div>
            </div>

            <!-- Cohorts table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="th">Cohorte</th>
                            <th class="th">Période</th>
                            <th class="th text-center">Statut</th>
                            <th class="th text-center">Apprenants</th>
                            <th class="th text-right">Planifié</th>
                            <th class="th text-right">Encaissé</th>
                            <th class="th text-right">Restant</th>
                            <th class="th text-center">Retards</th>
                            <th class="th" style="min-width:120px">Taux</th>
                            <th class="th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr
                            v-for="cohort in formation.cohorts"
                            :key="cohort.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="td font-semibold text-on-surface">{{ cohort.name }}</td>
                            <td class="td text-on-surface-variant text-body-sm">
                                {{ fmtMonth(cohort.started_at) }} → {{ fmtMonth(cohort.ended_at) }}
                            </td>
                            <td class="td text-center">
                                <span :class="['status-badge', cohortStatusClass[cohort.status]]">
                                    {{ cohortStatusLabel[cohort.status] ?? cohort.status }}
                                </span>
                            </td>
                            <td class="td text-center">
                                <span class="font-semibold">{{ cohort.learners_count }}</span>
                            </td>
                            <td class="td text-right text-body-sm text-on-surface-variant">
                                {{ cohort.total_expected > 0 ? fmtShort(cohort.total_expected) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-right font-semibold" style="color:#065f46">
                                {{ cohort.total_collected > 0 ? fmtShort(cohort.total_collected) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-right text-body-sm" :style="cohort.total_remaining > 0 ? 'color:#92400e' : 'color:#9aaabb'">
                                {{ cohort.total_remaining > 0 ? fmtShort(cohort.total_remaining) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-center">
                                <span v-if="cohort.overdue_count > 0" class="overdue-badge">
                                    {{ cohort.overdue_count }}
                                </span>
                                <span v-else class="text-on-surface-variant text-body-sm"></span>
                            </td>
                            <td class="td">
                                <div class="flex items-center gap-sm">
                                    <div class="progress-track flex-1" style="height:5px">
                                        <div
                                            class="progress-fill"
                                            :style="`width:${cohort.collect_rate}%;background:${progressColor(cohort.collect_rate)}`"
                                        />
                                    </div>
                                    <span class="text-body-sm text-on-surface-variant w-8 text-right shrink-0">
                                        {{ cohort.collect_rate }}%
                                    </span>
                                </div>
                            </td>
                            <td class="td text-right">
                                <Can :any="['campus.finance.view', 'campus.finance.collect', 'campus.finance.manage']">
                                    <Link
                                        :href="`/campus/cohorts/${cohort.id}/payments`"
                                        class="action-link"
                                        title="Gérer les paiements"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:16px">payments</span>
                                        Paiements
                                    </Link>
                                </Can>
                            </td>
                        </tr>

                        <!-- Formation totals row -->
                        <tr class="totals-row">
                            <td class="td font-bold text-on-surface" colspan="4">Total {{ formation.name }}</td>
                            <td class="td text-right text-body-sm font-semibold text-on-surface-variant">
                                {{ formation.totals.total_expected > 0 ? fmtShort(formation.totals.total_expected) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-right font-bold" style="color:#065f46">
                                {{ formation.totals.total_collected > 0 ? fmtShort(formation.totals.total_collected) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-right font-semibold" :style="formation.totals.total_remaining > 0 ? 'color:#92400e' : 'color:#9aaabb'">
                                {{ formation.totals.total_remaining > 0 ? fmtShort(formation.totals.total_remaining) + ' FCFA' : '' }}
                            </td>
                            <td class="td text-center">
                                <span v-if="formation.totals.overdue_count > 0" class="overdue-badge">
                                    {{ formation.totals.overdue_count }}
                                </span>
                                <span v-else class="text-on-surface-variant text-body-sm"></span>
                            </td>
                            <td class="td" colspan="2">
                                <div class="flex items-center gap-sm">
                                    <div class="progress-track flex-1" style="height:7px">
                                        <div
                                            class="progress-fill"
                                            :style="`width:${formation.totals.collect_rate}%;background:${progressColor(formation.totals.collect_rate)}`"
                                        />
                                    </div>
                                    <span class="text-body-sm font-bold text-on-surface w-8 text-right shrink-0">
                                        {{ formation.totals.collect_rate }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Retards de paiement -->
        <div class="bg-surface-container-lowest border rounded-xl overflow-hidden shadow-sm" style="border-color:#fca5a5">
            <div class="px-lg py-md border-b flex items-center justify-between" style="border-color:#fca5a5;background:#fff1f2">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#dc2626">schedule</span>
                    <h2 class="text-h2 font-semibold" style="color:#991b1b">
                        Échéances en retard
                        <span v-if="recentOverdue.length" class="ml-sm" style="font-weight:400;font-size:13px;color:#dc2626">
                            ({{ recentOverdue.length }} affichée(s))
                        </span>
                    </h2>
                </div>
                <span class="text-body-sm text-on-surface-variant">Session {{ selectedYear }}</span>
            </div>
            <div v-if="recentOverdue.length === 0" class="px-lg py-xl text-center">
                <span class="material-symbols-outlined" style="font-size:40px;color:#10b981;display:block;margin-bottom:8px">check_circle</span>
                <p class="font-semibold" style="color:#065f46">Aucun retard de paiement pour cette session</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="th">Apprenant</th>
                            <th class="th">Cohorte</th>
                            <th class="th text-right">Montant</th>
                            <th class="th text-right">Échéance</th>
                            <th class="th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr
                            v-for="p in recentOverdue"
                            :key="p.id"
                            class="hover:bg-surface-bright transition-colors"
                        >
                            <td class="td font-semibold text-on-surface">
                                {{ p.learner.first_name }} {{ p.learner.last_name }}
                            </td>
                            <td class="td text-body-sm text-on-surface-variant">
                                {{ p.cohort.campus_formation.name }} · {{ p.cohort.name }}
                            </td>
                            <td class="td text-right font-bold" style="color:#dc2626">{{ fmt(p.amount) }}</td>
                            <td class="td text-right text-body-sm text-on-surface-variant">{{ fmtDate(p.due_date) }}</td>
                            <td class="td text-right">
                                <Link
                                    :href="`/campus/cohorts/${p.cohort.id}/payments`"
                                    class="action-link"
                                >
                                    <span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>
                                    Voir
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>

<style scoped>
.page-header-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* Year select */
.year-select {
    padding: 8px 32px 8px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 16px;
}
.year-select:focus { border-color: #E5004C; }

/* KPIs */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}
@media (max-width: 1280px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.kpi-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}
.kpi-blue    { background: #dbeafe; color: #1d4ed8; }
.kpi-green   { background: #d1fae5; color: #065f46; }
.kpi-amber   { background: #fef3c7; color: #92400e; }
.kpi-red     { background: #fee2e2; color: #dc2626; }
.kpi-primary { background: #fff0f3; color: #E5004C; }
.kpi-label { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-val   { font-size: 16px; font-weight: 700; color: #191c1e; margin-top: 2px; }
.kpi-sub   { font-size: 10px; color: #9aaabb; margin-top: 2px; }

/* Global progress card */
.global-progress-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

/* Formation block */
.formation-block {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.formation-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e3e5;
    flex-wrap: wrap;
}

.formation-totals {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto;
}

.total-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}
.chip-green { background: #d1fae5; color: #065f46; }
.chip-amber { background: #fef3c7; color: #92400e; }
.chip-red   { background: #fee2e2; color: #dc2626; }
.chip-gray  { background: #f3f4f6; color: #374151; }

/* Table */
.th {
    padding: 8px 16px;
    font-size: 11px;
    font-weight: 600;
    color: #9aaabb;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.td {
    padding: 10px 16px;
    font-size: 13px;
    color: #191c1e;
    white-space: nowrap;
}

.totals-row {
    background: #f8f9fa;
}
.totals-row .td {
    border-top: 2px solid #e0e3e5;
    font-size: 12px;
}

/* Progress bar */
.progress-track {
    height: 6px;
    border-radius: 99px;
    background: #e0e3e5;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.5s ease;
}

/* Status badges */
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

/* Overdue badge */
.overdue-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    padding: 2px 6px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    background: #fee2e2;
    color: #dc2626;
}

/* Action link */
.action-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #E5004C;
    text-decoration: none;
    border: 1px solid #fbb6ce;
    background: #fff0f3;
    transition: all 0.15s;
}
.action-link:hover { background: #fce7f3; border-color: #E5004C; }

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    text-align: center;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
}
.empty-title { font-size: 18px; font-weight: 600; color: #191c1e; margin: 16px 0 4px; }
.empty-sub { font-size: 14px; color: #9aaabb; }
</style>
