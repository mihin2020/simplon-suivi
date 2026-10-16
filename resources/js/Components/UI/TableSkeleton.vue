<script setup lang="ts">
import Skeleton from './Skeleton.vue'

/**
 * A table-shaped placeholder: a header line and a few rows of cells, with
 * varied widths so it reads like real data.
 */
withDefaults(defineProps<{
    rows?: number
    columns?: number
    /** Leading checkbox column, as in lists with selection. */
    withCheckbox?: boolean
}>(), { rows: 6, columns: 5, withCheckbox: false })

const widths = ['70%', '45%', '85%', '55%', '35%', '65%', '50%']
const width = (row: number, column: number) => widths[(row * 3 + column) % widths.length]
</script>

<template>
    <div class="table-skeleton" role="status" aria-label="Chargement de la liste">
        <div class="ts-row ts-head">
            <span v-if="withCheckbox" class="ts-check"><Skeleton width="16px" height="16px" radius="4px" /></span>
            <span v-for="c in columns" :key="`h${c}`" class="ts-cell"><Skeleton width="50%" height="10px" /></span>
        </div>
        <div v-for="r in rows" :key="r" class="ts-row">
            <span v-if="withCheckbox" class="ts-check"><Skeleton width="16px" height="16px" radius="4px" /></span>
            <span v-for="c in columns" :key="c" class="ts-cell">
                <Skeleton :width="width(r, c)" height="13px" />
                <Skeleton v-if="c === 1" width="40%" height="10px" style="margin-top: 6px" />
            </span>
        </div>
    </div>
</template>

<style scoped>
.table-skeleton { width: 100%; }
.ts-row { display: flex; align-items: center; gap: 16px; padding: 14px 16px; border-bottom: 1px solid #f5f6f8; }
.ts-head { padding-top: 12px; padding-bottom: 12px; border-bottom-color: #f0f2f5; }
.ts-row:last-child { border-bottom: none; }
.ts-check { width: 24px; flex-shrink: 0; }
.ts-cell { flex: 1; min-width: 0; }
</style>
