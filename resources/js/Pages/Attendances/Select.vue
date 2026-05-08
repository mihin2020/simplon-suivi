<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    project_name: string
    status: string
    status_label: string
    active_learners_count: number
    today_count: number
    started_at: string | null
    ended_at: string | null
}

const props = defineProps<{
    formations: Formation[]
    today: string
}>()

const filterProject   = ref('')
const filterFormation = ref('')

const projectOptions = computed(() => {
    const names = [...new Set(props.formations.map(f => f.project_name))].sort()
    return names
})

const filtered = computed(() => {
    return props.formations.filter(f => {
        const matchProject   = !filterProject.value   || f.project_name === filterProject.value
        const matchFormation = !filterFormation.value || f.name.toLowerCase().includes(filterFormation.value.toLowerCase())
        return matchProject && matchFormation
    })
})

const statusClass = (s: string) => ({
    active:    'badge-active',
    completed: 'badge-completed',
    cancelled: 'badge-cancelled',
    archived:  'badge-archived',
}[s] ?? 'badge-archived')
</script>

<template>
    <div class="max-w-5xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div>
            <h1 class="text-h1 font-bold text-on-surface">Présences</h1>
            <p class="text-body-md text-secondary mt-xs">
                Sélectionnez une formation pour saisir ou consulter les présences.
            </p>
        </div>

        <!-- Filtres -->
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Projet</label>
                <select v-model="filterProject" class="filter-select">
                    <option value="">Tous les projets</option>
                    <option v-for="p in projectOptions" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>
            <div class="filter-group" style="flex:2">
                <label class="filter-label">Recherche</label>
                <div class="search-wrap">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input
                        v-model="filterFormation"
                        type="text"
                        placeholder="Rechercher une formation par nom..."
                        class="filter-input"
                    />
                    <button v-if="filterFormation" class="clear-btn" @click="filterFormation = ''">
                        <span class="material-symbols-outlined" style="font-size:14px">close</span>
                    </button>
                    <span v-if="filterFormation" class="result-count">
                        {{ filtered.length }} résultat{{ filtered.length > 1 ? 's' : '' }}
                    </span>
                </div>
            </div>
            <button
                v-if="filterProject || filterFormation"
                class="reset-btn"
                @click="filterProject = ''; filterFormation = ''"
            >
                <span class="material-symbols-outlined" style="font-size:15px">close</span>
                Réinitialiser
            </button>
        </div>

        <!-- Liste vide -->
        <div v-if="filtered.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:48px;color:#ddd">fact_check</span>
            <p class="mt-sm text-secondary text-body-md">
                {{ formations.length === 0 ? 'Aucune formation disponible.' : 'Aucun résultat pour ces filtres.' }}
            </p>
        </div>

        <!-- Grille des formations -->
        <div v-else class="formations-grid">
            <Link
                v-for="f in filtered"
                :key="f.id"
                :href="`/formations/${f.id}/attendances`"
                class="formation-card"
            >
                <!-- Header -->
                <div class="card-header">
                    <div class="flex-1 min-w-0">
                        <p class="card-project">{{ f.project_name }}</p>
                        <h3 class="card-name">{{ f.name }}</h3>
                    </div>
                    <span class="status-badge" :class="statusClass(f.status)">{{ f.status_label }}</span>
                </div>

                <!-- Stats -->
                <div class="card-stats">
                    <div class="stat">
                        <span class="material-symbols-outlined stat-icon">groups</span>
                        <span class="stat-value">{{ f.active_learners_count }}</span>
                        <span class="stat-label">apprenants</span>
                    </div>
                    <div class="stat" :class="{ 'stat-done': f.today_count > 0 }">
                        <span class="material-symbols-outlined stat-icon">fact_check</span>
                        <span class="stat-value">{{ f.today_count }}</span>
                        <span class="stat-label">présences aujourd'hui</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer">
                    <span v-if="f.today_count > 0" class="already-done">
                        <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
                        Saisie du jour effectuée
                    </span>
                    <span v-else-if="f.status === 'active'" class="pending">
                        <span class="material-symbols-outlined" style="font-size:14px">radio_button_unchecked</span>
                        Saisie du jour en attente
                    </span>
                    <span v-else class="text-secondary text-body-sm"></span>
                    <span class="cta">
                        Saisir les présences
                        <span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span>
                    </span>
                </div>
            </Link>
        </div>

    </div>
</template>

<style scoped>
/* Filtres */
.filters-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 10px;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 160px;
}
.filter-label {
    font-size: 11px;
    font-weight: 600;
    color: #9aaabb;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.filter-select {
    padding: 7px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 13px;
    color: #191c1e;
    background: #fff;
    outline: none;
    cursor: pointer;
}
.filter-select:focus { border-color: #E5004C; }
.search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 10px;
    font-size: 18px;
    color: #9aaabb;
    pointer-events: none;
}
.filter-input {
    width: 100%;
    padding: 9px 90px 9px 36px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-input:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.clear-btn {
    position: absolute;
    right: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 50%;
    background: #f5f7f9;
    color: #515f74;
    cursor: pointer;
    transition: background 0.15s;
}
.clear-btn:hover { background: #eceef0; }
.result-count {
    position: absolute;
    right: 10px;
    font-size: 11px;
    font-weight: 600;
    color: #9aaabb;
    background: #f5f7f9;
    padding: 2px 8px;
    border-radius: 99px;
}
.reset-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 7px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
    background: #fff;
    cursor: pointer;
    white-space: nowrap;
    align-self: flex-end;
    transition: background 0.15s;
}
.reset-btn:hover { background: #f5f7f9; }

/* Grille */
.formations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 14px;
}

.formation-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 10px;
    text-decoration: none;
    transition: box-shadow 0.15s, border-color 0.15s, transform 0.1s;
    cursor: pointer;
}
.formation-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.07);
    border-color: #E5004C;
    transform: translateY(-1px);
}

/* Header */
.card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.card-project {
    font-size: 11px;
    font-weight: 600;
    color: #9aaabb;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
}
.card-name {
    font-size: 15px;
    font-weight: 700;
    color: #191c1e;
    line-height: 1.3;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.badge-active    { background: #d1fae5; color: #065f46; }
.badge-completed { background: #dbeafe; color: #1e40af; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
.badge-archived  { background: #f3f4f6; color: #6b7280; }

/* Stats */
.card-stats {
    display: flex;
    gap: 20px;
    padding: 12px 14px;
    background: #fafbfc;
    border-radius: 8px;
    border: 1px solid #f0f1f3;
}
.stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #515f74;
}
.stat-icon { font-size: 16px; color: #9aaabb; }
.stat-value { font-weight: 700; color: #191c1e; }
.stat-label { color: #9aaabb; }
.stat-done .stat-icon  { color: #22c55e; }
.stat-done .stat-value { color: #15803d; }

/* Footer */
.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid #f3f4f6;
    font-size: 12px;
}
.already-done {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #15803d;
    font-weight: 600;
}
.pending {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #d97706;
    font-weight: 600;
}
.cta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #E5004C;
    opacity: 0;
    transition: opacity 0.15s;
}
.formation-card:hover .cta { opacity: 1; }

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px dashed #e0e3e5;
    border-radius: 10px;
}
</style>
