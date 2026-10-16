<script setup lang="ts">
import { useListLoading } from '@/composables/useListLoading'
import Skeleton from './Skeleton.vue'
import TableSkeleton from './TableSkeleton.vue'

/**
 * Wraps a list: while a search, a filter or a page change of this same page
 * is loading, the list is replaced by a skeleton of the same kind - a table,
 * or a grid of cards.
 */
withDefaults(defineProps<{
    variant?: 'table' | 'cards'
    rows?: number
    columns?: number
    withCheckbox?: boolean
}>(), { variant: 'table', rows: 6, columns: 5, withCheckbox: false })

const loading = useListLoading()
</script>

<template>
    <template v-if="loading">
        <div v-if="variant === 'cards'" class="cards-skeleton" role="status" aria-label="Chargement de la liste">
            <div v-for="i in rows" :key="i" class="card-skeleton">
                <div class="card-skeleton-head">
                    <Skeleton width="44px" height="44px" radius="12px" />
                    <div class="card-skeleton-title">
                        <Skeleton width="70%" height="14px" />
                        <Skeleton width="45%" height="11px" />
                    </div>
                </div>
                <Skeleton height="10px" />
                <Skeleton width="80%" height="10px" />
                <Skeleton width="35%" height="24px" radius="99px" />
            </div>
        </div>
        <TableSkeleton v-else :rows="rows" :columns="columns" :with-checkbox="withCheckbox" />
    </template>
    <slot v-else />
</template>

<style scoped>
.cards-skeleton { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.card-skeleton { display: flex; flex-direction: column; gap: 12px; padding: 18px; border: 1px solid #e8edf2; border-radius: 14px; background: #fff; }
.card-skeleton-head { display: flex; align-items: center; gap: 12px; }
.card-skeleton-title { flex: 1; display: flex; flex-direction: column; gap: 8px; }
</style>
