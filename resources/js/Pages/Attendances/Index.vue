<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface AttendanceCode {
    value: string
    label: string
    color: string
}

interface LearnerRow {
    id: string
    full_name: string
    attendance: { code: string; comment: string | null } | null
}

interface Formation {
    id: string
    name: string
    project: { name: string }
}

const props = defineProps<{
    formation: Formation
    date: string
    learners: LearnerRow[]
    codes: AttendanceCode[]
}>()

const form = useForm({
    date: props.date,
    records: Object.fromEntries(
        props.learners.map(l => [l.id, l.attendance?.code ?? 'P'])
    ) as Record<string, string>,
})

const submit = () => {
    form.post(`/formations/${props.formation.id}/attendances`, {
        preserveScroll: true,
    })
}

const stats = computed(() => {
    const values = Object.values(form.records)
    return {
        present:  values.filter(v => v === 'P').length,
        absent:   values.filter(v => v === 'AJ' || v === 'AN').length,
        late:     values.filter(v => v === 'RJ' || v === 'RN').length,
        total:    values.length,
    }
})

const codeColors: Record<string, string> = {
    P:  'code-P',
    AJ: 'code-AJ',
    AN: 'code-AN',
    RJ: 'code-RJ',
    RN: 'code-RN',
}

const rowHighlight: Record<string, string> = {
    AN: 'row-absent',
    RN: 'row-late',
    AJ: 'row-justified',
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg bg-surface-container-lowest p-lg rounded-xl border border-surface-container-highest">
            <div class="space-y-sm">
                <h1 class="text-h1 font-bold text-on-surface">Saisie des Présences</h1>
                <div class="flex flex-wrap items-center gap-md text-secondary text-body-md">
                    <span class="flex items-center gap-xs">
                        <span class="material-symbols-outlined" style="font-size:18px">school</span>
                        {{ formation.name }}
                    </span>
                    <span class="text-surface-container-highest">|</span>
                    <span class="flex items-center gap-xs">
                        <span class="material-symbols-outlined" style="font-size:18px">folder_open</span>
                        {{ formation.project.name }}
                    </span>
                </div>
            </div>
            <div class="flex items-end gap-md">
                <div class="flex flex-col gap-xs">
                    <label class="text-label-caps text-secondary uppercase tracking-wide">Date</label>
                    <input
                        v-model="form.date"
                        type="date"
                        class="date-input"
                    />
                </div>
                <Link
                    :href="`/formations/${formation.id}/attendances/pdf?date=${form.date}`"
                    class="btn-secondary"
                >
                    <span class="material-symbols-outlined" style="font-size:18px">picture_as_pdf</span>
                    Générer PDF
                </Link>
            </div>
        </div>

        <!-- Légende des codes -->
        <div class="flex flex-wrap items-center gap-lg bg-surface-container-lowest px-lg py-sm rounded border border-surface-container-highest text-body-sm text-secondary">
            <span class="font-semibold text-on-surface">Codes :</span>
            <span class="flex items-center gap-xs"><span class="dot dot-P"></span> P — Présent</span>
            <span class="flex items-center gap-xs"><span class="dot dot-AJ"></span> AJ — Absence justifiée</span>
            <span class="flex items-center gap-xs"><span class="dot dot-AN"></span> AN — Absence non justifiée</span>
            <span class="flex items-center gap-xs"><span class="dot dot-RJ"></span> RJ — Retard justifié</span>
            <span class="flex items-center gap-xs"><span class="dot dot-RN"></span> RN — Retard non justifié</span>
        </div>

        <!-- Grille de présences -->
        <form @submit.prevent="submit">
            <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container border-b border-surface-container-highest">
                            <th class="py-sm px-md text-label-caps text-secondary uppercase w-12 text-center">N°</th>
                            <th class="py-sm px-md text-label-caps text-secondary uppercase">Apprenant</th>
                            <th
                                v-for="code in codes"
                                :key="code.value"
                                class="py-sm px-md text-label-caps text-secondary uppercase text-center w-20"
                                :class="codeColors[code.value]"
                            >
                                {{ code.value }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest text-data-tabular text-on-surface">
                        <tr v-if="learners.length === 0">
                            <td :colspan="codes.length + 2" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun apprenant actif dans cette formation.
                            </td>
                        </tr>
                        <tr
                            v-for="(learner, idx) in learners"
                            :key="learner.id"
                            class="hover:bg-surface-container-low transition-colors"
                            :class="rowHighlight[form.records[learner.id]] ?? ''"
                        >
                            <td class="py-sm px-md text-secondary text-center">{{ String(idx + 1).padStart(2, '0') }}</td>
                            <td class="py-sm px-md font-medium">{{ learner.full_name }}</td>
                            <td
                                v-for="code in codes"
                                :key="code.value"
                                class="py-sm px-md text-center"
                            >
                                <input
                                    type="radio"
                                    :name="`records_${learner.id}`"
                                    :value="code.value"
                                    v-model="form.records[learner.id]"
                                    class="radio"
                                    :class="`radio-${code.value}`"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Résumé + boutons -->
            <div class="flex items-center justify-between bg-surface-container-lowest p-md rounded-xl border border-surface-container-highest mt-md">
                <div class="flex gap-lg">
                    <div class="stat-block">
                        <span class="stat-label">Présents</span>
                        <span class="stat-value stat-green">{{ stats.present }}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="stat-block">
                        <span class="stat-label">Absents</span>
                        <span class="stat-value stat-red">{{ stats.absent }}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="stat-block">
                        <span class="stat-label">Retards</span>
                        <span class="stat-value stat-orange">{{ stats.late }}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="stat-block">
                        <span class="stat-label">Total</span>
                        <span class="stat-value">{{ stats.total }}</span>
                    </div>
                </div>

                <div class="flex gap-md">
                    <button type="reset" class="btn-cancel" @click="form.reset()">
                        Annuler les modifications
                    </button>
                    <button type="submit" class="btn-save" :disabled="form.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">save</span>
                        Enregistrer les présences
                    </button>
                </div>
            </div>
        </form>

    </div>
</template>

<style scoped>
.date-input {
    padding: 8px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    cursor: pointer;
    outline: none;
}
.date-input:focus { border-color: #E5004C; }

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 14px;
    color: #515f74;
    background: #fff;
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

/* Légende dots */
.dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.dot-P  { background: #22c55e; }
.dot-AJ { background: #3b82f6; }
.dot-AN { background: #ef4444; }
.dot-RJ { background: #eab308; }
.dot-RN { background: #f97316; }

/* Couleurs des headers codes */
.code-P  { color: #15803d; }
.code-AJ { color: #1d4ed8; }
.code-AN { color: #b91c1c; }
.code-RJ { color: #a16207; }
.code-RN { color: #c2410c; }

/* Highlight des lignes */
.row-absent    { background: rgba(239,68,68,0.04); }
.row-late      { background: rgba(249,115,22,0.04); }
.row-justified { background: rgba(59,130,246,0.04); }

/* Radio buttons */
.radio { width: 16px; height: 16px; cursor: pointer; accent-color: #E5004C; }
.radio-P  { accent-color: #22c55e; }
.radio-AJ { accent-color: #3b82f6; }
.radio-AN { accent-color: #ef4444; }
.radio-RJ { accent-color: #eab308; }
.radio-RN { accent-color: #f97316; }

/* Stats */
.stat-block { display: flex; flex-direction: column; }
.stat-label { font-size: 11px; font-weight: 600; color: #515f74; text-transform: uppercase; letter-spacing: 0.05em; }
.stat-value { font-size: 24px; font-weight: 600; color: #191c1e; line-height: 32px; }
.stat-green  { color: #15803d; }
.stat-red    { color: #b91c1c; }
.stat-orange { color: #c2410c; }
.divider { width: 1px; background: #e0e3e5; margin: 0 8px; }

.btn-cancel {
    padding: 8px 20px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 14px;
    color: #515f74;
    background: #fff;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: #f2f4f6; }

.btn-save {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 24px;
    background: #E5004C;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    box-shadow: 0 4px 12px rgba(229,0,76,0.25);
}
.btn-save:hover:not(:disabled) { background: #c0003e; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
