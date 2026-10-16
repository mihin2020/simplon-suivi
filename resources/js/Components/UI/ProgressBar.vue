<script setup lang="ts">
/**
 * Progress bar. A null percent means "working, duration unknown": the bar
 * then shows an animated stripe instead of pretending a figure.
 */
defineProps<{
    label: string
    percent: number | null
}>()
</script>

<template>
    <div class="progress" role="progressbar" :aria-valuenow="percent ?? undefined" aria-valuemin="0" aria-valuemax="100" :aria-label="label">
        <div class="progress-head">
            <span class="progress-label">{{ label }}</span>
            <span v-if="percent !== null" class="progress-value">{{ percent }} %</span>
        </div>
        <div class="track">
            <div v-if="percent !== null" class="fill" :style="{ width: `${percent}%` }" />
            <div v-else class="fill indeterminate" />
        </div>
    </div>
</template>

<style scoped>
.progress { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.progress-head { display: flex; justify-content: space-between; gap: 12px; font-size: 12px; }
.progress-label { color: #515f74; font-weight: 600; }
.progress-value { color: #E5004C; font-weight: 700; font-variant-numeric: tabular-nums; }
.track { position: relative; height: 8px; border-radius: 99px; background: #eef1f4; overflow: hidden; }
.fill { height: 100%; border-radius: 99px; background: #E5004C; transition: width 0.25s ease; }
.indeterminate { position: absolute; width: 35%; animation: slide 1.2s ease-in-out infinite; }
@keyframes slide { from { left: -35%; } to { left: 100%; } }
</style>
