<script setup lang="ts">
import { computed } from 'vue'

export interface PlanDraft {
    type: 'percentage' | 'amount'
    value: number | ''
    due_date: string
}

const props = defineProps<{
    modelValue: PlanDraft[]
    totalCost: number
}>()

const emit = defineEmits<{
    'update:modelValue': [PlanDraft[]]
}>()

const fmt = (n: number) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

const sync = (next: PlanDraft[]) => emit('update:modelValue', next)

const calcAmount = (d: PlanDraft): number => {
    const v = Number(d.value) || 0
    if (d.type === 'percentage') {
        return props.totalCost > 0 ? Math.round((v / 100) * props.totalCost) : 0
    }
    return v
}

const totalPlanned = computed(() => props.modelValue.reduce((s, d) => s + calcAmount(d), 0))
const remaining = computed(() => props.totalCost - totalPlanned.value)
const totalPct = computed(() =>
    props.totalCost > 0 ? Math.round((totalPlanned.value / props.totalCost) * 100) : 0
)

const addRow = () => {
    sync([...props.modelValue, { type: 'percentage', value: '', due_date: '' }])
}

const removeRow = (i: number) => {
    const next = [...props.modelValue]
    next.splice(i, 1)
    sync(next)
}

const updateRow = (i: number, patch: Partial<PlanDraft>) => {
    const next = props.modelValue.map((row, idx) => idx === i ? { ...row, ...patch } : row)
    sync(next)
}
</script>

<template>
    <div class="plan-box">
        <div class="plan-head">
            <div>
                <p class="plan-title">Plan de paiement</p>
                <p class="plan-sub">
                    Définissez les tranches (% ou montant) avec une date au calendrier.
                    Appliqué automatiquement à l’inscription d’un apprenant.
                </p>
            </div>
            <button type="button" class="btn-add" @click="addRow">
                <span class="material-symbols-outlined" style="font-size:16px">add</span>
                Ajouter une tranche
            </button>
        </div>

        <p v-if="modelValue.length === 0" class="empty-plan">
            Aucune tranche — les échéanciers pourront toujours être définis manuellement sur la cohorte.
        </p>

        <template v-else>
            <div class="draft-head">
                <span>#</span>
                <span>Type</span>
                <span>Valeur</span>
                <span>Date prévue</span>
                <span></span>
            </div>

            <div v-for="(d, i) in modelValue" :key="i" class="draft-row">
                <span class="dnum">{{ i + 1 }}</span>
                <div class="type-toggle">
                    <button
                        type="button"
                        :class="['type-btn', d.type === 'percentage' ? 'type-active' : '']"
                        @click="updateRow(i, { type: 'percentage' })"
                    >%</button>
                    <button
                        type="button"
                        :class="['type-btn', d.type === 'amount' ? 'type-active' : '']"
                        @click="updateRow(i, { type: 'amount' })"
                    >FCFA</button>
                </div>
                <div class="value-wrap">
                    <input
                        :value="d.value"
                        type="number"
                        min="1"
                        :max="d.type === 'percentage' ? 100 : undefined"
                        class="input"
                        :placeholder="d.type === 'percentage' ? 'ex. 40' : 'ex. 50000'"
                        @input="updateRow(i, { value: ($event.target as HTMLInputElement).value === '' ? '' : Number(($event.target as HTMLInputElement).value) })"
                    />
                    <span v-if="d.type === 'percentage' && Number(d.value) > 0" class="equiv">
                        = {{ fmt(calcAmount(d)) }}
                    </span>
                </div>
                <input
                    :value="d.due_date"
                    type="date"
                    class="input"
                    required
                    @input="updateRow(i, { due_date: ($event.target as HTMLInputElement).value })"
                />
                <button
                    v-if="modelValue.length > 0"
                    type="button"
                    class="btn-remove"
                    title="Supprimer"
                    @click="removeRow(i)"
                >
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
            </div>

            <div class="summary">
                <div class="sum-row">
                    <span>Total planifié</span>
                    <strong>{{ fmt(totalPlanned) }} ({{ totalPct }}%)</strong>
                </div>
                <div v-if="remaining < 0" class="sum-row ko">
                    Dépassement de {{ fmt(Math.abs(remaining)) }}
                </div>
                <div v-else-if="remaining > 0" class="sum-row info">
                    {{ fmt(remaining) }} restant à planifier
                </div>
                <div v-else class="sum-row ok">
                    Coût entièrement couvert
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.plan-box {
    border: 1px solid #e0e3e5; border-radius: 12px; padding: 16px;
    background: #fafbfc; display: flex; flex-direction: column; gap: 12px;
}
.plan-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.plan-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.plan-sub { font-size: 12px; color: #515f74; margin-top: 4px; max-width: 420px; line-height: 1.4; }
.empty-plan { font-size: 13px; color: #9aaabb; font-style: italic; }
.btn-add {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 7px 12px; border-radius: 8px; border: 1.5px dashed #c7d4df;
    background: #fff; color: #1F3A4D; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: inherit;
}
.btn-add:hover { border-color: #E5004C; color: #E5004C; }
.draft-head, .draft-row {
    display: grid; grid-template-columns: 28px 110px 1fr 150px 32px;
    gap: 8px; align-items: center;
}
.draft-head {
    font-size: 11px; font-weight: 600; color: #9aaabb;
    text-transform: uppercase; letter-spacing: 0.04em; padding: 0 2px;
}
.dnum {
    width: 24px; height: 24px; border-radius: 6px; background: #e8edf2;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #1F3A4D;
}
.type-toggle { display: flex; border: 1px solid #e0e3e5; border-radius: 8px; overflow: hidden; }
.type-btn {
    flex: 1; padding: 7px 0; border: none; background: #fff; color: #515f74;
    font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit;
}
.type-active { background: #1F3A4D; color: #fff; }
.value-wrap { display: flex; flex-direction: column; gap: 2px; }
.equiv { font-size: 11px; color: #64748b; }
.input {
    width: 100%; padding: 8px 10px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-family: inherit; outline: none; background: #fff;
}
.input:focus { border-color: #E5004C; }
.btn-remove {
    width: 28px; height: 28px; border: none; border-radius: 6px;
    background: transparent; color: #9aaabb; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-remove:hover { background: #fde8e8; color: #ba1a1a; }
.summary {
    margin-top: 4px; padding: 10px 12px; border-radius: 8px;
    background: #fff; border: 1px solid #e0e3e5; font-size: 13px;
}
.sum-row { display: flex; justify-content: space-between; gap: 8px; color: #515f74; }
.sum-row.ok { color: #059669; font-weight: 600; margin-top: 4px; }
.sum-row.info { color: #b45309; margin-top: 4px; }
.sum-row.ko { color: #ba1a1a; font-weight: 600; margin-top: 4px; }
</style>
