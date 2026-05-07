<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

defineOptions({ layout: AdminLayout })

interface AttendanceCode { value: string; label: string; color: string }
interface LearnerRow {
    id: string
    full_name: string
    attendance: { code: string; comment: string | null } | null
    rate: number | null
}
interface RecapRow {
    id: string
    full_name: string
    days: Record<string, string | null>
    total: number
    present: number
    rate: number | null
}
interface Formation { id: string; name: string; project: { name: string } }

const props = defineProps<{
    formation: Formation
    date: string
    learners: LearnerRow[]
    codes: AttendanceCode[]
    savedDates: string[]
    recap?: {
        dates: string[]
        rows: RecapRow[]
        dayStats: Record<string, { present: number; total: number }>
    }
}>()

// ── Onglet actif ─────────────────────────────────────────────────
const tab = ref<'saisie' | 'recap'>(props.recap ? 'recap' : 'saisie')

// ── Saisie du jour ────────────────────────────────────────────────
const currentDate = ref(props.date)
const saving = ref<Record<string, boolean>>({})
const saved  = ref<Record<string, boolean>>({})
const searchQuery = ref('')

const records = ref<Record<string, string>>(
    Object.fromEntries(props.learners.map(l => [l.id, l.attendance?.code ?? 'P']))
)

watch(() => props.learners, (newLearners) => {
    records.value = Object.fromEntries(newLearners.map(l => [l.id, l.attendance?.code ?? 'P']))
    saved.value = {}
})
watch(() => props.date, (d) => { currentDate.value = d })

async function setCode(learnerId: string, code: string) {
    records.value[learnerId] = code
    saving.value[learnerId]  = true
    saved.value[learnerId]   = false
    try {
        await axios.post(`/formations/${props.formation.id}/attendances/single`, {
            learner_id: learnerId,
            date: currentDate.value,
            code,
        })
        saved.value[learnerId] = true
        setTimeout(() => { saved.value[learnerId] = false }, 2000)
    } finally {
        saving.value[learnerId] = false
    }
}

function goToDate(d: string) {
    currentDate.value = d
    router.visit(`/formations/${props.formation.id}/attendances?date=${d}`, {
        preserveScroll: true,
        preserveState: false,
    })
}

function prevDay() {
    const idx = props.savedDates.indexOf(currentDate.value)
    if (idx > 0) { goToDate(props.savedDates[idx - 1]); return }
    const d = new Date(currentDate.value)
    d.setDate(d.getDate() - 1)
    goToDate(d.toISOString().slice(0, 10))
}
function nextDay() {
    const idx = props.savedDates.indexOf(currentDate.value)
    if (idx >= 0 && idx < props.savedDates.length - 1) { goToDate(props.savedDates[idx + 1]); return }
    const d = new Date(currentDate.value)
    d.setDate(d.getDate() + 1)
    goToDate(d.toISOString().slice(0, 10))
}

const stats = computed(() => {
    const v = Object.values(records.value)
    return {
        present: v.filter(x => x === 'P').length,
        lateJ:   v.filter(x => x === 'RJ').length,
        lateN:   v.filter(x => x === 'RN').length,
        absentJ: v.filter(x => x === 'AJ').length,
        absentN: v.filter(x => x === 'AN').length,
        total:   v.length,
    }
})
const presentRate = computed(() =>
    stats.value.total > 0
        ? Math.round((stats.value.present + stats.value.lateJ + stats.value.lateN) / stats.value.total * 100)
        : 0
)

const codeBtnClass: Record<string, string> = {
    P: 'btn-P', AJ: 'btn-AJ', AN: 'btn-AN', RJ: 'btn-RJ', RN: 'btn-RN',
}

// ── Filtre de recherche ───────────────────────────────────────────
const filteredLearners = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return props.learners
    return props.learners.filter(l => l.full_name.toLowerCase().includes(q))
})

// ── Récapitulatif ─────────────────────────────────────────────────
function loadRecap() {
    router.visit(`/formations/${props.formation.id}/attendances/recap`, {
        preserveScroll: true,
    })
}

function formatDay(d: string) {
    return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}
function formatDateFull(d: string) {
    return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'long' })
}

const recapCodeClass: Record<string, string> = {
    P: 'rc-P', AJ: 'rc-AJ', AN: 'rc-AN', RJ: 'rc-RJ', RN: 'rc-RN',
}
</script>

<template>
    <div class="att-page">

        <!-- En-tête formation -->
        <div class="page-header">
            <div>
                <p class="header-project">
                    <span class="material-symbols-outlined" style="font-size:14px">folder_open</span>
                    {{ formation.project.name }}
                </p>
                <h1 class="header-title">{{ formation.name }}</h1>
            </div>
            <div style="display:flex;gap:8px;">
                <a :href="`/formations/${formation.id}/attendances/pdf?date=${currentDate}`" class="btn-outline">
                    <span class="material-symbols-outlined" style="font-size:16px">picture_as_pdf</span>
                    PDF du jour
                </a>
                <a :href="`/formations/${formation.id}/attendances/pdf-recap`" class="btn-outline btn-outline-primary">
                    <span class="material-symbols-outlined" style="font-size:16px">summarize</span>
                    PDF récapitulatif
                </a>
            </div>
        </div>

        <!-- Onglets -->
        <div class="tabs-bar">
            <button class="tab-btn" :class="{ 'tab-active': tab === 'saisie' }" @click="tab = 'saisie'; router.visit(`/formations/${formation.id}/attendances?date=${currentDate}`)">
                <span class="material-symbols-outlined" style="font-size:17px">edit_note</span>
                Saisie du jour
            </button>
            <button class="tab-btn" :class="{ 'tab-active': tab === 'recap' }" @click="loadRecap">
                <span class="material-symbols-outlined" style="font-size:17px">table_chart</span>
                Récapitulatif complet
            </button>
        </div>

        <!-- ════════════════════════════════════════════ -->
        <!-- ONGLET SAISIE                               -->
        <!-- ════════════════════════════════════════════ -->
        <template v-if="tab === 'saisie'">

            <!-- Navigation date -->
            <div class="date-nav">
                <button class="nav-arrow" @click="prevDay" title="Jour précédent">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <div class="date-center">
                    <input
                        type="date"
                        :value="currentDate"
                        @change="goToDate(($event.target as HTMLInputElement).value)"
                        class="date-picker"
                    />
                    <span class="date-label">{{ formatDateFull(currentDate) }}</span>
                </div>
                <button class="nav-arrow" @click="nextDay" title="Jour suivant">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>

            <!-- Barre de progression -->
            <div class="progress-wrap">
                <div class="progress-top">
                    <span class="progress-title">Taux de présence du jour</span>
                    <span class="progress-rate" :class="presentRate >= 80 ? 'r-good' : presentRate >= 50 ? 'r-mid' : 'r-low'">
                        {{ presentRate }}%
                    </span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" :style="{ width: presentRate + '%' }"
                        :class="presentRate >= 80 ? 'fill-good' : presentRate >= 50 ? 'fill-mid' : 'fill-low'">
                    </div>
                </div>
                <div class="progress-stats">
                    <span class="ps ps-P">{{ stats.present }} présents</span>
                    <span class="ps ps-R">{{ stats.lateJ + stats.lateN }} retards</span>
                    <span class="ps ps-A">{{ stats.absentJ + stats.absentN }} absents</span>
                    <span class="ps ps-T">{{ stats.total }} apprenants</span>
                </div>
            </div>

            <!-- Légende -->
            <div class="legend-bar">
                <span v-for="c in codes" :key="c.value" class="legend-item">
                    <span class="legend-dot" :class="`dot-${c.value}`"></span>
                    <strong>{{ c.value }}</strong> — {{ c.label }}
                </span>
            </div>

            <!-- Barre de recherche -->
            <div class="search-bar">
                <span class="material-symbols-outlined" style="font-size:18px;color:#9aaabb">search</span>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Rechercher un apprenant..."
                    class="search-input"
                />
                <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''">
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
                <span class="search-count" v-if="searchQuery">
                    {{ filteredLearners.length }}/{{ learners.length }}
                </span>
            </div>

            <!-- Liste apprenants -->
            <div class="learner-list">
                <div v-if="filteredLearners.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:40px;color:#ddd">groups</span>
                    <p>Aucun apprenant actif dans cette formation.</p>
                </div>
                <div
                    v-for="(learner, idx) in filteredLearners"
                    :key="learner.id"
                    class="learner-row"
                    :class="`row-${records[learner.id]}`"
                >
                    <div class="learner-left">
                        <span class="l-num">{{ String(learners.findIndex(l => l.id === learner.id) + 1).padStart(2, '0') }}</span>
                        <span class="l-name">{{ learner.full_name }}</span>
                    </div>
                    <div class="code-btns">
                        <button
                            v-for="c in codes"
                            :key="c.value"
                            type="button"
                            class="code-btn"
                            :class="[codeBtnClass[c.value], { 'active': records[learner.id] === c.value }]"
                            @click="setCode(learner.id, c.value)"
                            :title="c.label"
                        >
                            {{ c.value }}
                        </button>
                        <span v-if="saving[learner.id]" class="si si-saving">
                            <span class="material-symbols-outlined" style="font-size:15px">sync</span>
                        </span>
                        <span v-else-if="saved[learner.id]" class="si si-saved">
                            <span class="material-symbols-outlined" style="font-size:15px">check_circle</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Jours de cours enregistrés -->
            <div v-if="savedDates.length > 0" class="dates-panel">
                <p class="dates-title">
                    <span class="material-symbols-outlined" style="font-size:14px">calendar_month</span>
                    Jours de cours enregistrés ({{ savedDates.length }})
                </p>
                <div class="dates-grid">
                    <button
                        v-for="d in savedDates"
                        :key="d"
                        class="date-chip"
                        :class="{ 'chip-active': d === currentDate }"
                        @click="goToDate(d)"
                        :title="formatDateFull(d)"
                    >
                        {{ formatDay(d) }}
                    </button>
                </div>
            </div>

        </template>

        <!-- ════════════════════════════════════════════ -->
        <!-- ONGLET RÉCAPITULATIF                        -->
        <!-- ════════════════════════════════════════════ -->
        <template v-else-if="recap">

            <div v-if="recap.dates.length === 0" class="empty-state">
                <span class="material-symbols-outlined" style="font-size:40px;color:#ddd">fact_check</span>
                <p>Aucune présence enregistrée pour cette formation.</p>
            </div>

            <div v-else class="recap-container">
                <div class="recap-scroll">
                    <table class="recap-table">
                        <thead>
                            <tr>
                                <th class="th-sticky th-name">Apprenant</th>
                                <th v-for="d in recap.dates" :key="d" class="th-day" :title="formatDateFull(d)">
                                    {{ formatDay(d) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in recap.rows" :key="row.id" class="recap-tr">
                                <td class="td-sticky td-name">{{ row.full_name }}</td>
                                <td v-for="d in recap.dates" :key="d" class="td-day">
                                    <span v-if="row.days[d]" class="rc-badge" :class="recapCodeClass[row.days[d]!]">
                                        {{ row.days[d] }}
                                    </span>
                                    <span v-else class="rc-empty">—</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="recap-foot">
                                <td class="td-sticky td-foot-label">Présents / Total</td>
                                <td v-for="d in recap.dates" :key="d" class="td-day">
                                    <span class="day-stat">
                                        {{ recap.dayStats[d]?.present }}/{{ recap.dayStats[d]?.total }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Légende récap -->
                <div class="legend-bar" style="margin-top:10px">
                    <span v-for="c in codes" :key="c.value" class="legend-item">
                        <span class="rc-badge" :class="`rc-${c.value}`">{{ c.value }}</span>
                        {{ c.label }}
                    </span>
                </div>
            </div>

        </template>

        <template v-else>
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size:40px;color:#ddd">hourglass_empty</span>
                <p>Chargement…</p>
            </div>
        </template>

    </div>
</template>

<style scoped>
.att-page { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }

/* ── Header ── */
.page-header {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
    padding: 18px 22px; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
}
.header-project {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; color: #9aaabb;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px;
}
.header-title { font-size: 20px; font-weight: 700; color: #191c1e; }
.btn-outline {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border: 1px solid #e0e3e5; border-radius: 6px;
    font-size: 13px; color: #515f74; background: #fff; text-decoration: none;
    transition: background 0.15s; white-space: nowrap;
}
.btn-outline:hover { background: #f5f7f9; }
.btn-outline-primary { background: #fff0f5; border-color: #E5004C; color: #E5004C; }
.btn-outline-primary:hover { background: #ffe0ec; }

/* ── Onglets ── */
.tabs-bar {
    display: flex; gap: 4px;
    background: #f5f7f9; border-radius: 10px; padding: 4px;
}
.tab-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 20px; border: none; border-radius: 7px;
    font-size: 13px; font-weight: 600; color: #515f74;
    background: transparent; cursor: pointer; transition: background 0.15s, color 0.15s;
}
.tab-btn:hover { background: #eceef0; }
.tab-active { background: #fff !important; color: #E5004C !important; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

/* ── Navigation date ── */
.date-nav {
    display: flex; align-items: center; justify-content: center; gap: 12px;
    padding: 12px 16px; background: #fff; border: 1px solid #e0e3e5; border-radius: 10px;
}
.nav-arrow {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border: 1px solid #e0e3e5; border-radius: 50%;
    background: #fff; cursor: pointer; color: #515f74; transition: all 0.15s;
}
.nav-arrow:hover { background: #f5f7f9; border-color: #E5004C; color: #E5004C; }
.date-center { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.date-picker {
    padding: 7px 14px; border: 1px solid #e0e3e5; border-radius: 6px;
    font-size: 14px; color: #191c1e; background: #fff; outline: none; cursor: pointer; text-align: center;
}
.date-picker:focus { border-color: #E5004C; }
.date-label { font-size: 12px; color: #9aaabb; font-weight: 500; text-transform: capitalize; }

/* ── Progression ── */
.progress-wrap {
    padding: 14px 18px; background: #fff; border: 1px solid #e0e3e5; border-radius: 10px;
    display: flex; flex-direction: column; gap: 8px;
}
.progress-top { display: flex; justify-content: space-between; align-items: center; }
.progress-title { font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.05em; }
.progress-rate { font-size: 24px; font-weight: 700; }
.r-good { color: #15803d; } .r-mid { color: #d97706; } .r-low { color: #b91c1c; }
.progress-track { height: 8px; background: #f0f1f3; border-radius: 99px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.fill-good { background: #22c55e; } .fill-mid { background: #eab308; } .fill-low { background: #ef4444; }
.progress-stats { display: flex; flex-wrap: wrap; gap: 12px; }
.ps { font-size: 12px; font-weight: 600; }
.ps-P { color: #15803d; } .ps-R { color: #d97706; } .ps-A { color: #b91c1c; } .ps-T { color: #9aaabb; }

/* ── Barre de recherche ── */
.search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
}
.search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #191c1e;
    background: transparent;
    padding: 4px 0;
}
.search-input::placeholder { color: #9aaabb; }
.search-clear {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border: none;
    border-radius: 50%;
    background: #f5f7f9;
    color: #515f74;
    cursor: pointer;
    transition: background 0.15s;
}
.search-clear:hover { background: #eceef0; }
.search-count {
    font-size: 12px;
    font-weight: 600;
    color: #9aaabb;
    padding-left: 4px;
    border-left: 1px solid #e0e3e5;
    margin-left: 4px;
}

/* ── Légende ── */
.legend-bar {
    display: flex; flex-wrap: wrap; gap: 10px 18px;
    padding: 10px 14px; background: #fafbfc; border: 1px solid #f0f1f3;
    border-radius: 8px; font-size: 12px; color: #515f74;
}
.legend-item { display: flex; align-items: center; gap: 6px; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot-P { background: #22c55e; } .dot-AJ { background: #3b82f6; } .dot-AN { background: #ef4444; }
.dot-RJ { background: #eab308; } .dot-RN { background: #f97316; }

/* ── Liste apprenants ── */
.learner-list { display: flex; flex-direction: column; gap: 5px; }
.empty-state {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 48px 20px; background: #fff; border: 1px dashed #e0e3e5;
    border-radius: 10px; color: #9aaabb; font-size: 14px;
}
.learner-row {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 10px 16px; background: #fff; border: 1px solid #eceef0;
    border-radius: 8px; transition: background 0.1s;
}
.row-AJ { background: rgba(59,130,246,0.04);  border-color: rgba(59,130,246,0.2); }
.row-AN { background: rgba(239,68,68,0.04);   border-color: rgba(239,68,68,0.2); }
.row-RJ { background: rgba(234,179,8,0.05);   border-color: rgba(234,179,8,0.2); }
.row-RN { background: rgba(249,115,22,0.04);  border-color: rgba(249,115,22,0.2); }

.learner-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.l-num  { font-size: 11px; color: #c4cdd6; font-weight: 600; width: 20px; flex-shrink: 0; }
.l-name { font-size: 14px; font-weight: 600; color: #191c1e; }
.l-rate {
    font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 99px; flex-shrink: 0;
}

/* Boutons de code */
.code-btns { display: flex; align-items: center; gap: 5px; }
.code-btn {
    padding: 5px 11px; border-radius: 6px; font-size: 12px; font-weight: 700;
    border: 2px solid transparent; cursor: pointer;
    transition: opacity 0.1s, transform 0.1s, background 0.1s;
    opacity: 0.3; background: transparent;
}
.code-btn:hover { opacity: 0.75; }
.code-btn.active { opacity: 1 !important; transform: scale(1.08); }

.btn-P  { border-color: #22c55e; color: #15803d; background: #f0fdf4; }
.btn-AJ { border-color: #3b82f6; color: #1d4ed8; background: #eff6ff; }
.btn-AN { border-color: #ef4444; color: #b91c1c; background: #fef2f2; }
.btn-RJ { border-color: #eab308; color: #a16207; background: #fefce8; }
.btn-RN { border-color: #f97316; color: #c2410c; background: #fff7ed; }

.btn-P.active  { background: #22c55e; color: #fff; border-color: #22c55e; }
.btn-AJ.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.btn-AN.active { background: #ef4444; color: #fff; border-color: #ef4444; }
.btn-RJ.active { background: #eab308; color: #fff; border-color: #eab308; }
.btn-RN.active { background: #f97316; color: #fff; border-color: #f97316; }

.si { display: inline-flex; align-items: center; margin-left: 2px; }
.si-saving { color: #9aaabb; animation: spin 0.8s linear infinite; }
.si-saved  { color: #22c55e; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Jours enregistrés ── */
.dates-panel {
    padding: 14px 18px; background: #fff; border: 1px solid #e0e3e5; border-radius: 10px;
}
.dates-title {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 700; color: #9aaabb;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px;
}
.dates-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.date-chip {
    padding: 4px 10px; border: 1px solid #e0e3e5; border-radius: 6px;
    font-size: 12px; font-weight: 600; color: #515f74; background: #f5f7f9;
    cursor: pointer; transition: all 0.15s;
}
.date-chip:hover { background: #eceef0; border-color: #E5004C; color: #E5004C; }
.chip-active { background: #E5004C !important; color: #fff !important; border-color: #E5004C !important; }

/* ── Récapitulatif ── */
.recap-container { display: flex; flex-direction: column; gap: 6px; }
.recap-scroll { overflow-x: auto; border-radius: 10px; border: 1px solid #e0e3e5; }
.recap-table { border-collapse: collapse; width: 100%; font-size: 12px; background: #fff; }
.recap-table thead { background: #f5f7f9; }

.th-sticky, .td-sticky {
    position: sticky; left: 0; z-index: 2; background: inherit;
}
.th-name {
    padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700;
    color: #515f74; text-transform: uppercase; white-space: nowrap; min-width: 170px;
    border-right: 1px solid #e0e3e5;
}
.th-day {
    padding: 10px 6px; text-align: center; font-size: 11px; font-weight: 700;
    color: #515f74; white-space: nowrap; min-width: 50px;
}
.th-rate, .th-count {
    padding: 10px 10px; text-align: center; font-size: 11px; font-weight: 700;
    color: #515f74; white-space: nowrap;
}
.recap-tr { border-top: 1px solid #f0f1f3; }
.recap-tr:hover { background: #fafbfc; }
.recap-tr:hover .td-sticky { background: #fafbfc; }
.td-name {
    padding: 8px 14px; font-weight: 600; color: #191c1e; white-space: nowrap;
    background: #fff; border-right: 1px solid #f0f1f3;
}
.td-day { padding: 6px 4px; text-align: center; }
.td-rate, .td-count { padding: 6px 10px; text-align: center; }

.rc-badge {
    display: inline-block; padding: 2px 6px; border-radius: 4px;
    font-size: 11px; font-weight: 700;
}
.rc-P  { background: #d1fae5; color: #065f46; }
.rc-AJ { background: #dbeafe; color: #1e40af; }
.rc-AN { background: #fee2e2; color: #991b1b; }
.rc-RJ { background: #fef9c3; color: #854d0e; }
.rc-RN { background: #ffedd5; color: #9a3412; }
.rc-empty { color: #d1d5db; font-size: 11px; }

.rate-badge {
    display: inline-block; padding: 2px 8px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
}
.rb-good { background: #d1fae5; color: #065f46; }
.rb-mid  { background: #fef9c3; color: #854d0e; }
.rb-low  { background: #fee2e2; color: #991b1b; }
.rb-none { background: #f3f4f6; color: #9ca3af; }

.day-stat { font-size: 11px; font-weight: 600; color: #515f74; }
.recap-foot { background: #f5f7f9; border-top: 2px solid #e0e3e5; }
.td-foot-label {
    padding: 8px 14px; font-size: 11px; font-weight: 700; color: #515f74;
    background: #f5f7f9; white-space: nowrap; border-right: 1px solid #e0e3e5;
}
</style>
