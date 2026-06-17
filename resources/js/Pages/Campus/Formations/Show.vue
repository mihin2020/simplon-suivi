<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import { ref } from 'vue'

defineOptions({ layout: AdminLayout })

interface Cohort {
    id: string
    name: string
    started_at: string
    ended_at: string
    status: string
    learners_count: number
}

interface Formation {
    id: string
    name: string
    description: string | null
    duration_months: number
    mode: string
    total_cost: number
    is_active: boolean
    cohorts: Cohort[]
}

const props = defineProps<{ formation: Formation }>()

const fmt = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })

const formatCost = (n: number) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

const modeLabel: Record<string, string> = { presentiel: 'Présentiel', en_ligne: 'En ligne' }
const modeIcon:  Record<string, string> = { presentiel: 'location_on', en_ligne: 'wifi' }

const statusClass: Record<string, string> = {
    planifiee: 'badge-blue',
    en_cours:  'badge-green',
    cloturee:  'badge-gray',
}
const statusLabel: Record<string, string> = {
    planifiee: 'Planifiée',
    en_cours:  'En cours',
    cloturee:  'Clôturée',
}

// Suppression cohorte depuis cette page
const showDelete  = ref(false)
const deleting    = ref(false)
const targetCohort = ref<Cohort | null>(null)

const askDelete = (c: Cohort) => { targetCohort.value = c; showDelete.value = true }
const confirmDelete = () => {
    if (!targetCohort.value) return
    deleting.value = true
    router.delete(`/campus/cohorts/${targetCohort.value.id}`, {
        onFinish: () => { deleting.value = false; showDelete.value = false; targetCohort.value = null },
    })
}
</script>

<template>
    <Head :title="formation.name" />
    <div class="max-w-[1200px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-start justify-between gap-md">
            <div class="flex items-center gap-md">
                <Link href="/campus/formations" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <div class="flex items-center gap-sm mb-xs">
                        <span class="mode-badge">
                            <span class="material-symbols-outlined" style="font-size:13px">{{ modeIcon[formation.mode] }}</span>
                            {{ modeLabel[formation.mode] }}
                        </span>
                        <span v-if="!formation.is_active" class="inactive-badge">Inactif</span>
                    </div>
                    <h1 class="text-h1 font-bold text-on-surface">{{ formation.name }}</h1>
                    <p v-if="formation.description" class="text-body-md text-secondary mt-xs">{{ formation.description }}</p>
                </div>
            </div>
            <Link :href="`/campus/formations/${formation.id}/edit`" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                Modifier
            </Link>
        </div>

        <!-- Infos rapides -->
        <div class="info-row">
            <div class="info-card">
                <span class="material-symbols-outlined info-icon">schedule</span>
                <div>
                    <p class="info-label">Durée</p>
                    <p class="info-val">{{ formation.duration_months }} mois</p>
                </div>
            </div>
            <div class="info-card">
                <span class="material-symbols-outlined info-icon">payments</span>
                <div>
                    <p class="info-label">Coût total</p>
                    <p class="info-val">{{ formatCost(formation.total_cost) }}</p>
                </div>
            </div>
            <div class="info-card">
                <span class="material-symbols-outlined info-icon">groups</span>
                <div>
                    <p class="info-label">Cohortes</p>
                    <p class="info-val">{{ formation.cohorts.length }}</p>
                </div>
            </div>
        </div>

        <!-- Cohortes -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                <h2 class="text-h2 font-semibold text-on-surface">
                    Cohortes
                    <span class="count-badge ml-sm">{{ formation.cohorts.length }}</span>
                </h2>
                <Link :href="`/campus/cohorts/create?formation=${formation.id}`" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:16px">add</span>
                    Nouvelle cohorte
                </Link>
            </div>

            <div class="divide-y divide-surface-container-highest">
                <div v-if="formation.cohorts.length === 0" class="px-lg py-xl text-center text-secondary text-body-md">
                    Aucune cohorte pour cette formation.
                </div>
                <div
                    v-for="c in formation.cohorts"
                    :key="c.id"
                    class="px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors group"
                >
                    <div>
                        <div class="flex items-center gap-sm">
                            <Link
                                :href="`/campus/cohorts/${c.id}`"
                                class="font-semibold text-on-surface hover:text-primary transition-colors"
                            >
                                {{ c.name }}
                            </Link>
                            <span :class="['status-badge', statusClass[c.status]]">
                                {{ statusLabel[c.status] ?? c.status }}
                            </span>
                        </div>
                        <p class="text-body-sm text-secondary mt-xs">
                            {{ fmt(c.started_at) }} → {{ fmt(c.ended_at) }}
                            · {{ c.learners_count }} apprenant(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                        <Link :href="`/campus/cohorts/${c.id}`" class="icon-btn" title="Voir">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </Link>
                        <Link :href="`/campus/cohorts/${c.id}/edit`" class="icon-btn" title="Modifier">
                            <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                        </Link>
                        <button @click="askDelete(c)" class="icon-btn danger" title="Supprimer" type="button">
                            <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ConfirmModal
        :show="showDelete"
        title="Supprimer la cohorte"
        :message="targetCohort ? `Vous êtes sur le point de supprimer « ${targetCohort.name} ». Cette action est irréversible.` : ''"
        confirm-label="Supprimer"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="showDelete = false"
    />
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    transition: background 0.15s, color 0.15s; flex-shrink: 0; text-decoration: none;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

.mode-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
    background: #dbeafe; color: #1d4ed8;
}
.inactive-badge {
    display: inline-flex; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 600; background: #f3f4f6; color: #6b7280;
}

.info-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.info-card {
    display: flex; align-items: center; gap: 14px;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.info-icon { font-size: 22px; color: #E5004C; }
.info-label { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.info-val { font-size: 16px; font-weight: 700; color: #191c1e; margin-top: 2px; }

.count-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 24px; height: 24px; padding: 0 6px;
    background: #f2f4f6; border-radius: 99px;
    font-size: 12px; font-weight: 600; color: #515f74;
}

.status-badge {
    display: inline-flex; align-items: center;
    padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
}
.badge-blue  { background: #dbeafe; color: #1d4ed8; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-gray  { background: #f3f4f6; color: #6b7280; }

.icon-btn {
    padding: 4px; color: #515f74; border-radius: 4px;
    transition: color 0.15s; display: inline-flex;
    background: transparent; border: none; cursor: pointer; text-decoration: none;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    transition: background 0.2s; text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #1F3A4D;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #1F3A4D; color: #fff; }
</style>
