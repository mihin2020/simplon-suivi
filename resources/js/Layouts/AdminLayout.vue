<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'
import NotificationBell from '@/Components/NotificationBell.vue'
import { ref, watch, computed } from 'vue'

// Popup flash
const showFlash = ref(false)
const flashType = ref<'success' | 'warning' | 'error'>('success')
const flashMessage = ref('')
let flashTimer: ReturnType<typeof setTimeout> | null = null

const page = usePage()

// ── Badge email non lu + son de notification ──────────────────────
const unreadEmails = computed(() => (page.props as any).unread_emails_count ?? 0)
const prevUnreadEmails = ref(unreadEmails.value)

function playEmailSound() {
    try {
        const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
        // Note 1 : ding aigu
        const osc1 = ctx.createOscillator()
        const g1   = ctx.createGain()
        osc1.connect(g1); g1.connect(ctx.destination)
        osc1.frequency.setValueAtTime(880, ctx.currentTime)
        g1.gain.setValueAtTime(0.25, ctx.currentTime)
        g1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
        osc1.start(ctx.currentTime); osc1.stop(ctx.currentTime + 0.3)
        // Note 2 : ding plus grave décalé
        const osc2 = ctx.createOscillator()
        const g2   = ctx.createGain()
        osc2.connect(g2); g2.connect(ctx.destination)
        osc2.frequency.setValueAtTime(660, ctx.currentTime + 0.18)
        g2.gain.setValueAtTime(0.2, ctx.currentTime + 0.18)
        g2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.55)
        osc2.start(ctx.currentTime + 0.18); osc2.stop(ctx.currentTime + 0.55)
    } catch { /* ignore si AudioContext indisponible */ }
}

watch(unreadEmails, (newVal, oldVal) => {
    if (newVal > oldVal) {
        playEmailSound()
    }
    prevUnreadEmails.value = newVal
})

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash.success) {
            flashType.value = 'success'
            flashMessage.value = flash.success
            triggerFlash()
        } else if (flash.warning) {
            flashType.value = 'warning'
            flashMessage.value = flash.warning
            triggerFlash()
        } else if (flash.error) {
            flashType.value = 'error'
            flashMessage.value = flash.error
            triggerFlash()
        }
    },
    { immediate: true, deep: true }
)

function triggerFlash() {
    showFlash.value = true
    if (flashTimer) clearTimeout(flashTimer)
    flashTimer = setTimeout(() => {
        showFlash.value = false
    }, 5000)
}

function closeFlash() {
    showFlash.value = false
    if (flashTimer) clearTimeout(flashTimer)
}

const userRole   = (page.props.auth as any).user?.role as string | undefined
const isSuperAdmin = userRole === 'super_admin'
const isTrainer    = userRole === 'trainer'

const allNavItems = [
    { label: 'Tableau de Bord', icon: 'dashboard',            href: '/',             prefix: 'Dashboard/',      roles: ['super_admin', 'admin', 'trainer'] },
    { label: 'Utilisateurs',    icon: 'admin_panel_settings', href: '/users',         prefix: 'Users/',          roles: ['super_admin'] },
    { label: 'Projets',         icon: 'folder_open',          href: '/projects',      prefix: 'Projects/',       roles: ['super_admin', 'admin'] },
    { label: 'Apprenants',      icon: 'group',                href: '/learners',      prefix: 'Learners/',       roles: ['super_admin', 'admin'] },
    { label: 'Formateurs',      icon: 'school',               href: '/trainers',      prefix: 'Trainers/',       roles: ['super_admin', 'admin'] },
    { label: 'Présences',       icon: 'fact_check',            href: '/presences',     prefix: 'Attendances/',    roles: ['super_admin', 'admin', 'trainer'] },
    { label: 'Partenaires',     icon: 'handshake',             href: '/partners',      prefix: 'Partners/',       roles: ['super_admin', 'admin'] },
    { label: 'Référentiels',    icon: 'menu_book',             href: '/referentiels',  prefix: 'Referentiels/',   roles: ['super_admin', 'admin'] },
    { label: 'Statistiques',    icon: 'bar_chart',             href: '/statistics',    prefix: 'Statistics/',     roles: ['super_admin', 'admin'] },
    { label: 'Communication',   icon: 'chat',                  href: '/communication/emails', prefix: 'Communication/',  roles: ['super_admin', 'admin'] },
]

const navItems = allNavItems.filter(item => !userRole || item.roles.includes(userRole))

const isActive = (prefix: string) =>
    page.component === prefix.replace('/', '/Index') || page.component.startsWith(prefix)

const logout = () => router.post('/deconnexion')
</script>

<template>
    <div class="bg-background text-on-surface h-screen overflow-hidden flex">
        <!-- Sidebar -->
        <aside class="bg-on-secondary-fixed w-[260px] h-screen fixed left-0 top-0 flex flex-col py-md z-20">
            <!-- Logo -->
            <div class="px-md mb-xl flex justify-center">
                <img
                    src="/images/logo.jpeg"
                    alt="Simplon Burkina Faso"
                    class="h-10 w-auto object-contain"
                />
            </div>

            <!-- Nav items -->
            <nav class="flex-1 overflow-y-auto flex flex-col gap-xs custom-scroll">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="nav-link"
                    :class="isActive(item.prefix) ? 'nav-active' : 'nav-inactive'"
                >
                    <span class="material-symbols-outlined">{{ item.icon }}</span>
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Bottom -->
            <div class="mt-auto pt-md border-t border-on-secondary-fixed-variant flex flex-col gap-xs text-body-md">
                <Link
                    v-if="!isTrainer"
                    href="/configuration"
                    class="text-secondary-fixed-dim pl-5 py-sm flex items-center gap-md hover:text-on-secondary hover:bg-white/5 transition-colors duration-200 ease-in-out"
                >
                    <span class="material-symbols-outlined">settings</span>
                    Configuration
                </Link>
                <button
                    @click="logout"
                    class="text-secondary-fixed-dim pl-5 py-sm flex items-center gap-md hover:text-error hover:bg-white/5 transition-colors duration-200 ease-in-out w-full text-left"
                >
                    <span class="material-symbols-outlined">logout</span>
                    Déconnexion
                </button>
            </div>
        </aside>

        <!-- Main area -->
        <div class="flex-1 flex flex-col ml-[260px] w-[calc(100%-260px)] h-screen overflow-hidden">
            <!-- Top bar -->
            <header class="bg-surface fixed top-0 right-0 w-[calc(100%-260px)] h-16 border-b border-surface-container-highest flex justify-between items-center px-lg z-10">
                <div class="flex items-center gap-md w-1/3">
                    <div class="relative w-full max-w-[300px]">
                        <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size: 20px;">search</span>
                        <input
                            type="text"
                            placeholder="Rechercher..."
                            class="w-full bg-surface-container-low border border-surface-container-highest rounded-full py-xs pl-xl pr-md text-body-sm text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-md">
                                    <!-- Badge email non lu -->
                                    <Link href="/communication/emails" class="mail-bell" title="Messagerie">
                                        <span class="material-symbols-outlined" style="font-size:22px">mail</span>
                                        <span v-if="unreadEmails > 0" class="mail-badge">
                                            {{ String(unreadEmails).padStart(2, '0') }}
                                        </span>
                                    </Link>
                                    <NotificationBell :initial-count="($page.props.unread_notifications_count as number) ?? 0" />
                                    <div class="h-6 w-px bg-surface-container-highest mx-xs"></div>
                    <Link href="/profil" class="flex items-center gap-sm hover:opacity-80 transition-opacity">
                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-on-primary text-body-sm font-bold">
                            {{ $page.props.auth.user?.full_name?.charAt(0) ?? 'A' }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-body-sm font-semibold text-on-surface leading-tight">
                                {{ $page.props.auth.user?.full_name ?? 'Utilisateur' }}
                            </span>
                            <span class="text-body-sm text-secondary leading-tight">
                                {{ $page.props.auth.user?.role_label ?? '' }}
                            </span>
                        </div>
                    </Link>
                </div>
            </header>

            <!-- Toast notification -->
            <Transition name="toast">
                <div
                    v-if="showFlash"
                    class="toast-wrap"
                    :class="{
                        'toast-success': flashType === 'success',
                        'toast-warning': flashType === 'warning',
                        'toast-error':   flashType === 'error',
                    }"
                >
                    <span
                        class="material-symbols-outlined toast-icon"
                        :class="{
                            'toast-icon-success': flashType === 'success',
                            'toast-icon-warning': flashType === 'warning',
                            'toast-icon-error':   flashType === 'error',
                        }"
                    >
                        {{ flashType === 'success' ? 'check_circle' : flashType === 'warning' ? 'warning' : 'error' }}
                    </span>
                    <div class="toast-body">
                        <p class="toast-title">
                            {{ flashType === 'success' ? 'Succès' : flashType === 'warning' ? 'Attention' : 'Erreur' }}
                        </p>
                        <p class="toast-msg">{{ flashMessage }}</p>
                    </div>
                    <button @click="closeFlash" class="toast-close">
                        <span class="material-symbols-outlined" style="font-size:17px">close</span>
                    </button>
                    <!-- Barre de progression -->
                    <div class="toast-progress-track">
                        <div
                            class="toast-progress-bar"
                            :class="{
                                'tpb-success': flashType === 'success',
                                'tpb-warning': flashType === 'warning',
                                'tpb-error':   flashType === 'error',
                            }"
                            :key="flashMessage"
                        ></div>
                    </div>
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
/* Scrollbar discrète sidebar */
.custom-scroll {
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}
.custom-scroll::-webkit-scrollbar {
    width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 99px;
}
aside:hover .custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.35);
}

/* ── Toast notification ── */
.toast-wrap {
    position: fixed;
    top: 76px;
    right: 20px;
    z-index: 9999;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px 18px 16px;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.07);
    min-width: 280px;
    max-width: 360px;
    background: #fff;
    border-left: 4px solid transparent;
    overflow: hidden;
}
.toast-success { border-left-color: #22c55e; }
.toast-warning { border-left-color: #f59e0b; }
.toast-error   { border-left-color: #ef4444; }

.toast-icon { font-size: 22px; margin-top: 1px; flex-shrink: 0; }
.toast-icon-success { color: #22c55e; }
.toast-icon-warning { color: #f59e0b; }
.toast-icon-error   { color: #ef4444; }

.toast-body { flex: 1; min-width: 0; }
.toast-title {
    font-size: 13px;
    font-weight: 700;
    color: #191c1e;
    line-height: 1.3;
    margin-bottom: 2px;
}
.toast-msg {
    font-size: 12px;
    color: #515f74;
    line-height: 1.4;
    word-break: break-word;
}
.toast-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    color: #9aaabb;
    cursor: pointer;
    border-radius: 4px;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
    padding: 0;
}
.toast-close:hover { background: #f5f7f9; color: #515f74; }

/* Barre de progression */
.toast-progress-track {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: rgba(0,0,0,0.05);
}
.toast-progress-bar {
    height: 100%;
    width: 100%;
    animation: toast-shrink 5s linear forwards;
    transform-origin: left;
}
.tpb-success { background: #22c55e; }
.tpb-warning { background: #f59e0b; }
.tpb-error   { background: #ef4444; }
@keyframes toast-shrink {
    from { width: 100%; }
    to   { width: 0%; }
}

/* Animation slide depuis la droite */
.toast-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.4, 0.64, 1); }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from   { opacity: 0; transform: translateX(110%); }
.toast-leave-to     { opacity: 0; transform: translateX(110%); }

.nav-link {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 0;
    font-size: 14px;
    line-height: 20px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.nav-active {
    color: #ffffff;
    border-left: 4px solid #E5004C;
    padding-left: 16px;
    background: rgba(229, 0, 76, 0.1);
}
.nav-inactive {
    color: #b9c7e0;
    padding-left: 20px;
}
.nav-inactive:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.05);
}

/* ── Icône mail avec badge ── */
.mail-bell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    color: #515f74;
    text-decoration: none;
    transition: background 0.15s;
}
.mail-bell:hover { background: rgba(0,0,0,0.05); }
.mail-badge {
    position: absolute;
    top: -2px;
    right: -4px;
    background: #E5004C;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    border-radius: 99px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    line-height: 1;
    border: 2px solid #fff;
}
</style>
