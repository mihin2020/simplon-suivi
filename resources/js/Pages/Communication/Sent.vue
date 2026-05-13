<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    emails: any
    inboxCount: number
    archivedCount: number
    sentCount: number
    unreadCount: number
}>()

function deleteSent(id: string) {
    if (!confirm('Supprimer cet email définitivement ?')) return
    router.delete(`/communication/emails/${id}`, { preserveScroll: true })
}

function fmtDate(d: string | null) {
    if (!d) return ''
    const date = new Date(d)
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <Head title="Emails envoyés" />

    <div class="max-w-5xl mx-auto space-y-xl">
        <div class="flex items-center justify-between">
            <h1 class="text-h1 font-bold text-on-surface">Emails envoyés</h1>
            <Link href="/communication/emails/compose" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                Nouveau message
            </Link>
        </div>

        <div class="flex items-center gap-sm border-b border-surface-container-highest pb-sm">
            <Link href="/communication/emails" class="tab-inactive tab-link">
                Boîte de réception
                <span v-if="unreadCount > 0" class="tab-badge tab-badge-unread">{{ unreadCount }}</span>
                <span v-else-if="inboxCount > 0" class="tab-badge">{{ inboxCount }}</span>
            </Link>
            <Link href="/communication/emails/sent" class="tab-active tab-link">
                Envoyés
                <span v-if="sentCount > 0" class="tab-badge tab-badge-sent">{{ sentCount }}</span>
            </Link>
            <Link href="/communication/emails?filter=archived" class="tab-inactive tab-link">
                Archivés
                <span v-if="archivedCount > 0" class="tab-badge">{{ archivedCount }}</span>
            </Link>
        </div>

        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div v-if="!emails.data.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucun email envoyé.
            </div>
            <div class="divide-y divide-surface-container-highest">
                <div
                    v-for="e in emails.data"
                    :key="e.id"
                    class="px-lg py-md flex items-center gap-md hover:bg-surface-bright transition-colors group"
                >
                    <Link
                        :href="`/communication/emails/sent/${e.id}`"
                        class="flex-1 min-w-0 flex items-center gap-md"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="text-body-sm font-semibold text-on-surface truncate">
                                {{ e.subject }}
                            </div>
                            <div class="text-body-sm text-secondary truncate">
                                <span class="font-medium">À :</span> {{ e.to.map((t: any) => t.name || t.email).join(', ') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-sm flex-shrink-0">
                            <span class="text-body-sm text-secondary whitespace-nowrap">{{ fmtDate(e.sent_at) }}</span>
                            <span class="material-symbols-outlined text-secondary" style="font-size:16px">chevron_right</span>
                        </div>
                    </Link>
                    <button
                        @click.prevent="deleteSent(e.id)"
                        class="delete-btn opacity-0 group-hover:opacity-100 transition-opacity"
                        title="Supprimer"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="emails.last_page > 1" class="flex items-center justify-center gap-xs">
            <Link
                v-if="emails.current_page > 1"
                :href="emails.prev_page_url"
                class="pag-btn"
            >
                <span class="material-symbols-outlined" style="font-size:18px">chevron_left</span>
            </Link>
            <template v-for="p in emails.last_page" :key="p">
                <Link
                    :href="`/communication/emails/sent?page=${p}`"
                    class="pag-num"
                    :class="p === emails.current_page ? 'pag-active' : ''"
                >{{ p }}</Link>
            </template>
            <Link
                v-if="emails.current_page < emails.last_page"
                :href="emails.next_page_url"
                class="pag-btn"
            >
                <span class="material-symbols-outlined" style="font-size:18px">chevron_right</span>
            </Link>
        </div>
    </div>
</template>

<style scoped>
.tab-active {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 600;
    color: #E5004C;
    border-bottom: 2px solid #E5004C;
}
.tab-inactive {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    color: #515f74;
    border-bottom: 2px solid transparent;
}
.tab-inactive:hover {
    color: #191c1e;
}
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s;
}
.btn-primary:hover { background: #c4003f; }

.delete-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #dc2626;
    cursor: pointer;
    border-radius: 6px;
    flex-shrink: 0;
    transition: background 0.15s, opacity 0.15s;
}
.delete-btn:hover { background: #fee2e2; }

/* Pagination */
.pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #f2f4f6;
    color: #515f74;
    text-decoration: none;
    transition: background 0.15s;
}
.pag-btn:hover { background: #e0e3e5; }
.pag-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    color: #515f74;
    transition: background 0.15s;
}
.pag-num:hover { background: #f2f4f6; }
.pag-active {
    background: #E5004C !important;
    color: #fff !important;
}
.tab-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    padding: 0 5px;
    border-radius: 99px;
    background: #f0f1f3;
    color: #515f74;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}
.tab-badge-unread {
    background: #E5004C;
    color: #fff;
}
.tab-badge-sent {
    background: #e8f5e9;
    color: #2e7d32;
}

/* ══ Responsive Sent ══════════════════════════════════════ */
@media (max-width: 767px) {
    /* Header */
    .flex.items-center.justify-between { flex-wrap: wrap; gap: 10px; }
    .btn-primary { font-size: 12px; padding: 7px 12px; }
    /* Tabs scrollables */
    .flex.items-center.gap-sm.border-b {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex-wrap: nowrap;
    }
    .tab-active, .tab-inactive { white-space: nowrap; padding: 8px 12px; font-size: 13px; }
    /* Liste emails */
    .px-lg { padding-left: 12px !important; padding-right: 12px !important; }
    .py-md  { padding-top: 10px !important; padding-bottom: 10px !important; }
    /* Bouton supprimer : toujours visible sur touch */
    .delete-btn { opacity: 1 !important; }
    .group-hover\:opacity-100 { opacity: 1 !important; }
}
</style>
