<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()

const navItems = [
    { label: 'Tableau de Bord', icon: 'dashboard', href: '/', component: 'Dashboard/Index' },
    { label: 'Formations', icon: 'school', href: '/formations', component: 'Formations/Index' },
    { label: 'Apprenants', icon: 'group', href: '/apprenants', component: 'Learners/Index' },
    { label: 'Présences', icon: 'fact_check', href: '/presences', component: 'Attendances/Index' },
    { label: 'Insertion', icon: 'work_history', href: '/insertion', component: 'Insertion/Index' },
    { label: 'Communication', icon: 'chat', href: '/communication', component: 'Communication/Index' },
]

const isActive = (component: string) => page.component === component

const logout = () => router.post('/deconnexion')
</script>

<template>
    <div class="bg-background text-on-surface h-screen overflow-hidden flex">
        <!-- Sidebar -->
        <aside class="bg-on-secondary-fixed w-[260px] h-screen fixed left-0 top-0 flex flex-col py-md z-20">
            <!-- Logo -->
            <div class="px-md mb-xl">
                <img
                    src="/images/logo.jpeg"
                    alt="Simplon Burkina Faso"
                    class="h-10 w-auto object-contain brightness-0 invert"
                />
            </div>

            <!-- Nav items -->
            <nav class="flex-1 overflow-y-auto text-body-md flex flex-col gap-xs">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-md py-sm transition-all duration-200 ease-in-out',
                        isActive(item.component)
                            ? 'text-on-secondary border-l-4 border-primary pl-4 bg-primary/10'
                            : 'text-secondary-fixed-dim pl-5 hover:text-on-secondary hover:bg-white/5',
                    ]"
                >
                    <span class="material-symbols-outlined">{{ item.icon }}</span>
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Bottom -->
            <div class="mt-auto pt-md border-t border-on-secondary-fixed-variant flex flex-col gap-xs text-body-md">
                <Link
                    href="/configuration"
                    class="text-secondary-fixed-dim pl-5 py-sm flex items-center gap-md hover:text-on-secondary hover:bg-white/5 transition-colors duration-200 ease-in-out"
                >
                    <span class="material-symbols-outlined">settings</span>
                    Paramètres
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
                    <button class="text-on-surface-variant hover:bg-surface-container-low rounded-xl p-sm transition-transform scale-95 active:scale-90">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <div class="h-6 w-px bg-surface-container-highest mx-xs"></div>
                    <div class="flex items-center gap-sm">
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
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            <div
                v-if="$page.props.flash.success"
                class="fixed top-20 right-4 z-50 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-md py-sm text-body-sm flex items-center gap-sm shadow-sm"
            >
                <span class="material-symbols-outlined text-emerald-600" style="font-size: 18px;">check_circle</span>
                {{ $page.props.flash.success }}
            </div>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto mt-16 p-xl bg-background">
                <slot />
            </main>
        </div>
    </div>
</template>
