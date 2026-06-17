<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    email: {
        id: string
        subject: string
        to: { email: string; name?: string }[]
        cc?: { email: string; name?: string }[] | null
        body_html: string
        sent_at: string | null
        from_name: string
        from_email: string
        attachments?: { id: string; filename: string; path: string; size: number; mime_type?: string }[]
    }
}>()

function fmtDate(d: string | null) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

function fmtSize(bytes: number) {
    if (bytes < 1024) return bytes + ' o'
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' Ko'
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
}

function fileIcon(mime: string | null | undefined): string {
    if (!mime) return 'attach_file'
    if (mime.startsWith('image/'))       return 'image'
    if (mime === 'application/pdf')      return 'picture_as_pdf'
    if (mime.includes('word'))           return 'description'
    if (mime.includes('excel') || mime.includes('sheet')) return 'table_chart'
    if (mime.includes('zip'))            return 'folder_zip'
    return 'attach_file'
}

function fileColor(mime: string | null | undefined): string {
    if (!mime) return '#515f74'
    if (mime.startsWith('image/'))       return '#7c3aed'
    if (mime === 'application/pdf')      return '#dc2626'
    if (mime.includes('word'))           return '#1d4ed8'
    if (mime.includes('excel') || mime.includes('sheet')) return '#15803d'
    if (mime.includes('zip'))            return '#b45309'
    return '#515f74'
}

function downloadUrl(id: string) {
    return `/communication/emails/attachments/${id}/download`
}

// Décode les noms de fichiers encodés en MIME (RFC 2047 : =?utf-8?Q?...?=)
function decodeMimeFilename(name: string): string {
    if (!name || !name.includes('=?')) return name
    const joined = name.replace(/\?=\s+=\?/g, '?==?')
    return joined.replace(/=\?([^?]+)\?([BQbq])\?([^?]*)\?=/g, (_, charset, enc, text) => {
        try {
            if (enc.toUpperCase() === 'Q') {
                const bytes: number[] = []
                const s = text.replace(/_/g, ' ')
                let i = 0
                while (i < s.length) {
                    if (s[i] === '=' && i + 2 < s.length) { bytes.push(parseInt(s.slice(i + 1, i + 3), 16)); i += 3 }
                    else { bytes.push(s.charCodeAt(i)); i++ }
                }
                return new TextDecoder(charset).decode(new Uint8Array(bytes))
            }
            const bin = atob(text)
            const bytes = new Uint8Array(bin.length)
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
            return new TextDecoder(charset).decode(bytes)
        } catch { return text }
    })
}
</script>

<template>
    <Head :title="email.subject" />

    <div class="sent-show-page">

        <!-- ── Header ── -->
        <div class="show-header">
            <Link href="/communication/emails/sent" class="back-btn" title="Retour aux envoyés">
                <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
            </Link>
            <div class="show-header-info">
                <h1 class="show-subject">{{ email.subject || '(Sans sujet)' }}</h1>
                <div class="show-meta-row">
                    <span class="show-meta-badge">
                        <span class="material-symbols-outlined" style="font-size:13px">send</span>
                        Email envoyé
                    </span>
                    <span class="show-meta-date">{{ fmtDate(email.sent_at) }}</span>
                </div>
            </div>
        </div>

        <!-- ── Card email ── -->
        <div class="email-card">

            <!-- En-tête expéditeur -->
            <div class="email-card-head">
                <div class="sender-row">
                    <div class="sender-avatar">{{ (email.from_name || email.from_email || 'S')[0].toUpperCase() }}</div>
                    <div class="sender-info">
                        <span class="sender-name">{{ email.from_name || email.from_email }}</span>
                        <span class="sender-email">{{ email.from_email }}</span>
                    </div>
                </div>

                <!-- Destinataires -->
                <div class="recipients-block">
                    <div class="recipient-row">
                        <span class="recip-label">À :</span>
                        <div class="recip-chips">
                            <span v-for="t in email.to" :key="t.email" class="recip-chip">
                                {{ t.name || t.email }}
                            </span>
                        </div>
                    </div>
                    <div v-if="email.cc && email.cc.length" class="recipient-row">
                        <span class="recip-label">Cc :</span>
                        <div class="recip-chips">
                            <span v-for="t in email.cc" :key="t.email" class="recip-chip recip-chip-cc">
                                {{ t.name || t.email }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Corps -->
            <div class="email-card-body">
                <div class="email-body" v-html="email.body_html || '<em style=\'color:#9aaabb\'>(Corps vide)</em>'" />
            </div>

            <!-- Pièces jointes -->
            <div v-if="email.attachments?.length" class="email-card-attachments">
                <div class="attach-header">
                    <span class="material-symbols-outlined" style="font-size:14px">attach_file</span>
                    {{ email.attachments.length }} pièce{{ email.attachments.length > 1 ? 's' : '' }} jointe{{ email.attachments.length > 1 ? 's' : '' }}
                </div>
                <div class="attach-grid">
                    <a
                        v-for="att in email.attachments"
                        :key="att.id"
                        :href="downloadUrl(att.id)"
                        download
                        class="attach-card"
                        :title="`Télécharger ${decodeMimeFilename(att.filename)}`"
                    >
                        <span class="attach-icon material-symbols-outlined" :style="{ color: fileColor(att.mime_type) }">
                            {{ fileIcon(att.mime_type) }}
                        </span>
                        <div class="attach-info">
                            <span class="attach-name">{{ decodeMimeFilename(att.filename) }}</span>
                            <span class="attach-size">{{ fmtSize(att.size) }}</span>
                        </div>
                        <span class="attach-dl material-symbols-outlined">download</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sent-show-page { max-width: 760px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }

/* ── Header ── */
.show-header {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 12px;
}
.back-btn {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    background: #f5f7f9; color: #515f74; text-decoration: none; flex-shrink: 0;
    transition: background 0.15s;
}
.back-btn:hover { background: #e0e3e5; color: #191c1e; }
.show-header-info { flex: 1; min-width: 0; }
.show-subject {
    font-size: 16px; font-weight: 700; color: #191c1e;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;
}
.show-meta-row { display: flex; align-items: center; gap: 10px; margin-top: 4px; flex-wrap: wrap; }
.show-meta-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; background: #f0fdf4; border-radius: 99px;
    font-size: 11px; color: #15803d; font-weight: 600; border: 1px solid #bbf7d0;
}
.show-meta-date { font-size: 12px; color: #9aaabb; }

/* ── Card ── */
.email-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    overflow: hidden;
}
.email-card-head {
    padding: 20px 24px 16px; border-bottom: 1px solid #f0f1f3;
    display: flex; flex-direction: column; gap: 14px;
}
.sender-row { display: flex; align-items: center; gap: 12px; }
.sender-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: #E5004C; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700;
}
.sender-info { display: flex; flex-direction: column; gap: 1px; }
.sender-name { font-size: 14px; font-weight: 700; color: #191c1e; }
.sender-email { font-size: 12px; color: #9aaabb; }

.recipients-block { display: flex; flex-direction: column; gap: 6px; padding-left: 52px; }
.recipient-row { display: flex; align-items: flex-start; gap: 8px; }
.recip-label { font-size: 12px; font-weight: 600; color: #9aaabb; width: 24px; flex-shrink: 0; padding-top: 2px; }
.recip-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.recip-chip {
    display: inline-flex; align-items: center;
    padding: 2px 9px; border-radius: 99px;
    background: #f0f4ff; border: 1px solid #c7d7ff;
    font-size: 12px; color: #1d4ed8; font-weight: 500;
}
.recip-chip-cc {
    background: #f5f7f9; border-color: #e0e3e5; color: #515f74;
}

/* ── Corps ── */
.email-card-body { padding: 24px; }
.email-body { font-size: 14px; line-height: 1.75; color: #191c1e; }
.email-body :deep(p) { margin: 0 0 12px; }
.email-body :deep(p:last-child) { margin-bottom: 0; }
.email-body :deep(a) { color: #E5004C; text-decoration: underline; }
.email-body :deep(strong) { font-weight: 700; }
.email-body :deep(em) { font-style: italic; }
.email-body :deep(ul), .email-body :deep(ol) { padding-left: 20px; margin: 8px 0; }
.email-body :deep(blockquote) {
    border-left: 3px solid #e0e3e5; padding-left: 12px; color: #515f74; margin: 8px 0;
}
.email-body :deep(img) { max-width: 100%; border-radius: 6px; }

/* ── Pièces jointes ── */
.email-card-attachments { padding: 16px 24px 20px; border-top: 1px solid #f0f1f3; }
.attach-header {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; color: #9aaabb; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px;
}
.attach-grid { display: flex; flex-direction: column; gap: 6px; }
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
.attach-dl { font-size: 18px; color: #c7cdd4; opacity: 0; transition: all 0.15s; flex-shrink: 0; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .show-header { padding: 12px 14px; }
    .show-subject { font-size: 14px; }
    .email-card-head { padding: 16px; }
    .email-card-body { padding: 16px; }
    .recipients-block { padding-left: 0; }
    .email-card-attachments { padding: 14px 16px; }
}
</style>
