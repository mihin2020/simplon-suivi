<script setup lang="ts">
/**
 * Blocking progress window over a blurred page, for long operations (AI,
 * imports, grouped downloads). Same look as the backup progress window.
 * A null percent means the duration is unknown: an animated bar is shown
 * instead of a made-up figure.
 */
defineProps<{
    open: boolean
    title: string
    label?: string
    percent: number | null
    icon?: string
}>()
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="pm-overlay" role="alertdialog" aria-modal="true" :aria-label="title">
                <div class="pm-card">
                    <div class="pm-icon"><span class="material-symbols-outlined">{{ icon ?? 'auto_awesome' }}</span></div>
                    <h3 class="pm-title">{{ title }}</h3>
                    <p class="pm-label">{{ label }}</p>
                    <div class="pm-track">
                        <div v-if="percent !== null" class="pm-fill" :style="{ width: `${Math.min(100, percent)}%` }" />
                        <div v-else class="pm-fill pm-indeterminate" />
                    </div>
                    <div class="pm-pct">{{ percent !== null ? `${Math.min(100, percent)} %` : '…' }}</div>
                    <p class="pm-hint">Ne fermez pas la page pendant l'opération.</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.pm-overlay { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 16px; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(4px); }
.pm-card { width: min(440px, 100%); padding: 26px 28px; border-radius: 16px; background: #fff; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18); text-align: center; }
.pm-icon { width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 14px; display: grid; place-items: center; background: #fff0f4; color: #E5004C; animation: pm-pulse 1.2s ease-in-out infinite; }
.pm-icon .material-symbols-outlined { font-size: 28px; }
.pm-title { margin: 0; font-size: 18px; font-weight: 700; color: #1F3A4D; }
.pm-label { margin: 6px 0 16px; min-height: 1.2em; font-size: 14px; color: #64748b; }
.pm-track { position: relative; height: 10px; border-radius: 99px; background: #e8eef2; overflow: hidden; }
.pm-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #E5004C, #ff4d7a); transition: width 0.35s ease; }
.pm-indeterminate { position: absolute; width: 35%; animation: pm-slide 1.2s ease-in-out infinite; }
.pm-pct { margin-top: 10px; font-size: 20px; font-weight: 700; color: #1F3A4D; font-variant-numeric: tabular-nums; }
.pm-hint { margin-top: 8px; font-size: 12px; color: #94a3b8; }
@keyframes pm-pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.06); opacity: 0.85; } }
@keyframes pm-slide { from { left: -35%; } to { left: 100%; } }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
