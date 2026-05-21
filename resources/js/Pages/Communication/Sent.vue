<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    emails: any
    inboxCount: number
    archivedCount: number
    sentCount: number
    unreadCount: number
}>()

const confirmDeleteId = ref<string | null>(null)

function avatarLetter(name: string | null, email: string | null) {
    return ((name || email || '?')[0]).toUpperCase()
}

function avatarColor(str: string | null): string {
    const colors = ['#E5004C','#1d4ed8','#15803d','#7c3aed','#b45309','#0e7490','#be185d','#1F3A4D']
    const idx = (str || '?').charCodeAt(0) % colors.length
    return colors[idx]
}

function confirmDelete(id: string) {
    confirmDeleteId.value = id
}

function doDelete() {
    if (!confirmDeleteId.value) return
    router.delete(`/communication/emails/${confirmDeleteId.value}`, {})
    confirmDeleteId.value = null
}

function fmtDate(d: string | null) {
    if (!d) return ''
    const date = new Date(d)
    const now = new Date()
    const isToday = date.toDateString() === now.toDateString()
    if (isToday) return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}

function recipientsLabel(to: any[]) {
    if (!to?.length) return '—'
    return to.slice(0, 3).map((t: any) => t.name || t.email).join(', ') + (to.length > 3 ? ` +${to.length - 3}` : '')
}
</script>

<template>
    <Head title="Emails envoyés" />

    <div class="mail-layout">

        <!-- ══ SIDEBAR ══ -->
        <aside class="mail-sidebar">
            <Link href="/communication/emails/compose" class="compose-btn">
                <span class="material-symbols-outlined" style="font-size:20px">edit_square</span>
                <span>Nouveau message</span>
            </Link>

            <nav class="sidebar-nav">
                <Link href="/communication/emails" class="nav-item">
                    <span class="material-symbols-outlined nav-icon">inbox</span>
                    <span class="nav-label">Boîte de réception</span>
                    <span v-if="unreadCount > 0" class="nav-badge nav-badge-unread">{{ unreadCount }}</span>
                    <span v-else-if="inboxCount > 0" class="nav-badge">{{ inboxCount }}</span>
                </Link>
                <Link href="/communication/emails/sent" class="nav-item nav-item-active">
                    <span class="material-symbols-outlined nav-icon">send</span>
                    <span class="nav-label">Messages envoyés</span>
                    <span v-if="sentCount > 0" class="nav-badge">{{ sentCount }}</span>
                </Link>
                <Link href="/communication/emails?filter=archived" class="nav-item">
                    <span class="material-symbols-outlined nav-icon">archive</span>
                    <span class="nav-label">Archivés</span>
                    <span v-if="archivedCount > 0" class="nav-badge">{{ archivedCount }}</span>
                </Link>
                <div class="nav-divider"></div>
                <Link href="/communication/whatsapp" class="nav-item">
                    <span class="material-symbols-outlined nav-icon">chat</span>
                    <span class="nav-label">WhatsApp</span>
                </Link>
            </nav>
        </aside>

        <!-- ══ MAIN ══ -->
        <main class="mail-main">

            <!-- Toolbar -->
            <div class="mail-toolbar">
                <div class="toolbar-left">
                    <span class="toolbar-title">Messages envoyés</span>
                </div>
                <div class="toolbar-right">
                    <span class="pagination-info" v-if="emails.total > 0">
                        {{ (emails.current_page - 1) * emails.per_page + 1 }}–{{ Math.min(emails.current_page * emails.per_page, emails.total) }} sur {{ emails.total }}
                    </span>
                    <Link v-if="emails.current_page > 1" :href="emails.prev_page_url" class="pag-arrow">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_left</span>
                    </Link>
                    <span v-else class="pag-arrow pag-arrow-disabled">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_left</span>
                    </span>
                    <Link v-if="emails.current_page < emails.last_page" :href="emails.next_page_url" class="pag-arrow">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_right</span>
                    </Link>
                    <span v-else class="pag-arrow pag-arrow-disabled">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_right</span>
                    </span>
                </div>
            </div>

            <!-- Liste -->
            <div class="thread-list">
                <div v-if="!emails.data.length" class="empty-state">
                    <span class="material-symbols-outlined empty-icon">send</span>
                    <p class="empty-title">Aucun email envoyé</p>
                    <p class="empty-sub">Vos emails envoyés apparaîtront ici.</p>
                    <Link href="/communication/emails/compose" class="compose-btn-empty">
                        <span class="material-symbols-outlined" style="font-size:18px">edit_square</span>
                        Rédiger un message
                    </Link>
                </div>

                <div
                    v-for="e in emails.data"
                    :key="e.id"
                    class="thread-row"
                >
                    <!-- Avatar initiale du premier destinataire -->
                    <div
                        class="thread-avatar"
                        :style="{ background: avatarColor(e.to?.[0]?.name || e.to?.[0]?.email) }"
                    >
                        {{ avatarLetter(e.to?.[0]?.name, e.to?.[0]?.email) }}
                    </div>

                    <!-- Corps cliquable -->
                    <Link :href="`/communication/emails/sent/${e.id}`" class="thread-body">
                        <div class="thread-sender">
                            <span class="sent-to-label">À :</span>
                            {{ recipientsLabel(e.to) }}
                        </div>
                        <div class="thread-subject-row">
                            <span class="thread-subject">{{ e.subject || '(Sans sujet)' }}</span>
                        </div>
                    </Link>

                    <!-- Date + action supprimer -->
                    <div class="thread-right">
                        <span class="thread-date">{{ fmtDate(e.sent_at) }}</span>
                        <button
                            class="del-btn"
                            title="Supprimer"
                            @click.prevent="confirmDelete(e.id)"
                        >
                            <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modale confirmation suppression -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="confirmDeleteId" class="modal-overlay" @click.self="confirmDeleteId = null">
                <div class="modal-box">
                    <div class="modal-head">
                        <div class="modal-err-icon">
                            <span class="material-symbols-outlined" style="font-size:20px">delete</span>
                        </div>
                        <h3 class="modal-title">Supprimer cet email ?</h3>
                    </div>
                    <p class="modal-msg">Cette action est irréversible. L'email sera définitivement supprimé.</p>
                    <div class="modal-foot">
                        <button class="modal-cancel" @click="confirmDeleteId = null">Annuler</button>
                        <button class="modal-delete" @click="doDelete">
                            <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                            Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Layout (identique à Index.vue) ── */
.mail-layout {
    display: flex; gap: 0;
    min-height: calc(100vh - 80px);
    max-width: 1200px; margin: 0 auto;
}

.mail-sidebar {
    width: 220px; flex-shrink: 0;
    display: flex; flex-direction: column; gap: 6px;
    padding-right: 16px;
}

.compose-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; background: #E5004C; color: #fff;
    border-radius: 24px; font-size: 14px; font-weight: 700;
    text-decoration: none; margin-bottom: 8px; transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(229,0,76,0.3);
}
.compose-btn:hover { background: #c4003f; box-shadow: 0 4px 14px rgba(229,0,76,0.35); transform: translateY(-1px); }

.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }
.nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 14px; border-radius: 0 24px 24px 0;
    font-size: 14px; font-weight: 500; color: #3c4043;
    text-decoration: none; transition: background 0.12s;
    margin-right: -16px;
}
.nav-item:hover { background: #e8eaed; }
.nav-item-active { background: #fce8ef; color: #E5004C; font-weight: 700; }
.nav-item-active .nav-icon { color: #E5004C; }
.nav-icon { font-size: 20px; color: #444; flex-shrink: 0; }
.nav-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 18px; padding: 0 5px;
    border-radius: 99px; background: #f0f1f3; color: #515f74;
    font-size: 11px; font-weight: 700;
}
.nav-badge-unread { background: #E5004C; color: #fff; }
.nav-divider { height: 1px; background: #e8eaed; margin: 6px 0 6px -4px; }

/* ── Main ── */
.mail-main {
    flex: 1; min-width: 0; background: #fff;
    border: 1px solid #e8eaed; border-radius: 16px;
    overflow: hidden; display: flex; flex-direction: column;
}

/* ── Toolbar ── */
.mail-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; border-bottom: 1px solid #f0f1f3;
    min-height: 48px; gap: 12px;
}
.toolbar-left { display: flex; align-items: center; gap: 10px; flex: 1; }
.toolbar-right { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.toolbar-title { font-size: 14px; font-weight: 600; color: #3c4043; }

.pagination-info { font-size: 12px; color: #888; margin-right: 4px; }
.pag-arrow {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 50%;
    color: #444; text-decoration: none; transition: background 0.12s;
}
.pag-arrow:hover { background: #f0f1f3; }
.pag-arrow-disabled { color: #ccc; cursor: default; }

/* ── Thread list ── */
.thread-list { flex: 1; overflow-y: auto; }

.empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 64px 32px; text-align: center; gap: 8px;
}
.empty-icon { font-size: 56px; color: #e0e3e5; }
.empty-title { font-size: 16px; font-weight: 600; color: #444; margin: 0; }
.empty-sub { font-size: 13px; color: #888; margin: 0; }
.compose-btn-empty {
    display: inline-flex; align-items: center; gap: 7px;
    margin-top: 12px; padding: 10px 20px;
    background: #E5004C; color: #fff; border-radius: 24px;
    font-size: 13px; font-weight: 600; text-decoration: none;
    transition: all 0.2s; box-shadow: 0 2px 8px rgba(229,0,76,0.3);
}
.compose-btn-empty:hover { background: #c4003f; transform: translateY(-1px); }

/* ── Row ── */
.thread-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; border-bottom: 1px solid #f0f1f3;
    transition: background 0.1s; position: relative;
}
.thread-row:hover { background: #f5f6f7; }
.thread-row:hover .del-btn { opacity: 1; }
.thread-row:last-child { border-bottom: none; }

.thread-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff;
}

.thread-body {
    flex: 1; min-width: 0; text-decoration: none;
    display: grid; grid-template-columns: 200px 1fr;
    gap: 0 8px; align-items: center;
}
.thread-sender {
    font-size: 13px; font-weight: 500; color: #202124;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 5px;
}
.sent-to-label { font-size: 11px; color: #9aaabb; font-weight: 600; flex-shrink: 0; }
.thread-subject-row { min-width: 0; }
.thread-subject {
    font-size: 13px; color: #444;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}

.thread-right {
    display: flex; align-items: center; gap: 6px; flex-shrink: 0;
}
.thread-date { font-size: 12px; color: #888; white-space: nowrap; }

.del-btn {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    border: none; background: transparent; color: #9aaabb;
    cursor: pointer; transition: all 0.12s;
    opacity: 0;
}
.del-btn:hover { background: #ffdad6; color: #ba1a1a; }

/* ── Modale ── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(25,28,30,0.5); backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
}
.modal-box {
    background: #fff; border-radius: 14px; width: 100%; max-width: 400px;
    padding: 24px; box-shadow: 0 24px 48px -12px rgba(0,0,0,0.22);
    display: flex; flex-direction: column; gap: 14px;
}
.modal-head { display: flex; align-items: center; gap: 12px; }
.modal-err-icon {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 9px;
    background: #ffdad6; color: #ba1a1a; flex-shrink: 0;
}
.modal-title { font-size: 16px; font-weight: 700; color: #191c1e; margin: 0; }
.modal-msg   { font-size: 14px; color: #515f74; line-height: 1.6; margin: 0; }
.modal-foot  { display: flex; justify-content: flex-end; gap: 8px; }
.modal-cancel {
    padding: 9px 16px; background: transparent; color: #515f74;
    border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s;
}
.modal-cancel:hover { background: #f2f4f6; }
.modal-delete {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; background: #ba1a1a; color: #fff;
    border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.modal-delete:hover { background: #9b1616; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 860px) {
    .mail-sidebar { width: 180px; }
    .thread-body { grid-template-columns: 160px 1fr; }
}
@media (max-width: 660px) {
    .mail-layout { flex-direction: column; }
    .mail-sidebar { width: 100%; flex-direction: row; flex-wrap: wrap; padding-right: 0; gap: 8px; }
    .compose-btn { border-radius: 12px; flex: 1; justify-content: center; margin-bottom: 0; }
    .sidebar-nav { flex-direction: row; flex-wrap: wrap; gap: 4px; }
    .nav-item { border-radius: 8px; margin-right: 0; padding: 7px 12px; }
    .thread-body { grid-template-columns: 110px 1fr; }
    .mail-main { border-radius: 12px; }
    .del-btn { opacity: 1; }
}
</style>
