<script setup lang="ts">
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps<{
    /** Une permission suffit */
    permission?: string
    /** Au moins une de ces permissions */
    any?: string[]
    /** Toutes ces permissions requises */
    all?: string[]
}>()

const { can, canAny, canAll } = usePermissions()

const allowed = computed(() => {
    if (props.permission) return can(props.permission)
    if (props.any?.length) return canAny(props.any)
    if (props.all?.length) return canAll(props.all)
    return true
})
</script>

<template>
    <slot v-if="allowed" />
</template>
