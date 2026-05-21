<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    threads: any
    filter: string
    inboxCount: number
    archivedCount: number
    sentCount: number
    unreadCount: number
}>()

const selected  = ref<string[]>([])
const syncing   = ref(false)
const showConfirmDelete = ref(false)
let intervalId: ReturnType<typeof setInterval> | null = null

const allIds = computed(() => props.threads.data.map((t: any) => t.last_email?.id).filter(Boolean))
const allSelected = computed(() => allIds.value.length > 0 && allIds.value.every((id: string) => selected.value.includes(id)))

function toggleAll() {
    if (allSelected.value) {
        selected.value = []
    } else {
        selected.value = [...allIds.value]
    }
}

function toggleSelect(id: string) {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter(x => x !== id)
    } else {
        selected.value.push(id)
    }
}

function avatarLetter(name: string | null, email: string | null) {
    return ((name || email || '?')[0]).toUpperCase()
}

function avatarColor(str: string | null): string {
    const colors = ['#E5004C','#1d4ed8','#15803d','#7c3aed','#b45309','#0e7490','#be185d','#1F3A4D']
    const idx = (str || '?').charCodeAt(0) % colors.length
    return colors[idx]
}

function sync(deep = false) {
    syncing.value = true
    router.post('/communication/emails/sync', deep ? { deep: true } : {}, {
        onFinish: () => {
            syncing.value = false
            window.setTimeout(() => router.reload({ only: ['threads', 'unreadCount', 'inboxCount'] }), 4000)
        },
    })
}

function archiveSelected() {
    selected.value.forEach(id => router.patch(`/communication/emails/${id}/archive`, {}, {}))
    selected.value = []
}

function restoreSelected() {
    selected.value.forEach(id => router.patch(`/communication/emails/${id}/unarchive`, {}, {}))
    selected.value = []
}

function deleteSelected() {
    selected.value.forEach(id => router.delete(`/communication/emails/${id}`, {}))
    selected.value = []
    showConfirmDelete.value = false
}

function fmtDate(d: string | null) {
    if (!d) return ''
    const date = new Date(d)
    const now = new Date()
    const isToday = date.toDateString() === now.toDateString()
    if (isToday) return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}

onMounted(() => {
    intervalId = setInterval(() => {
        router.reload({ only: ['threads'] })
    }, 60000)
})

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId)
})
</script>

<template>
    <Head title="Messagerie" />

    <div class="mail-layout">

        <!-- ══ SIDEBAR ══ -->
        <aside class="mail-sidebar">

            <!-- Composer -->
            <Link href="/communication/emails/compose" class="compose-btn">
                <span class="material-symbols-outlined" style="font-size:20px">edit_square</span>
                <span>Nouveau message</span>
            </Link>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <Link
                    href="/communication/emails"
                    class="nav-item"
                    :class="{ 'nav-item-active': filter === 'inbox' }"
                >
                    <span class="material-symbols-outlined nav-icon">inbox</span>
                    <span class="nav-label">Boîte de réception</span>
                    <span v-if="unreadCount > 0" class="nav-badge nav-badge-unread">{{ unreadCount }}</span>
                    <span v-else-if="inboxCount > 0" class="nav-badge">{{ inboxCount }}</span>
                </Link>

                <Link
                    href="/communication/emails/sent"
                    class="nav-item"
                >
                    <span class="material-symbols-outlined nav-icon">send</span>
                    <span class="nav-label">Messages envoyés</span>
                    <span v-if="sentCount > 0" class="nav-badge">{{ sentCount }}</span>
                </Link>

                <Link
                    href="/communication/emails?filter=archived"
                    class="nav-item"
                    :class="{ 'nav-item-active': filter === 'archived' }"
                >
                    <span class="material-symbols-outlined nav-icon">archive</span>
                    <span class="nav-label">Archivés</span>
                    <span v-if="archivedCount > 0" class="nav-badge">{{ archivedCount }}</span>
                </Link>

                <div class="nav-divider"></div>

                <Link
                    href="/communication/whatsapp"
                    class="nav-item"
                >
                    <span class="material-symbols-outlined nav-icon">chat</span>
                    <span class="nav-label">WhatsApp</span>
                </Link>
            </nav>

            <!-- Sync -->
            <div class="sidebar-footer">
                <button @click="sync(false)" :disabled="syncing" class="sync-btn" :title="syncing ? 'Synchronisation en cours…' : 'Actualiser'">
                    <span class="material-symbols-outlined" style="font-size:18px" :class="{ 'spin-icon': syncing }">sync</span>
                    <span>{{ syncing ? 'En cours…' : 'Actualiser' }}</span>
                </button>
                <button @click="sync(true)" :disabled="syncing" class="sync-btn-deep" title="Sync approfondi (6 mois)">
                    <span class="material-symbols-outlined" style="font-size:14px">history</span>
                    6 mois
                </button>
            </div>
        </aside>

        <!-- ══ MAIN ══ -->
        <main class="mail-main">

            <!-- ── Toolbar ── -->
            <div class="mail-toolbar">
                <div class="toolbar-left">
                    <input
                        type="checkbox"
                        class="check-all"
                        :checked="allSelected"
                        :indeterminate="selected.length > 0 && !allSelected"
                        @change="toggleAll"
                        title="Tout sélectionner"
                    />
                    <!-- Actions sélection (apparaissent si sélection) -->
                    <Transition name="fade">
                        <div v-if="selected.length > 0" class="sel-actions">
                            <span class="sel-count">{{ selected.length }} sélectionné{{ selected.length > 1 ? 's' : '' }}</span>
                            <button v-if="filter !== 'archived'" @click="archiveSelected" class="sel-btn" title="Archiver">
                                <span class="material-symbols-outlined" style="font-size:17px">archive</span>
                                Archiver
                            </button>
                            <button v-if="filter === 'archived'" @click="restoreSelected" class="sel-btn" title="Restaurer">
                                <span class="material-symbols-outlined" style="font-size:17px">unarchive</span>
                                Restaurer
                            </button>
                            <button @click="showConfirmDelete = true" class="sel-btn sel-btn-danger" title="Supprimer">
                                <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                Supprimer
                            </button>
                        </div>
                    </Transition>
                </div>
                <div class="toolbar-right">
                    <span class="pagination-info" v-if="threads.total > 0">
                        {{ (threads.current_page - 1) * threads.per_page + 1 }}–{{ Math.min(threads.current_page * threads.per_page, threads.total) }} sur {{ threads.total }}
                    </span>
                    <Link v-if="threads.current_page > 1" :href="threads.prev_page_url" class="pag-arrow">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_left</span>
                    </Link>
                    <span v-else class="pag-arrow pag-arrow-disabled">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_left</span>
                    </span>
                    <Link v-if="threads.current_page < threads.last_page" :href="threads.next_page_url" class="pag-arrow">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_right</span>
                    </Link>
                    <span v-else class="pag-arrow pag-arrow-disabled">
                        <span class="material-symbols-outlined" style="font-size:20px">chevron_right</span>
                    </span>
                </div>
            </div>

            <!-- ── Liste de threads ── -->
            <div class="thread-list">

                <!-- État vide -->
                <div v-if="!threads.data.length" class="empty-state">
                    <span class="material-symbols-outlined empty-icon">mail</span>
                    <p class="empty-title">
                        {{ filter === 'archived' ? 'Aucun email archivé' : 'Aucun message reçu' }}
                    </p>
                    <p class="empty-sub">
                        {{ filter === 'archived' ? 'Les emails archivés apparaîtront ici.' : 'Vos emails entrants apparaîtront ici.' }}
                    </p>
                </div>

                <!-- Rows -->
                <div
                    v-for="t in threads.data"
                    :key="t.thread_id"
                    class="thread-row"
                    :class="{
                        'thread-unread': !t.last_email?.is_read,
                        'thread-selected': t.last_email?.id && selected.includes(t.last_email.id)
                    }"
                >
                    <!-- Checkbox -->
                    <input
                        v-if="t.last_email?.id"
                        type="checkbox"
                        class="thread-check"
                        :checked="selected.includes(t.last_email.id)"
                        @change.stop="toggleSelect(t.last_email.id)"
                        @click.stop
                    />

                    <!-- Avatar -->
                    <div
                        class="thread-avatar"
                        :style="{ background: avatarColor(t.last_email?.from_name || t.last_email?.from_email) }"
                    >
                        {{ avatarLetter(t.last_email?.from_name, t.last_email?.from_email) }}
                    </div>

                    <!-- Corps -->
                    <Link :href="`/communication/emails/thread/${t.thread_id}`" class="thread-body">
                        <div class="thread-sender">
                            {{ t.last_email?.from_name || t.last_email?.from_email || '—' }}
                            <span v-if="t.reply_count > 1" class="thread-count-chip">{{ t.reply_count }}</span>
                        </div>
                        <div class="thread-subject-row">
                            <span class="thread-subject">{{ t.sent_subject || t.last_email?.subject || '(Sans sujet)' }}</span>
                        </div>
                    </Link>

                    <!-- Date + indicateur non-lu -->
                    <div class="thread-right">
                        <span class="thread-date">{{ fmtDate(t.last_email?.received_at || t.last_email?.created_at) }}</span>
                        <span v-if="!t.last_email?.is_read" class="unread-dot"></span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ── Modale de confirmation suppression ── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showConfirmDelete" class="modal-overlay" @click.self="showConfirmDelete = false">
                <div class="modal-box">
                    <div class="modal-head">
                        <div class="modal-err-icon">
                            <span class="material-symbols-outlined" style="font-size:20px">delete</span>
                        </div>
                        <h3 class="modal-title">Supprimer les emails sélectionnés ?</h3>
                    </div>
                    <p class="modal-msg">Cette action est irréversible. {{ selected.length }} email{{ selected.length > 1 ? 's' : '' }} sera{{ selected.length > 1 ? 'ont' : '' }} définitivement supprimé{{ selected.length > 1 ? 's' : '' }}.</p>
                    <div class="modal-foot">
                        <button class="modal-cancel" @click="showConfirmDelete = false">Annuler</button>
                        <button class="modal-delete" @click="deleteSelected">
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
/* ── Layout Gmail-like ── */
.mail-layout {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 80px);
    max-width: 1200px;
    margin: 0 auto;
}

/* ── Sidebar ── */
.mail-sidebar {
    width: 220px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-right: 16px;
}

.compose-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    background: #E5004C;
    color: #fff;
    border-radius: 24px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    margin-bottom: 8px;
    transition: all 0.2s;
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

.sidebar-footer { margin-top: auto; padding-top: 12px; display: flex; flex-direction: column; gap: 4px; }
.sync-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 8px;
    background: transparent; border: none; color: #515f74;
    font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.12s;
}
.sync-btn:hover:not(:disabled) { background: #f0f1f3; }
.sync-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.sync-btn-deep {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 10px; border-radius: 6px;
    background: transparent; border: 1px solid #e0e3e5; color: #9aaabb;
    font-size: 11px; cursor: pointer; transition: all 0.12s;
}
.sync-btn-deep:hover:not(:disabled) { border-color: #adb5bd; color: #515f74; }

/* ── Main ── */
.mail-main {
    flex: 1; min-width: 0;
    background: #fff;
    border: 1px solid #e8eaed;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── Toolbar ── */
.mail-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; border-bottom: 1px solid #f0f1f3;
    min-height: 48px; gap: 12px;
}
.toolbar-left { display: flex; align-items: center; gap: 10px; flex: 1; }
.toolbar-right { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }

.check-all {
    width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;
    accent-color: #E5004C;
}

.sel-actions { display: flex; align-items: center; gap: 6px; }
.sel-count { font-size: 13px; color: #515f74; font-weight: 500; padding: 0 4px; }
.sel-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 12px; border-radius: 6px;
    border: 1px solid #e0e3e5; background: #fff;
    font-size: 12px; font-weight: 600; color: #3c4043;
    cursor: pointer; transition: all 0.12s;
}
.sel-btn:hover { background: #f0f1f3; }
.sel-btn-danger { color: #ba1a1a; border-color: #ffdad6; }
.sel-btn-danger:hover { background: #ffdad6; }

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
    padding: 64px 32px; text-align: center;
}
.empty-icon { font-size: 56px; color: #e0e3e5; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #444; margin: 0 0 4px; }
.empty-sub { font-size: 13px; color: #888; margin: 0; }

/* ── Thread row ── */
.thread-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; border-bottom: 1px solid #f0f1f3;
    cursor: pointer; transition: background 0.1s;
    position: relative;
}
.thread-row:hover { background: #f5f6f7; }
.thread-row:hover .thread-check { opacity: 1; }
.thread-row:last-child { border-bottom: none; }
.thread-selected { background: #fce8ef !important; }
.thread-unread .thread-sender,
.thread-unread .thread-subject { font-weight: 700; }

.thread-check {
    width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;
    accent-color: #E5004C; opacity: 0; transition: opacity 0.1s;
}
.thread-selected .thread-check { opacity: 1; }
.thread-row:hover .thread-check { opacity: 1; }

.thread-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff;
}

.thread-body {
    flex: 1; min-width: 0; text-decoration: none;
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 0 8px;
    align-items: center;
}
.thread-sender {
    font-size: 13px; font-weight: 500; color: #202124;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 5px;
}
.thread-count-chip {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 16px; padding: 0 4px;
    border-radius: 99px; background: #e0e3e5; color: #515f74;
    font-size: 10px; font-weight: 700; flex-shrink: 0;
}
.thread-subject-row { min-width: 0; }
.thread-subject {
    font-size: 13px; font-weight: 400; color: #444;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
.thread-unread .thread-subject { color: #202124; font-weight: 600; }

.thread-right {
    display: flex; align-items: center; gap: 6px; flex-shrink: 0; margin-left: auto;
}
.thread-date { font-size: 12px; color: #888; white-space: nowrap; }
.thread-unread .thread-date { font-weight: 700; color: #202124; }
.unread-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #E5004C; flex-shrink: 0;
    box-shadow: 0 0 0 2px rgba(229,0,76,0.15);
}

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

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Spinner ── */
@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon { animation: spin 0.9s linear infinite; display: inline-block; }

/* ── Responsive ── */
@media (max-width: 860px) {
    .mail-sidebar { width: 180px; }
    .thread-body { grid-template-columns: 140px 1fr; }
}
@media (max-width: 660px) {
    .mail-layout { flex-direction: column; }
    .mail-sidebar {
        width: 100%; flex-direction: row; flex-wrap: wrap;
        padding-right: 0; gap: 8px;
    }
    .compose-btn { border-radius: 12px; flex: 1; justify-content: center; margin-bottom: 0; }
    .sidebar-nav { flex-direction: row; flex-wrap: wrap; gap: 4px; }
    .nav-item { border-radius: 8px; margin-right: 0; padding: 7px 12px; }
    .sidebar-footer { flex-direction: row; margin-top: 0; }
    .thread-body { grid-template-columns: 120px 1fr; }
    .mail-main { border-radius: 12px; }
}
</style>
