<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{ notifications: any }>()

function markAsRead(id: string) {
    router.patch(`/notifications/${id}/read`, {}, { preserveScroll: true })
}

function markAllAsRead() {
    router.post('/notifications/mark-all-read', {}, { preserveScroll: true })
}

function fmtDate(d: string | null) {
    if (!d) return '—'
    const date = new Date(d)
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <Head title="Notifications" />

    <div class="max-w-3xl mx-auto space-y-xl">
        <div class="flex items-center justify-between">
            <h1 class="text-h1 font-bold text-on-surface">Notifications</h1>
            <button @click="markAllAsRead" class="btn-secondary">Tout marquer comme lu</button>
        </div>

        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div v-if="!notifications.data.length" class="px-lg py-xl text-center text-secondary text-body-md">
                Aucune notification.
            </div>
            <div class="divide-y divide-surface-container-highest">
                <div
                    v-for="n in notifications.data"
                    :key="n.id"
                    class="px-lg py-md flex items-center justify-between hover:bg-surface-bright transition-colors"
                    :class="{ 'bg-primary/5': !n.read_at }"
                >
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-on-surface text-body-md">{{ n.title }}</div>
                        <div class="text-body-sm text-secondary">{{ n.message }}</div>
                        <div class="text-label-sm text-on-surface-variant mt-xs">{{ fmtDate(n.created_at) }}</div>
                    </div>
                    <div class="flex items-center gap-sm">
                        <button v-if="!n.read_at" @click="markAsRead(n.id)" class="btn-secondary">Marquer comme lu</button>
                        <Link v-if="n.data?.route" :href="n.data.route" class="text-primary text-body-sm hover:underline">Voir</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
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
    transition: background 0.15s;
}
.btn-secondary:hover { background: #e0e3e5; }
</style>
