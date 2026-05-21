<script setup lang="ts">
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Mode { value: string; label: string }

interface CampusFormation {
    id: string
    name: string
    description: string | null
    duration_months: number
    mode: string
    total_cost: number
    is_active: boolean
    cohorts_count: number
}

interface Paginated {
    data: CampusFormation[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

const props = defineProps<{
    formations: Paginated
    modes: Mode[]
}>()

const search = ref('')
let timer: ReturnType<typeof setTimeout>
watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/campus/formations', { search: val }, { preserveState: true, replace: true })
    }, 400)
})

const modeLabel = (val: string) => props.modes.find(m => m.value === val)?.label ?? val

const modeIcon: Record<string, string> = {
    presentiel: 'location_on',
    en_ligne:   'wifi',
}

const formatCost = (n: number) =>
    new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

const destroy = (f: CampusFormation) => {
    if (confirm(`Supprimer la formation « ${f.name} » ?`)) {
        router.delete(`/campus/formations/${f.id}`)
    }
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Formations</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ formations.meta?.total ?? 0 }} formation(s) dans le catalogue.
                </p>
            </div>
            <Link href="/campus/formations/create" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">add</span>
                Nouvelle formation
            </Link>
        </div>

        <!-- Barre de recherche -->
        <div class="search-bar">
            <div class="search-input-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Rechercher une formation..."
                    class="search-input"
                />
                <button v-if="search" @click="search = ''" class="search-clear" title="Effacer">
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
            </div>
            <span v-if="search" class="search-hint">{{ formations.data.length }} résultat(s)</span>
        </div>

        <!-- Grille de cartes -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-lg">
            <div
                v-for="f in formations.data"
                :key="f.id"
                class="formation-card"
            >
                <!-- Mode + statut -->
                <div class="card-top">
                    <span :class="['mode-badge', `mode-${f.mode}`]">
                        <span class="material-symbols-outlined" style="font-size:13px">{{ modeIcon[f.mode] }}</span>
                        {{ modeLabel(f.mode) }}
                    </span>
                    <span v-if="!f.is_active" class="inactive-badge">Inactif</span>
                </div>

                <!-- Titre + description -->
                <h2 class="card-title">{{ f.name }}</h2>
                <p v-if="f.description" class="card-desc">{{ f.description }}</p>

                <!-- Stats -->
                <div class="card-stats">
                    <div class="stat">
                        <span class="stat-val">{{ f.duration_months }}</span>
                        <span class="stat-lbl">mois</span>
                    </div>
                    <div class="stat-sep"></div>
                    <div class="stat">
                        <span class="stat-val">{{ f.cohorts_count }}</span>
                        <span class="stat-lbl">Cohortes</span>
                    </div>
                </div>

                <!-- Coût -->
                <div class="card-cost">
                    <span class="material-symbols-outlined" style="font-size:16px;color:#515f74">payments</span>
                    {{ formatCost(f.total_cost) }}
                </div>

                <!-- Actions -->
                <div class="card-actions">
                    <Link :href="`/campus/formations/${f.id}`" class="btn-secondary flex-1 text-center">
                        Voir les cohortes
                    </Link>
                    <Link :href="`/campus/formations/${f.id}/edit`" class="icon-btn" title="Modifier">
                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    </Link>
                    <button @click="destroy(f)" class="icon-btn danger" title="Supprimer" type="button">
                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="formations.data.length === 0" class="col-span-full empty-state">
                <span class="material-symbols-outlined" style="font-size:56px;color:#b9c7e0">local_library</span>
                <p class="empty-title">Aucune formation trouvée</p>
                <p class="empty-sub">Commencez par créer votre premier parcours de formation.</p>
                <Link href="/campus/formations/create" class="btn-primary mt-md">
                    Créer une formation
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="formations.links?.length > 3" class="flex items-center justify-between">
            <span class="text-body-sm text-on-surface-variant">
                {{ formations.meta?.from }}–{{ formations.meta?.to }} sur {{ formations.meta?.total }}
            </span>
            <div class="flex items-center gap-xs">
                <template v-for="link in formations.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                    <span v-else class="page-btn page-disabled" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #e0e3e5;
    transition: background 0.15s;
    text-decoration: none;
    cursor: pointer;
}
.btn-secondary:hover { background: #f2f4f6; }

.icon-btn {
    padding: 6px;
    color: #515f74;
    border-radius: 6px;
    border: 1px solid #e0e3e5;
    background: transparent;
    transition: color 0.15s, background 0.15s;
    display: inline-flex;
    cursor: pointer;
}
.icon-btn:hover { color: #E5004C; background: #fff5f8; border-color: #fbb6ce; }
.icon-btn.danger:hover { color: #ba1a1a; background: #fff1f2; border-color: #fca5a5; }

/* Search bar */
.search-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.search-input-wrapper {
    position: relative;
    flex: 1;
    max-width: 400px;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: #9aaabb;
    font-size: 20px;
    pointer-events: none;
}
.search-input {
    width: 100%;
    padding: 10px 40px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    transition: all 0.15s ease;
    outline: none;
}
.search-input:focus { background: #fff; border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.search-input::placeholder { color: #9aaabb; }
.search-clear {
    position: absolute;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #e0e3e5;
    color: #515f74;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.search-clear:hover { background: #d1d5db; color: #191c1e; }
.search-hint { font-size: 13px; color: #9aaabb; font-weight: 500; }

/* Cards */
.formation-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s;
}
.formation-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

.card-top { display: flex; align-items: center; gap: 8px; }
.mode-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.mode-presentiel { background: #dbeafe; color: #1d4ed8; }
.mode-en_ligne   { background: #ede9fe; color: #7c3aed; }
.inactive-badge { display: inline-flex; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #6b7280; }

.card-title { font-size: 16px; font-weight: 700; color: #191c1e; margin: 0; }
.card-desc { font-size: 13px; color: #515f74; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.card-stats {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-top: 1px solid #f2f4f6;
    border-bottom: 1px solid #f2f4f6;
}
.stat { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; }
.stat-val { font-size: 18px; font-weight: 700; color: #191c1e; }
.stat-val.primary { color: #E5004C; }
.stat-lbl { font-size: 10px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-sep { width: 1px; height: 32px; background: #e0e3e5; }

.card-cost {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #191c1e;
}

.card-actions { display: flex; align-items: center; gap: 8px; margin-top: auto; }

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    text-align: center;
}
.empty-title { font-size: 18px; font-weight: 600; color: #191c1e; margin: 16px 0 4px; }
.empty-sub { font-size: 14px; color: #9aaabb; }

/* Pagination */
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 6px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
    transition: background 0.15s;
    cursor: pointer;
    text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }
</style>
