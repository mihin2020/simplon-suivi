<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed } from 'vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    threadId: string
    emails: any[]
}>()

const replyBody  = ref('')
const showReply  = ref(false)
const showFwd    = ref(false)
const forwardTo  = ref('')
const forwardBody = ref('')

// Dernier email reçu (pour répondre/transférer/archiver)
const lastReceived = computed(() =>
    [...props.emails].reverse().find(e => e.direction === 'received')
)

// Sujet original (sans préfixes Re:/Fwd:)
const threadSubject = computed(() => {
    const subj = props.emails[0]?.subject ?? ''
    return subj.replace(/^(re|fwd?|réf?|tr)\s*:\s*/iu, '').trim()
})

function fmtDate(d: string | null) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

function reply() {
    if (!lastReceived.value) return
    router.post(`/communication/emails/${lastReceived.value.id}/reply`, { body: replyBody.value }, {
        preserveScroll: true,
        onSuccess: () => { replyBody.value = ''; showReply.value = false }
    })
}

function forward() {
    if (!lastReceived.value) return
    const to = forwardTo.value.split(',').map(e => e.trim()).filter(Boolean).map(e => ({ email: e }))
    router.post(`/communication/emails/${lastReceived.value.id}/forward`, { to, body: forwardBody.value }, {
        preserveScroll: true,
        onSuccess: () => { forwardTo.value = ''; forwardBody.value = ''; showFwd.value = false }
    })
}

function archive(email: any) {
    router.patch(`/communication/emails/${email.id}/archive`, {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Fil de discussion" />

    <div class="thread-page">

        <!-- En-tête thread -->
        <div class="thread-header">
            <Link href="/communication/emails" class="back-btn">
                <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
            </Link>
            <div class="thread-header-info">
                <h1 class="thread-subject">{{ threadSubject }}</h1>
                <span class="thread-count">{{ emails.length }} message{{ emails.length > 1 ? 's' : '' }}</span>
            </div>
        </div>

        <!-- Conversation -->
        <div class="conversation">
            <div
                v-for="(e, idx) in emails"
                :key="e.id"
                class="msg-wrap"
                :class="e.direction === 'sent' ? 'msg-sent' : 'msg-received'"
            >
                <!-- Avatar + méta -->
                <div class="msg-meta">
                    <div class="msg-avatar" :class="e.direction === 'sent' ? 'av-sent' : 'av-recv'">
                        {{ (e.from_name || e.from_email || '?')[0].toUpperCase() }}
                    </div>
                    <div class="msg-meta-info">
                        <span class="msg-sender">
                            {{ e.direction === 'sent' ? 'Vous' : (e.from_name || e.from_email) }}
                        </span>
                        <span class="msg-date">{{ fmtDate(e.received_at || e.sent_at) }}</span>
                    </div>
                </div>

                <!-- Bulle -->
                <div class="msg-bubble" :class="e.direction === 'sent' ? 'bubble-sent' : 'bubble-recv'">
                    <div class="msg-body" v-html="e.body_html" />

                    <!-- Pièces jointes -->
                    <div v-if="e.attachments?.length" class="msg-attachments">
                        <a
                            v-for="a in e.attachments"
                            :key="a.id"
                            :href="`/storage/${a.path}`"
                            target="_blank"
                            class="attach-chip"
                        >
                            <span class="material-symbols-outlined" style="font-size:15px">attach_file</span>
                            {{ a.filename }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions sur le dernier email reçu -->
        <div v-if="lastReceived" class="thread-actions">
            <button @click="showReply = !showReply; showFwd = false" class="action-btn action-reply">
                <span class="material-symbols-outlined" style="font-size:17px">reply</span>
                Répondre
            </button>
            <button @click="showFwd = !showFwd; showReply = false" class="action-btn">
                <span class="material-symbols-outlined" style="font-size:17px">forward</span>
                Transférer
            </button>
            <button @click="archive(lastReceived)" class="action-btn">
                <span class="material-symbols-outlined" style="font-size:17px">archive</span>
                Archiver
            </button>
        </div>

        <!-- Formulaire réponse -->
        <div v-if="showReply" class="reply-box">
            <p class="reply-label">
                <span class="material-symbols-outlined" style="font-size:15px">reply</span>
                Répondre à <strong>{{ lastReceived?.from_name || lastReceived?.from_email }}</strong>
            </p>
            <TiptapEditor v-model="replyBody" />
            <div class="reply-footer">
                <button @click="showReply = false" class="action-btn">Annuler</button>
                <button @click="reply()" class="action-btn action-reply">
                    <span class="material-symbols-outlined" style="font-size:16px">send</span>
                    Envoyer
                </button>
            </div>
        </div>

        <!-- Formulaire transfert -->
        <div v-if="showFwd" class="reply-box">
            <p class="reply-label">
                <span class="material-symbols-outlined" style="font-size:15px">forward</span>
                Transférer le message
            </p>
            <input v-model="forwardTo" type="text" placeholder="Emails séparés par des virgules" class="fwd-input" />
            <textarea v-model="forwardBody" rows="3" placeholder="Message optionnel..." class="fwd-input" style="resize:vertical" />
            <div class="reply-footer">
                <button @click="showFwd = false" class="action-btn">Annuler</button>
                <button @click="forward()" class="action-btn action-reply">
                    <span class="material-symbols-outlined" style="font-size:16px">send</span>
                    Transférer
                </button>
            </div>
        </div>

    </div>
</template>

<style scoped>
.thread-page {
    max-width: 780px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ── En-tête ── */
.thread-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
}
.back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f5f7f9;
    color: #515f74;
    text-decoration: none;
    flex-shrink: 0;
    transition: background 0.15s;
}
.back-btn:hover { background: #eceef0; }
.thread-header-info { flex: 1; min-width: 0; }
.thread-subject {
    font-size: 17px;
    font-weight: 700;
    color: #191c1e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.thread-count {
    font-size: 12px;
    color: #9aaabb;
    font-weight: 500;
}

/* ── Conversation ── */
.conversation {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 20px;
    background: #fafbfc;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
}

.msg-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-width: 88%;
}
.msg-sent     { align-self: flex-end; align-items: flex-end; }
.msg-received { align-self: flex-start; align-items: flex-start; }

.msg-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.msg-sent .msg-meta { flex-direction: row-reverse; }

.msg-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}
.av-sent { background: #ffe0ec; color: #E5004C; }
.av-recv { background: #e8f0fe; color: #1d4ed8; }

.msg-meta-info { display: flex; flex-direction: column; gap: 1px; }
.msg-sent .msg-meta-info { align-items: flex-end; }
.msg-sender { font-size: 12px; font-weight: 700; color: #191c1e; }
.msg-date   { font-size: 11px; color: #9aaabb; }

.msg-bubble {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
    color: #191c1e;
}
.bubble-sent {
    background: #fff0f5;
    border: 1px solid #ffd6e7;
    border-top-right-radius: 4px;
}
.bubble-recv {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-top-left-radius: 4px;
}

.msg-body { word-break: break-word; }
.msg-body :deep(p)  { margin: 0 0 6px; }
.msg-body :deep(a)  { color: #E5004C; }
.msg-body :deep(img){ max-width: 100%; border-radius: 6px; }

.msg-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(0,0,0,0.06);
}
.attach-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    background: #f5f7f9;
    border: 1px solid #e0e3e5;
    border-radius: 99px;
    font-size: 11px;
    color: #515f74;
    text-decoration: none;
    transition: background 0.15s;
}
.attach-chip:hover { background: #eceef0; }

/* ── Actions ── */
.thread-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
    color: #515f74;
    cursor: pointer;
    transition: background 0.15s;
}
.action-btn:hover { background: #f5f7f9; }
.action-reply { background: #E5004C; color: #fff; border-color: #E5004C; }
.action-reply:hover { background: #c4003f; border-color: #c4003f; }

/* ── Zone réponse ── */
.reply-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
}
.reply-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #515f74;
    font-weight: 600;
}
.reply-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.fwd-input {
    width: 100%;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: #191c1e;
    background: #fafbfc;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.fwd-input:focus { border-color: #E5004C; }

/* ══ Responsive Thread ════════════════════════════════════ */
@media (max-width: 767px) {
    .thread-page { gap: 10px; }

    /* Header thread */
    .thread-header { padding: 12px 14px; gap: 10px; }
    .thread-subject { font-size: 15px; }
    .back-btn { width: 32px; height: 32px; }

    /* Messages */
    .conversation { padding: 14px; gap: 10px; }
    .msg-wrap { max-width: 96%; }
    .msg-bubble { padding: 10px 13px; font-size: 12px; }
    .msg-avatar { width: 26px; height: 26px; font-size: 11px; }
    .msg-sender { font-size: 11px; }
    .msg-date   { font-size: 10px; }

    /* Actions */
    .thread-actions { flex-wrap: wrap; gap: 6px; }
    .action-btn { padding: 7px 12px; font-size: 12px; flex: 1; justify-content: center; }

    /* Reply box */
    .reply-box { padding: 12px 14px; gap: 8px; }
    .fwd-input  { font-size: 13px; padding: 7px 10px; }
}
@media (min-width: 768px) and (max-width: 1023px) {
    .thread-page { max-width: 100%; }
    .msg-bubble { font-size: 13px; }
}
</style>
