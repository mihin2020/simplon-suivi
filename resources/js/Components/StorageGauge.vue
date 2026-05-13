<script setup lang="ts">
import { computed } from 'vue'

interface CloudinaryUsage {
  storage_used: number
  storage_limit: number
  storage_used_formatted: string
  storage_limit_formatted: string
  storage_remaining_formatted: string
  percent_used: number
  alert_level: 'normal' | 'warning' | 'critical'
}

const props = defineProps<{
  usage: CloudinaryUsage
}>()

const gaugeColor = computed(() => {
  switch (props.usage.alert_level) {
    case 'critical':
      return '#dc2626' // Rouge
    case 'warning':
      return '#f59e0b' // Orange
    default:
      return '#16a34a' // Vert
  }
})

const gaugeBgColor = computed(() => {
  switch (props.usage.alert_level) {
    case 'critical':
      return 'rgba(220, 38, 38, 0.1)'
    case 'warning':
      return 'rgba(245, 158, 11, 0.1)'
    default:
      return 'rgba(22, 163, 74, 0.1)'
  }
})

const alertMessage = computed(() => {
  switch (props.usage.alert_level) {
    case 'critical':
      return '⚠️ Stockage presque plein ! Supprimez des médias.'
    case 'warning':
      return 'Stockage à 80% - Pensez à faire du ménage.'
    default:
      return null
  }
})

const formattedPercent = computed(() => {
  // Afficher 2 décimales si < 1%, sinon 1 décimale
  if (props.usage.percent_used < 1) {
    return props.usage.percent_used.toFixed(2)
  }
  return props.usage.percent_used.toFixed(1)
})
</script>

<template>
  <div class="storage-gauge">
    <div class="gauge-header">
      <div class="gauge-title">
        <span class="material-symbols-outlined gauge-icon">cloud</span>
        <span>Stockage Cloudinary</span>
      </div>
      <span class="gauge-values">
        {{ usage.storage_used_formatted }} / {{ usage.storage_limit_formatted }}
      </span>
    </div>

    <div class="gauge-bar-container" :style="{ backgroundColor: gaugeBgColor }">
      <div
        class="gauge-bar"
        :style="{ 
          width: `${Math.min(usage.percent_used, 100)}%`,
          backgroundColor: gaugeColor
        }"
      />
    </div>

    <div class="gauge-footer">
      <span class="gauge-percent" :style="{ color: gaugeColor }">
        {{ formattedPercent }}% utilisé
      </span>
      <span class="gauge-remaining">
        {{ usage.storage_remaining_formatted }} restants
      </span>
    </div>

    <div v-if="alertMessage" class="gauge-alert" :class="`alert-${usage.alert_level}`">
      {{ alertMessage }}
    </div>
  </div>
</template>

<style scoped>
.storage-gauge {
  background: #fff;
  border: 1px solid #e0e3e5;
  border-radius: 12px;
  padding: 16px 20px;
}

.gauge-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.gauge-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #191c1e;
}

.gauge-icon {
  font-size: 20px;
  color: #1F3A4D;
}

.gauge-values {
  font-size: 13px;
  color: #515f74;
  font-weight: 500;
}

.gauge-bar-container {
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 10px;
}

.gauge-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease, background-color 0.3s ease;
}

.gauge-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.gauge-percent {
  font-size: 13px;
  font-weight: 600;
}

.gauge-remaining {
  font-size: 12px;
  color: #9aaabb;
}

.gauge-alert {
  margin-top: 12px;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
}

.alert-warning {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fcd34d;
}

.alert-critical {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}
</style>
