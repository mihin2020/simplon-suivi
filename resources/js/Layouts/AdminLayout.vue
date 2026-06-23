<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import NotificationBell from '@/Components/NotificationBell.vue'
import AiChatbot from '@/Components/AiChatbot.vue'
import { chatStore } from '@/stores/chatStore'
import { usePermissions } from '@/composables/usePermissions'

const page = usePage()
const { canAny, isSuperAdmin, userRole } = usePermissions()

const auth  = page.props.auth  as { user?: { full_name?: string; role?: string; role_label?: string } }
const flash = computed(() => page.props.flash as { success?: string; warning?: string; error?: string })

const visibleFlash = ref<{ success?: string; warning?: string; error?: string }>({})
watch(flash, (f) => {
    if (!f) return
    visibleFlash.value = { ...f }
    setTimeout(() => { visibleFlash.value = {} }, 4000)
}, { immediate: true })

const userRoleLegacy  = auth.user?.role as string | undefined
const isTrainer = userRoleLegacy === 'trainer'

const showConfiguration = computed(() =>
    !isTrainer && canAny(['configuration.view', 'configuration.manage'])
)
const showAiChatbot = computed(() => showConfiguration.value)

// ─── Sidebar collapse (persisted in localStorage) ───────────────────────────
const getStoredCollapsed = (): boolean => {
    try {
        return JSON.parse(localStorage.getItem('sidebar_collapsed') ?? 'false')
    } catch {
        return false
    }
}

const sidebarCollapsed = ref<boolean>(getStoredCollapsed())
const sidebarWidth = computed(() => sidebarCollapsed.value ? '76px' : '260px')

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value
    try { localStorage.setItem('sidebar_collapsed', JSON.stringify(sidebarCollapsed.value)) } catch {}
}

// ─── Nav groups ────────────────────────────────────────────────────────────
interface NavItem {
    label: string
    icon: string
    href: string
    prefix: string
    roles: string[]
    permissions?: string[]
    badge?: string
}

interface NavGroup {
    id: string
    label: string | null
    icon?: string
    collapsible: boolean
    items: NavItem[]
}

const allNavGroups: NavGroup[] = [
    {
        id: 'flat',
        label: null,
        collapsible: false,
        items: [
            { label: 'Tableau de Bord', icon: 'dashboard',            href: '/',                     prefix: 'Dashboard/',     roles: ['super_admin', 'admin', 'trainer'] },
            { label: 'Utilisateurs',    icon: 'admin_panel_settings', href: '/users',                prefix: 'Users/',         roles: ['super_admin', 'admin'], permissions: ['users.view'] },
            { label: 'Projets',         icon: 'folder_open',          href: '/projects',             prefix: 'Projects/',      roles: ['super_admin', 'admin'], permissions: ['projects.view'] },
            { label: 'Apprenants',      icon: 'person_book',          href: '/learners',             prefix: 'Learners/',      roles: ['super_admin', 'admin'], permissions: ['learners.view'] },
            { label: 'Présences',       icon: 'fact_check',           href: '/presences',            prefix: 'Attendances/',   roles: ['super_admin', 'admin', 'trainer'], permissions: ['attendances.view'] },
            { label: 'Référentiels',    icon: 'menu_book',            href: '/referentiels',         prefix: 'Referentiels/',  roles: ['super_admin', 'admin'], permissions: ['referentiels.view'] },
            { label: 'Partenaires',     icon: 'handshake',            href: '/partners',             prefix: 'Partners/',      roles: ['super_admin', 'admin'], permissions: ['partners.view'] },
            { label: 'Statistiques',    icon: 'bar_chart',            href: '/statistics',           prefix: 'Statistics/',    roles: ['super_admin', 'admin'], permissions: ['statistics.view'] },
            { label: 'Communication',   icon: 'chat',                 href: '/communication/emails', prefix: 'Communication/', roles: ['super_admin', 'admin'], permissions: ['communication.view', 'communication.send', 'communication.manage', 'whatsapp.view', 'whatsapp.send', 'whatsapp.manage'] },
        ],
    },
    {
        id: 'campus',
        label: 'Campus Workforce',
        icon: 'domain',
        collapsible: true,
        items: [
            { label: 'Formations', icon: 'local_library', href: '/campus/formations', prefix: 'Campus/Formations/', roles: ['super_admin', 'admin'], permissions: ['campus.formations.view'] },
            { label: 'Cohortes',   icon: 'groups',        href: '/campus/cohorts',    prefix: 'Campus/Cohorts/',    roles: ['super_admin', 'admin'], permissions: ['campus.cohorts.view'] },
            { label: 'Finance',    icon: 'payments',      href: '/campus/finance',    prefix: 'Campus/Finance/',    roles: ['super_admin', 'admin'], permissions: ['campus.finance.view', 'campus.finance.collect', 'campus.finance.manage', 'campus.finance.dashboard'] },
        ],
    },
]

// ─── Active detection ───────────────────────────────────────────────────────
const isActive = (prefix: string) =>
    page.component === prefix.replace('/', '/Index') || page.component.startsWith(prefix)

const isGroupActive = (group: NavGroup) =>
    group.items.some(item => isActive(item.prefix))

// ─── Collapsible state (persisted in localStorage) ──────────────────────────
const getStoredOpen = (id: string, defaultVal: boolean): boolean => {
    try {
        const stored = localStorage.getItem(`sidebar_group_${id}`)
        return stored !== null ? JSON.parse(stored) : defaultVal
    } catch {
        return defaultVal
    }
}

const openGroups = ref<Record<string, boolean>>(
    Object.fromEntries(
        allNavGroups
            .filter(g => g.collapsible)
            .map(g => [g.id, getStoredOpen(g.id, isGroupActive(g))])
    )
)

// Auto-open groups when a child route becomes active
watch(
    () => page.component,
    () => {
        allNavGroups.forEach(g => {
            if (g.collapsible && isGroupActive(g)) {
                openGroups.value[g.id] = true
            }
        })
    },
    { immediate: true }
)

const toggleGroup = (id: string) => {
    openGroups.value[id] = !openGroups.value[id]
    try { localStorage.setItem(`sidebar_group_${id}`, JSON.stringify(openGroups.value[id])) } catch {}
}

// ─── Filtered groups by role & permissions ─────────────────────────────────
const canSeeNavItem = (item: NavItem): boolean => {
    const role = userRole.value ?? userRoleLegacy
    if (!role || !item.roles.includes(role)) return false
    if (isSuperAdmin.value) return true
    if (role === 'trainer') return true
    if (!item.permissions?.length) return true
    return canAny(item.permissions)
}

const navGroups = computed(() =>
    allNavGroups
        .map(g => ({
            ...g,
            items: g.items.filter(canSeeNavItem),
        }))
        .filter(g => g.items.length > 0)
)

// ─── Logout ──────────────────────────────────────────────────────────────────
const logout = () => router.post('/deconnexion', {}, {
    onSuccess: () => {
        chatStore.messages.splice(0, chatStore.messages.length)
        chatStore.isOpen = false
    },
})
</script>

<template>
    <div class="bg-background text-on-surface h-screen overflow-hidden flex">

        <!-- ═══════════════ SIDEBAR ═══════════════ -->
        <aside
            class="bg-on-secondary-fixed h-screen fixed left-0 top-0 flex flex-col z-20 transition-[width] duration-200"
            :style="{ width: sidebarWidth }"
        >

            <!-- Logo (toujours centré) -->
            <div class="relative px-md pt-md pb-sm flex items-center justify-center border-b border-white/10">
                <img
                    v-if="!sidebarCollapsed"
                    src="/images/logo.jpeg"
                    alt="Simplon Burkina Faso"
                    class="h-10 w-auto object-contain"
                />
                <img
                    v-else
                    src="/images/logo.jpeg"
                    alt="Simplon Burkina Faso"
                    class="h-8 w-8 object-contain rounded"
                />
            </div>

            <!-- Bouton bascule (flotte sur le bord de la sidebar) -->
            <button
                @click="toggleSidebar"
                type="button"
                class="sidebar-toggle-btn"
                :title="sidebarCollapsed ? 'Étendre la barre latérale' : 'Réduire la barre latérale'"
            >
                <span class="material-symbols-outlined text-[16px]">{{ sidebarCollapsed ? 'chevron_right' : 'chevron_left' }}</span>
            </button>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-sm custom-scroll">
                <template v-for="group in navGroups" :key="group.id">

                    <!-- ── Flat items (no header) ── -->
                    <template v-if="!group.collapsible">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="nav-link"
                            :class="[isActive(item.prefix) ? 'nav-active' : 'nav-inactive', sidebarCollapsed ? 'justify-center' : '']"
                            :title="sidebarCollapsed ? item.label : ''"
                        >
                            <span class="material-symbols-outlined nav-icon">{{ item.icon }}</span>
                            <span v-if="!sidebarCollapsed" class="flex-1">{{ item.label }}</span>
                        </Link>
                    </template>

                    <!-- ── Collapsible group ── -->
                    <div v-else class="mb-xs">
                        <!-- Group header -->
                        <button
                            class="group-header"
                            :class="[isGroupActive(group) ? 'group-header-active' : '', sidebarCollapsed ? 'justify-center' : '']"
                            @click="toggleGroup(group.id)"
                            type="button"
                            :title="sidebarCollapsed ? group.label ?? '' : ''"
                        >
                            <span class="material-symbols-outlined group-header-icon" v-if="group.icon">{{ group.icon }}</span>
                            <template v-if="!sidebarCollapsed">
                                <span class="flex-1 text-left text-xs font-semibold uppercase tracking-widest">{{ group.label }}</span>
                                <span
                                    class="material-symbols-outlined transition-transform duration-200 text-[16px]"
                                    :class="openGroups[group.id] ? 'rotate-180' : ''"
                                >expand_more</span>
                            </template>
                        </button>

                        <!-- Group items (animated) -->
                        <div
                            v-if="!sidebarCollapsed"
                            class="overflow-hidden transition-all duration-300 ease-in-out"
                            :style="openGroups[group.id] ? 'max-height: 400px; opacity: 1' : 'max-height: 0; opacity: 0'"
                        >
                            <Link
                                v-for="item in group.items"
                                :key="item.href"
                                :href="item.href"
                                class="nav-link nav-child"
                                :class="isActive(item.prefix) ? 'nav-active' : 'nav-inactive'"
                            >
                                <span class="material-symbols-outlined nav-icon text-[18px]">{{ item.icon }}</span>
                                <span class="flex-1">{{ item.label }}</span>
                                <span v-if="item.badge" class="badge">{{ item.badge }}</span>
                            </Link>
                        </div>
                    </div>

                </template>
            </nav>

            <!-- Bottom actions -->
            <div class="border-t border-white/10 pt-sm pb-md flex flex-col gap-xs">
                <Link
                    v-if="showConfiguration"
                    href="/configuration"
                    class="nav-link nav-inactive"
                    :class="[isActive('Configuration/') ? 'nav-active' : 'nav-inactive', sidebarCollapsed ? 'justify-center' : '']"
                    :title="sidebarCollapsed ? 'Configuration' : ''"
                >
                    <span class="material-symbols-outlined nav-icon">settings</span>
                    <span v-if="!sidebarCollapsed">Configuration</span>
                </Link>
                <button
                    @click="logout"
                    class="nav-link nav-inactive hover:!text-error w-full text-left"
                    :class="sidebarCollapsed ? 'justify-center' : ''"
                    type="button"
                    :title="sidebarCollapsed ? 'Déconnexion' : ''"
                >
                    <span class="material-symbols-outlined nav-icon">logout</span>
                    <span v-if="!sidebarCollapsed">Déconnexion</span>
                </button>
            </div>
        </aside>

        <!-- ═══════════════ MAIN AREA ═══════════════ -->
        <div
            class="flex-1 flex flex-col h-screen overflow-hidden transition-[margin,width] duration-200"
            :style="{ marginLeft: sidebarWidth, width: `calc(100% - ${sidebarWidth})` }"
        >

            <!-- Top bar -->
            <header
                class="bg-surface fixed top-0 right-0 h-16 border-b border-surface-container-highest flex justify-end items-center px-lg z-10 transition-[width] duration-200"
                :style="{ width: `calc(100% - ${sidebarWidth})` }"
            >
                <div class="flex items-center gap-md">
                    <AiChatbot v-if="showAiChatbot" />
                    <NotificationBell :initial-count="($page.props.unread_notifications_count as number) ?? 0" />
                    <div class="h-6 w-px bg-surface-container-highest mx-xs"></div>
                    <Link href="/profil" class="flex items-center gap-sm hover:opacity-80 transition-opacity">
                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-on-primary text-body-sm font-bold">
                            {{ auth.user?.full_name?.charAt(0) ?? 'A' }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-body-sm font-semibold text-on-surface leading-tight">
                                {{ auth.user?.full_name ?? 'Utilisateur' }}
                            </span>
                            <span class="text-body-sm text-secondary leading-tight">
                                {{ auth.user?.role_label ?? '' }}
                            </span>
                        </div>
                    </Link>
                </div>
            </header>

            <!-- Flash messages -->
            <Transition name="flash">
                <div
                    v-if="visibleFlash.success"
                    class="fixed top-20 right-4 z-50 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-md py-sm text-body-sm flex items-center gap-sm shadow-md"
                >
                    <span class="material-symbols-outlined text-emerald-600" style="font-size: 18px;">check_circle</span>
                    {{ visibleFlash.success }}
                </div>
            </Transition>

            <Transition name="flash">
                <div
                    v-if="visibleFlash.warning"
                    class="fixed top-20 right-4 z-50 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-md py-sm text-body-sm flex items-center gap-sm shadow-md"
                >
                    <span class="material-symbols-outlined text-amber-700" style="font-size: 18px;">warning</span>
                    {{ visibleFlash.warning }}
                </div>
            </Transition>

            <Transition name="flash">
                <div
                    v-if="visibleFlash.error"
                    class="fixed top-20 right-4 z-50 bg-rose-50 border border-rose-200 text-rose-900 rounded-lg px-md py-sm text-body-sm flex items-center gap-sm shadow-md"
                >
                    <span class="material-symbols-outlined text-rose-700" style="font-size: 18px;">error</span>
                    {{ visibleFlash.error }}
                </div>
            </Transition>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto mt-16 p-xl bg-background">
                <slot />
            </main>
        </div>

    </div>
</template>

<style scoped>
/* ── Sidebar toggle ── */
.sidebar-toggle-btn {
    position: absolute;
    top: 18px;
    right: -12px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1.5px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.7);
    background: #1F3A4D;
    flex-shrink: 0;
    z-index: 1;
    transition: background 0.15s, color 0.15s;
}
.sidebar-toggle-btn:hover {
    background: #2d5a7b;
    color: #fff;
}

/* ── Scrollbar ── */
.custom-scroll {
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.15) transparent;
}
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 99px; }
aside:hover .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.35); }

/* ── Nav link base ── */
.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    font-size: 13.5px;
    line-height: 20px;
    transition: all 0.15s ease;
    text-decoration: none;
    border-radius: 0;
}

/* Child items slightly indented */
.nav-child {
    padding-left: 20px;
    font-size: 13px;
}

/* Active state */
.nav-active {
    color: #ffffff;
    border-left: 3px solid #E5004C;
    padding-left: 13px;
    background: linear-gradient(90deg, rgba(229,0,76,0.18) 0%, rgba(229,0,76,0.04) 100%);
}
.nav-child.nav-active {
    padding-left: 17px;
}

/* Inactive state */
.nav-inactive {
    color: #b9c7e0;
    border-left: 3px solid transparent;
}
.nav-inactive:hover {
    color: #e8edf5;
    background: rgba(255,255,255,0.06);
}

/* Icon sizing */
.nav-icon { font-size: 20px; flex-shrink: 0; }

/* ── Group header ── */
.group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 6px 16px;
    margin-top: 4px;
    color: #7a90a8;
    cursor: pointer;
    transition: color 0.15s;
    background: transparent;
    border: none;
}
.group-header:hover { color: #b9c7e0; }
.group-header-active { color: #E5004C !important; }
.group-header-icon { font-size: 15px; }

/* ── Badge ── */
.badge {
    font-size: 10px;
    font-weight: 700;
    background: #E5004C;
    color: #fff;
    border-radius: 99px;
    padding: 1px 6px;
    line-height: 16px;
}

.flash-enter-active { transition: all 0.3s ease; }
.flash-leave-active { transition: all 0.4s ease; }
.flash-enter-from   { opacity: 0; transform: translateY(-8px); }
.flash-leave-to     { opacity: 0; transform: translateY(-8px); }
</style>
