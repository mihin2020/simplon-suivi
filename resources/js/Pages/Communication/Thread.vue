<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, nextTick } from 'vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    threadId: string
    emails: any[]
}>()

const showReply  = ref(false)
const showFwd    = ref(false)
const sending    = ref(false)
const archiving  = ref(false)

const replyForm = useForm({ body: '' })
const replyFiles = ref<File[]>([])
const replyFileInput = ref<HTMLInputElement | null>(null)

const forwardTo   = ref('')
const forwardBody = ref('')

const imgPreview = ref<{ src: string; name: string } | null>(null)

const lastReceived = computed(() =>
    [...props.emails].reverse().find(e => e.direction === 'received')
)

const threadSubject = computed(() => {
    const subj = props.emails[0]?.subject ?? ''
    return subj.replace(/^(re|fwd?|réf?|tr)\s*:\s*/iu, '').trim()
})

const participants = computed(() => {
    const names = new Set<string>()
    props.emails.forEach(e => {
        if (e.direction === 'received') names.add(e.from_name || e.from_email)
    })
    return [...names]
})

function fmtDate(d: string | null) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

function fmtSize(bytes: number | null) {
    if (!bytes) return ''
    if (bytes < 1024) return `${bytes} o`
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`
    return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

function fileIcon(mime: string | null): string {
    if (!mime) return 'attach_file'
    if (mime.startsWith('image/'))       return 'image'
    if (mime === 'application/pdf')       return 'picture_as_pdf'
    if (mime.includes('word') || mime.includes('document')) return 'description'
    if (mime.includes('excel') || mime.includes('sheet'))   return 'table_chart'
    if (mime.includes('zip') || mime.includes('compressed')) return 'folder_zip'
    return 'attach_file'
}

function fileColor(mime: string | null): string {
    if (!mime) return '#515f74'
    if (mime.startsWith('image/'))       return '#7c3aed'
    if (mime === 'application/pdf')       return '#dc2626'
    if (mime.includes('word') || mime.includes('document')) return '#1d4ed8'
    if (mime.includes('excel') || mime.includes('sheet'))   return '#15803d'
    if (mime.includes('zip'))             return '#b45309'
    return '#515f74'
}

function isImage(mime: string | null) {
    return mime?.startsWith('image/') ?? false
}

function downloadUrl(id: string) {
    return `/communication/emails/attachments/${id}/download`
}

function openReply() {
    showReply.value = true
    showFwd.value = false
    nextTick(() => {
        document.querySelector<HTMLElement>('.reply-box')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    })
}

function addReplyFiles(e: Event) {
    const files = (e.target as HTMLInputElement).files
    if (files) replyFiles.value.push(...Array.from(files))
}

function removeReplyFile(i: number) {
    replyFiles.value.splice(i, 1)
}

function reply() {
    if (!lastReceived.value || sending.value) return
    sending.value = true

    const formData = new FormData()
    formData.append('body', replyForm.body)
    replyFiles.value.forEach((f, i) => formData.append(`attachments[${i}]`, f))

    router.post(`/communication/emails/${lastReceived.value.id}/reply`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.body = ''
            replyFiles.value = []
            showReply.value = false
        },
        onFinish: () => { sending.value = false }
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

function decodeMimeFilename(name: string): string {
    if (!name || !name.includes('=?')) return name
    // Coller les mots encodés consécutifs (RFC 2047 : espace entre mots ignoré)
    const joined = name.replace(/\?=\s+=\?/g, '?==?')
    return joined.replace(/=\?([^?]+)\?([BQbq])\?([^?]*)\?=/g, (_, charset, enc, text) => {
        try {
            if (enc.toUpperCase() === 'Q') {
                const bytes: number[] = []
                const s = text.replace(/_/g, ' ')
                let i = 0
                while (i < s.length) {
                    if (s[i] === '=' && i + 2 < s.length) {
                        bytes.push(parseInt(s.slice(i + 1, i + 3), 16))
                        i += 3
                    } else {
                        bytes.push(s.charCodeAt(i))
                        i++
                    }
                }
                return new TextDecoder(charset).decode(new Uint8Array(bytes))
            }
            if (enc.toUpperCase() === 'B') {
                const bin = atob(text)
                const bytes = new Uint8Array(bin.length)
                for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
                return new TextDecoder(charset).decode(bytes)
            }
        } catch { /* nom brut en fallback */ }
        return text
    })
}

function openDownload(id: string) {
    window.location.href = downloadUrl(id)
}

function archive(email: any) {
    archiving.value = true
    router.patch(`/communication/emails/${email.id}/archive`, {}, {
        preserveScroll: true,
        onFinish: () => { archiving.value = false }
    })
}
</script>

<template>
    <Head title="Fil de discussion" />

    <div class="thread-page">

        <!-- ── En-tête ── -->
        <div class="thread-header">
            <Link href="/communication/emails" class="back-btn" title="Retour à la messagerie">
                <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
            </Link>
            <div class="thread-header-info">
                <h1 class="thread-subject">{{ threadSubject || '(Sans sujet)' }}</h1>
                <div class="thread-meta-row">
                    <span class="thread-count-badge">
                        <span class="material-symbols-outlined" style="font-size:13px">forum</span>
                        {{ emails.length }} message{{ emails.length > 1 ? 's' : '' }}
                    </span>
                    <span v-for="p in participants" :key="p" class="thread-participant">{{ p }}</span>
                </div>
            </div>
            <!-- Actions rapides -->
            <div class="thread-header-actions">
                <button v-if="lastReceived" @click="openReply" class="hdr-btn hdr-btn-reply" title="Répondre">
                    <span class="material-symbols-outlined" style="font-size:17px">reply</span>
                    <span class="hdr-btn-label">Répondre</span>
                </button>
                <button v-if="lastReceived" @click="showFwd = !showFwd; showReply = false" class="hdr-btn" title="Transférer">
                    <span class="material-symbols-outlined" style="font-size:17px">forward</span>
                    <span class="hdr-btn-label">Transférer</span>
                </button>
                <button v-if="lastReceived" @click="archive(lastReceived)" class="hdr-btn" :disabled="archiving" title="Archiver">
                    <span class="material-symbols-outlined" style="font-size:17px">{{ archiving ? 'hourglass_empty' : 'archive' }}</span>
                </button>
            </div>
        </div>

        <!-- ── Fil de messages ── -->
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
                        <span class="msg-sender">{{ e.direction === 'sent' ? 'Vous' : (e.from_name || e.from_email) }}</span>
                        <span class="msg-date">{{ fmtDate(e.received_at || e.sent_at) }}</span>
                    </div>
                    <span v-if="e.direction === 'received' && !e.is_read" class="unread-dot" title="Non lu"></span>
                </div>

                <!-- Bulle -->
                <div class="msg-bubble" :class="e.direction === 'sent' ? 'bubble-sent' : 'bubble-recv'">
                    <div class="msg-body" v-html="e.body_html || e.body_text || '<em>(corps vide)</em>'" />

                    <!-- Pièces jointes -->
                    <div v-if="e.attachments?.length" class="msg-attachments">
                        <div class="attachments-label">
                            <span class="material-symbols-outlined" style="font-size:13px">attach_file</span>
                            {{ e.attachments.length }} pièce{{ e.attachments.length > 1 ? 's' : '' }} jointe{{ e.attachments.length > 1 ? 's' : '' }}
                        </div>
                        <div class="attachments-grid">
                            <a
                                v-for="a in e.attachments"
                                :key="a.id"
                                :href="downloadUrl(a.id)"
                                download
                                class="attach-card"
                                :title="`Télécharger ${decodeMimeFilename(a.filename)}`"
                                @click.prevent="isImage(a.mime_type) ? (imgPreview = { src: downloadUrl(a.id), name: decodeMimeFilename(a.filename) }) : openDownload(a.id)"
                            >
                                <span class="attach-icon material-symbols-outlined" :style="{ color: fileColor(a.mime_type) }">
                                    {{ fileIcon(a.mime_type) }}
                                </span>
                                <div class="attach-info">
                                    <span class="attach-name">{{ decodeMimeFilename(a.filename) }}</span>
                                    <span class="attach-size">{{ fmtSize(a.size) }}</span>
                                </div>
                                <span class="attach-dl material-symbols-outlined">download</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Zone réponse ── -->
        <Transition name="slide-up">
            <div v-if="showReply" class="reply-box">
                <div class="reply-box-head">
                    <div class="reply-to-badge">
                        <span class="material-symbols-outlined" style="font-size:15px">reply</span>
                        Répondre à <strong>{{ lastReceived?.from_name || lastReceived?.from_email }}</strong>
                    </div>
                    <button class="reply-close" @click="showReply = false" title="Annuler">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                    </button>
                </div>

                <TiptapEditor v-model="replyForm.body" />

                <!-- Fichiers joints à la réponse -->
                <div v-if="replyFiles.length" class="reply-files">
                    <div v-for="(f, i) in replyFiles" :key="i" class="reply-file-chip">
                        <span class="material-symbols-outlined" style="font-size:14px">attach_file</span>
                        <span class="reply-file-name">{{ f.name }}</span>
                        <button type="button" class="reply-file-rm" @click="removeReplyFile(i)">
                            <span class="material-symbols-outlined" style="font-size:14px">close</span>
                        </button>
                    </div>
                </div>

                <div class="reply-footer">
                    <button type="button" class="attach-btn" @click="replyFileInput?.click()" title="Joindre un fichier">
                        <span class="material-symbols-outlined" style="font-size:18px">attach_file</span>
                        Joindre
                    </button>
                    <input ref="replyFileInput" type="file" multiple class="hidden-input" @change="addReplyFiles" />
                    <div style="flex:1"></div>
                    <button @click="showReply = false" class="reply-cancel-btn">Annuler</button>
                    <button @click="reply()" class="reply-send-btn" :disabled="sending || !replyForm.body.trim()">
                        <span v-if="sending" class="spin-sm"></span>
                        <span v-else class="material-symbols-outlined" style="font-size:16px">send</span>
                        {{ sending ? 'Envoi...' : 'Envoyer la réponse' }}
                    </button>
                </div>
            </div>
        </Transition>

        <!-- ── Zone transfert ── -->
        <Transition name="slide-up">
            <div v-if="showFwd" class="reply-box">
                <div class="reply-box-head">
                    <div class="reply-to-badge">
                        <span class="material-symbols-outlined" style="font-size:15px">forward</span>
                        Transférer le message
                    </div>
                    <button class="reply-close" @click="showFwd = false">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                    </button>
                </div>
                <div class="fwd-field">
                    <label class="fwd-label">Destinataires <span style="color:#E5004C">*</span></label>
                    <input v-model="forwardTo" type="text" placeholder="email1@ex.com, email2@ex.com" class="fwd-input" />
                </div>
                <div class="fwd-field">
                    <label class="fwd-label">Message additionnel <span class="fwd-optional">(optionnel)</span></label>
                    <textarea v-model="forwardBody" rows="3" placeholder="Ajoutez une note..." class="fwd-input" style="resize:vertical" />
                </div>
                <div class="reply-footer">
                    <div style="flex:1"></div>
                    <button @click="showFwd = false" class="reply-cancel-btn">Annuler</button>
                    <button @click="forward()" class="reply-send-btn" :disabled="!forwardTo.trim()">
                        <span class="material-symbols-outlined" style="font-size:16px">send</span>
                        Transférer
                    </button>
                </div>
            </div>
        </Transition>

        <!-- ── Aperçu image ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="imgPreview" class="img-overlay" @click.self="imgPreview = null">
                    <div class="img-preview-box">
                        <div class="img-preview-head">
                            <span class="img-preview-name">{{ imgPreview.name }}</span>
                            <div style="display:flex;gap:8px">
                                <a :href="imgPreview.src" download class="img-btn img-btn-dl">
                                    <span class="material-symbols-outlined" style="font-size:18px">download</span>
                                    Télécharger
                                </a>
                                <button class="img-btn" @click="imgPreview = null">
                                    <span class="material-symbols-outlined" style="font-size:18px">close</span>
                                </button>
                            </div>
                        </div>
                        <img :src="imgPreview.src" :alt="imgPreview.name" class="img-preview-img" />
                    </div>
                </div>
            </Transition>
        </Teleport>

    </div>
</template>

<style scoped>
/* ── Page ── */
.thread-page { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }

/* ── Header ── */
.thread-header {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
}
.back-btn {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    background: #f5f7f9; color: #515f74; text-decoration: none; flex-shrink: 0;
    transition: background 0.15s;
}
.back-btn:hover { background: #e0e3e5; color: #191c1e; }

.thread-header-info { flex: 1; min-width: 0; }
.thread-subject {
    font-size: 16px; font-weight: 700; color: #191c1e;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;
}
.thread-meta-row { display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.thread-count-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; background: #f2f4f6; border-radius: 99px;
    font-size: 11px; color: #515f74; font-weight: 600;
}
.thread-participant {
    font-size: 11px; color: #9aaabb; font-weight: 500;
}
.thread-participant::before { content: '· '; }

.thread-header-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.hdr-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 12px; border-radius: 8px;
    border: 1px solid #e0e3e5; background: #fff;
    font-size: 13px; font-weight: 600; color: #515f74;
    cursor: pointer; transition: all 0.15s;
}
.hdr-btn:hover:not(:disabled) { background: #f5f7f9; color: #191c1e; }
.hdr-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.hdr-btn-reply { background: #E5004C; color: #fff; border-color: #E5004C; }
.hdr-btn-reply:hover:not(:disabled) { background: #c4003f; border-color: #c4003f; }
.hdr-btn-label { font-size: 13px; }

/* ── Conversation ── */
.conversation {
    display: flex; flex-direction: column; gap: 16px;
    padding: 20px; background: #f8f9fb;
    border: 1px solid #e0e3e5; border-radius: 12px;
}

.msg-wrap { display: flex; flex-direction: column; gap: 6px; max-width: 85%; }
.msg-sent     { align-self: flex-end;   align-items: flex-end; }
.msg-received { align-self: flex-start; align-items: flex-start; }

.msg-meta { display: flex; align-items: center; gap: 8px; position: relative; }
.msg-sent .msg-meta { flex-direction: row-reverse; }

.msg-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.av-sent { background: #ffe0ec; color: #E5004C; }
.av-recv { background: #dbeafe; color: #1d4ed8; }

.msg-meta-info { display: flex; flex-direction: column; gap: 1px; }
.msg-sent .msg-meta-info { align-items: flex-end; }
.msg-sender { font-size: 12px; font-weight: 700; color: #191c1e; }
.msg-date   { font-size: 11px; color: #9aaabb; }

.unread-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #E5004C; flex-shrink: 0;
    box-shadow: 0 0 0 2px rgba(229,0,76,0.2);
}

.msg-bubble {
    padding: 12px 16px; border-radius: 12px;
    font-size: 13px; line-height: 1.65; color: #191c1e;
    word-break: break-word;
}
.bubble-sent {
    background: #fff0f5; border: 1px solid #ffd6e7; border-top-right-radius: 3px;
}
.bubble-recv {
    background: #fff; border: 1px solid #e0e3e5; border-top-left-radius: 3px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}

.msg-body :deep(p)   { margin: 0 0 6px; }
.msg-body :deep(p:last-child) { margin-bottom: 0; }
.msg-body :deep(a)   { color: #E5004C; text-decoration: underline; }
.msg-body :deep(img) { max-width: 100%; border-radius: 6px; }
.msg-body :deep(ul), .msg-body :deep(ol) { padding-left: 18px; margin: 4px 0; }
.msg-body :deep(blockquote) {
    border-left: 3px solid #e0e3e5; margin: 6px 0; padding-left: 12px; color: #515f74;
}

/* ── Pièces jointes ── */
.msg-attachments { margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.07); }
.attachments-label {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; color: #9aaabb; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px;
}
.attachments-grid { display: flex; flex-direction: column; gap: 6px; }

.attach-card {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px;
    background: #f8f9fb; border: 1px solid #e8eaec;
    text-decoration: none; cursor: pointer; transition: all 0.15s;
}
.attach-card:hover { background: #f0f2f5; border-color: #c7cdd4; }
.attach-card:hover .attach-dl { opacity: 1; color: #E5004C; }

.attach-icon { font-size: 22px; flex-shrink: 0; }
.attach-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.attach-name {
    font-size: 13px; font-weight: 600; color: #191c1e;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    word-break: break-all;
}
.attach-size { font-size: 11px; color: #9aaabb; }
.attach-dl   { font-size: 18px; color: #c7cdd4; opacity: 0; transition: all 0.15s; flex-shrink: 0; }

/* ── Reply / Forward box ── */
.reply-box {
    display: flex; flex-direction: column; gap: 12px;
    padding: 18px 20px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.reply-box-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.reply-to-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 99px;
    background: #f0f4ff; border: 1px solid #c7d7ff;
    font-size: 12px; color: #1d4ed8; font-weight: 600;
}
.reply-close {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 6px;
    background: transparent; border: none; color: #9aaabb;
    cursor: pointer; transition: all 0.15s;
}
.reply-close:hover { background: #f2f4f6; color: #515f74; }

.reply-footer {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.attach-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 12px; border-radius: 8px;
    border: 1px solid #e0e3e5; background: #fafbfc;
    font-size: 13px; font-weight: 500; color: #515f74;
    cursor: pointer; transition: all 0.15s;
}
.attach-btn:hover { background: #f0f2f5; border-color: #adb5bd; color: #191c1e; }
.hidden-input { display: none; }

.reply-cancel-btn {
    padding: 8px 16px; border-radius: 8px;
    border: 1px solid #e0e3e5; background: #fff;
    font-size: 13px; font-weight: 500; color: #515f74;
    cursor: pointer; transition: all 0.15s;
}
.reply-cancel-btn:hover { background: #f2f4f6; }

.reply-send-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 20px; background: #E5004C; color: #fff;
    border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.reply-send-btn:hover:not(:disabled) { background: #c4003f; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(229,0,76,0.25); }
.reply-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* ── Fichiers joints en attente ── */
.reply-files { display: flex; flex-wrap: wrap; gap: 6px; }
.reply-file-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 99px;
    background: #f0f4ff; border: 1px solid #c7d7ff;
    font-size: 12px; color: #1d4ed8;
}
.reply-file-name { max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.reply-file-rm {
    display: flex; align-items: center; justify-content: center;
    background: none; border: none; color: #9aaabb;
    cursor: pointer; padding: 0; line-height: 1; transition: color 0.12s;
}
.reply-file-rm:hover { color: #dc2626; }

/* ── Transfert ── */
.fwd-field { display: flex; flex-direction: column; gap: 4px; }
.fwd-label { font-size: 12px; font-weight: 600; color: #515f74; }
.fwd-optional { font-size: 11px; font-weight: 400; color: #9aaabb; }
.fwd-input {
    width: 100%; padding: 9px 12px;
    border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; color: #191c1e; background: #fafafa;
    outline: none; font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
}
.fwd-input:focus { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229,0,76,0.07); }

/* ── Aperçu image ── */
.img-overlay {
    position: fixed; inset: 0;
    background: rgba(15,20,25,0.85);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 20px;
    backdrop-filter: blur(4px);
}
.img-preview-box {
    background: #fff; border-radius: 14px; overflow: hidden;
    max-width: min(90vw, 900px); max-height: 90vh;
    display: flex; flex-direction: column;
    box-shadow: 0 32px 64px rgba(0,0,0,0.4);
}
.img-preview-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 14px 18px; border-bottom: 1px solid #e0e3e5; flex-shrink: 0;
}
.img-preview-name { font-size: 14px; font-weight: 600; color: #191c1e; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.img-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 7px;
    border: 1px solid #e0e3e5; background: #fff;
    font-size: 13px; font-weight: 500; color: #515f74;
    cursor: pointer; text-decoration: none; transition: all 0.15s;
}
.img-btn:hover { background: #f2f4f6; }
.img-btn-dl { background: #E5004C; color: #fff; border-color: #E5004C; }
.img-btn-dl:hover { background: #c4003f; border-color: #c4003f; }

.img-preview-img { max-width: 100%; max-height: calc(90vh - 60px); object-fit: contain; display: block; }

/* ── Spinner ── */
.spin-sm {
    display: inline-block; width: 13px; height: 13px;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transitions ── */
.slide-up-enter-active { transition: all 0.25s cubic-bezier(0.34,1.4,0.64,1); }
.slide-up-leave-active { transition: all 0.18s ease-in; }
.slide-up-enter-from  { opacity: 0; transform: translateY(12px); }
.slide-up-leave-to    { opacity: 0; transform: translateY(6px); }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .thread-header { padding: 12px 14px; gap: 8px; }
    .thread-subject { font-size: 14px; }
    .hdr-btn-label { display: none; }
    .hdr-btn { padding: 7px 10px; }
    .conversation { padding: 14px; gap: 12px; }
    .msg-wrap { max-width: 95%; }
    .msg-bubble { padding: 10px 13px; font-size: 12px; }
    .reply-box { padding: 14px; }
    .reply-footer { gap: 6px; }
    .reply-send-btn { flex: 1; justify-content: center; }
}
</style>
