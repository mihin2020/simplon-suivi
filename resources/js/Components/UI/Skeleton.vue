<script setup lang="ts">
/**
 * Placeholder block shown while content loads: a grey shape with a soft
 * shimmer, sized like the content it stands for.
 */
withDefaults(defineProps<{
    width?: string
    height?: string
    radius?: string
}>(), { width: '100%', height: '14px', radius: '6px' })
</script>

<template>
    <span class="skeleton" :style="{ width, height, borderRadius: radius }" aria-hidden="true" />
</template>

<style scoped>
.skeleton {
    display: block;
    flex-shrink: 0;
    background: linear-gradient(90deg, #eef1f4 0%, #f7f9fb 40%, #eef1f4 80%);
    background-size: 200% 100%;
    animation: shimmer 1.3s ease-in-out infinite;
}

@keyframes shimmer {
    from { background-position: 100% 0; }
    to { background-position: -100% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton { animation: none; }
}
</style>
