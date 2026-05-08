<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    threads: any
    filter: string
}>()

const selected = ref<string[]>([])
let intervalId: ReturnType<typeof setInterval> | null = null

function toggleSelect(id: string) {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter(x => x !== id)
    } else {
        selected.value.push(id)
    }
}

function sync() {
    router.post('/communication/emails/sync', {}, { preserveScroll: true })
}

function archiveSelected() {
    selected.value.forEach(id => {
        router.patch(`/communication/emails/${id}/archive`, {}, { preserveScroll: true })
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
    if (!d) return '—'
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
                <button @click="sync" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:18px">sync</span>
                    Actualiser
                </button>
                <Link href="/communication/emails/compose" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Nouveau message
                </Link>
            </div>
        </div>

        <div class="flex items-center gap-sm border-b border-surface-container-highest pb-sm">
            <Link href="/communication/emails" :class="filter === 'inbox' ? 'tab-active' : 'tab-inactive'">Boîte de réception</Link>
            <Link href="/communication/emails/sent" class="tab-inactive">Envoyés</Link>
            <Link href="/communication/emails?filter=archived" :class="filter === 'archived' ? 'tab-active' : 'tab-inactive'">Archivés</Link>
            <Link href="/communication/whatsapp" class="tab-inactive flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:16px">chat</span>
                WhatsApp
            </Link>
        </div>

        <div v-if="selected.length > 0" class="flex items-center gap-sm">
            <button @click="archiveSelected" class="btn-secondary">Archiver la sélection</button>
            <button @click="deleteSelected" class="btn-danger">Supprimer la sélection</button>
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
                            <div class="flex items-center gap-sm">
                                <span class="font-semibold text-on-surface text-body-md truncate">{{ t.last_email?.from_name || t.last_email?.from_email }}</span>
                                <span v-if="!t.last_email?.is_read" class="unread-dot"></span>
                            </div>
                            <div class="text-body-sm text-on-surface truncate">{{ t.last_email?.subject }}</div>
                        </div>
                        <span class="text-body-sm text-secondary whitespace-nowrap">{{ fmtDate(t.last_email?.received_at) }}</span>
                    </Link>
                </div>
            </div>
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
</style>
