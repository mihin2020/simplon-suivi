<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{ emails: any }>()

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
            <Link href="/communication/emails" class="tab-inactive">Boîte de réception</Link>
            <Link href="/communication/emails/sent" class="tab-active">Envoyés</Link>
            <Link href="/communication/emails?filter=archived" class="tab-inactive">Archivés</Link>
        </div>

        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div v-if="!emails.data.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucun email envoyé.
            </div>
            <div class="divide-y divide-surface-container-highest">
                <div
                    v-for="e in emails.data"
                    :key="e.id"
                    class="px-lg py-md flex items-center gap-md hover:bg-surface-bright transition-colors"
                >
                    <div class="flex-1 min-w-0">
                        <div class="text-body-sm text-on-surface truncate">
                            <span class="font-medium">À :</span> {{ e.to.map((t: any) => t.name || t.email).join(', ') }}
                        </div>
                        <div class="text-body-sm text-secondary truncate">{{ e.subject }}</div>
                    </div>
                    <span class="text-body-sm text-secondary whitespace-nowrap">{{ fmtDate(e.sent_at) }}</span>
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
</style>
