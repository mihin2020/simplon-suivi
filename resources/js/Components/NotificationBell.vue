<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps<{ initialCount: number }>()

const count = ref(props.initialCount ?? 0)
const open = ref(false)
const notifications = ref<any[]>([])

let intervalId: ReturnType<typeof setInterval> | null = null

async function fetchCount() {
    try {
        const res = await fetch('/notifications/unread-count')
        const data = await res.json()
        count.value = data.count
    } catch {
        // ignore
    }
}

async function fetchNotifications() {
    try {
        const res = await fetch('/notifications')
        const data = await res.json()
        notifications.value = data.notifications?.data ?? []
    } catch {
        // ignore
    }
}

function toggle() {
    open.value = !open.value
    if (open.value) fetchNotifications()
}

function markAndRedirect(n: any) {
    router.patch(`/notifications/${n.id}/read`)
    open.value = false
    if (n.data?.route) {
        router.visit(n.data.route)
    }
}

onMounted(() => {
    intervalId = setInterval(fetchCount, 30000)
})

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId)
})
</script>

<template>
    <div class="relative">
        <button
            @click="toggle"
            class="relative text-on-surface-variant hover:bg-surface-container-low rounded-xl p-sm transition-transform scale-95 active:scale-90"
        >
            <span class="material-symbols-outlined">notifications</span>
            <span
                v-if="count > 0"
                class="absolute top-0 right-0 bg-error text-on-error text-label-sm rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
            >
                {{ count > 9 ? '9+' : count }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 mt-2 w-80 bg-surface border border-surface-container-highest rounded-xl shadow-lg z-50"
        >
            <div class="flex items-center justify-between px-md py-sm border-b border-surface-container-highest">
                <span class="text-body-sm font-semibold text-on-surface">Notifications</span>
                <button
                    @click="router.post('/notifications/mark-all-read')"
                    class="text-label-sm text-primary hover:underline"
                >
                    Tout lire
                </button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    @click="markAndRedirect(n)"
                    class="px-md py-sm cursor-pointer hover:bg-surface-container-low transition-colors border-b border-surface-container-low last:border-0"
                    :class="{ 'bg-primary/5': !n.read_at }"
                >
                    <div class="text-body-sm font-medium text-on-surface">{{ n.title }}</div>
                    <div class="text-label-sm text-on-surface-variant truncate">{{ n.message }}</div>
                </div>
                <div v-if="notifications.length === 0" class="px-md py-lg text-body-sm text-on-surface-variant text-center">
                    Aucune notification
                </div>
            </div>
            <div class="px-md py-sm border-t border-surface-container-highest text-center">
                <Link href="/notifications" class="text-label-sm text-primary hover:underline">
                    Voir tout
                </Link>
            </div>
        </div>
    </div>
</template>
