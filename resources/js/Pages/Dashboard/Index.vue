<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Stats {
    projects: { total: number; active: number }
    formations: { total: number; active: number }
    learners: { total: number; active: number }
    trainers: { total: number }
    insertion_rate: number
}

const props = defineProps<{ stats: Stats }>()

const kpis = [
    {
        label: 'Total Projets',
        value: props.stats.projects.total,
        sub: `${props.stats.projects.active} En cours`,
        highlight: true,
    },
    {
        label: 'Total Formations',
        value: props.stats.formations.total,
        sub: `${props.stats.formations.active} En cours`,
        highlight: true,
    },
    {
        label: 'Total Apprenants',
        value: props.stats.learners.total.toLocaleString('fr-FR'),
        sub: `${props.stats.learners.active} Actifs`,
        highlight: true,
    },
    {
        label: 'Formateurs',
        value: props.stats.trainers.total,
        sub: '+0 ce trimestre',
        highlight: false,
    },
    {
        label: "Taux d'insertion",
        value: `${props.stats.insertion_rate}%`,
        sub: 'Promotion en cours',
        highlight: true,
    },
]
</script>

<template>
    <Head title="Tableau de Bord" />

    <div class="max-w-[1600px] mx-auto space-y-xl">
        <!-- Page header -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Aperçu Global</h1>
                <p class="text-body-md text-secondary mt-xs">
                    Métriques en temps réel sur tous les programmes actifs.
                </p>
            </div>
            <div class="flex gap-sm">
                <select class="bg-surface-container-lowest border border-surface-container-highest text-on-surface text-body-sm rounded-lg py-xs px-md focus:ring-primary focus:border-primary">
                    <option>Tous les Projets</option>
                </select>
                <select class="bg-surface-container-lowest border border-surface-container-highest text-on-surface text-body-sm rounded-lg py-xs px-md focus:ring-primary focus:border-primary">
                    <option>Cette Année</option>
                    <option>Dernier Trimestre</option>
                </select>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-md">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                :class="[
                    'bg-surface-container-lowest border border-surface-container-highest rounded-lg p-md flex flex-col justify-between border-l-[3px]',
                    kpi.highlight ? 'border-l-primary' : 'border-l-surface-container-highest',
                ]"
            >
                <span class="text-body-sm text-secondary uppercase tracking-wider mb-sm">{{ kpi.label }}</span>
                <div class="text-h2 font-semibold text-on-surface">{{ kpi.value }}</div>
                <div class="text-body-sm text-tertiary mt-xs flex items-center gap-xs">
                    <span v-if="kpi.highlight" class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                    {{ kpi.sub }}
                </div>
            </div>
        </div>

        <!-- Placeholder sections -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-md">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-lg p-md">
                <h2 class="text-h3 font-semibold text-on-surface mb-md">Formations actives</h2>
                <p class="text-body-md text-secondary">Aucune formation active pour le moment.</p>
            </div>
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-lg p-md">
                <h2 class="text-h3 font-semibold text-on-surface mb-md">Activité récente</h2>
                <p class="text-body-md text-secondary">Aucune activité récente.</p>
            </div>
        </div>
    </div>
</template>
