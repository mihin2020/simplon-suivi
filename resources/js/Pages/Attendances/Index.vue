<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { router, Link, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

defineOptions({ layout: AdminLayout })

interface CodeOption { value: string; label: string; color: string; acceptsReason: boolean }
interface LearnerRow {
    id: string
    name: string
    enrolled_from: string | null
    joined_today: boolean
    not_enrolled_yet: boolean
    attendance: { code: string; comment: string | null } | null
}
interface StripDay { date: string; state: 'empty' | 'partial' | 'complete' }
interface RecapCell { enrolled: boolean; code: string | null; comment: string | null }
interface RecapRow {
    id: string
    name: string
    days: Record<string, RecapCell>
    counts: Record<string, number>
    absences: number
    expected: number
    recorded: number
    rate: number | null
}
interface RecapWeek { number: number | null; start: string; end: string; days: string[] }
interface RecapPeriod { view: 'week' | 'month' | 'all'; label: string; prev: string | null; next: string | null; anchor: string }
interface Recap {
    period: RecapPeriod | null
    days: string[]
    weeks: RecapWeek[]
    rows: RecapRow[]
    dayStats: Record<string, { present: number; expected: number }>
    reasons: { learner: string; date: string; code: string; reason: string }[]
}

const props = defineProps<{
    formation: { id: string; name: string; project: { name: string } }
    date: string
    dayType: { value: string; label: string | null }
    isCourseDay: boolean
    isFuture: boolean
    allowExtra: boolean
    learners: LearnerRow[]
    dayStrip: StripDay[]
    codes: CodeOption[]
    recap?: Recap
    attendanceSettings: { absenceAlertThreshold: number | null }
}>()

const baseUrl = computed(() => `/formations/${props.formation.id}/attendances`)

// ── Helpers de date ──────────────────────────────────────────────
const toDate = (d: string) => new Date(d + 'T00:00:00')
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
const weekdayShort = (d: string) => capitalize(toDate(d).toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', ''))
const dayNumber = (d: string) => toDate(d).getDate()
const formatShort = (d: string) => toDate(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
const formatDateFull = (d: string) => capitalize(toDate(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))
const mondayOf = (d: string) => {
    const date = toDate(d)
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7))
    return date.toLocaleDateString('sv-SE')
}

const tab = ref<'saisie' | 'recap'>(props.recap ? 'recap' : 'saisie')

// ── Saisie du jour ────────────────────────────────────────────────
interface RowState { code: string | null; comment: string }

const states = ref<Record<string, RowState>>({})
const saving = ref<Record<string, boolean>>({})
const saved = ref<Record<string, boolean>>({})
const errorMessage = ref('')
const searchQuery = ref('')

function resetStates() {
    states.value = Object.fromEntries(
        props.learners.map(l => [l.id, { code: l.attendance?.code ?? null, comment: l.attendance?.comment ?? '' }]),
    )
    saved.value = {}
}
resetStates()
watch(() => props.learners, resetStates)

const acceptsReason = (code: string | null) => !!code && !!props.codes.find(c => c.value === code)?.acceptsReason
const canRecord = computed(() => !props.isFuture && (props.isCourseDay || props.allowExtra))

const activeRows = computed(() => props.learners.filter(l => !l.not_enrolled_yet))
const pendingRows = computed(() => props.learners.filter(l => l.not_enrolled_yet))

const visibleRows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return q ? activeRows.value.filter(l => l.name.toLowerCase().includes(q)) : activeRows.value
})

let refreshTimer: ReturnType<typeof setTimeout> | undefined
function refreshStrip() {
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => router.reload({ only: ['dayStrip'], preserveScroll: true, preserveState: true }), 700)
}

async function persist(learnerId: string) {
    const state = states.value[learnerId]
    if (!state.code) return
    saving.value[learnerId] = true
    errorMessage.value = ''
    try {
        await axios.post(`${baseUrl.value}/single`, {
            learner_id: learnerId,
            date: props.date,
            code: state.code,
            comment: acceptsReason(state.code) ? state.comment : null,
        })
        saved.value[learnerId] = true
        setTimeout(() => { saved.value[learnerId] = false }, 1800)
        refreshStrip()
    } catch (e: any) {
        errorMessage.value = e.response?.data?.errors
            ? Object.values<string[]>(e.response.data.errors).flat()[0]
            : "L'enregistrement a échoué. Vérifiez votre connexion et réessayez."
        throw e
    } finally {
        saving.value[learnerId] = false
    }
}

async function setCode(learnerId: string, code: string) {
    if (!canRecord.value) return
    const previous = { ...states.value[learnerId] }
    states.value[learnerId].code = code
    if (!acceptsReason(code)) states.value[learnerId].comment = ''
    try {
        await persist(learnerId)
    } catch {
        states.value[learnerId] = previous
    }
}

const commentTimers: Record<string, ReturnType<typeof setTimeout>> = {}
function onCommentInput(learnerId: string) {
    clearTimeout(commentTimers[learnerId])
    commentTimers[learnerId] = setTimeout(() => persist(learnerId).catch(() => {}), 700)
}
function setReason(learnerId: string, reason: string) {
    states.value[learnerId].comment = reason
    clearTimeout(commentTimers[learnerId])
    persist(learnerId).catch(() => {})
}

const REASON_SUGGESTIONS = ['Maladie', 'Raison familiale', 'Transport', 'Rendez-vous', 'Autre engagement']

const unrecordedIds = computed(() => activeRows.value.filter(l => !states.value[l.id]?.code).map(l => l.id))

function markAllPresent() {
    if (!canRecord.value || unrecordedIds.value.length === 0) return
    router.post(baseUrl.value, {
        date: props.date,
        records: Object.fromEntries(unrecordedIds.value.map(id => [id, { code: 'P' }])),
    }, { preserveScroll: true })
}

const stats = computed(() => {
    const codes = activeRows.value.map(l => states.value[l.id]?.code)
    const count = (...wanted: string[]) => codes.filter(c => c && wanted.includes(c)).length
    const recorded = codes.filter(Boolean).length
    return {
        present: count('P'),
        late: count('RJ', 'RN'),
        absent: count('AJ', 'AN'),
        recorded,
        total: codes.length,
        rate: recorded > 0 ? Math.round(count('P', 'RJ', 'RN') / recorded * 100) : 0,
    }
})

// ── Navigation entre les jours ────────────────────────────────────
const stripDates = computed(() => props.dayStrip.map(d => d.date))
const prevDate = computed(() => [...stripDates.value].reverse().find(d => d < props.date) ?? null)
const nextDate = computed(() => stripDates.value.find(d => d > props.date) ?? null)
const lastCourseDate = computed(() => stripDates.value[stripDates.value.length - 1] ?? null)

function goToDate(d: string | null, extra = false) {
    if (!d) return
    router.get(baseUrl.value, extra ? { date: d, extra: 1 } : { date: d }, { preserveScroll: true, preserveState: false })
}

const stripGroups = computed(() => {
    const groups: { monday: string; days: StripDay[] }[] = []
    for (const day of props.dayStrip) {
        const monday = mondayOf(day.date)
        const last = groups[groups.length - 1]
        if (last && last.monday === monday) last.days.push(day)
        else groups.push({ monday, days: [day] })
    }
    return groups
})

const stripEl = ref<HTMLElement | null>(null)
function centerActiveChip() {
    nextTick(() => {
        stripEl.value?.querySelector<HTMLElement>('.chip-active')
            ?.scrollIntoView({ inline: 'center', block: 'nearest' })
    })
}
onMounted(centerActiveChip)
watch(() => props.date, centerActiveChip)

// ── Récapitulatif ─────────────────────────────────────────────────
function loadRecap(view: 'week' | 'month' | 'all', anchor?: string | null) {
    router.get(`${baseUrl.value}/recap`, { view, anchor: anchor ?? props.recap?.period?.anchor }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function openTab(target: 'saisie' | 'recap') {
    tab.value = target
    if (target === 'saisie') router.get(baseUrl.value, { date: props.date }, { preserveScroll: true })
    else router.get(`${baseUrl.value}/recap`, { view: 'week' }, { preserveScroll: true })
}

const recapPdfUrl = computed(() => {
    const period = props.recap?.period
    return period
        ? `${baseUrl.value}/pdf-recap?view=${period.view}&anchor=${period.anchor}`
        : `${baseUrl.value}/pdf-recap?view=all`
})

const CODE_ORDER = ['P', 'AJ', 'AN', 'RJ', 'RN']
const recapCodeClass: Record<string, string> = { P: 'rc-P', AJ: 'rc-AJ', AN: 'rc-AN', RJ: 'rc-RJ', RN: 'rc-RN' }

function rowSummary(row: RecapRow) {
    return CODE_ORDER.filter(c => row.counts[c]).map(c => ({ code: c, count: row.counts[c] }))
}
function isRowAlert(row: RecapRow): boolean {
    const threshold = props.attendanceSettings.absenceAlertThreshold
    return !!threshold && threshold > 0 && row.absences >= threshold
}
function weekTitle(week: RecapWeek): string {
    const range = `${formatShort(week.start)} – ${formatShort(week.end)}`
    return week.number ? `Semaine ${week.number} · ${range}` : range
}
function rateClass(rate: number | null): string {
    if (rate === null) return 'rb-none'
    return rate >= 80 ? 'rb-good' : rate >= 50 ? 'rb-mid' : 'rb-low'
}
</script>

<template>
    <Head :title="`Présences · ${formation.name}`" />
    <div class="att-page">

        <!-- En-tête formation -->
        <div class="page-header">
            <div class="header-left">
                <Link href="/presences" class="btn-back" title="Retour aux présences">
                    <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
                </Link>
                <div>
                    <p class="header-project">
                        <span class="material-symbols-outlined" style="font-size:14px">folder_open</span>
                        {{ formation.project.name }}
                    </p>
                    <h1 class="header-title">{{ formation.name }}</h1>
                </div>
            </div>
            <div class="header-actions">
                <a v-if="tab === 'saisie'" :href="`${baseUrl}/pdf?date=${date}`" class="btn-pdf">
                    <span class="material-symbols-outlined" style="font-size:16px">picture_as_pdf</span>
                    Feuille du jour (PDF)
                </a>
                <a :href="recapPdfUrl" class="btn-pdf-primary">
                    <span class="material-symbols-outlined" style="font-size:16px">summarize</span>
                    {{ tab === 'recap' && recap?.period && recap.period.view !== 'all' ? 'PDF de la période' : 'PDF récapitulatif' }}
                </a>
            </div>
        </div>

        <!-- Onglets -->
        <div class="tabs-bar">
            <button class="tab-btn" :class="{ 'tab-active': tab === 'saisie' }" @click="openTab('saisie')">
                <span class="material-symbols-outlined" style="font-size:17px">edit_note</span>
                Saisie du jour
            </button>
            <button class="tab-btn" :class="{ 'tab-active': tab === 'recap' }" @click="openTab('recap')">
                <span class="material-symbols-outlined" style="font-size:17px">table_chart</span>
                Récapitulatif
            </button>
        </div>

        <!-- ════════════════ SAISIE ════════════════ -->
        <template v-if="tab === 'saisie'">

            <!-- Zone collante : dates + jours de cours + avancement -->
            <div class="sticky-zone">
                <div class="date-nav">
                    <button class="nav-arrow" :disabled="!prevDate" @click="goToDate(prevDate)" title="Jour de cours précédent">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <div class="date-center">
                        <span class="date-label">{{ formatDateFull(date) }}</span>
                        <input type="date" :value="date" class="date-picker"
                            @change="goToDate(($event.target as HTMLInputElement).value)" />
                    </div>
                    <button class="nav-arrow" :disabled="!nextDate" @click="goToDate(nextDate)" title="Jour de cours suivant">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                    <button class="btn-today" :disabled="!lastCourseDate || date === lastCourseDate" @click="goToDate(lastCourseDate)">
                        Dernier jour
                    </button>
                </div>

                <div v-if="dayStrip.length" ref="stripEl" class="strip">
                    <div v-for="group in stripGroups" :key="group.monday" class="strip-week">
                        <button v-for="d in group.days" :key="d.date" class="chip" type="button"
                            :class="[`chip-${d.state}`, { 'chip-active': d.date === date }]"
                            :title="`${formatDateFull(d.date)} · ${d.state === 'complete' ? 'saisie complète' : d.state === 'partial' ? 'saisie incomplète' : 'non saisi'}`"
                            @click="goToDate(d.date)">
                            <span class="chip-wd">{{ weekdayShort(d.date) }}</span>
                            <span class="chip-day">{{ dayNumber(d.date) }}</span>
                            <span class="chip-dot"></span>
                        </button>
                    </div>
                </div>

                <div v-if="canRecord && activeRows.length" class="progress-line">
                    <span class="pl-item pl-recorded">
                        <strong>{{ stats.recorded }}/{{ stats.total }}</strong> saisis
                    </span>
                    <span class="pl-item pl-P">{{ stats.present }} présents</span>
                    <span class="pl-item pl-R">{{ stats.late }} retards</span>
                    <span class="pl-item pl-A">{{ stats.absent }} absents</span>
                    <span v-if="stats.recorded" class="pl-rate" :class="stats.rate >= 80 ? 'r-good' : stats.rate >= 50 ? 'r-mid' : 'r-low'">
                        {{ stats.rate }}% de présence
                    </span>
                </div>
            </div>

            <!-- Jour sans formation -->
            <div v-if="!isCourseDay && !allowExtra" class="notice notice-off">
                <span class="material-symbols-outlined">event_busy</span>
                <div class="notice-body">
                    <strong>{{ dayType.label }}</strong>
                    <p>Ce jour n'est pas comptabilisé dans les présences, les taux et les PDF.</p>
                </div>
                <div class="notice-actions">
                    <button v-if="lastCourseDate" class="btn-soft" @click="goToDate(lastCourseDate)">Dernier jour de cours</button>
                    <button class="btn-soft btn-soft-muted" @click="goToDate(date, true)">Cours exceptionnel ?</button>
                </div>
            </div>

            <div v-else-if="isFuture" class="notice notice-off">
                <span class="material-symbols-outlined">schedule</span>
                <div class="notice-body">
                    <strong>Jour à venir</strong>
                    <p>Les présences ne peuvent être saisies qu'à partir du jour même.</p>
                </div>
            </div>

            <template v-else>
                <div v-if="!isCourseDay && allowExtra" class="notice notice-extra">
                    <span class="material-symbols-outlined">add_circle</span>
                    <div class="notice-body">
                        <strong>Jour de cours exceptionnel</strong>
                        <p>{{ dayType.label }}. Dès qu'une présence est saisie, ce jour apparaît dans le calendrier et les PDF.</p>
                    </div>
                </div>

                <div v-if="errorMessage" class="notice notice-error">
                    <span class="material-symbols-outlined">error</span>
                    <div class="notice-body"><strong>{{ errorMessage }}</strong></div>
                </div>

                <!-- Barre d'outils -->
                <div class="toolbar">
                    <div class="search-bar">
                        <span class="material-symbols-outlined" style="font-size:18px;color:#9aaabb">search</span>
                        <input v-model="searchQuery" type="text" placeholder="Rechercher un apprenant…" class="search-input" />
                        <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''">
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>
                    <button class="btn-all-present" :disabled="unrecordedIds.length === 0" @click="markAllPresent">
                        <span class="material-symbols-outlined" style="font-size:18px">done_all</span>
                        Marquer tous présents
                        <span v-if="unrecordedIds.length" class="btn-badge">{{ unrecordedIds.length }}</span>
                    </button>
                </div>

                <div class="legend-bar">
                    <span v-for="c in codes" :key="c.value" class="legend-item">
                        <span class="legend-dot" :class="`dot-${c.value}`"></span>
                        <strong>{{ c.value }}</strong> · {{ c.label }}
                    </span>
                </div>

                <!-- Liste apprenants -->
                <div class="learner-list">
                    <div v-if="activeRows.length === 0" class="empty-state">
                        <span class="material-symbols-outlined" style="font-size:40px;color:#ddd">groups</span>
                        <p>Aucun apprenant actif à cette date.</p>
                    </div>
                    <div v-for="learner in visibleRows" :key="learner.id" class="learner-block">
                        <div class="learner-row" :class="states[learner.id]?.code ? `row-${states[learner.id].code}` : 'row-none'">
                            <div class="learner-left">
                                <span class="l-num">{{ String(activeRows.indexOf(learner) + 1).padStart(2, '0') }}</span>
                                <span class="l-name">{{ learner.name }}</span>
                                <span v-if="learner.joined_today" class="tag-new" title="Inscrit ce jour : absent des jours précédents sans que ce soit une absence">Arrivé ce jour</span>
                                <span v-if="!states[learner.id]?.code" class="tag-pending">Non saisi</span>
                            </div>
                            <div class="code-btns">
                                <button v-for="c in codes" :key="c.value" type="button" class="code-btn"
                                    :class="[`btn-${c.value}`, { active: states[learner.id]?.code === c.value }]"
                                    :title="c.label" @click="setCode(learner.id, c.value)">
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

                        <!-- Motif : absences / retards justifiés -->
                        <div v-if="acceptsReason(states[learner.id]?.code)" class="reason-row">
                            <span class="material-symbols-outlined reason-icon">sticky_note_2</span>
                            <input v-model="states[learner.id].comment" type="text" maxlength="255" class="reason-input"
                                placeholder="Motif (facultatif) : maladie, transport…"
                                @input="onCommentInput(learner.id)" />
                            <div class="reason-suggestions">
                                <button v-for="s in REASON_SUGGESTIONS" :key="s" type="button" class="reason-chip"
                                    @click="setReason(learner.id, s)">{{ s }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Apprenants arrivés après cette date -->
                <div v-if="pendingRows.length" class="pending-panel">
                    <p class="pending-title">
                        <span class="material-symbols-outlined" style="font-size:16px">person_add</span>
                        {{ pendingRows.length }} apprenant(s) pas encore inscrit(s) à cette date — non comptés absents
                    </p>
                    <p v-for="l in pendingRows" :key="l.id" class="pending-item">
                        {{ l.name }} <span>· inscrit le {{ l.enrolled_from ? formatShort(l.enrolled_from) : '' }}</span>
                    </p>
                </div>
            </template>
        </template>

        <!-- ════════════════ RÉCAPITULATIF ════════════════ -->
        <template v-else-if="recap">

            <div class="recap-toolbar">
                <div class="seg">
                    <button v-for="v in [{ k: 'week', l: 'Semaine' }, { k: 'month', l: 'Mois' }, { k: 'all', l: 'Toute la formation' }]"
                        :key="v.k" class="seg-btn" :class="{ 'seg-active': recap.period?.view === v.k }"
                        @click="loadRecap(v.k as 'week' | 'month' | 'all')">
                        {{ v.l }}
                    </button>
                </div>
                <div v-if="recap.period" class="period-nav">
                    <button class="nav-arrow" :disabled="!recap.period.prev" @click="loadRecap(recap.period.view, recap.period.prev)">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <span class="period-label">{{ recap.period.label }}</span>
                    <button class="nav-arrow" :disabled="!recap.period.next" @click="loadRecap(recap.period.view, recap.period.next)">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>

            <div v-if="recap.days.length === 0" class="empty-state">
                <span class="material-symbols-outlined" style="font-size:40px;color:#ddd">fact_check</span>
                <p>Aucun jour de cours sur cette période.</p>
            </div>

            <div v-else class="recap-container">
                <div v-if="attendanceSettings.absenceAlertThreshold" class="recap-alert-banner">
                    <span class="material-symbols-outlined" style="font-size:18px">warning</span>
                    Ligne en rouge à partir de {{ attendanceSettings.absenceAlertThreshold }} absence(s) (AJ + AN) sur la période.
                </div>

                <div class="recap-scroll">
                    <table class="recap-table">
                        <thead>
                            <tr>
                                <th class="th-sticky th-name" rowspan="2">Apprenant</th>
                                <th v-for="w in recap.weeks" :key="w.start" class="th-week" :colspan="w.days.length">
                                    {{ weekTitle(w) }}
                                </th>
                                <th class="th-summary th-sticky-right" rowspan="2">Résumé</th>
                                <th class="th-rate th-sticky-rate" rowspan="2">Taux</th>
                            </tr>
                            <tr>
                                <template v-for="w in recap.weeks" :key="w.start">
                                    <th v-for="d in w.days" :key="d" class="th-day" :title="formatDateFull(d)">
                                        <span class="th-wd">{{ weekdayShort(d) }}</span>
                                        <span class="th-dn">{{ dayNumber(d) }}</span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in recap.rows" :key="row.id" class="recap-tr" :class="{ 'recap-tr-alert': isRowAlert(row) }">
                                <td class="td-sticky td-name">
                                    {{ row.name }}
                                    <span v-if="isRowAlert(row)" class="absence-alert-badge">{{ row.absences }} abs.</span>
                                </td>
                                <td v-for="d in recap.days" :key="d" class="td-day">
                                    <span v-if="!row.days[d].enrolled" class="rc-na" title="Pas encore inscrit à cette date">N/A</span>
                                    <span v-else-if="row.days[d].code" class="rc-badge" :class="recapCodeClass[row.days[d].code!]"
                                        :title="row.days[d].comment ? `Motif : ${row.days[d].comment}` : undefined">
                                        {{ row.days[d].code }}<sup v-if="row.days[d].comment" class="rc-note">●</sup>
                                    </span>
                                    <span v-else class="rc-empty" title="Non saisi">·</span>
                                </td>
                                <td class="td-summary td-sticky-right">
                                    <span class="summary-chips">
                                        <span v-for="s in rowSummary(row)" :key="s.code" class="summary-chip rc-badge"
                                            :class="recapCodeClass[s.code]" :title="codes.find(c => c.value === s.code)?.label">
                                            {{ s.count }}{{ s.code }}
                                        </span>
                                    </span>
                                </td>
                                <td class="td-rate td-sticky-rate">
                                    <span class="rate-badge" :class="rateClass(row.rate)"
                                        :title="`${row.recorded} jour(s) saisi(s) sur ${row.expected} depuis l'inscription`">
                                        {{ row.rate !== null ? row.rate + '%' : '—' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="recap-foot">
                                <td class="td-sticky td-foot-label">Présents / effectif</td>
                                <td v-for="d in recap.days" :key="d" class="td-day">
                                    <span class="day-stat">{{ recap.dayStats[d]?.present }}/{{ recap.dayStats[d]?.expected }}</span>
                                </td>
                                <td class="td-summary td-sticky-right td-foot-summary"></td>
                                <td class="td-rate td-sticky-rate td-foot-summary"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="legend-bar" style="margin-top:10px">
                    <span v-for="c in codes" :key="c.value" class="legend-item">
                        <span class="rc-badge" :class="`rc-${c.value}`">{{ c.value }}</span>{{ c.label }}
                    </span>
                    <span class="legend-item"><span class="rc-note">●</span> motif renseigné (survol)</span>
                    <span class="legend-item"><span class="rc-na">N/A</span> pas encore inscrit</span>
                    <span class="legend-item"><span class="rc-empty">·</span> non saisi</span>
                </div>

                <div v-if="recap.reasons.length" class="reasons-panel">
                    <p class="reasons-title">
                        <span class="material-symbols-outlined" style="font-size:16px">sticky_note_2</span>
                        Motifs renseignés sur la période
                    </p>
                    <p v-for="(r, i) in recap.reasons" :key="i" class="reason-line">
                        <span class="reason-date">{{ formatShort(r.date) }}</span>
                        <strong>{{ r.learner }}</strong>
                        <span class="rc-badge" :class="recapCodeClass[r.code]">{{ r.code }}</span>
                        {{ r.reason }}
                    </p>
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
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 16px 20px; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
}
.header-left { display: flex; align-items: center; gap: 14px; }
.header-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; transition: background 0.15s, color 0.15s; flex-shrink: 0;
}
.btn-back:hover { background: #1F3A4D; color: #fff; }
.header-project {
    display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #9aaabb;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px;
}
.header-title { font-size: 20px; font-weight: 700; color: #191c1e; }
.btn-pdf {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
    border: 1.5px solid #1F3A4D; border-radius: 8px; font-size: 13px; font-weight: 600; color: #1F3A4D;
    background: transparent; text-decoration: none; transition: background 0.15s, color 0.15s; white-space: nowrap;
}
.btn-pdf:hover { background: #1F3A4D; color: #fff; }
.btn-pdf-primary {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; color: #fff; background: #E5004C; text-decoration: none;
    transition: background 0.15s; white-space: nowrap;
}
.btn-pdf-primary:hover { background: #c0003e; }

/* ── Onglets ── */
.tabs-bar { display: flex; gap: 4px; background: #f5f7f9; border-radius: 10px; padding: 4px; }
.tab-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border: none; border-radius: 7px;
    font-size: 13px; font-weight: 600; color: #515f74; background: transparent; cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.tab-btn:hover { background: #eceef0; }
.tab-active { background: #fff !important; color: #E5004C !important; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

/* ── Zone collante ── */
.sticky-zone {
    position: sticky; top: 0; z-index: 20;
    display: flex; flex-direction: column; gap: 8px;
    padding: 10px 14px; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    box-shadow: 0 4px 14px rgba(25, 28, 30, 0.08);
}
.date-nav { display: flex; align-items: center; justify-content: center; gap: 10px; }
.nav-arrow {
    display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;
    border: 1px solid #e0e3e5; border-radius: 50%; background: #fff; cursor: pointer; color: #515f74;
    transition: all 0.15s; flex-shrink: 0;
}
.nav-arrow:hover:not(:disabled) { background: #f5f7f9; border-color: #E5004C; color: #E5004C; }
.nav-arrow:disabled { opacity: 0.35; cursor: not-allowed; }
.date-center { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: center; }
.date-label { font-size: 15px; font-weight: 700; color: #191c1e; }
.date-picker {
    padding: 5px 8px; border: 1px solid #e0e3e5; border-radius: 6px; font-size: 12px;
    color: #515f74; background: #fff; outline: none; cursor: pointer;
}
.date-picker:focus { border-color: #E5004C; }
.btn-today {
    padding: 6px 12px; border: 1px solid #e0e3e5; border-radius: 6px; font-size: 12px; font-weight: 600;
    color: #515f74; background: #fff; cursor: pointer; white-space: nowrap;
}
.btn-today:hover:not(:disabled) { border-color: #E5004C; color: #E5004C; }
.btn-today:disabled { opacity: 0.4; cursor: default; }

/* Bandeau des jours de cours */
.strip { display: flex; gap: 14px; overflow-x: auto; padding: 2px 2px 6px; scrollbar-width: thin; }
.strip-week { display: flex; gap: 4px; flex-shrink: 0; padding-right: 14px; border-right: 1px dashed #dfe3e7; }
.strip-week:last-child { border-right: none; padding-right: 0; }
.chip {
    display: flex; flex-direction: column; align-items: center; gap: 1px; min-width: 42px; padding: 4px 6px 5px;
    border: 1px solid #e0e3e5; border-radius: 8px; background: #fafbfc; cursor: pointer; transition: all 0.12s;
}
.chip:hover { border-color: #E5004C; }
.chip-wd { font-size: 10px; font-weight: 600; color: #9aaabb; text-transform: uppercase; }
.chip-day { font-size: 14px; font-weight: 700; color: #191c1e; line-height: 1.1; }
.chip-dot { width: 7px; height: 7px; border-radius: 50%; margin-top: 2px; border: 1.5px solid #ef4444; background: transparent; }
.chip-partial .chip-dot { border-color: #f59e0b; background: linear-gradient(90deg, #f59e0b 50%, transparent 50%); }
.chip-complete .chip-dot { border-color: #22c55e; background: #22c55e; }
.chip-active { background: #E5004C !important; border-color: #E5004C !important; }
.chip-active .chip-wd, .chip-active .chip-day { color: #fff; }
.chip-active .chip-dot { border-color: #fff; }
.chip-active.chip-complete .chip-dot { background: #fff; }

.progress-line { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; font-size: 12px; font-weight: 600; }
.pl-recorded { color: #191c1e; }
.pl-P { color: #15803d; } .pl-R { color: #d97706; } .pl-A { color: #b91c1c; }
.pl-rate { margin-left: auto; font-weight: 700; }
.r-good { color: #15803d; } .r-mid { color: #d97706; } .r-low { color: #b91c1c; }

/* ── Bandeaux d'information ── */
.notice {
    display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 10px;
    border: 1px solid #e0e3e5; background: #f5f7f9; color: #515f74;
}
.notice .material-symbols-outlined { font-size: 26px; flex-shrink: 0; }
.notice-body { flex: 1; min-width: 0; }
.notice-body strong { display: block; font-size: 14px; color: #191c1e; }
.notice-body p { font-size: 12px; margin-top: 2px; }
.notice-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.notice-extra { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.notice-error { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.notice-error .notice-body strong { color: #991b1b; }
.btn-soft {
    padding: 7px 12px; border: 1px solid #1F3A4D; border-radius: 7px; font-size: 12px; font-weight: 600;
    color: #1F3A4D; background: #fff; cursor: pointer; white-space: nowrap;
}
.btn-soft:hover { background: #1F3A4D; color: #fff; }
.btn-soft-muted { border-color: #cbd2d9; color: #515f74; }

/* ── Barre d'outils ── */
.toolbar { display: flex; gap: 10px; align-items: stretch; flex-wrap: wrap; }
.search-bar {
    flex: 1; min-width: 200px; display: flex; align-items: center; gap: 8px; padding: 8px 14px;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 8px;
}
.search-input { flex: 1; border: none; outline: none; font-size: 14px; color: #191c1e; background: transparent; padding: 4px 0; }
.search-input::placeholder { color: #9aaabb; }
.search-clear {
    display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none;
    border-radius: 50%; background: #f5f7f9; color: #515f74; cursor: pointer;
}
.btn-all-present {
    display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; border: none; border-radius: 8px;
    background: #15803d; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.btn-all-present:hover:not(:disabled) { background: #166534; }
.btn-all-present:disabled { background: #cfd6dc; cursor: default; }
.btn-badge { background: rgba(255,255,255,0.28); border-radius: 99px; padding: 1px 8px; font-size: 11px; }

/* ── Légende ── */
.legend-bar {
    display: flex; flex-wrap: wrap; gap: 8px 18px; padding: 10px 14px; background: #fafbfc;
    border: 1px solid #f0f1f3; border-radius: 8px; font-size: 12px; color: #515f74;
}
.legend-item { display: flex; align-items: center; gap: 6px; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot-P { background: #22c55e; } .dot-AJ { background: #3b82f6; } .dot-AN { background: #ef4444; }
.dot-RJ { background: #eab308; } .dot-RN { background: #f97316; }

/* ── Liste apprenants ── */
.learner-list { display: flex; flex-direction: column; gap: 5px; }
.empty-state {
    display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 48px 20px;
    background: #fff; border: 1px dashed #e0e3e5; border-radius: 10px; color: #9aaabb; font-size: 14px;
}
.learner-block { display: flex; flex-direction: column; }
.learner-row {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 16px;
    background: #fff; border: 1px solid #eceef0; border-radius: 8px; transition: background 0.1s;
}
.row-none { background: #fcfcfd; border-style: dashed; border-color: #d9dee3; }
.row-AJ { background: rgba(59,130,246,0.05); border-color: rgba(59,130,246,0.25); }
.row-AN { background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.25); }
.row-RJ { background: rgba(234,179,8,0.06); border-color: rgba(234,179,8,0.3); }
.row-RN { background: rgba(249,115,22,0.05); border-color: rgba(249,115,22,0.25); }
.row-P { background: rgba(34,197,94,0.04); border-color: rgba(34,197,94,0.2); }
.learner-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; flex-wrap: wrap; }
.l-num { font-size: 11px; color: #c4cdd6; font-weight: 600; width: 20px; flex-shrink: 0; }
.l-name { font-size: 14px; font-weight: 600; color: #191c1e; }
.tag-new { font-size: 10px; font-weight: 700; color: #1d4ed8; background: #dbeafe; padding: 2px 7px; border-radius: 99px; cursor: help; }
.tag-pending { font-size: 10px; font-weight: 700; color: #9aaabb; background: #f0f2f4; padding: 2px 7px; border-radius: 99px; }

.code-btns { display: flex; align-items: center; gap: 5px; }
.code-btn {
    padding: 5px 11px; border-radius: 6px; font-size: 12px; font-weight: 700; border: 2px solid transparent;
    cursor: pointer; transition: opacity 0.1s, transform 0.1s, background 0.1s; opacity: 0.55; background: transparent;
}
.code-btn:hover { opacity: 0.9; }
.code-btn.active { opacity: 1 !important; transform: scale(1.08); }
.btn-P  { border-color: #22c55e; color: #15803d; background: #f0fdf4; }
.btn-AJ { border-color: #3b82f6; color: #1d4ed8; background: #eff6ff; }
.btn-AN { border-color: #ef4444; color: #b91c1c; background: #fef2f2; }
.btn-RJ { border-color: #eab308; color: #a16207; background: #fefce8; }
.btn-RN { border-color: #f97316; color: #c2410c; background: #fff7ed; }
.btn-P.active  { background: #22c55e; color: #fff; }
.btn-AJ.active { background: #3b82f6; color: #fff; }
.btn-AN.active { background: #ef4444; color: #fff; }
.btn-RJ.active { background: #eab308; color: #fff; }
.btn-RN.active { background: #f97316; color: #fff; }
.si { display: inline-flex; align-items: center; margin-left: 2px; width: 16px; }
.si-saving { color: #9aaabb; animation: spin 0.8s linear infinite; }
.si-saved  { color: #22c55e; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Motif */
.reason-row {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 3px 0 4px 30px;
    padding: 7px 10px; background: #f8fafc; border: 1px solid #e3e8ee; border-radius: 8px;
}
.reason-icon { font-size: 18px; color: #9aaabb; }
.reason-input {
    flex: 1; min-width: 200px; border: 1px solid #dfe3e7; border-radius: 6px; padding: 6px 10px;
    font-size: 13px; background: #fff; outline: none;
}
.reason-input:focus { border-color: #E5004C; }
.reason-suggestions { display: flex; flex-wrap: wrap; gap: 5px; }
.reason-chip {
    padding: 3px 9px; border: 1px solid #dfe3e7; border-radius: 99px; background: #fff; font-size: 11px;
    font-weight: 600; color: #515f74; cursor: pointer;
}
.reason-chip:hover { border-color: #E5004C; color: #E5004C; }

.pending-panel { padding: 12px 16px; background: #fafbfc; border: 1px dashed #d9dee3; border-radius: 10px; font-size: 12px; color: #515f74; }
.pending-title { display: flex; align-items: center; gap: 6px; font-weight: 700; margin-bottom: 6px; }
.pending-item { padding-left: 22px; }
.pending-item span { color: #9aaabb; }

/* ── Récapitulatif ── */
.recap-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.seg { display: inline-flex; background: #f5f7f9; border-radius: 9px; padding: 3px; gap: 2px; }
.seg-btn { padding: 7px 14px; border: none; border-radius: 7px; background: transparent; font-size: 12px; font-weight: 600; color: #515f74; cursor: pointer; }
.seg-btn:hover { background: #eceef0; }
.seg-active { background: #fff !important; color: #E5004C; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.period-nav { display: flex; align-items: center; gap: 10px; }
.period-label { font-size: 14px; font-weight: 700; color: #191c1e; min-width: 190px; text-align: center; }

.recap-container { display: flex; flex-direction: column; gap: 6px; }
.recap-scroll { overflow-x: auto; border-radius: 10px; border: 1px solid #e0e3e5; }
.recap-table { border-collapse: collapse; width: 100%; font-size: 12px; background: #fff; }
.recap-table thead { background: #f5f7f9; }
.th-sticky, .td-sticky { position: sticky; left: 0; z-index: 2; background: inherit; }
.th-name {
    padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; white-space: nowrap; min-width: 180px; border-right: 1px solid #e0e3e5;
}
.th-week {
    padding: 6px 8px; text-align: center; font-size: 11px; font-weight: 700; color: #1F3A4D;
    background: #eaeff3; border-left: 2px solid #cfd8e0; border-bottom: 1px solid #dfe5ea; white-space: nowrap;
}
.th-day { padding: 5px 4px; text-align: center; color: #515f74; white-space: nowrap; min-width: 46px; }
.th-wd { display: block; font-size: 10px; font-weight: 600; color: #9aaabb; text-transform: uppercase; }
.th-dn { display: block; font-size: 13px; font-weight: 700; }
.th-rate { padding: 10px 10px; text-align: center; font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase; }
.recap-tr { border-top: 1px solid #f0f1f3; }
.recap-tr:hover { background: #fafbfc; }
.recap-tr:hover .td-sticky, .recap-tr:hover .td-sticky-right, .recap-tr:hover .td-sticky-rate { background: #fafbfc; }
.recap-tr-alert { background: #fef2f2 !important; }
.recap-tr-alert .td-sticky, .recap-tr-alert .td-sticky-right, .recap-tr-alert .td-sticky-rate { background: #fef2f2 !important; }
.recap-alert-banner {
    display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 14px; background: #fff5f5;
    border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-weight: 600; color: #991b1b;
}
.absence-alert-badge {
    display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 99px; background: #fee2e2;
    color: #991b1b; font-size: 10px; font-weight: 700; vertical-align: middle;
}
.td-name { padding: 8px 14px; font-weight: 600; color: #191c1e; white-space: nowrap; background: #fff; border-right: 1px solid #f0f1f3; }
.td-day { padding: 6px 4px; text-align: center; }
.rc-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.rc-P  { background: #d1fae5; color: #065f46; }
.rc-AJ { background: #dbeafe; color: #1e40af; }
.rc-AN { background: #fee2e2; color: #991b1b; }
.rc-RJ { background: #fef9c3; color: #854d0e; }
.rc-RN { background: #ffedd5; color: #9a3412; }
.rc-empty { color: #cbd2d9; font-size: 16px; line-height: 1; }
.rc-na { display: inline-block; padding: 2px 5px; border-radius: 4px; background: #eef0f2; color: #aab3bc; font-size: 9px; font-weight: 700; }
.rc-note { color: #1d4ed8; font-size: 8px; margin-left: 1px; }

.rate-badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.rb-good { background: #d1fae5; color: #065f46; }
.rb-mid  { background: #fef9c3; color: #854d0e; }
.rb-low  { background: #fee2e2; color: #991b1b; }
.rb-none { background: #f3f4f6; color: #9ca3af; }
.day-stat { font-size: 11px; font-weight: 600; color: #515f74; }
.recap-foot { background: #f5f7f9; border-top: 2px solid #e0e3e5; }
.td-foot-label {
    padding: 8px 14px; font-size: 11px; font-weight: 700; color: #515f74; background: #f5f7f9;
    white-space: nowrap; border-right: 1px solid #e0e3e5;
}

/* Colonnes collantes à droite */
.th-sticky-right, .td-sticky-right { position: sticky; right: 62px; z-index: 2; background: inherit; }
.th-sticky-rate, .td-sticky-rate { position: sticky; right: 0; z-index: 2; background: inherit; }
.th-summary {
    padding: 10px 12px; text-align: center; font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase;
    letter-spacing: 0.05em; white-space: nowrap; border-left: 2px solid #e0e3e5; background: #f5f7f9; min-width: 120px;
}
.th-rate { background: #f5f7f9; min-width: 62px; }
.td-summary { padding: 6px 10px; text-align: center; border-left: 2px solid #f0f1f3; background: #fff; white-space: nowrap; }
.td-rate { padding: 6px 8px; text-align: center; background: #fff; min-width: 62px; }
.td-foot-summary { background: #f5f7f9; border-left: 2px solid #e0e3e5; }
.summary-chips { display: inline-flex; flex-wrap: wrap; gap: 4px; justify-content: center; }
.summary-chip { font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; cursor: default; }

.reasons-panel { margin-top: 8px; padding: 12px 16px; background: #fff; border: 1px solid #e0e3e5; border-radius: 10px; font-size: 12px; color: #515f74; }
.reasons-title { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.reason-line { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 3px 0; }
.reason-date { color: #9aaabb; font-weight: 600; min-width: 52px; }

/* ══ Responsive ══ */
@media (max-width: 767px) {
    .att-page { gap: 10px; padding: 0 2px; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 10px; padding: 14px 16px; }
    .header-actions { flex-wrap: wrap; width: 100%; }
    .btn-pdf, .btn-pdf-primary { flex: 1; justify-content: center; font-size: 12px; padding: 7px 10px; }
    .tabs-bar { padding: 3px; gap: 3px; }
    .tab-btn { padding: 7px 10px; font-size: 12px; gap: 4px; }
    .sticky-zone { padding: 8px 10px; }
    .date-label { font-size: 13px; }
    .learner-row { padding: 8px 10px; gap: 8px; flex-wrap: wrap; }
    .code-btn { padding: 5px 9px; font-size: 11px; }
    .l-name { font-size: 13px; }
    .reason-row { margin-left: 0; }
    .notice { flex-wrap: wrap; }
    .th-name, .td-name { min-width: 120px !important; padding: 8px 8px !important; font-size: 11px; }
    .th-day { min-width: 36px !important; }
    .period-label { min-width: 0; font-size: 12px; }
}
@media (min-width: 768px) and (max-width: 1023px) {
    .code-btn { padding: 5px 9px; font-size: 11px; }
    .th-name, .td-name { min-width: 140px !important; }
}
</style>
