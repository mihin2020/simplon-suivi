<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

/**
 * Pagination bar for a Laravel paginator: "21–40 sur 57", previous / next,
 * page numbers and the number of rows per page. Always visible, so the user
 * knows how many items there are even on a single page.
 */
interface PaginatorLink { url: string | null; label: string; active: boolean }

const props = withDefaults(defineProps<{
    from: number | null
    to: number | null
    total: number
    perPage: number
    links: PaginatorLink[]
    /** Rows-per-page choices; the page is reloaded with ?per_page=. */
    perPageOptions?: number[]
    /** Inertia props to reload when the page changes. */
    only?: string[]
}>(), { perPageOptions: () => [10, 20, 50, 100], only: undefined })

// Laravel's first and last links are "previous" and "next".
const previous = computed(() => props.links[0] ?? null)
const next = computed(() => props.links[props.links.length - 1] ?? null)
const pages = computed(() => props.links.slice(1, -1))

const changePerPage = (event: Event) => {
    const url = new URL(window.location.href)
    url.searchParams.set('per_page', (event.target as HTMLSelectElement).value)
    url.searchParams.delete('page')
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, only: props.only })
}
</script>

<template>
    <nav class="pagination" aria-label="Pagination">
        <span class="summary">
            <template v-if="total > 0"><strong>{{ from }}–{{ to }}</strong> sur <strong>{{ total }}</strong></template>
            <template v-else>Aucun élément</template>
        </span>

        <div v-if="links.length > 3" class="pages">
            <component :is="previous?.url ? Link : 'span'" :href="previous?.url ?? undefined" :only="only" preserve-scroll class="page nav" :class="{ disabled: !previous?.url }" aria-label="Page précédente">
                <span class="material-symbols-outlined">chevron_left</span>
            </component>
            <template v-for="link in pages" :key="link.label">
                <span v-if="!link.url" class="page gap">…</span>
                <Link v-else :href="link.url" :only="only" preserve-scroll class="page" :class="{ active: link.active }" :aria-current="link.active ? 'page' : undefined">{{ link.label }}</Link>
            </template>
            <component :is="next?.url ? Link : 'span'" :href="next?.url ?? undefined" :only="only" preserve-scroll class="page nav" :class="{ disabled: !next?.url }" aria-label="Page suivante">
                <span class="material-symbols-outlined">chevron_right</span>
            </component>
        </div>

        <label class="per-page">
            Par page
            <select :value="perPage" @change="changePerPage">
                <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
            </select>
        </label>
    </nav>
</template>

<style scoped>
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-top: 1px solid #f0f2f5; background: #fafbfc; }
.summary { font-size: 13px; color: #515f74; }
.summary strong { color: #191c1e; }
.pages { display: flex; align-items: center; gap: 4px; }
.page { min-width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; padding: 0 8px; border: 1px solid #e0e3e5; border-radius: 8px; background: #fff; color: #374151; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.15s, border-color 0.15s; }
.page:hover:not(.disabled):not(.gap):not(.active) { border-color: #E5004C; color: #E5004C; }
.page.active { background: #E5004C; border-color: #E5004C; color: #fff; }
.page.disabled { opacity: 0.4; cursor: not-allowed; }
.page.gap { border: none; background: transparent; }
.page .material-symbols-outlined { font-size: 20px; }
.per-page { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #515f74; }
.per-page select { padding: 6px 8px; border: 1px solid #e0e3e5; border-radius: 8px; background: #fff; font-size: 13px; }
</style>
