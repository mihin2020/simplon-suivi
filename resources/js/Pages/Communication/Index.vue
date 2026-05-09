<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'

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
let intervalId: ReturnType<typeof setInterval> | null = null

function toggleSelect(id: string) {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter(x => x !== id)
    } else {
        selected.value.push(id)
    }
}

function sync(deep = false) {
    syncing.value = true
    router.post(
        '/communication/emails/sync',
        deep ? { deep: true } : {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false
                // Recharge les threads 4 s après (le sync tourne en arrière-plan)
                setTimeout(() => router.reload({ only: ['threads', 'unreadCount', 'inboxCount'], preserveScroll: true }), 4000)
            },
        }
    )
}

function archiveSelected() {
    selected.value.forEach(id => {
        router.patch(`/communication/emails/${id}/archive`, {}, { preserveScroll: true })
    })
    selected.value = []
}

function restoreSelected() {
    selected.value.forEach(id => {
        router.patch(`/communication/emails/${id}/unarchive`, {}, { preserveScroll: true })
    })
    selected.value = []
}

function deleteSelected() {
    if (!confirm('Supprimer les emails sélectionnés ?')) return
    selected.value.forEach(id => {
        router.delete(`/communication/emails/${id}`, { preserveScroll: true })
    })
    selected.value = []
}

function fmtDate(d: string | null) {
    if (!d) return ''
    const date = new Date(d)
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
    intervalId = setInterval(() => {
        router.reload({ only: ['threads'], preserveScroll: true })
    }, 60000)
})

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId)
})
</script>

<template>
    <Head title="Messagerie" />

    <div class="max-w-5xl mx-auto space-y-xl">
        <div class="flex items-center justify-between">
            <h1 class="text-h1 font-bold text-on-surface">Messagerie</h1>
            <div class="flex items-center gap-sm">
                <button @click="sync(false)" :disabled="syncing" class="btn-secondary" :class="{ 'opacity-60': syncing }">
                    <span class="material-symbols-outlined" style="font-size:18px" :class="{ 'spin-icon': syncing }">sync</span>
                    {{ syncing ? 'En cours…' : 'Actualiser' }}
                </button>
                <button @click="sync(true)" :disabled="syncing" class="btn-secondary" title="Synchronisation profonde (6 mois)" :class="{ 'opacity-60': syncing }">
                    <span class="material-symbols-outlined" style="font-size:16px">history</span>
                    Sync approfondi
                </button>
                <Link href="/communication/emails/compose" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Nouveau message
                </Link>
            </div>
        </div>

        <div class="flex items-center gap-sm border-b border-surface-container-highest pb-sm">
            <Link href="/communication/emails" :class="filter === 'inbox' ? 'tab-active' : 'tab-inactive'" class="tab-link">
                Boîte de réception
                <span v-if="unreadCount > 0" class="tab-badge tab-badge-unread">{{ unreadCount }}</span>
                <span v-else-if="inboxCount > 0" class="tab-badge">{{ inboxCount }}</span>
            </Link>
            <Link href="/communication/emails/sent" class="tab-inactive tab-link">
                Envoyés
                <span v-if="sentCount > 0" class="tab-badge">{{ sentCount }}</span>
            </Link>
            <Link href="/communication/emails?filter=archived" :class="filter === 'archived' ? 'tab-active' : 'tab-inactive'" class="tab-link">
                Archivés
                <span v-if="archivedCount > 0" class="tab-badge">{{ archivedCount }}</span>
            </Link>
            <Link href="/communication/whatsapp" class="tab-inactive tab-link flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:16px">chat</span>
                WhatsApp
            </Link>
        </div>

        <div v-if="selected.length > 0" class="flex items-center gap-sm">
            <button v-if="filter !== 'archived'" @click="archiveSelected" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:16px">archive</span>
                Archiver la sélection
            </button>
            <button v-if="filter === 'archived'" @click="restoreSelected" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:16px">unarchive</span>
                Restaurer dans la réception
            </button>
            <button @click="deleteSelected" class="btn-danger">
                <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                Supprimer définitivement
            </button>
        </div>

        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div v-if="!threads.data.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucun message.
            </div>
            <div class="divide-y divide-surface-container-highest">
                <div
                    v-for="t in threads.data"
                    :key="t.thread_id"
                    class="px-lg py-md flex items-center gap-md hover:bg-surface-bright transition-colors"
                >
                    <input
                        type="checkbox"
                        :value="t.last_email?.id"
                        :checked="selected.includes(t.last_email?.id)"
                        @change="toggleSelect(t.last_email?.id)"
                        class="w-4 h-4 accent-primary"
                    />
                    <Link :href="`/communication/emails/thread/${t.thread_id}`" class="flex-1 min-w-0 flex items-center gap-md">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-xs flex-wrap">
                                <span class="font-semibold text-on-surface text-body-md truncate">
                                    {{ t.sent_subject || t.last_email?.subject }}
                                </span>
                                <span v-if="!t.last_email?.is_read" class="unread-dot"></span>
                                <span v-if="t.reply_count > 1" class="reply-badge">
                                    {{ t.reply_count }} réponses
                                </span>
                            </div>
                            <div class="text-body-sm text-secondary truncate">
                                Dernière réponse de <strong>{{ t.last_email?.from_name || t.last_email?.from_email }}</strong>
                            </div>
                        </div>
                        <span class="text-body-sm text-secondary whitespace-nowrap flex-shrink-0">{{ fmtDate(t.last_email?.received_at) }}</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="threads.last_page > 1" class="flex items-center justify-center gap-xs">
            <Link
                v-if="threads.current_page > 1"
                :href="threads.prev_page_url"
                class="pag-btn"
            >
                <span class="material-symbols-outlined" style="font-size:18px">chevron_left</span>
            </Link>
            <template v-for="p in threads.last_page" :key="p">
                <Link
                    :href="`/communication/emails?page=${p}`"
                    class="pag-num"
                    :class="p === threads.current_page ? 'pag-active' : ''"
                >{{ p }}</Link>
            </template>
            <Link
                v-if="threads.current_page < threads.last_page"
                :href="threads.next_page_url"
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
.unread-dot {
    width: 8px;
    height: 8px;
    background: #E5004C;
    border-radius: 50%;
    display: inline-block;
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
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #f2f4f6;
    color: #191c1e;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
.btn-secondary:hover { background: #e0e3e5; }
.btn-danger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
}
.btn-danger:hover { background: #fecaca; }

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
.reply-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 8px;
    background: #ffe0ec;
    color: #E5004C;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
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
@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon { animation: spin 0.9s linear infinite; display: inline-block; }
</style>
