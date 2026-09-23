<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    form: {
        id: string
        title: string
        status: string
        status_label: string
        project?: { id: string; name: string }
        formation?: { id: string; name: string }
    }
    stats: {
        totals: {
            responses: number
            submitted: number
            selected: number
            enrolled: number
            rejected: number
        }
        by_status: Array<{ value: string; label: string; color: string; count: number }>
        conversion: {
            selection_rate: number
            enrollment_rate: number
            rejection_rate: number
        }
        daily: Array<{ date: string; count: number }>
        recent: Array<{
            id: string
            email: string | null
            status: string
            status_label: string
            status_color: string
            submitted_at: string | null
        }>
    }
}>()

const maxDaily = computed(() => Math.max(1, ...props.stats.daily.map(d => d.count)))

const badgeClass = (color: string) => {
    const map: Record<string, string> = {
        gray: 'badge-gray', blue: 'badge-blue', green: 'badge-green',
        red: 'badge-red', purple: 'badge-purple',
    }
    return map[color] ?? 'badge-gray'
}

const formatDay = (iso: string) => {
    const d = new Date(iso + 'T00:00:00')
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}
</script>

<template>
    <Head :title="`Stats — ${form.title}`" />
    <div class="max-w-[1100px] mx-auto space-y-xl">
        <div class="flex items-start justify-between gap-md flex-wrap">
            <div class="page-title-row">
                <Link :href="`/forms/${form.id}/edit`" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <h1 class="page-title">Statistiques</h1>
                    <p class="page-subtitle">
                        {{ form.title }} · {{ form.project?.name }} · {{ form.formation?.name }}
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <Can permission="forms.responses">
                    <Link :href="`/forms/${form.id}/responses`" class="btn-secondary">Réponses</Link>
                </Can>
                <Link :href="`/forms/${form.id}/edit`" class="btn-secondary">Éditeur</Link>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">inbox</span>
                <div class="kpi-value">{{ stats.totals.responses }}</div>
                <div class="kpi-label">Candidatures</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">hourglass_top</span>
                <div class="kpi-value">{{ stats.totals.submitted }}</div>
                <div class="kpi-label">En attente</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">how_to_reg</span>
                <div class="kpi-value">{{ stats.totals.selected }}</div>
                <div class="kpi-label">Sélectionnées</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">school</span>
                <div class="kpi-value">{{ stats.totals.enrolled }}</div>
                <div class="kpi-label">Inscrites</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon material-symbols-outlined">person_off</span>
                <div class="kpi-value">{{ stats.totals.rejected }}</div>
                <div class="kpi-label">Refusées</div>
            </div>
        </div>

        <div class="rates-grid">
            <div class="rate-card">
                <p class="rate-label">Taux de sélection</p>
                <p class="rate-value">{{ stats.conversion.selection_rate }}%</p>
            </div>
            <div class="rate-card">
                <p class="rate-label">Taux d’inscription</p>
                <p class="rate-value">{{ stats.conversion.enrollment_rate }}%</p>
            </div>
            <div class="rate-card">
                <p class="rate-label">Taux de refus</p>
                <p class="rate-value">{{ stats.conversion.rejection_rate }}%</p>
            </div>
        </div>

        <div class="panels">
            <section class="panel">
                <h2>Répartition par statut</h2>
                <ul class="status-list">
                    <li v-for="s in stats.by_status" :key="s.value">
                        <span class="status-badge" :class="badgeClass(s.color)">{{ s.label }}</span>
                        <div class="bar-track">
                            <div
                                class="bar-fill"
                                :style="{ width: (stats.totals.responses ? (s.count / stats.totals.responses) * 100 : 0) + '%' }"
                            />
                        </div>
                        <strong>{{ s.count }}</strong>
                    </li>
                </ul>
            </section>

            <section class="panel">
                <h2>Soumissions (14 derniers jours)</h2>
                <div class="chart">
                    <div
                        v-for="d in stats.daily"
                        :key="d.date"
                        class="chart-col"
                        :title="`${formatDay(d.date)} : ${d.count}`"
                    >
                        <div class="chart-bar-wrap">
                            <div
                                class="chart-bar"
                                :style="{ height: ((d.count / maxDaily) * 100) + '%' }"
                            />
                        </div>
                        <span class="chart-label">{{ formatDay(d.date) }}</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="panel">
            <div class="panel-head">
                <h2>Dernières candidatures</h2>
                <Can permission="forms.responses">
                    <Link :href="`/forms/${form.id}/responses`" class="link-more">Voir tout</Link>
                </Can>
            </div>
            <div v-if="stats.recent.length === 0" class="empty">Aucune candidature pour le moment.</div>
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>E-mail</th>
                        <th>Statut</th>
                        <th>Soumis le</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in stats.recent" :key="r.id">
                        <td>{{ r.email || '—' }}</td>
                        <td>
                            <span class="status-badge" :class="badgeClass(r.status_color)">{{ r.status_label }}</span>
                        </td>
                        <td class="muted">
                            {{ r.submitted_at ? new Date(r.submitted_at).toLocaleString('fr-FR') : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<style scoped>
.page-title-row { display: flex; align-items: flex-start; gap: 14px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: #fff; border: 1px solid #e0e3e5; color: #1F3A4D;
    text-decoration: none;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; }
.page-subtitle { font-size: 14px; color: #515f74; margin-top: 4px; }
.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-secondary {
    display: inline-flex; align-items: center; padding: 8px 14px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: 1.5px solid #e0e3e5; color: #515f74; background: #fff;
}
.kpi-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;
}
.kpi-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 16px;
}
.kpi-icon { font-size: 22px; color: #E5004C; }
.kpi-value { font-size: 28px; font-weight: 700; color: #1F3A4D; margin-top: 6px; }
.kpi-label { font-size: 12px; color: #515f74; margin-top: 2px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.rates-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
@media (max-width: 700px) { .rates-grid { grid-template-columns: 1fr; } }
.rate-card {
    background: #1F3A4D; color: #fff; border-radius: 12px; padding: 16px 18px;
}
.rate-label { font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.04em; }
.rate-value { font-size: 28px; font-weight: 700; margin-top: 4px; }
.panels { display: grid; grid-template-columns: 1fr 1.2fr; gap: 12px; }
@media (max-width: 900px) { .panels { grid-template-columns: 1fr; } }
.panel {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 18px;
}
.panel h2 { font-size: 15px; font-weight: 700; color: #191c1e; margin: 0 0 14px; }
.panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.panel-head h2 { margin: 0; }
.link-more { font-size: 13px; font-weight: 600; color: #E5004C; text-decoration: none; }
.status-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.status-list li { display: grid; grid-template-columns: 120px 1fr 36px; gap: 10px; align-items: center; }
.bar-track { height: 8px; background: #f1f3f5; border-radius: 99px; overflow: hidden; }
.bar-fill { height: 100%; background: #E5004C; border-radius: 99px; min-width: 0; }
.chart {
    display: flex; align-items: flex-end; gap: 6px; height: 160px; padding-top: 8px;
}
.chart-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; }
.chart-bar-wrap {
    flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center;
}
.chart-bar {
    width: 70%; max-width: 28px; background: linear-gradient(180deg, #E5004C, #1F3A4D);
    border-radius: 6px 6px 2px 2px; min-height: 2px;
}
.chart-label { font-size: 9px; color: #80868b; margin-top: 6px; white-space: nowrap; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em;
    color: #515f74; padding: 8px 0; border-bottom: 1px solid #eef0f2;
}
.data-table td { padding: 12px 0; border-bottom: 1px solid #f5f6f7; font-size: 14px; color: #191c1e; }
.muted { color: #515f74; font-size: 13px; }
.empty { color: #80868b; font-size: 14px; padding: 12px 0; }
.status-badge {
    display: inline-flex; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.badge-gray { background: #f1f3f5; color: #515f74; }
.badge-blue { background: #e8f1ff; color: #1d4ed8; }
.badge-green { background: #e6f7ed; color: #0f7a3d; }
.badge-red { background: #fde8e8; color: #b91c1c; }
.badge-purple { background: #f3e8ff; color: #7e22ce; }
</style>
