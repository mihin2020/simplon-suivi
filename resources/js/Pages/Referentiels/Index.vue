<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface Referentiel {
    id: string
    name: string
    description: string | null
    formations_count: number
    blocks_count: number
}

defineProps<{
    referentiels: Referentiel[]
}>()
</script>

<template>
    <Head title="Référentiels" />
    <div class="max-w-[1200px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined">menu_book</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Référentiels de Compétences</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        Gérez les référentiels partagés entre plusieurs formations.
                    </p>
                </div>
            </div>
            <Can permission="referentiels.create">
                <Link href="/referentiels/create" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                    Nouveau référentiel
                </Link>
            </Can>
        </div>

        <!-- Liste vide -->
        <div v-if="referentiels.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:52px; color:#e0e3e5">menu_book</span>
            <h2 class="font-semibold text-on-surface mt-md">Aucun référentiel</h2>
            <p class="text-body-md text-secondary mt-xs">
                Créez votre premier référentiel pour l'associer à des formations.
            </p>
            <Can permission="referentiels.create">
                <Link href="/referentiels/create" class="btn-primary mt-lg inline-flex">
                    <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                    Créer un référentiel
                </Link>
            </Can>
        </div>

        <!-- Grille -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
            <Link
                v-for="ref in referentiels"
                :key="ref.id"
                :href="`/referentiels/${ref.id}`"
                class="ref-card"
            >
                <div class="ref-icon">
                    <span class="material-symbols-outlined" style="font-size:22px">menu_book</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-on-surface truncate">{{ ref.name }}</h3>
                    <p v-if="ref.description" class="text-body-sm text-secondary mt-xs line-clamp-2">{{ ref.description }}</p>
                    <div class="flex items-center gap-md mt-sm">
                        <span class="stat-badge">
                            <span class="material-symbols-outlined" style="font-size:14px">folder_copy</span>
                            {{ ref.blocks_count }} bloc(s)
                        </span>
                        <span class="stat-badge">
                            <span class="material-symbols-outlined" style="font-size:14px">school</span>
                            {{ ref.formations_count }} formation(s)
                        </span>
                    </div>
                </div>
                <span class="material-symbols-outlined text-secondary" style="font-size:20px">chevron_right</span>
            </Link>
        </div>

    </div>
</template>

<style scoped>
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    transition: background 0.2s; border: none; cursor: pointer; text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.empty-state {
    display: flex; flex-direction: column; align-items: center;
    padding: 60px 40px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 16px; text-align: center;
}

.ref-card {
    display: flex; align-items: flex-start; gap: 16px;
    padding: 20px; background: #fff; border: 1px solid #e0e3e5;
    border-radius: 12px; text-decoration: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ref-card:hover { border-color: #E5004C; box-shadow: 0 2px 8px rgba(229,0,76,0.08); }

.ref-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: #fff0f4; color: #E5004C;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.stat-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: #9aaabb; font-weight: 500;
}
</style>
