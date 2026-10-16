<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'

/**
 * Sub-navigation of the contracts section, styled like the other tab bars of
 * the app (Configuration, formation page): a grey rail, the active tab as a
 * raised white chip.
 */
const { can } = usePermissions()
const page = usePage()

interface Tab { label: string; href: string; icon: string; permission: string; match: (url: string) => boolean }

// Order matters: the first matching tab wins, so specific prefixes come first.
const tabs: Tab[] = [
    { label: 'Contrats', href: '/contracts', icon: 'contract', permission: 'contracts.view', match: u => u === '/contracts' || /^\/contracts\/(?!layouts|projects)/.test(u) },
    { label: 'Projets', href: '/contracts/projects', icon: 'folder_open', permission: 'contracts.view', match: u => u.startsWith('/contracts/projects') },
    { label: 'En-têtes & pieds', href: '/contracts/layouts', icon: 'branding_watermark', permission: 'contracts.templates', match: u => u.startsWith('/contracts/layouts') },
]

const currentPath = computed(() => page.url.split('?')[0])
const visibleTabs = computed(() => tabs.filter(t => can(t.permission)))
</script>

<template>
    <nav class="tabs-bar" aria-label="Sections contrats">
        <Link
            v-for="tab in visibleTabs"
            :key="tab.href"
            :href="tab.href"
            prefetch
            class="tab-btn"
            :class="{ 'tab-active': tab.match(currentPath) }"
            :aria-current="tab.match(currentPath) ? 'page' : undefined"
        >
            <span class="material-symbols-outlined tab-icon">{{ tab.icon }}</span>
            {{ tab.label }}
        </Link>
    </nav>
</template>

<style scoped>
.tabs-bar {
    display: flex; gap: 4px; padding: 4px;
    background: #f2f4f6; border-radius: 10px;
    width: fit-content; max-width: 100%; overflow-x: auto;
}
.tab-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; border-radius: 7px;
    font-size: 13px; font-weight: 600; white-space: nowrap;
    color: #515f74; text-decoration: none;
    transition: all 0.15s;
}
.tab-btn:hover:not(.tab-active) { background: #e8eaec; color: #191c1e; }
.tab-active { background: #fff; color: #191c1e; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1); }
.tab-active .tab-icon { color: #E5004C; }
.tab-icon { font-size: 17px; }

@media (max-width: 640px) {
    .tabs-bar { width: 100%; }
    .tab-btn { flex: 1; justify-content: center; padding: 8px 10px; }
}
</style>
