<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    percentage: number
    size?: 'sm' | 'md'
    showLabel?: boolean
}>(), {
    size: 'md',
    showLabel: true,
})

const safe = computed(() => Math.min(100, Math.max(0, Math.round(props.percentage || 0))))

const tone = computed(() => {
    if (safe.value === 0) return 'idle'
    if (safe.value < 50) return 'started'
    if (safe.value < 100) return 'advanced'
    return 'done'
})

const statusLabel = computed(() => {
    if (safe.value === 0) return 'Non démarré'
    if (safe.value < 50) return 'En cours'
    if (safe.value < 100) return 'Presque terminé'
    return 'Terminé'
})
</script>

<template>
    <div class="progress" :class="[`size-${size}`, `tone-${tone}`]">
        <div class="progress-track" :title="`${safe} % — ${statusLabel}`">
            <div class="progress-fill" :style="{ width: `${safe}%` }" />
        </div>
        <span v-if="showLabel" class="progress-label">{{ safe }} %</span>
    </div>
</template>

<style scoped>
.progress {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.progress-track {
    flex: 1;
    min-width: 64px;
    background: #eef1f4;
    border-radius: 99px;
    overflow: hidden;
}
.size-sm .progress-track { height: 6px; }
.size-md .progress-track { height: 8px; }
.progress-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.25s ease;
}
.tone-idle .progress-fill { background: #9aaabb; }
.tone-started .progress-fill { background: #d97706; }
.tone-advanced .progress-fill { background: #1F3A4D; }
.tone-done .progress-fill { background: #059669; }
.progress-label {
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: #1F3A4D;
}
.size-sm .progress-label { font-size: 11px; }
.size-md .progress-label { font-size: 12px; }
.tone-idle .progress-label { color: #6b7280; }
.tone-started .progress-label { color: #b45309; }
.tone-advanced .progress-label { color: #1F3A4D; }
.tone-done .progress-label { color: #047857; }
</style>
