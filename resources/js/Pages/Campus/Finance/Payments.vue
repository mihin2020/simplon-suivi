<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineOptions({ layout: AdminLayout })

// ── Types ─────────────────────────────────────────────────────────────────
interface Learner { id: string; first_name: string; last_name: string }
interface Payment {
    id: string
    installment_number: number
    amount: number
    due_date: string
    paid_at: string | null
    status: string
    payment_method: string | null
    reference: string | null
    notes: string | null
}
interface LearnerPayment {
    learner: Learner
    payments: Payment[]
    paid_amount: number
    remaining_amount: number
    progress: number
}
interface Cohort {
    id: string
    name: string
    campus_formation: { id: string; name: string }
}
interface Stats {
    total_expected: number
    total_collected: number
    total_remaining: number
    overdue_count: number
}
interface PaymentMethodOpt { value: string; label: string; icon: string }

const props = defineProps<{
    cohort: Cohort
    total_cost: number
    learnerPayments: LearnerPayment[]
    stats: Stats
    paymentMethods: PaymentMethodOpt[]
}>()

// ── Formatting ────────────────────────────────────────────────────────────
const fmt     = (n: number) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'
const fmtDate = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const today   = () => new Date().toISOString().slice(0, 10)

// ── Status ────────────────────────────────────────────────────────────────
const statusCss: Record<string, string>  = {
    en_attente: 's-amber', paye: 's-emerald', en_retard: 's-rose', annule: 's-gray',
}
const statusIcon: Record<string, string> = {
    en_attente: 'schedule', paye: 'check_circle', en_retard: 'warning', annule: 'cancel',
}
const statusLabel: Record<string, string> = {
    en_attente: 'En attente', paye: 'Payé', en_retard: 'En retard', annule: 'Annulé',
}
const methodLabel: Record<string, string> = {
    especes: 'Espèces', mobile_money: 'Mobile Money',
}
const methodIcon: Record<string, string> = {
    especes: 'payments', mobile_money: 'phone_android',
}

// ── Helpers ───────────────────────────────────────────────────────────────
const visible = (lp: LearnerPayment) => lp.payments.filter(p => p.status !== 'annule')

const rowBadge = (lp: LearnerPayment) => {
    if (lp.progress >= 100)                                return { css: 's-emerald', text: 'Soldé ✓' }
    if (lp.payments.some(p => p.status === 'en_retard'))   return { css: 's-rose',    text: 'En retard' }
    if (visible(lp).length === 0)                          return { css: 's-gray',    text: 'Non configuré' }
    return { css: 's-amber', text: 'En cours' }
}

// ── Expand ────────────────────────────────────────────────────────────────
const expanded = ref<Set<string>>(new Set())
const toggle   = (id: string) => {
    const s = new Set(expanded.value)
    s.has(id) ? s.delete(id) : s.add(id)
    expanded.value = s
}

// ── Cancel installment ────────────────────────────────────────────────────
const showCancelModal  = ref(false)
const cancelTarget     = ref<Payment | null>(null)
const cancelLearner    = ref('')
const cancelProcessing = ref(false)

const askCancel = (p: Payment, learnerName: string) => {
    cancelTarget.value    = p
    cancelLearner.value   = learnerName
    showCancelModal.value = true
}
const confirmCancel = () => {
    if (!cancelTarget.value) return
    cancelProcessing.value = true
    router.delete(`/campus/payments/${cancelTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            cancelProcessing.value = false
            showCancelModal.value  = false
            cancelTarget.value     = null
        },
    })
}

// ── Mark paid modal ───────────────────────────────────────────────────────
const showPaidModal  = ref(false)
const paidTarget     = ref<Payment | null>(null)
const paidLearner    = ref<string>('')
const paidForm       = useForm({ paid_at: today(), payment_method: 'especes' })

const openPaidModal = (p: Payment, learnerName: string) => {
    paidTarget.value         = p
    paidLearner.value        = learnerName
    paidForm.paid_at         = today()
    paidForm.payment_method  = 'especes'
    showPaidModal.value      = true
}
const submitPaid = () => {
    if (!paidTarget.value) return
    paidForm.patch(`/campus/payments/${paidTarget.value.id}/mark-paid`, {
        preserveScroll: true,
        onSuccess: () => { showPaidModal.value = false },
    })
}

// ── Schedule modal ────────────────────────────────────────────────────────
const showSchedule   = ref(false)
const scheTarget     = ref<LearnerPayment | null>(null)
const scheProcessing = ref(false)

interface Draft { amount: number | ''; due_date: string }
const drafts = ref<Draft[]>([])

const openSchedule = (lp: LearnerPayment) => {
    scheTarget.value = lp
    // Start with 1 empty row (or remaining amount pre-filled)
    drafts.value = [{ amount: lp.remaining_amount > 0 ? lp.remaining_amount : props.total_cost, due_date: '' }]
    showSchedule.value = true
}

const addDraft     = () => drafts.value.push({ amount: '', due_date: '' })
const removeDraft  = (i: number) => drafts.value.splice(i, 1)

const scheMaxAmount = computed(() => props.total_cost - (scheTarget.value?.paid_amount ?? 0))
const scheTotal     = computed(() => drafts.value.reduce((s, d) => s + (Number(d.amount) || 0), 0))
const scheRemaining = computed(() => scheMaxAmount.value - scheTotal.value)
const scheValid     = computed(() =>
    drafts.value.length > 0 &&
    drafts.value.every(d => Number(d.amount) > 0) &&
    scheTotal.value > 0 &&
    scheRemaining.value >= 0   // partial schedule allowed — only block if over budget
)

const submitSchedule = () => {
    if (!scheTarget.value || !scheValid.value) return
    scheProcessing.value = true
    router.post(`/campus/cohorts/${props.cohort.id}/payments/schedule`, {
        learner_id:   scheTarget.value.learner.id,
        installments: drafts.value,
    }, {
        preserveScroll: true,
        onSuccess:  () => { showSchedule.value = false },
        onFinish:   () => { scheProcessing.value = false },
    })
}

const hasPending = (lp: LearnerPayment) =>
    lp.payments.some(p => p.status === 'en_attente' || p.status === 'en_retard')

// ── Global schedule modal ─────────────────────────────────────────────────
const showGlobalSchedule   = ref(false)
const globalProcessing     = ref(false)

interface GlobalDraft { type: 'percentage' | 'amount'; value: number | ''; due_date: string }
const globalDrafts = ref<GlobalDraft[]>([])

const openGlobalSchedule = () => {
    globalDrafts.value = [{ type: 'percentage', value: 100, due_date: '' }]
    showGlobalSchedule.value = true
}
const addGlobalDraft    = () => globalDrafts.value.push({ type: 'percentage', value: '', due_date: '' })
const removeGlobalDraft = (i: number) => globalDrafts.value.splice(i, 1)

const globalCalcAmount = (d: GlobalDraft): number => {
    if (!d.value) return 0
    return d.type === 'percentage'
        ? Math.round((Number(d.value) / 100) * props.total_cost)
        : Number(d.value)
}
const globalTotal    = computed(() => globalDrafts.value.reduce((s, d) => s + globalCalcAmount(d), 0))
const globalTotalPct = computed(() =>
    props.total_cost > 0 ? Math.round((globalTotal.value / props.total_cost) * 100) : 0
)
const globalRemaining = computed(() => props.total_cost - globalTotal.value)
const globalValid = computed(() =>
    globalDrafts.value.length > 0 &&
    globalDrafts.value.every(d => Number(d.value) > 0) &&
    globalTotal.value > 0 &&
    globalRemaining.value >= 0
)

const activeLearnerCount = computed(() =>
    props.learnerPayments.length
)
const globalHasPending = computed(() =>
    props.learnerPayments.some(lp => hasPending(lp))
)

const submitGlobalSchedule = () => {
    if (!globalValid.value) return
    globalProcessing.value = true
    router.post(`/campus/cohorts/${props.cohort.id}/payments/schedule-global`, {
        installments: globalDrafts.value.map(d => ({
            type:     d.type,
            value:    d.value,
            due_date: d.due_date || null,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { showGlobalSchedule.value = false },
        onFinish:  () => { globalProcessing.value = false },
    })
}

// ── Add single tranche modal ──────────────────────────────────────────────
const showAdd   = ref(false)
const addTarget = ref<LearnerPayment | null>(null)
const addForm   = useForm({
    learner_id:     '',
    amount:         0 as number | '',
    paid_at:        '',
    payment_method: 'especes',
    notes:          '',
})

const openAdd = (lp: LearnerPayment) => {
    addTarget.value        = lp
    addForm.learner_id     = lp.learner.id
    addForm.amount         = lp.remaining_amount > 0 ? lp.remaining_amount : props.total_cost
    addForm.paid_at        = today()
    addForm.payment_method = 'especes'
    addForm.notes          = ''
    showAdd.value          = true
}
const submitAdd = () => {
    addForm.post(`/campus/cohorts/${props.cohort.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => { showAdd.value = false },
    })
}
</script>

<template>
    <div class="max-w-[1100px] mx-auto space-y-xl">

        <!-- ── En-tête ─────────────────────────────────────────────────────── -->
        <div class="flex items-start gap-md">
            <Link :href="`/campus/cohorts/${cohort.id}`" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <p class="text-body-sm text-secondary font-medium">{{ cohort.campus_formation.name }}</p>
                <h1 class="text-h1 font-bold text-on-surface">Paiements {{ cohort.name }}</h1>
                <p class="text-body-sm text-secondary mt-xs">
                    Frais de formation :
                    <strong class="text-on-surface">{{ fmt(total_cost) }}</strong> par apprenant
                </p>
            </div>
        </div>

        <!-- ── Stats ──────────────────────────────────────────────────────── -->
        <div class="grid grid-cols-4 gap-md">
            <div class="stat-card">
                <span class="sic emerald"><span class="material-symbols-outlined">payments</span></span>
                <div>
                    <p class="slbl">Collecté</p>
                    <p class="sval emerald">{{ fmt(stats.total_collected) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic blue"><span class="material-symbols-outlined">account_balance</span></span>
                <div>
                    <p class="slbl">Attendu</p>
                    <p class="sval">{{ fmt(stats.total_expected) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic amber"><span class="material-symbols-outlined">hourglass_empty</span></span>
                <div>
                    <p class="slbl">Restant</p>
                    <p class="sval amber">{{ fmt(stats.total_remaining) }}</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="sic rose"><span class="material-symbols-outlined">warning</span></span>
                <div>
                    <p class="slbl">En retard</p>
                    <p class="sval rose">{{ stats.overdue_count }} apprenant(s)</p>
                </div>
            </div>
        </div>

        <!-- ── Action globale ─────────────────────────────────────────────── -->
        <div class="global-action-bar">
            <div class="global-bar-info">
                <span class="material-symbols-outlined" style="font-size:18px;color:#1F3A4D">groups</span>
                <span class="text-body-sm text-on-surface">
                    <strong>{{ activeLearnerCount }}</strong> apprenant(s) actif(s)
                </span>
            </div>
            <button class="btn-global-schedule" type="button" @click="openGlobalSchedule">
                <span class="material-symbols-outlined" style="font-size:16px">calendar_month</span>
                Définir l'échéancier de la cohorte
            </button>
        </div>

        <!-- ── Liste apprenants ────────────────────────────────────────────── -->
        <div class="list-card">
            <div class="list-header">
                <h2 class="text-h2 font-semibold text-on-surface">
                    Apprenants
                    <span class="count-pill">{{ learnerPayments.length }}</span>
                </h2>
                <p class="text-body-sm text-secondary">Cliquez sur un apprenant pour gérer ses paiements</p>
            </div>

            <div v-if="learnerPayments.length === 0" class="empty-list">
                Aucun apprenant actif dans cette cohorte.
            </div>

            <div
                v-for="lp in learnerPayments"
                :key="lp.learner.id"
                class="learner-block"
                :class="{ 'block-open': expanded.has(lp.learner.id) }"
            >
                <!-- ── Row header ── -->
                <button class="learner-row" @click="toggle(lp.learner.id)" type="button">
                    <div class="avatar">
                        {{ lp.learner.first_name.charAt(0) }}{{ lp.learner.last_name.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="learner-name">{{ lp.learner.last_name }} {{ lp.learner.first_name }}</p>
                        <div class="prog-row">
                            <div class="prog-track">
                                <div
                                    class="prog-fill"
                                    :class="lp.progress >= 100 ? 'pf-green' : lp.progress > 0 ? 'pf-amber' : 'pf-empty'"
                                    :style="{ width: lp.progress + '%' }"
                                />
                            </div>
                            <span class="prog-lbl">
                                {{ fmt(lp.paid_amount) }} / {{ fmt(total_cost) }} ({{ lp.progress }}%)
                            </span>
                        </div>
                    </div>
                    <span :class="['status-pill', rowBadge(lp).css]">{{ rowBadge(lp).text }}</span>
                    <span class="material-symbols-outlined chevron" :class="{ rot: expanded.has(lp.learner.id) }">
                        expand_more
                    </span>
                </button>

                <!-- ── Expanded panel ── -->
                <div v-show="expanded.has(lp.learner.id)" class="panel">

                    <!-- No tranches yet -->
                    <div v-if="visible(lp).length === 0" class="empty-panel">
                        <span class="material-symbols-outlined" style="font-size:36px;color:#9aaabb">payments</span>
                        <p class="mt-xs text-body-sm text-secondary">Aucune tranche enregistrée.</p>
                        <button class="btn-sched mt-sm" @click.stop="openSchedule(lp)" type="button">
                            <span class="material-symbols-outlined" style="font-size:15px">auto_awesome</span>
                            Définir l'échéancier
                        </button>
                    </div>

                    <!-- Installment list -->
                    <template v-else>
                        <div class="inst-list">
                            <div
                                v-for="p in visible(lp)"
                                :key="p.id"
                                class="inst-row"
                                :class="p.status === 'paye' ? 'ir-paid' : p.status === 'en_retard' ? 'ir-late' : ''"
                            >
                                <!-- Tranche label -->
                                <div class="tnum">T{{ p.installment_number }}</div>

                                <!-- Amount -->
                                <div class="iamount">{{ fmt(p.amount) }}</div>

                                <!-- Dates -->
                                <div class="idates">
                                    <span class="ditem">
                                        <span class="material-symbols-outlined" style="font-size:13px;color:#9aaabb">calendar_today</span>
                                        Prévue {{ fmtDate(p.due_date) }}
                                    </span>
                                    <span v-if="p.paid_at" class="ditem paid-date">
                                        <span class="material-symbols-outlined" style="font-size:13px">check</span>
                                        Encaissée le {{ fmtDate(p.paid_at) }}
                                    </span>
                                </div>

                                <!-- Payment method badge -->
                                <span v-if="p.payment_method" class="method-badge">
                                    <span class="material-symbols-outlined" style="font-size:12px">{{ methodIcon[p.payment_method] }}</span>
                                    {{ methodLabel[p.payment_method] }}
                                </span>

                                <!-- Status badge -->
                                <span :class="['s-badge', statusCss[p.status]]">
                                    <span class="material-symbols-outlined" style="font-size:12px">{{ statusIcon[p.status] }}</span>
                                    {{ statusLabel[p.status] }}
                                </span>

                                <!-- Actions -->
                                <div class="iactions">
                                    <a
                                        v-if="p.status === 'paye'"
                                        :href="`/campus/payments/${p.id}/receipt`"
                                        target="_blank"
                                        class="btn-receipt"
                                        title="Voir le reçu"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:14px">receipt_long</span>
                                        Reçu
                                    </a>
                                    <a
                                        v-if="p.status === 'paye'"
                                        :href="`/campus/payments/${p.id}/receipt/download`"
                                        class="btn-dl-receipt"
                                        title="Télécharger le reçu PDF"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:15px">download</span>
                                    </a>
                                    <button
                                        v-if="p.status !== 'paye'"
                                        class="btn-encaisser"
                                        @click.stop="openPaidModal(p, `${lp.learner.last_name} ${lp.learner.first_name}`)"
                                        type="button"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
                                        Encaisser
                                    </button>
                                    <button
                                        v-if="p.status !== 'paye'"
                                        class="btn-del"
                                        @click.stop="askCancel(p, `${lp.learner.last_name} ${lp.learner.first_name}`)"
                                        type="button"
                                        title="Annuler cette tranche"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:14px">close</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel footer -->
                        <div class="panel-footer">
                            <div class="footer-left">
                                <span v-if="lp.remaining_amount > 0" class="remaining-txt">
                                    Restant à percevoir : <strong>{{ fmt(lp.remaining_amount) }}</strong>
                                </span>
                                <span v-else class="paid-txt">
                                    <span class="material-symbols-outlined" style="font-size:14px">verified</span>
                                    Entièrement réglé
                                </span>
                            </div>
                            <div class="footer-right">
                                <button
                                    v-if="lp.remaining_amount > 0"
                                    class="btn-add-t"
                                    @click.stop="openAdd(lp)"
                                    type="button"
                                >
                                    <span class="material-symbols-outlined" style="font-size:14px">add</span>
                                    Enregistrer un versement
                                </button>
                                <button
                                    v-if="lp.progress < 100"
                                    class="btn-sched"
                                    @click.stop="openSchedule(lp)"
                                    type="button"
                                >
                                    <span class="material-symbols-outlined" style="font-size:14px">auto_awesome</span>
                                    {{ hasPending(lp) ? 'Modifier' : 'Définir' }} l'échéancier
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Modal : Encaisser un paiement ═══════════════════════════════════ -->
    <Teleport to="body">
        <div v-if="showPaidModal" class="backdrop" @click.self="showPaidModal = false">
            <div class="modal" style="max-width:400px">
                <div class="mhd">
                    <div>
                        <h3 class="mtitle">Encaisser le paiement</h3>
                        <p class="msub" v-if="paidTarget">
                            {{ paidLearner }} · Tranche {{ paidTarget.installment_number }} ·
                            <strong>{{ fmt(paidTarget.amount) }}</strong>
                        </p>
                    </div>
                    <button @click="showPaidModal = false" class="close-btn" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitPaid">
                    <div class="mbody">
                        <!-- Date d'encaissement -->
                        <div class="field">
                            <label class="label">Date d'encaissement *</label>
                            <input v-model="paidForm.paid_at" type="date" class="input"
                                :class="{ 'input-err': paidForm.errors.paid_at }" />
                            <p v-if="paidForm.errors.paid_at" class="err-msg">{{ paidForm.errors.paid_at }}</p>
                        </div>

                        <!-- Mode de paiement -->
                        <div class="field">
                            <label class="label">Mode de paiement *</label>
                            <div class="method-selector">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m.value"
                                    type="button"
                                    :class="['method-opt', paidForm.payment_method === m.value ? 'method-active' : '']"
                                    @click="paidForm.payment_method = m.value"
                                >
                                    <span class="material-symbols-outlined" style="font-size:22px">{{ m.icon }}</span>
                                    <span>{{ m.label }}</span>
                                </button>
                            </div>
                            <p v-if="paidForm.errors.payment_method" class="err-msg">{{ paidForm.errors.payment_method }}</p>
                        </div>
                    </div>
                    <div class="mft">
                        <button type="button" class="btn-secondary" @click="showPaidModal = false">Annuler</button>
                        <button type="submit" class="btn-primary" :disabled="paidForm.processing">
                            <span class="spinner" v-if="paidForm.processing" />
                            <span v-else class="material-symbols-outlined" style="font-size:15px">check_circle</span>
                            Confirmer l'encaissement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ══ Modal : Définir / modifier l'échéancier ═════════════════════════ -->
    <Teleport to="body">
        <div v-if="showSchedule" class="backdrop" @click.self="showSchedule = false">
            <div class="modal" style="max-width:580px">
                <div class="mhd">
                    <div>
                        <h3 class="mtitle">Planifier les paiements</h3>
                        <p class="msub" v-if="scheTarget">
                            {{ scheTarget.learner.last_name }} {{ scheTarget.learner.first_name }} ·
                            Total formation : <strong>{{ fmt(total_cost) }}</strong>
                            <template v-if="scheTarget.paid_amount > 0">
                                · Déjà encaissé : <strong class="text-emerald">{{ fmt(scheTarget.paid_amount) }}</strong>
                            </template>
                        </p>
                    </div>
                    <button @click="showSchedule = false" class="close-btn" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mbody" v-if="scheTarget">

                    <!-- Help text -->
                    <p class="help-txt">
                        Définissez les tranches selon l'accord avec l'apprenant. Les montants sont libres.
                    </p>

                    <!-- Draft rows -->
                    <div class="draft-list">
                        <div class="draft-head">
                            <span style="width:40px">#</span>
                            <span class="flex-1">Montant (FCFA)</span>
                            <span style="width:160px">Date prévue <span class="opt-lbl">(optionnel)</span></span>
                            <span style="width:32px"></span>
                        </div>

                        <div v-for="(d, i) in drafts" :key="i" class="draft-row">
                            <span class="dnum">{{ i + 1 }}</span>
                            <input
                                v-model.number="d.amount"
                                type="number"
                                min="1"
                                class="dinput"
                                placeholder="Montant"
                            />
                            <input
                                v-model="d.due_date"
                                type="date"
                                class="dinput"
                                style="width:160px"
                            />
                            <button
                                v-if="drafts.length > 1"
                                type="button"
                                class="del-draft"
                                @click="removeDraft(i)"
                                title="Supprimer"
                            >
                                <span class="material-symbols-outlined" style="font-size:16px">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Add row -->
                    <button class="btn-add-draft" type="button" @click="addDraft">
                        <span class="material-symbols-outlined" style="font-size:15px">add</span>
                        Ajouter une tranche
                    </button>

                    <!-- Total summary -->
                    <div class="total-summary">
                        <div class="ts-row">
                            <span class="ts-lbl">Restant à percevoir</span>
                            <span class="ts-val">{{ fmt(scheMaxAmount) }}</span>
                        </div>
                        <div class="ts-row">
                            <span class="ts-lbl">Total planifié dans cet échéancier</span>
                            <span class="ts-val">{{ fmt(scheTotal) }}</span>
                        </div>
                        <!-- Over budget → error -->
                        <div v-if="scheRemaining < 0" class="ts-row ts-final ts-ko">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">error</span>
                                Montant dépassé de {{ fmt(Math.abs(scheRemaining)) }}
                            </span>
                        </div>
                        <!-- Partial → info -->
                        <div v-else-if="scheRemaining > 0" class="ts-row ts-final ts-info">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">info</span>
                                {{ fmt(scheRemaining) }} restant à planifier ultérieurement
                            </span>
                        </div>
                        <!-- Fully covered → success -->
                        <div v-else class="ts-row ts-final ts-ok">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">check_circle</span>
                                Frais entièrement couverts par l'échéancier
                            </span>
                        </div>
                    </div>

                    <!-- Warning: replace pending -->
                    <div v-if="hasPending(scheTarget)" class="info-box">
                        <span class="material-symbols-outlined" style="font-size:15px;flex-shrink:0">info</span>
                        Les tranches en attente seront remplacées. Les paiements déjà encaissés sont conservés.
                    </div>
                </div>

                <div class="mft">
                    <button @click="showSchedule = false" class="btn-secondary" type="button">Annuler</button>
                    <button
                        @click="submitSchedule"
                        class="btn-primary"
                        type="button"
                        :disabled="!scheValid || scheProcessing"
                    >
                        <span class="spinner" v-if="scheProcessing" />
                        <span v-else class="material-symbols-outlined" style="font-size:15px">auto_awesome</span>
                        Enregistrer l'échéancier
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ══ Modal : Enregistrer un versement ═════════════════════════════════ -->
    <Teleport to="body">
        <div v-if="showAdd" class="backdrop" @click.self="showAdd = false">
            <div class="modal" style="max-width:420px">
                <div class="mhd">
                    <div>
                        <h3 class="mtitle">Enregistrer un versement</h3>
                        <p class="msub" v-if="addTarget">
                            {{ addTarget.learner.last_name }} {{ addTarget.learner.first_name }}
                            · Restant : <strong>{{ fmt(addTarget.remaining_amount) }}</strong>
                        </p>
                    </div>
                    <button @click="showAdd = false" class="close-btn" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form @submit.prevent="submitAdd">
                    <div class="mbody">
                        <div class="field">
                            <label class="label">
                                Montant versé (FCFA) *
                                <span v-if="addTarget" class="opt-lbl">(max {{ fmt(addTarget.remaining_amount) }})</span>
                            </label>
                            <input
                                v-model.number="addForm.amount"
                                type="number"
                                min="1"
                                :max="addTarget?.remaining_amount"
                                class="input"
                                :class="{ 'input-err': addForm.errors.amount }"
                            />
                            <p v-if="addForm.errors.amount" class="err-msg">{{ addForm.errors.amount }}</p>
                        </div>
                        <div class="field">
                            <label class="label">
                                Date du versement
                                <span class="opt-lbl">(optionnel)</span>
                            </label>
                            <input v-model="addForm.paid_at" type="date" class="input" />
                        </div>
                        <div class="field">
                            <label class="label">Mode de paiement *</label>
                            <div class="method-selector">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m.value"
                                    type="button"
                                    :class="['method-opt', addForm.payment_method === m.value ? 'method-active' : '']"
                                    @click="addForm.payment_method = m.value"
                                >
                                    <span class="material-symbols-outlined" style="font-size:22px">{{ m.icon }}</span>
                                    <span>{{ m.label }}</span>
                                </button>
                            </div>
                            <p v-if="addForm.errors.payment_method" class="err-msg">{{ addForm.errors.payment_method }}</p>
                        </div>
                        <div class="field">
                            <label class="label">Notes <span class="opt-lbl">(optionnel)</span></label>
                            <textarea v-model="addForm.notes" class="input" rows="2" />
                        </div>
                    </div>
                    <div class="mft">
                        <button @click="showAdd = false" type="button" class="btn-secondary">Annuler</button>
                        <button type="submit" class="btn-primary" :disabled="addForm.processing">
                            <span class="spinner" v-if="addForm.processing" />
                            <span v-else class="material-symbols-outlined" style="font-size:15px">check_circle</span>
                            Confirmer le versement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ══ Modal : Échéancier global de la cohorte ═══════════════════════════ -->
    <Teleport to="body">
        <div v-if="showGlobalSchedule" class="backdrop" @click.self="showGlobalSchedule = false">
            <div class="modal" style="max-width:620px">
                <div class="mhd">
                    <div>
                        <h3 class="mtitle">Échéancier global {{ cohort.name }}</h3>
                        <p class="msub">
                            Applicable à <strong>{{ activeLearnerCount }}</strong> apprenant(s) actif(s) ·
                            Frais : <strong>{{ fmt(total_cost) }}</strong> / apprenant
                        </p>
                    </div>
                    <button @click="showGlobalSchedule = false" class="close-btn" type="button">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="mbody">
                    <p class="help-txt">
                        Définissez les tranches en <strong>pourcentage (%)</strong> du coût total ou en <strong>montant fixe (FCFA)</strong>.
                        Cet échéancier sera appliqué à tous les apprenants actifs.
                    </p>

                    <!-- Colonnes entête -->
                    <div class="draft-head" style="grid-template-columns:28px 120px 1fr 160px 32px">
                        <span>#</span>
                        <span>Type</span>
                        <span>Valeur</span>
                        <span>Date prévue <span class="opt-lbl">(optionnel)</span></span>
                        <span></span>
                    </div>

                    <!-- Lignes -->
                    <div v-for="(d, i) in globalDrafts" :key="i" class="global-draft-row">
                        <span class="dnum">{{ i + 1 }}</span>

                        <!-- Type toggle -->
                        <div class="type-toggle">
                            <button
                                type="button"
                                :class="['type-btn', d.type === 'percentage' ? 'type-active' : '']"
                                @click="d.type = 'percentage'"
                            >%</button>
                            <button
                                type="button"
                                :class="['type-btn', d.type === 'amount' ? 'type-active' : '']"
                                @click="d.type = 'amount'"
                            >FCFA</button>
                        </div>

                        <!-- Valeur + aperçu -->
                        <div class="value-wrap">
                            <input
                                v-model.number="d.value"
                                type="number"
                                min="1"
                                :max="d.type === 'percentage' ? 100 : undefined"
                                class="dinput"
                                :placeholder="d.type === 'percentage' ? 'Ex: 30' : 'Ex: 75000'"
                            />
                            <span v-if="d.type === 'percentage' && d.value" class="calc-preview">
                                = {{ new Intl.NumberFormat('fr-FR').format(globalCalcAmount(d)) }} FCFA
                            </span>
                        </div>

                        <input
                            v-model="d.due_date"
                            type="date"
                            class="dinput"
                            style="width:160px"
                        />

                        <button
                            v-if="globalDrafts.length > 1"
                            type="button"
                            class="del-draft"
                            @click="removeGlobalDraft(i)"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>

                    <!-- Ajouter tranche -->
                    <button class="btn-add-draft" type="button" @click="addGlobalDraft">
                        <span class="material-symbols-outlined" style="font-size:15px">add</span>
                        Ajouter une tranche
                    </button>

                    <!-- Résumé -->
                    <div class="total-summary">
                        <div class="ts-row">
                            <span class="ts-lbl">Frais de formation par apprenant</span>
                            <span class="ts-val">{{ fmt(total_cost) }}</span>
                        </div>
                        <div class="ts-row">
                            <span class="ts-lbl">Total planifié</span>
                            <span class="ts-val">{{ fmt(globalTotal) }} ({{ globalTotalPct }}%)</span>
                        </div>
                        <div v-if="globalRemaining < 0" class="ts-row ts-final ts-ko">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">error</span>
                                Montant dépassé de {{ fmt(Math.abs(globalRemaining)) }}
                            </span>
                        </div>
                        <div v-else-if="globalRemaining > 0" class="ts-row ts-final ts-info">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">info</span>
                                {{ fmt(globalRemaining) }} restant à planifier ultérieurement
                            </span>
                        </div>
                        <div v-else class="ts-row ts-final ts-ok">
                            <span>
                                <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">check_circle</span>
                                Frais entièrement couverts par l'échéancier
                            </span>
                        </div>
                    </div>

                    <!-- Avertissement si des tranches en attente vont être écrasées -->
                    <div v-if="globalHasPending" class="info-box" style="margin-top:12px">
                        <span class="material-symbols-outlined" style="font-size:15px;flex-shrink:0">warning</span>
                        Les tranches <strong>en attente / en retard</strong> de certains apprenants seront remplacées.
                        Les paiements déjà encaissés sont conservés.
                    </div>
                </div>

                <div class="mft">
                    <button @click="showGlobalSchedule = false" class="btn-secondary" type="button">Annuler</button>
                    <button
                        class="btn-primary"
                        type="button"
                        :disabled="!globalValid || globalProcessing"
                        @click="submitGlobalSchedule"
                    >
                        <span class="spinner" v-if="globalProcessing" />
                        <span v-else class="material-symbols-outlined" style="font-size:15px">groups</span>
                        Appliquer à {{ activeLearnerCount }} apprenant(s)
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ══ Modal : Annuler une tranche ══════════════════════════════════════ -->
    <ConfirmModal
        :show="showCancelModal"
        title="Annuler la tranche"
        :message="cancelTarget
            ? `Annuler la tranche n° ${cancelTarget.installment_number} de ${cancelLearner} (${fmt(cancelTarget.amount)}) ? Cette action est irréversible.`
            : ''"
        confirm-label="Annuler la tranche"
        :loading="cancelProcessing"
        @confirm="confirmCancel"
        @cancel="showCancelModal = false"
    />
</template>

<style scoped>
/* ── Global action bar ── */
.global-action-bar {
    display: flex; align-items: center; justify-content: space-between;
    background: #f0f4f8; border: 1.5px solid #c7d4df; border-radius: 12px;
    padding: 14px 20px; gap: 12px;
}
.global-bar-info { display: flex; align-items: center; gap: 8px; }
.btn-global-schedule {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    background: #1F3A4D; color: #fff;
    border-radius: 8px; border: none; cursor: pointer;
    font-size: 13px; font-weight: 600;
    transition: background 0.15s;
}
.btn-global-schedule:hover { background: #162d3c; }

/* ── Global draft rows ── */
.global-draft-row {
    display: grid;
    grid-template-columns: 28px 120px 1fr 160px 32px;
    gap: 8px; align-items: center; padding: 6px 0;
}
.type-toggle { display: flex; border: 1px solid #d1d9e0; border-radius: 6px; overflow: hidden; }
.type-btn {
    flex: 1; padding: 5px 8px; font-size: 12px; font-weight: 600;
    background: transparent; color: #515f74; border: none; cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.type-active { background: #1F3A4D; color: #fff; }
.value-wrap { display: flex; align-items: center; gap: 8px; }
.calc-preview {
    font-size: 11px; color: #059669; font-weight: 600; white-space: nowrap;
}

/* ── Layout ── */
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    transition: background 0.15s, color 0.15s; flex-shrink: 0; text-decoration: none;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

/* ── Stat cards ── */
.stat-card {
    display: flex; align-items: center; gap: 14px;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sic {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
}
.sic .material-symbols-outlined { font-size: 20px; }
.sic.emerald { background: #d1fae5; color: #059669; }
.sic.blue    { background: #dbeafe; color: #2563eb; }
.sic.amber   { background: #fef3c7; color: #d97706; }
.sic.rose    { background: #ffe4e6; color: #e11d48; }
.slbl { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.sval { font-size: 15px; font-weight: 700; color: #191c1e; margin-top: 2px; }
.sval.emerald { color: #059669; }
.sval.amber   { color: #d97706; }
.sval.rose    { color: #e11d48; }

/* ── List card ── */
.list-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.list-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 24px; border-bottom: 1px solid #f2f4f6;
}
.count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 6px;
    background: #f2f4f6; border-radius: 99px;
    font-size: 12px; font-weight: 600; color: #515f74; margin-left: 8px;
}
.empty-list { padding: 48px; text-align: center; color: #9aaabb; }

/* ── Learner block ── */
.learner-block { border-bottom: 1px solid #f2f4f6; }
.learner-block:last-child { border-bottom: none; }
.block-open { background: #fafbfc; }

.learner-row {
    width: 100%; display: flex; align-items: center; gap: 14px;
    padding: 14px 24px; text-align: left; background: transparent;
    border: none; cursor: pointer; transition: background 0.15s;
}
.learner-row:hover { background: #f7f8f9; }

.avatar {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #E5004C, #ff4d8c);
    color: #fff; font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.learner-name { font-size: 14px; font-weight: 600; color: #191c1e; }

.prog-row { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
.prog-track { flex: 1; max-width: 200px; height: 6px; background: #e0e3e5; border-radius: 99px; overflow: hidden; }
.prog-fill  { height: 100%; border-radius: 99px; transition: width 0.4s; }
.pf-green   { background: #059669; }
.pf-amber   { background: #d97706; }
.pf-empty   { background: transparent; }
.prog-lbl   { font-size: 12px; color: #9aaabb; white-space: nowrap; }

.status-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.s-emerald { background: #d1fae5; color: #065f46; }
.s-amber   { background: #fef3c7; color: #92400e; }
.s-rose    { background: #ffe4e6; color: #9f1239; }
.s-gray    { background: #f3f4f6; color: #6b7280; }

.chevron { font-size: 20px; color: #9aaabb; transition: transform 0.2s; flex-shrink: 0; }
.chevron.rot { transform: rotate(180deg); }

/* ── Panel ── */
.panel { padding: 0 24px 16px; }
.empty-panel { display: flex; flex-direction: column; align-items: center; padding: 28px 0; text-align: center; }

/* ── Installment list ── */
.inst-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
.inst-row {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 8px; padding: 10px 14px;
}
.ir-paid { border-color: #a7f3d0; background: #f0fdf4; }
.ir-late { border-color: #fecdd3; background: #fff1f2; }

.tnum {
    min-width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
    background: #f2f4f6; color: #515f74; font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.iamount { font-size: 14px; font-weight: 700; color: #191c1e; min-width: 120px; }

.idates { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.ditem  { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #515f74; }
.paid-date { color: #059669; }

.method-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600;
    background: #eff6ff; color: #2563eb;
}

.s-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 99px; font-size: 11px; font-weight: 600;
}

.iactions { display: flex; align-items: center; gap: 6px; margin-left: auto; }

.btn-receipt {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px; border-radius: 6px;
    background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
    font-size: 12px; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: background 0.15s;
}
.btn-receipt:hover { background: #dbeafe; }

.btn-dl-receipt {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;
    cursor: pointer; text-decoration: none; transition: background 0.15s;
    flex-shrink: 0;
}
.btn-dl-receipt:hover { background: #dcfce7; }

.btn-encaisser {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px; border-radius: 6px;
    background: #059669; color: #fff; border: none;
    font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.btn-encaisser:hover { background: #047857; }

.btn-del {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid #e0e3e5; background: transparent; color: #9aaabb; cursor: pointer; transition: all 0.15s;
}
.btn-del:hover { color: #e11d48; border-color: #fecdd3; background: #fff1f2; }

/* ── Panel footer ── */
.panel-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 10px; border-top: 1px solid #f2f4f6; gap: 10px; flex-wrap: wrap;
}
.footer-left   { font-size: 13px; }
.remaining-txt { color: #515f74; }
.paid-txt { display: flex; align-items: center; gap: 4px; color: #059669; font-weight: 600; }
.footer-right  { display: flex; align-items: center; gap: 8px; }

.btn-add-t {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 12px; border-radius: 6px;
    border: 1px solid #e0e3e5; background: #fff; color: #515f74;
    font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.btn-add-t:hover { border-color: #E5004C; color: #E5004C; background: #fff5f8; }

.btn-sched {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 12px; border-radius: 6px;
    background: #E5004C; color: #fff; border: none;
    font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.btn-sched:hover { background: #c0003e; }

/* ── Modal ── */
.backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 24px;
}
.modal {
    background: #fff; border-radius: 16px; width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    display: flex; flex-direction: column; max-height: 90vh; overflow-y: auto;
}
.mhd {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 20px 24px; border-bottom: 1px solid #f2f4f6; gap: 12px;
}
.mtitle { font-size: 17px; font-weight: 700; color: #191c1e; }
.msub   { font-size: 13px; color: #515f74; margin-top: 3px; }
.close-btn {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    border: none; background: transparent; color: #515f74; cursor: pointer; flex-shrink: 0;
}
.close-btn:hover { background: #f2f4f6; }
.mbody { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.mft   { padding: 16px 24px; border-top: 1px solid #f2f4f6; display: flex; justify-content: flex-end; gap: 10px; }

/* ── Encaisser — method selector ── */
.method-selector { display: flex; gap: 10px; }
.method-opt {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 14px 10px; border-radius: 10px;
    border: 2px solid #e0e3e5; background: #fff; color: #515f74;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.method-opt:hover { border-color: #E5004C; color: #E5004C; }
.method-active { border-color: #E5004C !important; background: #fff5f8 !important; color: #E5004C !important; }

/* ── Schedule — draft list ── */
.help-txt { font-size: 13px; color: #515f74; }
.draft-list { display: flex; flex-direction: column; gap: 8px; }
.draft-head {
    display: flex; align-items: center; gap: 8px; padding: 0 4px;
    font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em;
}
.draft-row { display: flex; align-items: center; gap: 8px; }
.dnum {
    width: 32px; height: 36px; border-radius: 8px;
    background: #f2f4f6; color: #515f74; font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.dinput {
    flex: 1; padding: 8px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; color: #191c1e; background: #fff; outline: none; font-family: inherit;
}
.dinput:focus { border-color: #E5004C; box-shadow: 0 0 0 2px rgba(229,0,76,0.08); }
.del-draft {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 6px;
    border: 1px solid #e0e3e5; background: transparent; color: #9aaabb; cursor: pointer; flex-shrink: 0;
}
.del-draft:hover { color: #e11d48; border-color: #fecdd3; background: #fff1f2; }

.btn-add-draft {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 7px 14px; border-radius: 8px;
    border: 1px dashed #e0e3e5; background: transparent; color: #515f74;
    font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s;
}
.btn-add-draft:hover { border-color: #E5004C; color: #E5004C; }

/* ── Total summary ── */
.total-summary {
    background: #f8f9fa; border-radius: 10px; padding: 14px 16px;
    display: flex; flex-direction: column; gap: 6px;
}
.ts-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #515f74; }
.ts-val { font-weight: 600; color: #191c1e; }
.ts-final {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 8px; border-top: 1px solid #e0e3e5;
    font-size: 13px; font-weight: 600; margin-top: 2px;
}
.ts-ok   { color: #059669; }
.ts-ko   { color: #e11d48; }
.ts-info { color: #2563eb; }

.info-box {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px; border-radius: 8px; background: #eff6ff; color: #1d4ed8; font-size: 12px;
}
.opt-lbl { font-size: 11px; color: #9aaabb; font-weight: 400; }

/* ── Form ── */
.field { display: flex; flex-direction: column; gap: 5px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; }
.input {
    padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff; outline: none;
    width: 100%; font-family: inherit; transition: border-color 0.15s;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 2px rgba(229,0,76,0.08); }
.input-err { border-color: #ba1a1a !important; }
textarea.input { resize: vertical; }
.err-msg { font-size: 12px; color: #ba1a1a; }

/* ── Buttons ── */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: none; cursor: pointer; transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: transparent; color: #515f74;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    border: 1px solid #e0e3e5; cursor: pointer; transition: background 0.15s;
}
.btn-secondary:hover { background: #f2f4f6; }

.spinner {
    display: inline-block; width: 13px; height: 13px;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.mt-xs { margin-top: 4px; }
.mt-sm { margin-top: 8px; }
</style>
