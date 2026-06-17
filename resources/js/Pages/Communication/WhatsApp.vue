<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import WhatsAppComposer from './Partials/WhatsAppComposer.vue'

defineOptions({ layout: AdminLayout })

interface Learner   { id: string; first_name: string; last_name: string; phone: string | null }
interface Formation { id: string; name: string; project: { id: string; name: string }; learners: Learner[] }
interface WaStatus  { connected: boolean; authenticating: boolean; qr: string | null; available: boolean; phone: string | null }
interface Broadcast {
    broadcast_id: string; formation_name: string | null; project_name: string | null
    message: string; sent_at: string; recipient_count: number; reply_count: number; failed_count: number
}
interface Recipient {
    id: number; phone: string; learner_id: string | null; name: string
    status: string; error: string | null; sent_at: string; has_replied: boolean; reply_count: number
    last_reply: { message: string; created_at: string } | null
}
interface ThreadMsg {
    id: number; message: string; direction: 'sent' | 'received'; status: string
    error: string | null; broadcast_id: string | null; created_at: string; deleted_at: string | null
    attachment_path: string | null; attachment_mimetype: string | null; attachment_filename: string | null
}

const props = defineProps<{ formations: Formation[]; initialWaStatus: WaStatus }>()
const csrf  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

// ── WhatsApp status ───────────────────────────────────────────────────────────
const waStatus      = ref<WaStatus>({ ...props.initialWaStatus, authenticating: false, phone: null })
const disconnecting = ref(false)
// Horodatage de déconnexion : ignore les réponses "connecté" résiduelles du microservice
let loggedOutAt = 0
let pollTimer:    ReturnType<typeof setInterval> | null = null
let autoSyncTimer: ReturnType<typeof setInterval> | null = null
let failCount = 0, polling = false

// Compteur de secondes d'attente du QR (pour feedback utilisateur)
const waitingSeconds = ref(0)
let waitTimer: ReturnType<typeof setInterval> | null = null

function startWaitTimer() {
    if (waitTimer) return
    waitingSeconds.value = 0
    waitTimer = setInterval(() => { waitingSeconds.value++ }, 1000)
}
function stopWaitTimer() {
    if (waitTimer) { clearInterval(waitTimer); waitTimer = null }
    waitingSeconds.value = 0
}

async function fetchStatus() {
    if (polling) return; polling = true
    try {
        const r = await fetch('/communication/whatsapp/status', {
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        if (!r.ok) throw new Error()
        const d = await r.json(); failCount = 0

        // Anti-clignotement : pendant ~8s après une déconnexion, on ignore un
        // éventuel "connecté" résiduel renvoyé par le microservice pas encore arrêté.
        if (loggedOutAt && Date.now() - loggedOutAt < 8000 && d.connected) {
            return
        }

        const next: WaStatus = d.connected
            ? { connected: true,  authenticating: false, available: true, qr: null, phone: d.phone ?? waStatus.value.phone }
            : d.available === false
                ? { ...waStatus.value, available: false }
                : { connected: false, authenticating: !!d.authenticating, available: true,
                    qr: d.authenticating ? null : (d.qr ?? waStatus.value.qr), phone: null }

        // Ne mettre à jour QUE si l'état réel a changé — évite les re-renders inutiles
        const cur = waStatus.value
        if (next.connected !== cur.connected ||
            next.authenticating !== cur.authenticating ||
            next.available !== cur.available ||
            next.qr !== cur.qr) {
            waStatus.value = next
            // Ajuste la fréquence de polling selon l'état :
            // - Pas de QR encore → 2s pour afficher le QR dès qu'il est prêt
            // - QR affiché ou authentification → 5s (moins urgent)
            restartPollingWithInterval(next.available && !next.connected && !next.qr ? 2000 : 5000)
        }
    } catch {
        if (++failCount >= 5 && waStatus.value.available) {
            waStatus.value = { ...waStatus.value, available: false }
        }
    } finally { polling = false }
}

let currentPollInterval = 2000
function startPolling(interval = 2000) {
    currentPollInterval = interval
    if (pollTimer) return
    fetchStatus()
    pollTimer = setInterval(fetchStatus, interval)
}
function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}
function restartPollingWithInterval(interval: number) {
    if (interval === currentPollInterval || !pollTimer) return
    clearInterval(pollTimer)
    currentPollInterval = interval
    pollTimer = setInterval(fetchStatus, interval)
}
function startAutoSync() { if (autoSyncTimer) return; autoSyncTimer = setInterval(doSync, 20_000) }
function stopAutoSync()  { if (autoSyncTimer) { clearInterval(autoSyncTimer); autoSyncTimer = null } }

watch(() => waStatus.value.connected, c => {
    if (c) {
        stopPolling()
        stopWaitTimer()
        requestNotifPermission() // demander la permission de notification
        doSync()        // sync immédiat dès la connexion
        startAutoSync() // puis toutes les 20s
    } else {
        stopAutoSync()
        if (!pollTimer) startPolling(2000)
    }
})

// Démarre le timer d'attente dès qu'on est disponible mais sans QR ni connexion
watch(() => waStatus.value, (s) => {
    if (s.available && !s.connected && !s.authenticating && !s.qr) startWaitTimer()
    else stopWaitTimer()
}, { deep: true })

function logout() {
    // Bascule INSTANTANÉE de l'interface — on n'attend pas le microservice.
    // Le watcher sur waStatus.connected relance automatiquement le polling.
    disconnecting.value = true
    loggedOutAt = Date.now()
    waStatus.value = { connected: false, authenticating: false, qr: null, available: true }

    // Nettoyage côté serveur en arrière-plan (non bloquant pour l'UI)
    fetch('/communication/whatsapp/logout', {
        method:  'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => {}).finally(() => { disconnecting.value = false })
}

onMounted(() => {
    notifPermission.value = ('Notification' in window ? Notification.permission : 'denied') as NotificationPermission
    if (waStatus.value.connected) { requestNotifPermission(); startAutoSync() }
    else {
        startPolling(2000)
        if (waStatus.value.available && !waStatus.value.qr && !waStatus.value.authenticating)
            startWaitTimer()
    }
    loadBroadcasts()
})
onUnmounted(() => { stopPolling(); stopAutoSync(); stopWaitTimer() })

// ── Navigation : broadcasts → recipients → thread ─────────────────────────────
type HistoryView = 'broadcasts' | 'recipients' | 'thread'
const historyView = ref<HistoryView>('broadcasts')

// Niveau 1 — Campagnes
const broadcasts        = ref<Broadcast[]>([])
const loadingBroadcasts = ref(false)

// ── Modal de confirmation ─────────────────────────────────────────────────────
const confirmModal = ref<{
    open: boolean; title: string; message: string
    confirmLabel: string; confirmClass: string
    resolve: ((v: boolean) => void) | null
}>({ open: false, title: '', message: '', confirmLabel: 'Confirmer', confirmClass: 'btn-danger', resolve: null })

function openConfirm(title: string, message: string, opts?: { confirmLabel?: string; confirmClass?: string }): Promise<boolean> {
    return new Promise(resolve => {
        confirmModal.value = { open: true, title, message,
            confirmLabel: opts?.confirmLabel ?? 'Confirmer',
            confirmClass: opts?.confirmClass ?? 'btn-danger',
            resolve }
    })
}
function resolveConfirm(val: boolean) {
    confirmModal.value.open = false
    confirmModal.value.resolve?.(val)
    confirmModal.value.resolve = null
}

async function deleteBroadcast(b: Broadcast) {
    if (!await openConfirm(
        'Supprimer la campagne',
        `Supprimer la campagne "${b.formation_name ?? 'Envoi groupé'}" et tous ses messages ?`,
        { confirmLabel: 'Supprimer' }
    )) return
    await fetch(`/communication/whatsapp/broadcasts/${b.broadcast_id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    await loadBroadcasts()
    if (selectedBroadcast.value?.broadcast_id === b.broadcast_id) {
        historyView.value = 'broadcasts'
        selectedBroadcast.value = null
    }
}

async function deleteMessage(id: number) {
    if (!await openConfirm('Supprimer le message', 'Supprimer ce message envoyé ?')) return
    await fetch(`/communication/whatsapp/messages/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (selectedBroadcast.value) await openBroadcast(selectedBroadcast.value, true)
    await loadBroadcasts(true)
}

async function loadBroadcasts(silent = false) {
    if (!silent) loadingBroadcasts.value = true
    try {
        const r = await fetch('/communication/whatsapp/broadcasts', {
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        if (r.ok) {
            const data: Broadcast[] = await r.json()
            if (JSON.stringify(data) !== JSON.stringify(broadcasts.value)) {
                broadcasts.value = data
            }
        }
    } finally { if (!silent) loadingBroadcasts.value = false }
}

// Niveau 2 — Destinataires d'une campagne
const selectedBroadcast   = ref<Broadcast | null>(null)
const broadcastRecipients = ref<Recipient[]>([])
const loadingRecipients   = ref(false)
const recipientFilter     = ref<'all' | 'replied' | 'pending'>('all')
const recipientSearch     = ref('')

const filteredRecipients = computed(() => {
    let list = broadcastRecipients.value
    if (recipientFilter.value === 'replied') list = list.filter(r => r.has_replied)
    if (recipientFilter.value === 'pending') list = list.filter(r => !r.has_replied)
    if (recipientSearch.value) {
        const s = recipientSearch.value.toLowerCase()
        list = list.filter(r => r.name.toLowerCase().includes(s) || r.phone.includes(s))
    }
    return list
})

async function openBroadcast(b: Broadcast, silent = false) {
    selectedBroadcast.value = b
    historyView.value       = 'recipients'
    if (!silent) { loadingRecipients.value = true; broadcastRecipients.value = [] }
    try {
        const r = await fetch(`/communication/whatsapp/broadcasts/${b.broadcast_id}/recipients`, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        if (r.ok) {
            const data: Recipient[] = await r.json()
            if (JSON.stringify(data) !== JSON.stringify(broadcastRecipients.value)) {
                broadcastRecipients.value = data
            }
        }
    } finally { if (!silent) loadingRecipients.value = false }
}

// Niveau 3 — Thread individuel
const activeRecipient = ref<Recipient | null>(null)
const threadMessages  = ref<ThreadMsg[]>([])
const loadingThread   = ref(false)
const threadRef       = ref<HTMLDivElement | null>(null)

async function openThread(recipient: Recipient, silent = false) {
    if (!recipient.learner_id) return
    activeRecipient.value = recipient
    historyView.value     = 'thread'
    if (!silent) { loadingThread.value = true; threadMessages.value = [] }
    try {
        const r = await fetch(`/communication/whatsapp/thread/${recipient.learner_id}`, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        if (r.ok) {
            const data: ThreadMsg[] = await r.json()
            if (JSON.stringify(data) !== JSON.stringify(threadMessages.value)) {
                threadMessages.value = data
            }
        }
    } finally {
        if (!silent) loadingThread.value = false
        nextTick(() => { if (threadRef.value) threadRef.value.scrollTop = threadRef.value.scrollHeight })
    }
    // Marquer les messages reçus comme lus → réinitialise le badge reply_count localement
    fetch(`/communication/whatsapp/thread/${recipient.learner_id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(() => {
        // Mise à jour locale uniquement — pas de changement de vue
        const idx = broadcastRecipients.value.findIndex(r => r.learner_id === recipient.learner_id)
        if (idx !== -1) broadcastRecipients.value[idx] = { ...broadcastRecipients.value[idx], reply_count: 0 }
    }).catch(() => {})
}

function goBack() {
    if (historyView.value === 'thread') {
        historyView.value = 'recipients'; activeRecipient.value = null
    } else if (historyView.value === 'recipients') {
        historyView.value = 'broadcasts'; selectedBroadcast.value = null
        recipientFilter.value = 'all'; recipientSearch.value = ''
    }
}

// ── Notifications & son ───────────────────────────────────────────────────────
const notifPermission = ref<NotificationPermission>('default')

function requestNotifPermission() {
    if (!('Notification' in window)) return
    Notification.requestPermission().then(p => { notifPermission.value = p })
}

function playNotifSound() {
    try {
        const Ctx  = window.AudioContext ?? (window as any).webkitAudioContext
        if (!Ctx) return
        const ctx  = new Ctx()
        const osc  = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.connect(gain); gain.connect(ctx.destination)
        osc.type = 'sine'; osc.frequency.value = 830
        gain.gain.setValueAtTime(0.25, ctx.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.55)
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.55)
        // Deuxième note légèrement plus haute
        const osc2 = ctx.createOscillator()
        osc2.connect(gain); osc2.type = 'sine'; osc2.frequency.value = 1046
        osc2.start(ctx.currentTime + 0.12); osc2.stop(ctx.currentTime + 0.5)
    } catch {}
}

function showDesktopNotif(count: number) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return
    const n = new Notification('💬 Nouveau message WhatsApp', {
        body: count === 1 ? '1 nouvelle réponse reçue' : `${count} nouvelles réponses reçues`,
        icon: '/logo.jpeg',
        tag:  'wa-reply',
    })
    setTimeout(() => n.close(), 5000)
}

function handleNewReplies(count: number) {
    if (count <= 0) return
    playNotifSound()
    // Ne pas notifier si la fenêtre est déjà active et visible
    if (document.visibilityState === 'hidden' || !document.hasFocus()) {
        showDesktopNotif(count)
    }
}

// Sync réponses (silencieux : ne perturbe pas l'interface)
const syncing = ref(false)
async function doSync(manual = false) {
    if (syncing.value || !waStatus.value.connected) return
    if (manual) syncing.value = true
    try {
        const r = await fetch('/communication/whatsapp/sync-replies', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        const syncData = await r.json().catch(() => ({}))
        const synced   = syncData.synced ?? 0

        await loadBroadcasts(true)
        if (historyView.value === 'recipients' && selectedBroadcast.value)
            await openBroadcast(selectedBroadcast.value, true)
        if (historyView.value === 'thread' && activeRecipient.value)
            await openThread(activeRecipient.value, true)

        // Notifier seulement si de nouveaux messages ont été sauvegardés
        if (synced > 0) handleNewReplies(synced)
    } finally { if (manual) syncing.value = false }
}

// ── Envoi (déclenché par le composant enfant) ─────────────────────────────────
// ── Réponse directe dans le fil de conversation ───────────────────────────────
const replyText        = ref('')
const sendingReply     = ref(false)
const replyError       = ref<string | null>(null)
const replyTextareaRef = ref<HTMLTextAreaElement | null>(null)
const replyAttachment  = ref<File | null>(null)
const attachInputRef   = ref<HTMLInputElement | null>(null)
const showEmojiPicker  = ref(false)
const hoveredMsgId     = ref<number | null>(null)

const commonEmojis = ['😊','😂','❤️','👍','🙏','😍','🎉','👋','✅','🔥','💪','😭','🤔','👀','💯','🤝','📞','📝','📚','🎓','⏰','📅','✔️','❗','❓','🔔','💬','🌍','🏫','✏️']

function autoResize(el: HTMLTextAreaElement) {
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 160) + 'px'
}
function onReplyInput(e: Event) { autoResize(e.target as HTMLTextAreaElement) }

function insertEmoji(emoji: string) {
    replyText.value += emoji
    showEmojiPicker.value = false
    nextTick(() => {
        if (replyTextareaRef.value) { autoResize(replyTextareaRef.value); replyTextareaRef.value.focus() }
    })
}

function onAttachChange(e: Event) {
    const files = (e.target as HTMLInputElement).files
    replyAttachment.value = files?.[0] ?? null
}
function removeAttachment() {
    replyAttachment.value = null
    if (attachInputRef.value) attachInputRef.value.value = ''
}

async function deleteMsgFromThread(id: number) {
    if (!await openConfirm('Supprimer le message', 'Supprimer ce message de la conversation ?')) return
    await fetch(`/communication/whatsapp/messages/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (activeRecipient.value) await openThread(activeRecipient.value, true)
    await loadBroadcasts(true)
}

async function sendReply() {
    if ((!replyText.value.trim() && !replyAttachment.value) || !activeRecipient.value?.learner_id || sendingReply.value) return

    const recipient = activeRecipient.value
    const learnerId = recipient.learner_id!
    const text      = replyText.value.trim()
    const file      = replyAttachment.value

    // ── Affichage optimiste : la bulle apparaît INSTANTANÉMENT ──
    const tempId = -Date.now()
    const optimistic: ThreadMsg = {
        id: tempId, message: text, direction: 'sent', status: 'sending',
        error: null, broadcast_id: null, created_at: new Date().toISOString(), deleted_at: null,
        attachment_path: file ? URL.createObjectURL(file) : null,
        attachment_mimetype: file ? file.type : null,
        attachment_filename: file ? file.name : null,
    }
    threadMessages.value = [...threadMessages.value, optimistic]

    // Vide l'input immédiatement — l'utilisateur peut continuer à écrire
    replyText.value = ''
    replyAttachment.value = null
    if (attachInputRef.value) attachInputRef.value.value = ''
    if (replyTextareaRef.value) replyTextareaRef.value.style.height = 'auto'
    replyError.value = null
    nextTick(() => { if (threadRef.value) threadRef.value.scrollTop = threadRef.value.scrollHeight })

    sendingReply.value = true
    try {
        let r: Response
        if (file) {
            const fd = new FormData()
            fd.append('learner_id', learnerId)
            if (text) fd.append('message', text)
            fd.append('attachment', file)
            r = await fetch('/communication/whatsapp/reply', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body:    fd,
            })
        } else {
            r = await fetch('/communication/whatsapp/reply', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body:    JSON.stringify({ learner_id: learnerId, message: text }),
            })
        }
        const data = await r.json()
        if (data.success) {
            // Réconciliation silencieuse : remplace la bulle temporaire par la vraie
            if (activeRecipient.value?.learner_id === learnerId) {
                await openThread(recipient, true)
            }
        } else {
            markOptimisticFailed(tempId, data.error ?? 'Erreur d\'envoi')
        }
    } catch {
        markOptimisticFailed(tempId, 'Impossible de joindre le serveur')
    } finally {
        sendingReply.value = false
    }
}

function markOptimisticFailed(tempId: number, error: string) {
    const idx = threadMessages.value.findIndex(m => m.id === tempId)
    if (idx !== -1) {
        threadMessages.value[idx] = { ...threadMessages.value[idx], status: 'failed', error }
    }
    replyError.value = error
}

function onReplyKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply() }
}

const composerRef  = ref<InstanceType<typeof WhatsAppComposer> | null>(null)
const sendEstimate = ref(0)
let countdownTimer: ReturnType<typeof setInterval> | null = null

const form = useForm<{ recipients: any[]; message: string; formation_name: string | null; project_name: string | null; attachments: File[] }>({
    recipients: [], message: '', formation_name: null, project_name: null, attachments: [],
})

watch(() => form.processing, v => {
    if (v) {
        sendEstimate.value = form.recipients.length
        countdownTimer = setInterval(() => { if (sendEstimate.value > 0) sendEstimate.value-- }, 1000)
    } else if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null }
})

function handleSend(payload: {
    recipients: { phone: string; name: string; learner_id: string }[]
    message: string; formationName: string | null; projectName: string | null; attachments: File[]
}) {
    form.recipients     = payload.recipients
    form.message        = payload.message
    form.formation_name = payload.formationName
    form.project_name   = payload.projectName
    form.attachments    = payload.attachments
    form.post('/communication/whatsapp/send', {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            composerRef.value?.reset()
            await loadBroadcasts()
            historyView.value = 'broadcasts'
        },
    })
}

// ── Formatage ─────────────────────────────────────────────────────────────────
function initials(name: string) { return name.split(' ').map(w => w[0] ?? '').join('').slice(0, 2).toUpperCase() }
function fmtTime(iso: string)   { return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }
function fmtDate(iso: string) {
    const d = new Date(iso), t = new Date()
    if (d.toDateString() === t.toDateString()) return "Aujourd'hui"
    const y = new Date(t); y.setDate(t.getDate() - 1)
    if (d.toDateString() === y.toDateString()) return 'Hier'
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
function truncate(s: string, n = 70) { return s.length > n ? s.slice(0, n) + '…' : s }
function isImage(mime: string | null) { return !!mime && /^image\//i.test(mime) }
function isVideo(mime: string | null) { return !!mime && /^video\//i.test(mime) }
function isAudio(mime: string | null) { return !!mime && /^audio\//i.test(mime) }
function mediaUrl(path: string | null) {
    if (!path) return ''
    // Aperçu local instantané (envoi optimiste) — ne pas passer par le serveur
    if (path.startsWith('blob:') || path.startsWith('data:')) return path
    return `/communication/whatsapp/media/${path.split('/').pop()}`
}
interface MediaGroup { type: 'media-group'; msgs: ThreadMsg[]; created_at: string }
interface SingleItem { type: 'single'; msg: ThreadMsg }
type DisplayItem = MediaGroup | SingleItem

function buildDisplayItems(msgs: ThreadMsg[]): DisplayItem[] {
    const result: DisplayItem[] = []
    for (const msg of msgs) {
        // Regrouper les messages reçus avec pièce jointe uniquement et sans texte
        if (msg.direction === 'received' && !msg.message && msg.attachment_path && !msg.deleted_at) {
            const last = result[result.length - 1]
            if (last?.type === 'media-group') { last.msgs.push(msg); continue }
            result.push({ type: 'media-group', msgs: [msg], created_at: msg.created_at })
            continue
        }
        result.push({ type: 'single', msg })
    }
    // Groupes d'1 seul fichier → bulle normale
    return result.map(item =>
        item.type === 'media-group' && item.msgs.length === 1
            ? ({ type: 'single' as const, msg: item.msgs[0] })
            : item
    )
}

function groupByDate(msgs: ThreadMsg[]) {
    const groups: { date: string; items: DisplayItem[] }[] = []
    let cur = ''
    for (const m of buildDisplayItems(msgs)) {
        const d = fmtDate(m.type === 'single' ? m.msg.created_at : m.created_at)
        if (d !== cur) { cur = d; groups.push({ date: d, items: [] }) }
        groups[groups.length - 1].items.push(m)
    }
    return groups
}
</script>

<template>
<Head title="WhatsApp" />
<div class="wa-page" @click="showEmojiPicker = false">

    <!-- Top bar -->
    <div class="top-bar">
        <div class="top-left">
            <Link href="/communication/emails" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div class="wa-icon"><span class="material-symbols-outlined" style="font-size:22px;color:#25d366">chat</span></div>
            <div>
                <h1 class="page-title">WhatsApp</h1>
                <p class="page-sub">Messagerie directe depuis votre numéro</p>
            </div>
        </div>
        <div class="top-right">
            <!-- Bouton activation notifications -->
            <button v-if="waStatus.connected && notifPermission !== 'granted'" class="notif-btn"
                :title="notifPermission === 'denied' ? 'Notifications bloquées dans le navigateur' : 'Activer les notifications'"
                :disabled="notifPermission === 'denied'"
                @click="requestNotifPermission">
                <span class="material-symbols-outlined" style="font-size:15px">{{ notifPermission === 'denied' ? 'notifications_off' : 'add_alert' }}</span>
                {{ notifPermission === 'denied' ? 'Notifs bloquées' : 'Activer les notifs' }}
            </button>
            <template v-if="waStatus.connected">
                <div class="connected-info">
                    <div class="status-pill connected"><span class="dot dot-green"></span>Connecté</div>
                    <div v-if="waStatus.phone" class="connected-phone">
                        <span class="material-symbols-outlined" style="font-size:13px;color:#25d366">phone_iphone</span>
                        +{{ waStatus.phone }}
                    </div>
                </div>
                <button @click="logout" :disabled="disconnecting" class="btn-disconnect">
                    <span class="material-symbols-outlined" style="font-size:14px">logout</span>
                    {{ disconnecting ? 'Déconnexion…' : 'Déconnecter' }}
                </button>
            </template>
            <div v-else-if="waStatus.available && waStatus.authenticating" class="status-pill authing">
                <div class="spinner-xs"></div>Connexion…
            </div>
            <div v-else-if="waStatus.available" class="status-pill pending">
                <span class="dot dot-orange"></span>En attente
            </div>
            <div v-else class="status-pill offline">
                <span class="material-symbols-outlined" style="font-size:13px">power_off</span>Service arrêté
            </div>
        </div>
    </div>

    <div v-if="!waStatus.available" class="alert-banner">
        <span class="material-symbols-outlined" style="font-size:17px">terminal</span>
        Démarrez le microservice : <code>cd whatsapp-service &amp;&amp; npm start</code>
    </div>

    <div v-if="waStatus.available && !waStatus.connected && waStatus.authenticating" class="auth-panel">
        <div class="auth-spinner-wrap"><div class="spinner-ring"></div><span class="material-symbols-outlined" style="font-size:26px;color:#25d366">phone_android</span></div>
        <div><h2 class="auth-title">Connexion en cours…</h2><p class="auth-sub">QR code scanné — synchronisation avec votre téléphone.</p></div>
    </div>

    <div v-if="waStatus.available && !waStatus.connected && !waStatus.authenticating" class="qr-panel">
        <div class="qr-left">
            <h2 class="qr-title">Connecter votre WhatsApp</h2>
            <ol class="qr-steps">
                <li>Ouvrez WhatsApp sur votre téléphone</li>
                <li>Allez dans <strong>Paramètres → Appareils connectés</strong></li>
                <li>Appuyez sur <strong>Connecter un appareil</strong></li>
                <li>Scannez le QR code ci-contre</li>
            </ol>
        </div>
        <div class="qr-right">
            <div v-if="waStatus.qr" class="qr-box">
                <img :src="waStatus.qr" alt="QR" class="qr-img" />
                <p style="font-size:11px;color:#9aaabb;margin-top:6px;text-align:center">Se régénère automatiquement</p>
            </div>
            <div v-else class="qr-placeholder">
                <div class="startup-info">
                    <div class="spinner spinner-lg" style="flex-shrink:0"></div>
                    <div style="text-align:center">
                        <p class="startup-label">QR en cours de génération…</p>
                        <p v-if="waitingSeconds < 15" class="startup-hint">Démarrage du service (30–60 s environ)</p>
                        <p v-else-if="waitingSeconds < 60" class="startup-hint">
                            {{ waitingSeconds }}s — patience, Edge / Chrome charge WhatsApp…
                        </p>
                        <p v-else class="startup-warn">
                            {{ waitingSeconds }}s — si rien n'apparaît, vérifiez que le terminal <code>npm start</code> est ouvert
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface principale -->
    <div class="main-grid">

        <!-- Panneau gauche : composer (composant isolé du polling) -->
        <WhatsAppComposer
            ref="composerRef"
            :formations="props.formations"
            :connected="waStatus.connected"
            :processing="form.processing"
            :send-estimate="sendEstimate"
            @send="handleSend"
        />

        <!-- Panneau droit : historique 3 niveaux -->
        <div class="card history-panel">

            <div class="card-head" style="justify-content:space-between;gap:8px">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1">
                    <button v-if="historyView !== 'broadcasts'" type="button" @click="goBack" class="icon-btn">
                        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
                    </button>

                    <template v-if="historyView === 'broadcasts'">
                        <span class="material-symbols-outlined" style="font-size:15px;color:#6b7280">campaign</span>
                        <span>Campagnes</span>
                        <span class="count-chip">{{ broadcasts.length }}</span>
                    </template>

                    <template v-else-if="historyView === 'recipients' && selectedBroadcast">
                        <div style="flex:1;min-width:0">
                            <div class="bc-name-h">{{ selectedBroadcast.formation_name ?? 'Envoi groupé' }}</div>
                            <div style="display:flex;gap:5px;margin-top:2px">
                                <span class="stat-chip stat-sent">✓ {{ selectedBroadcast.recipient_count }}</span>
                                <span class="stat-chip stat-reply">💬 {{ selectedBroadcast.reply_count }}</span>
                                <span v-if="selectedBroadcast.failed_count > 0" class="stat-chip stat-fail">✗ {{ selectedBroadcast.failed_count }}</span>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="historyView === 'thread' && activeRecipient">
                        <div class="conv-av-sm">{{ initials(activeRecipient.name) }}</div>
                        <div>
                            <div class="thread-name">{{ activeRecipient.name }}</div>
                            <div class="thread-phone">{{ activeRecipient.phone }}</div>
                        </div>
                    </template>
                </div>

                <button type="button" @click="doSync(true)" :disabled="syncing || !waStatus.connected" class="sync-btn">
                    <span class="material-symbols-outlined" :class="{ spinning: syncing }" style="font-size:16px">sync</span>
                </button>
            </div>

            <!-- ═══ NIVEAU 1 : CAMPAGNES ═══ -->
            <div v-if="historyView === 'broadcasts'" class="scroll-area">
                <div v-if="loadingBroadcasts" class="empty-feed">
                    <div class="spinner spinner-lg"></div><p>Chargement…</p>
                </div>
                <div v-else-if="broadcasts.length === 0" class="empty-feed">
                    <span class="material-symbols-outlined" style="font-size:48px;color:#d1d5db">campaign</span>
                    <p style="font-weight:600;color:#374151">Aucune campagne</p>
                    <p style="font-size:11px;color:#9aaabb">Envoyez votre premier message groupé</p>
                </div>
                <div v-for="b in broadcasts" :key="b.broadcast_id" class="broadcast-item" @click="openBroadcast(b)">
                    <div class="bc-icon">
                        <span class="material-symbols-outlined" style="font-size:20px;color:#25d366">campaign</span>
                    </div>
                    <div class="bc-body">
                        <div class="bc-row1">
                            <span class="bc-item-name">{{ b.formation_name ?? 'Envoi groupé' }}</span>
                            <span class="bc-item-date">{{ fmtDate(b.sent_at) }}</span>
                        </div>
                        <p class="bc-preview">{{ truncate(b.message) }}</p>
                        <div class="bc-row2">
                            <span class="stat-chip stat-sent">✓ {{ b.recipient_count }} envoyé{{ b.recipient_count > 1 ? 's' : '' }}</span>
                            <span v-if="b.reply_count > 0" class="stat-chip stat-reply">💬 {{ b.reply_count }} réponse{{ b.reply_count > 1 ? 's' : '' }}</span>
                            <span v-if="b.failed_count > 0" class="stat-chip stat-fail">✗ {{ b.failed_count }}</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:4px;flex-shrink:0" @click.stop>
                        <button class="del-btn" title="Supprimer cette campagne" @click="deleteBroadcast(b)">
                            <span class="material-symbols-outlined" style="font-size:15px">delete</span>
                        </button>
                        <span class="material-symbols-outlined" style="font-size:16px;color:#d1d5db">chevron_right</span>
                    </div>
                </div>
            </div>

            <!-- ═══ NIVEAU 2 : DESTINATAIRES ═══ -->
            <template v-else-if="historyView === 'recipients'">
                <div class="campaign-msg">
                    <span class="material-symbols-outlined" style="font-size:13px;color:#25d366;flex-shrink:0;margin-top:1px">campaign</span>
                    <p>{{ selectedBroadcast?.message }}</p>
                </div>
                <div class="filter-bar">
                    <div class="filter-tabs">
                        <button @click="recipientFilter = 'all'"     :class="{ active: recipientFilter === 'all' }"     class="ftab">Tous <span class="ftab-n">{{ broadcastRecipients.length }}</span></button>
                        <button @click="recipientFilter = 'replied'" :class="{ active: recipientFilter === 'replied' }" class="ftab">Répondu <span class="ftab-n">{{ broadcastRecipients.filter(r=>r.has_replied).length }}</span></button>
                        <button @click="recipientFilter = 'pending'" :class="{ active: recipientFilter === 'pending' }" class="ftab">En attente <span class="ftab-n">{{ broadcastRecipients.filter(r=>!r.has_replied).length }}</span></button>
                    </div>
                    <input v-model="recipientSearch" type="text" class="search-input" placeholder="Rechercher…" />
                </div>
                <div class="scroll-area">
                    <div v-if="loadingRecipients" class="empty-feed">
                        <div class="spinner spinner-lg"></div><p>Chargement…</p>
                    </div>
                    <div v-else-if="filteredRecipients.length === 0" class="empty-feed">
                        <span class="material-symbols-outlined" style="font-size:36px;color:#d1d5db">search_off</span>
                        <p>Aucun résultat</p>
                    </div>
                    <div v-for="r in filteredRecipients" :key="r.id"
                        class="recipient-item"
                        :class="{ clickable: !!r.learner_id }"
                        @click="r.learner_id ? openThread(r) : null">
                        <div class="rec-av" :class="{ 'av-replied': r.has_replied }">{{ initials(r.name) }}</div>
                        <div class="rec-info">
                            <div class="rec-row1">
                                <span class="rec-name">{{ r.name }}</span>
                                <span v-if="r.last_reply" class="rec-time">{{ fmtTime(r.last_reply.created_at) }}</span>
                            </div>
                            <div class="rec-row2">
                                <span v-if="r.has_replied" class="rec-reply">
                                    <span class="material-symbols-outlined" style="font-size:11px;color:#25d366">reply</span>
                                    {{ truncate(r.last_reply!.message, 55) }}
                                </span>
                                <span v-else-if="r.status === 'failed'" class="rec-failed">
                                    <span class="material-symbols-outlined" style="font-size:11px;color:#ef4444">error</span>
                                    {{ truncate(r.error ?? 'Non délivré', 45) }}
                                </span>
                                <span v-else class="rec-pending">Pas encore de réponse</span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;flex-shrink:0" @click.stop>
                            <span v-if="r.has_replied" class="reply-badge">{{ r.reply_count }}</span>
                            <button class="del-btn" title="Supprimer ce message" @click="deleteMessage(r.id)">
                                <span class="material-symbols-outlined" style="font-size:14px">delete</span>
                            </button>
                            <span v-if="r.learner_id" class="material-symbols-outlined" style="font-size:14px;color:#d1d5db">chevron_right</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══ NIVEAU 3 : THREAD ═══ -->
            <template v-else-if="historyView === 'thread'">
                <div v-if="loadingThread" class="empty-feed">
                    <div class="spinner spinner-lg"></div><p>Chargement…</p>
                </div>
                <div v-else ref="threadRef" class="thread-feed">
                    <div v-if="threadMessages.length === 0" class="empty-feed">
                        <span class="material-symbols-outlined" style="font-size:36px;color:#d1d5db">chat_bubble_outline</span>
                        <p>Aucun échange</p>
                    </div>
                    <template v-for="group in groupByDate(threadMessages)" :key="group.date">
                        <div class="date-sep"><span>{{ group.date }}</span></div>
                        <template v-for="item in group.items" :key="item.type === 'single' ? item.msg.id : 'mg-' + item.msgs[0].id">

                            <!-- ── Groupe de médias reçus ── -->
                            <div v-if="item.type === 'media-group'" class="bubble-row received">
                                <div class="bubble bubble-received bubble-media-group">
                                    <div class="media-grid">
                                        <div v-for="m in item.msgs" :key="m.id" class="media-cell">
                                            <button class="media-cell-del" title="Supprimer" @click.stop="deleteMsgFromThread(m.id)">
                                                <span class="material-symbols-outlined" style="font-size:10px">close</span>
                                            </button>
                                            <img v-if="isImage(m.attachment_mimetype)"
                                                :src="mediaUrl(m.attachment_path)"
                                                class="media-thumb-img" :alt="m.attachment_filename ?? 'image'" />
                                            <div v-else-if="isAudio(m.attachment_mimetype)" class="media-thumb-other">
                                                <span class="material-symbols-outlined">mic</span>
                                                <span class="media-cell-label">Vocal</span>
                                            </div>
                                            <div v-else-if="isVideo(m.attachment_mimetype)" class="media-thumb-other">
                                                <span class="material-symbols-outlined">videocam</span>
                                                <span class="media-cell-label">Vidéo</span>
                                            </div>
                                            <div v-else class="media-thumb-other">
                                                <span class="material-symbols-outlined">description</span>
                                                <span class="media-cell-label">{{ (m.attachment_filename ?? 'Fichier').slice(0,12) }}</span>
                                            </div>
                                            <a :href="mediaUrl(m.attachment_path)" target="_blank" download class="media-cell-dl">
                                                <span class="material-symbols-outlined" style="font-size:12px">download</span>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="bubble-foot">
                                        <span class="bubble-time">{{ fmtTime(item.created_at) }}</span>
                                        <span class="media-count">{{ item.msgs.length }} fichier(s)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Bulle normale (single) ── -->
                            <div v-else class="bubble-row" :class="item.msg.direction">
                                <div class="bubble-wrap">
                                    <button class="bubble-del"
                                        title="Supprimer" @click.stop="deleteMsgFromThread(item.msg.id)">
                                        <span class="material-symbols-outlined" style="font-size:11px">close</span>
                                    </button>
                                    <!-- Bulle message supprimé -->
                                    <div v-if="item.msg.deleted_at" class="bubble bubble-deleted">
                                        <span class="material-symbols-outlined" style="font-size:13px;opacity:.5">block</span>
                                        <span>{{ item.msg.direction === 'sent' ? 'Vous avez supprimé ce message' : 'Ce message a été supprimé' }}</span>
                                        <div class="bubble-foot"><span class="bubble-time">{{ fmtTime(item.msg.created_at) }}</span></div>
                                    </div>

                                    <!-- Bulle normale -->
                                    <div v-else class="bubble" :class="['bubble-' + item.msg.direction, { 'bubble-failed': item.msg.direction === 'sent' && item.msg.status === 'failed' }]">
                                        <!-- Pièce jointe -->
                                        <div v-if="item.msg.attachment_path" class="bubble-attachment">
                                            <img v-if="isImage(item.msg.attachment_mimetype)"
                                                :src="mediaUrl(item.msg.attachment_path)"
                                                class="attachment-img"
                                                :alt="item.msg.attachment_filename ?? 'image'" />
                                            <video v-else-if="isVideo(item.msg.attachment_mimetype)"
                                                :src="mediaUrl(item.msg.attachment_path)"
                                                class="attachment-video" controls />
                                            <div v-else-if="isAudio(item.msg.attachment_mimetype)" class="voice-note">
                                                <span class="material-symbols-outlined voice-note-icon">mic</span>
                                                <div>
                                                    <p class="voice-note-label">Note vocale</p>
                                                    <audio :src="mediaUrl(item.msg.attachment_path)" class="attachment-audio" controls />
                                                </div>
                                            </div>
                                            <a v-else
                                                :href="mediaUrl(item.msg.attachment_path)"
                                                class="attachment-file"
                                                target="_blank" download>
                                                <span class="material-symbols-outlined" style="font-size:16px">attach_file</span>
                                                {{ item.msg.attachment_filename ?? 'Fichier' }}
                                            </a>
                                        </div>
                                        <p v-if="item.msg.message" class="bubble-text">{{ item.msg.message }}</p>
                                        <div v-if="item.msg.direction === 'sent' && item.msg.status === 'failed'" class="bubble-error">
                                            <span class="material-symbols-outlined" style="font-size:12px">error</span>
                                            {{ item.msg.error ?? 'Non délivré' }}
                                        </div>
                                        <div class="bubble-foot">
                                            <span class="bubble-time">{{ fmtTime(item.msg.created_at) }}</span>
                                            <span v-if="item.msg.direction === 'sent'" class="material-symbols-outlined" style="font-size:11px"
                                                :style="{ color: item.msg.status === 'sent' ? '#25d366' : item.msg.status === 'sending' ? '#9ca3af' : '#ef4444' }">
                                                {{ item.msg.status === 'sent' ? 'done_all' : item.msg.status === 'sending' ? 'schedule' : 'error' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </template>
                    </template>
                </div>

                <!-- Zone de réponse directe -->
                <div class="reply-box" :class="{ 'reply-box--disabled': !waStatus.connected }">
                    <div v-if="replyError" class="reply-error">
                        <span class="material-symbols-outlined" style="font-size:13px">error</span>
                        {{ replyError }}
                    </div>
                    <!-- Aperçu pièce jointe -->
                    <div v-if="replyAttachment" class="attach-preview">
                        <span class="material-symbols-outlined" style="font-size:13px;color:#6b7280">attach_file</span>
                        <span class="attach-name">{{ replyAttachment.name }}</span>
                        <button class="attach-rm" @click.stop="removeAttachment" title="Retirer">
                            <span class="material-symbols-outlined" style="font-size:12px">close</span>
                        </button>
                    </div>
                    <div class="reply-input-row">
                        <!-- Barre d'outils -->
                        <div class="reply-tools" @click.stop>
                            <div class="emoji-wrap">
                                <button class="tool-btn" :disabled="!waStatus.connected" title="Emoji"
                                    @click.stop="showEmojiPicker = !showEmojiPicker">
                                    <span class="material-symbols-outlined" style="font-size:18px">mood</span>
                                </button>
                                <div v-if="showEmojiPicker" class="emoji-picker" @click.stop>
                                    <div class="emoji-grid">
                                        <button v-for="em in commonEmojis" :key="em" class="emoji-btn" @click="insertEmoji(em)">{{ em }}</button>
                                    </div>
                                </div>
                            </div>
                            <button class="tool-btn" :disabled="!waStatus.connected" title="Pièce jointe"
                                @click.stop="attachInputRef?.click()">
                                <span class="material-symbols-outlined" style="font-size:18px">attach_file</span>
                            </button>
                            <input ref="attachInputRef" type="file"
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                                style="display:none" @change="onAttachChange" />
                        </div>
                        <!-- Textarea auto-extensible -->
                        <textarea
                            ref="replyTextareaRef"
                            v-model="replyText"
                            :disabled="!waStatus.connected || sendingReply"
                            :placeholder="waStatus.connected ? 'Écrire une réponse… (Entrée pour envoyer, Maj+Entrée pour saut)' : 'WhatsApp non connecté'"
                            class="reply-textarea"
                            rows="1"
                            @keydown="onReplyKeydown"
                            @input="onReplyInput"
                        />
                        <button
                            class="reply-send-btn"
                            :disabled="(!replyText.trim() && !replyAttachment) || !waStatus.connected || sendingReply"
                            @click="sendReply"
                        >
                            <div v-if="sendingReply" class="spinner" style="width:16px;height:16px;border-width:2px"></div>
                            <span v-else class="material-symbols-outlined" style="font-size:18px">send</span>
                        </button>
                    </div>
                </div>
            </template>

        </div>
    </div>

    <!-- Modal de confirmation -->
    <Teleport to="body">
        <div v-if="confirmModal.open" class="modal-overlay" @click.self="resolveConfirm(false)">
            <div class="modal-box" role="dialog" aria-modal="true">
                <div class="modal-head">
                    <span class="material-symbols-outlined" style="font-size:20px;color:#dc2626">warning</span>
                    <h3 class="modal-title">{{ confirmModal.title }}</h3>
                </div>
                <p class="modal-msg">{{ confirmModal.message }}</p>
                <div class="modal-actions">
                    <button class="btn-modal-cancel" @click="resolveConfirm(false)">Annuler</button>
                    <button :class="['btn-modal-confirm', confirmModal.confirmClass]" @click="resolveConfirm(true)">
                        {{ confirmModal.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</div>
</template>

<style scoped>
.wa-page { max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }

/* Top bar */
.top-bar  { display:flex; align-items:center; justify-content:space-between; }
.top-left { display:flex; align-items:center; gap:12px; }
.top-right{ display:flex; align-items:center; gap:10px; }
.back-btn { display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;border:1.5px solid #1F3A4D;color:#1F3A4D;background:transparent;text-decoration:none;transition:background .15s,color .15s; }
.back-btn:hover { background:#1F3A4D;color:#fff; }
.wa-icon  { width:38px;height:38px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;display:flex;align-items:center;justify-content:center; }
.page-title { font-size:20px;font-weight:700;color:#191c1e;margin:0; }
.page-sub   { font-size:12px;color:#6b7280;margin:0; }
.connected-info  { display:flex;flex-direction:column;align-items:flex-end;gap:2px; }
.connected-phone { display:flex;align-items:center;gap:3px;font-size:11px;color:#374151;font-family:monospace;font-weight:600; }
.status-pill { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:99px;font-size:12px;font-weight:600; }
.status-pill.connected { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534; }
.status-pill.authing   { background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8; }
.status-pill.pending   { background:#fff7ed;border:1px solid #fed7aa;color:#9a3412; }
.status-pill.offline   { background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280; }
.dot { width:8px;height:8px;border-radius:50%; }
.dot-green  { background:#22c55e;animation:pulse 2s infinite; }
.dot-orange { background:#f97316; }
.notif-btn { display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border:1px solid #fed7aa;border-radius:8px;font-size:11px;font-weight:600;color:#9a3412;background:#fff7ed;cursor:pointer;transition:all .15s; }
.notif-btn:hover:not(:disabled) { background:#ffedd5; }
.notif-btn:disabled { opacity:.55;cursor:not-allowed; }
.btn-disconnect { display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1px solid #fca5a5;border-radius:8px;font-size:12px;font-weight:600;color:#dc2626;background:#fff;cursor:pointer;transition:all .15s; }
.btn-disconnect:hover:not(:disabled){ background:#fee2e2; }
.btn-disconnect:disabled{ opacity:.5;cursor:not-allowed; }

.alert-banner { display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;font-size:12px;background:#fef9c3;border:1px solid #fde68a;color:#78350f; }
.alert-banner code { background:#fef3c7;padding:1px 5px;border-radius:4px;font-weight:600; }

/* Auth */
.auth-panel { display:flex;align-items:center;gap:24px;padding:24px 28px;border:1px solid #bbf7d0;border-radius:14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7); }
.auth-spinner-wrap { position:relative;width:60px;height:60px;flex-shrink:0;display:flex;align-items:center;justify-content:center; }
.spinner-ring { position:absolute;inset:0;border-radius:50%;border:3px solid #bbf7d0;border-top-color:#25d366;animation:spin 1s linear infinite; }
.auth-title { font-size:16px;font-weight:700;color:#14532d;margin:0 0 4px; }
.auth-sub   { font-size:13px;color:#166534;margin:0; }

/* QR */
.qr-panel { display:grid;grid-template-columns:1fr auto;gap:32px;background:#fff;border:1px solid #e0e3e5;border-radius:14px;padding:24px 28px; }
.qr-title { font-size:15px;font-weight:700;color:#191c1e;margin-bottom:12px; }
.qr-steps { list-style:none;padding:0;margin:0;counter-reset:step;display:flex;flex-direction:column;gap:9px; }
.qr-steps li { font-size:13px;color:#515f74;padding-left:24px;position:relative;counter-increment:step; }
.qr-steps li::before { content:counter(step);position:absolute;left:0;top:0;width:17px;height:17px;background:#25d366;color:#fff;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center; }
.qr-right { display:flex;justify-content:flex-end; }
.qr-box   { display:flex;flex-direction:column;align-items:center; }
.qr-img   { width:180px;height:180px;border:1px solid #e0e3e5;border-radius:8px; }
.qr-placeholder { width:220px;min-height:180px;border:2px dashed #e0e3e5;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:16px; }
.startup-info   { display:flex;flex-direction:column;align-items:center;gap:12px; }
.startup-label  { font-size:12px;font-weight:600;color:#374151;margin:0; }
.startup-hint   { font-size:11px;color:#6b7280;margin:0; }
.startup-warn   { font-size:11px;color:#b45309;margin:0; }
.startup-warn code { background:#fef3c7;padding:1px 4px;border-radius:3px;font-weight:600; }

/* Grid */
.main-grid { display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start; }
@media(max-width:960px){ .main-grid{ grid-template-columns:1fr; } }

/* Card */
.card { background:#fff;border:1px solid #e0e3e5;border-radius:14px;overflow:hidden; }
.card-head { display:flex;align-items:center;gap:7px;padding:12px 16px;background:#f8f9fa;border-bottom:1px solid #e0e3e5;font-size:11px;font-weight:700;color:#515f74;text-transform:uppercase;letter-spacing:.06em; }

/* History panel */
.history-panel { display:flex;flex-direction:column;min-height:520px; }
.scroll-area   { overflow-y:auto;max-height:560px; }
.icon-btn { display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:none;background:none;border-radius:6px;color:#6b7280;cursor:pointer;transition:all .15s;flex-shrink:0; }
.icon-btn:hover { background:#e5e7eb; }
.count-chip { display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#f3f4f6;border-radius:99px;font-size:10px;font-weight:700;color:#6b7280; }
.sync-btn { display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #e0e3e5;border-radius:6px;background:#fff;color:#6b7280;cursor:pointer;transition:all .15s;flex-shrink:0; }
.sync-btn:hover:not(:disabled){ background:#f0fdf4;color:#25d366;border-color:#86efac; }
.sync-btn:disabled { opacity:.4;cursor:not-allowed; }
.empty-feed { display:flex;flex-direction:column;align-items:center;gap:8px;padding:60px 20px;text-align:center;color:#6b7280;font-size:13px; }

/* Broadcast header */
.bc-name-h { font-size:13px;font-weight:700;color:#191c1e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.stat-chip  { display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600; }
.stat-sent  { background:#f0fdf4;color:#166534; }
.stat-reply { background:#eff6ff;color:#1d4ed8; }
.stat-fail  { background:#fee2e2;color:#991b1b; }

/* Broadcast list */
.broadcast-item { display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .12s; }
.broadcast-item:last-child { border-bottom:none; }
.broadcast-item:hover { background:#f8fffe; }
.bc-icon { width:40px;height:40px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.bc-body { flex:1;min-width:0;display:flex;flex-direction:column;gap:4px; }
.bc-row1 { display:flex;align-items:center;justify-content:space-between; }
.bc-item-name { font-size:13px;font-weight:600;color:#191c1e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px; }
.bc-item-date { font-size:11px;color:#9aaabb;flex-shrink:0; }
.bc-preview   { font-size:12px;color:#6b7280;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.bc-row2      { display:flex;gap:6px;flex-wrap:wrap; }

/* Recipients */
.campaign-msg { display:flex;align-items:flex-start;gap:8px;padding:10px 16px;background:#f0fdf4;border-bottom:1px solid #dcfce7;font-size:12px;color:#166534;font-style:italic;line-height:1.5; }
.campaign-msg p { margin:0; }
.filter-bar  { display:flex;align-items:center;gap:8px;padding:8px 16px;border-bottom:1px solid #f0f0f0;background:#fafafa;flex-wrap:wrap; }
.filter-tabs { display:flex;gap:2px; }
.ftab        { padding:4px 10px;border:1px solid #e5e7eb;border-radius:99px;font-size:11px;font-weight:500;color:#6b7280;background:#fff;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:4px; }
.ftab.active { background:#25d366;border-color:#25d366;color:#fff; }
.ftab-n      { display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;padding:0 3px;background:rgba(0,0,0,.1);border-radius:99px;font-size:10px; }
.ftab.active .ftab-n { background:rgba(255,255,255,.25); }
.search-input { flex:1;min-width:100px;padding:5px 10px;border:1px solid #e0e3e5;border-radius:8px;font-size:12px;outline:none; }
.search-input:focus { border-color:#25d366; }
.recipient-item { display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid #f3f4f6;transition:background .12s; }
.recipient-item:last-child { border-bottom:none; }
.recipient-item.clickable { cursor:pointer; }
.recipient-item.clickable:hover { background:#f8fffe; }
.rec-av { width:40px;height:40px;background:#e5e7eb;color:#374151;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0; }
.av-replied { background:#dcfce7;color:#166534; }
.rec-info { flex:1;min-width:0; }
.rec-row1 { display:flex;align-items:center;justify-content:space-between;margin-bottom:2px; }
.rec-name { font-size:13px;font-weight:600;color:#191c1e; }
.rec-time { font-size:11px;color:#9aaabb;flex-shrink:0; }
.rec-row2 { display:flex;align-items:center;gap:4px; }
.rec-reply  { display:flex;align-items:center;gap:3px;font-size:12px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.rec-failed { display:flex;align-items:center;gap:3px;font-size:12px;color:#ef4444;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.rec-pending { font-size:12px;color:#9aaabb;font-style:italic; }
.reply-badge { display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 4px;background:#25d366;color:#fff;border-radius:99px;font-size:10px;font-weight:700; }
.del-btn { display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:none;background:none;border-radius:6px;color:#d1d5db;cursor:pointer;transition:all .15s;flex-shrink:0; }
.del-btn:hover { background:#fee2e2;color:#dc2626; }

/* Thread */
.conv-av-sm  { width:36px;height:36px;background:#dcfce7;color:#166534;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0; }
.thread-name { font-size:13px;font-weight:700;color:#191c1e; }
.thread-phone{ font-size:11px;color:#6b7280;font-family:monospace; }
.thread-feed { overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:4px;max-height:520px; }
.date-sep    { display:flex;align-items:center;justify-content:center;margin:8px 0 4px; }
.date-sep span { padding:2px 12px;background:#f3f4f6;border-radius:99px;font-size:11px;font-weight:600;color:#6b7280; }
.bubble-row { display:flex;margin-bottom:2px; }
.bubble-row.sent     { justify-content:flex-end; }
.bubble-row.received { justify-content:flex-start; }
.bubble-wrap { position:relative;max-width:75%; }
.bubble          { border-radius:12px;padding:8px 12px; }
.bubble-sent     { background:#dcfce7;border-bottom-right-radius:3px; }
.bubble-received { background:#f9fafb;border:1px solid #e5e7eb;border-bottom-left-radius:3px; }
.bubble-failed   { background:#fef2f2;border:1px solid #fecaca; }
.bubble-text { font-size:13px;color:#191c1e;margin:0;white-space:pre-wrap;word-break:break-word;line-height:1.5; }
.bubble-error { display:flex;align-items:center;gap:3px;margin-top:4px;font-size:11px;color:#dc2626;font-weight:500; }
.bubble-foot { display:flex;align-items:center;gap:4px;justify-content:flex-end;margin-top:4px; }
.bubble-time { font-size:10px;color:#9aaabb; }
.bubble-deleted { display:flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;color:#9ca3af;font-size:12px;font-style:italic;border-radius:12px;padding:7px 11px;pointer-events:none; }
.bubble-deleted .bubble-foot { margin-top:3px; }
.bubble-attachment { margin-bottom:4px; }
.attachment-img   { max-width:220px;max-height:200px;border-radius:8px;display:block;cursor:pointer;object-fit:cover; }
.attachment-video { max-width:220px;border-radius:8px;display:block; }
.attachment-audio { max-width:200px;height:32px;display:block; }
.voice-note { display:flex;align-items:center;gap:8px;padding:4px 0; }
.voice-note-icon { font-size:22px;color:#25d366;flex-shrink:0; }
.voice-note-label { font-size:10px;color:#9aaabb;margin:0 0 3px;font-weight:600;text-transform:uppercase;letter-spacing:.04em; }
.attachment-file  { display:inline-flex;align-items:center;gap:5px;padding:6px 10px;background:rgba(0,0,0,.06);border-radius:8px;font-size:12px;color:#374151;text-decoration:none;font-weight:500;word-break:break-all; }
.attachment-file:hover { background:rgba(0,0,0,.1); }
.bubble-del { position:absolute;top:-7px;right:-7px;width:20px;height:20px;border-radius:50%;background:#ef4444;border:2px solid #fff;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s;z-index:2;padding:0;pointer-events:none; }
.bubble-wrap:hover .bubble-del { opacity:1;pointer-events:auto; }

/* Groupe de médias reçus */
.bubble-media-group { padding:6px 6px 8px; }
.media-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:3px;max-width:220px; }
.media-cell { position:relative;border-radius:6px;overflow:hidden;background:#e5e7eb;aspect-ratio:1;display:flex;align-items:center;justify-content:center; }
.media-thumb-img { width:100%;height:100%;object-fit:cover;display:block; }
.media-thumb-other { display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:#6b7280;font-size:22px;padding:4px;width:100%;height:100%; }
.media-cell-label { font-size:9px;text-align:center;line-height:1.1;max-width:90%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6b7280; }
.media-cell-del { position:absolute;top:2px;left:2px;z-index:3;background:rgba(239,68,68,.85);border:none;border-radius:50%;width:16px;height:16px;display:none;align-items:center;justify-content:center;cursor:pointer;color:#fff;padding:0; }
.media-cell:hover .media-cell-del { display:flex; }
.media-cell-dl { position:absolute;bottom:2px;right:2px;background:rgba(0,0,0,.45);border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none; }
.media-count { font-size:10px;color:#9aaabb;margin-left:4px; }

/* Reply box */
.reply-box { border-top:1px solid #e5e7eb;padding:8px 12px 10px;background:#fff;flex-shrink:0; }
.reply-box--disabled { background:#f9fafb; }
.reply-error { display:flex;align-items:center;gap:5px;padding:5px 10px;margin-bottom:6px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#dc2626;font-weight:500; }
.attach-preview { display:flex;align-items:center;gap:6px;padding:4px 8px;margin-bottom:6px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;font-size:12px;color:#0369a1; }
.attach-name { flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px; }
.attach-rm { display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border:none;background:none;cursor:pointer;color:#0369a1;border-radius:3px;padding:0; }
.attach-rm:hover { background:#bae6fd; }
.reply-input-row { display:flex;align-items:flex-end;gap:6px; }
.reply-tools { display:flex;align-items:center;gap:2px;flex-shrink:0;position:relative; }
.tool-btn { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:none;background:none;border-radius:8px;color:#9aaabb;cursor:pointer;transition:all .15s;padding:0; }
.tool-btn:hover:not(:disabled) { background:#f3f4f6;color:#374151; }
.tool-btn:disabled { opacity:.35;cursor:not-allowed; }
.emoji-wrap { position:relative; }
.emoji-picker { position:absolute;bottom:36px;left:0;z-index:50;background:#fff;border:1px solid #e0e3e5;border-radius:10px;padding:8px;box-shadow:0 4px 20px rgba(0,0,0,.12);width:230px; }
.emoji-grid { display:grid;grid-template-columns:repeat(6,1fr);gap:2px; }
.emoji-btn { font-size:18px;padding:4px;border:none;background:none;cursor:pointer;border-radius:6px;transition:background .1s;text-align:center; }
.emoji-btn:hover { background:#f3f4f6; }
.reply-textarea { flex:1;resize:none;border:1.5px solid #e5e7eb;border-radius:10px;padding:7px 11px;font-size:13px;line-height:1.5;color:#191c1e;background:#f8f9fa;transition:border-color .15s;font-family:inherit;outline:none;min-height:36px;max-height:160px;overflow-y:auto; }
.reply-textarea:focus { border-color:#E5004C;background:#fff; }
.reply-textarea:disabled { background:#f3f4f6;color:#9ca3af;cursor:not-allowed; }
.reply-send-btn { width:36px;height:36px;min-width:36px;border-radius:50%;background:#E5004C;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;transition:background .15s,opacity .15s;flex-shrink:0; }
.reply-send-btn:hover:not(:disabled) { background:#c0003e; }
.reply-send-btn:disabled { background:#f3c1ce;cursor:not-allowed;opacity:.7; }

/* Modal de confirmation */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px; }
.modal-box { background:#fff;border-radius:14px;padding:24px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-head { display:flex;align-items:center;gap:10px;margin-bottom:10px; }
.modal-title { font-size:15px;font-weight:700;color:#191c1e;margin:0; }
.modal-msg { font-size:13px;color:#515f74;margin:0 0 20px;line-height:1.5; }
.modal-actions { display:flex;justify-content:flex-end;gap:10px; }
.btn-modal-cancel  { padding:7px 16px;border:1px solid #e0e3e5;border-radius:8px;font-size:13px;font-weight:500;color:#374151;background:#fff;cursor:pointer;transition:all .15s; }
.btn-modal-cancel:hover  { background:#f3f4f6; }
.btn-modal-confirm { padding:7px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s; }
.btn-danger { background:#dc2626;color:#fff; }
.btn-danger:hover { background:#b91c1c; }

/* Spinners */
.spinner    { width:20px;height:20px;border:2px solid #e0e3e5;border-top-color:#25d366;border-radius:50%;animation:spin .8s linear infinite; }
.spinner-lg { width:36px;height:36px;border-width:3px; }
.spinner-xs { width:12px;height:12px;border:2px solid #bfdbfe;border-top-color:#3b82f6;border-radius:50%;animation:spin .8s linear infinite; }
.spinning   { animation:spin 1s linear infinite; }
@keyframes spin  { to{ transform:rotate(360deg); } }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.35} }
</style>
