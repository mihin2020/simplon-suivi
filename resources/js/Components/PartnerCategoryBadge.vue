<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    category: string
    size?: 'sm' | 'md'
}>()

const label = computed(() => {
    const labels: Record<string, string> = {
        bailleur: 'Bailleur',
        client: 'Client',
        mixte: 'Mixte',
    }

    return labels[props.category] ?? props.category
})
</script>

<template>
    <span
        class="partner-category-badge"
        :class="[`badge-${category}`, size === 'sm' ? 'badge-sm' : 'badge-md']"
    >
        {{ label }}
    </span>
</template>

<style scoped>
.partner-category-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 99px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.badge-sm {
    padding: 2px 8px;
    font-size: 10px;
}

.badge-md {
    padding: 2px 10px;
    font-size: 11px;
}

.badge-bailleur {
    background: #eef2f8;
    color: #1F3A4D;
}

.badge-client {
    background: #fff0f4;
    color: #E5004C;
}

.badge-mixte {
    background: #f3f0ff;
    color: #6b46c1;
}
</style>
