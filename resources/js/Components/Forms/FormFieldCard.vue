<script setup lang="ts">
import { computed } from 'vue'

export interface FieldOption {
    label: string
    value: string
}

export interface BuilderField {
    id?: string
    type: string
    label: string
    help_text?: string | null
    is_required: boolean
    learner_attribute?: string | null
    options?: FieldOption[] | null
    settings?: Record<string, unknown> | null
    _key: string
}

const props = defineProps<{
    field: BuilderField
    fieldTypes: Array<{ value: string; label: string; icon: string; requires_options: boolean }>
    locked?: boolean
    index: number
}>()

const emit = defineEmits<{
    remove: []
    duplicate: []
}>()

const typeMeta = computed(() =>
    props.fieldTypes.find(t => t.value === props.field.type)
)

const needsOptions = computed(() =>
    ['single_choice', 'multiple_choice', 'dropdown'].includes(props.field.type)
)

const isLayout = computed(() =>
    ['section_header', 'paragraph'].includes(props.field.type)
)

const selectableTypes = computed(() => {
    if (props.field.learner_attribute) return []
    if (isLayout.value) {
        return props.fieldTypes.filter(t => ['section_header', 'paragraph'].includes(t.value))
    }
    return props.fieldTypes.filter(t => !['section_header', 'paragraph'].includes(t.value))
})

const onTypeChange = () => {
    if (needsOptions.value && (!props.field.options || props.field.options.length === 0)) {
        props.field.options = [
            { label: 'Option 1', value: 'option_1' },
            { label: 'Option 2', value: 'option_2' },
        ]
    }
    if (props.field.type === 'linear_scale') {
        props.field.settings = {
            min: 1,
            max: 5,
            min_label: '',
            max_label: '',
            ...(props.field.settings ?? {}),
        }
    }
    if (props.field.type === 'file') {
        props.field.settings = {
            accept: '.pdf,.jpg,.jpeg,.png,.doc,.docx',
            max_size_kb: 5120,
            ...(props.field.settings ?? {}),
        }
    }
}

const addOption = () => {
    if (!props.field.options) props.field.options = []
    const n = props.field.options.length + 1
    props.field.options.push({ label: `Option ${n}`, value: `option_${n}` })
}

const removeOption = (oi: number) => {
    props.field.options?.splice(oi, 1)
}

const scaleMin = computed({
    get: () => Number(props.field.settings?.min ?? 1),
    set: (v: number) => {
        props.field.settings = { ...(props.field.settings ?? {}), min: v }
    },
})

const scaleMax = computed({
    get: () => Number(props.field.settings?.max ?? 5),
    set: (v: number) => {
        props.field.settings = { ...(props.field.settings ?? {}), max: v }
    },
})
</script>

<template>
    <div class="field-card" :class="{ layout: isLayout }">
        <div class="field-card__toolbar">
            <span class="drag-handle material-symbols-outlined" title="Glisser pour réordonner">drag_indicator</span>
            <span class="field-index">{{ index + 1 }}</span>
            <span
                v-if="field.learner_attribute"
                class="learner-badge"
            >Apprenant</span>
            <span
                v-if="field.type === 'file'"
                class="file-badge"
            >Fichier</span>
            <span class="type-chip">
                <span class="material-symbols-outlined text-[14px]">{{ typeMeta?.icon ?? 'help' }}</span>
                {{ typeMeta?.label ?? field.type }}
            </span>
            <div class="spacer" />
            <button type="button" class="icon-btn" :disabled="locked" title="Dupliquer la question" @click="emit('duplicate')">
                <span class="material-symbols-outlined text-[18px]">content_copy</span>
            </button>
            <button type="button" class="icon-btn danger" :disabled="locked" title="Supprimer" @click="emit('remove')">
                <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
        </div>

        <div class="field-card__body">
            <div class="type-row">
                <div class="grow">
                    <label class="filter-label">{{ isLayout ? 'Titre de la section' : 'Question' }}</label>
                    <input
                        v-model="field.label"
                        type="text"
                        class="filter-input w-full"
                        :disabled="locked"
                        :placeholder="isLayout ? 'Ex. Section identification' : 'Votre question…'"
                    />
                </div>
                <div v-if="!field.learner_attribute" class="type-select-wrap">
                    <label class="filter-label">Type de champ</label>
                    <select
                        v-model="field.type"
                        class="filter-select"
                        :disabled="locked"
                        @change="onTypeChange"
                    >
                        <option v-for="t in selectableTypes" :key="t.value" :value="t.value">
                            {{ t.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="!isLayout">
                <label class="filter-label">Texte d'aide</label>
                <input v-model="field.help_text" type="text" class="filter-input w-full" :disabled="locked" />
            </div>

            <label v-if="!isLayout" class="required-row">
                <input v-model="field.is_required" type="checkbox" :disabled="locked" />
                Obligatoire
            </label>

            <div v-if="needsOptions" class="options-block">
                <div v-for="(opt, oi) in field.options || []" :key="oi" class="flex gap-sm items-center">
                    <span class="material-symbols-outlined text-secondary text-[18px]">
                        {{ field.type === 'multiple_choice' ? 'check_box_outline_blank' : field.type === 'dropdown' ? 'arrow_right' : 'radio_button_unchecked' }}
                    </span>
                    <input v-model="opt.label" type="text" class="filter-input flex-1" :disabled="locked" placeholder="Libellé" />
                    <input v-model="opt.value" type="text" class="filter-input w-36" :disabled="locked" placeholder="Valeur" />
                    <button type="button" class="icon-btn danger" :disabled="locked" @click="removeOption(oi)">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                </div>
                <button type="button" class="btn-ghost text-sm" :disabled="locked" @click="addOption">+ Ajouter une option</button>
            </div>

            <div v-if="field.type === 'file'" class="file-preview-zone">
                <span class="material-symbols-outlined file-preview-icon">upload_file</span>
                <div>
                    <p class="file-preview-title">Zone d’upload fichier</p>
                    <p class="file-preview-hint">
                        Le candidat pourra choisir un fichier (PDF, JPG, PNG, Word) — max {{ field.settings?.max_size_kb ?? 5120 }} Ko
                    </p>
                </div>
            </div>

            <div v-if="field.type === 'linear_scale'" class="scale-block">
                <div class="flex gap-md items-end flex-wrap">
                    <div>
                        <label class="filter-label">Min</label>
                        <input v-model.number="scaleMin" type="number" min="0" max="10" class="filter-input w-20" :disabled="locked" />
                    </div>
                    <div>
                        <label class="filter-label">Max</label>
                        <input v-model.number="scaleMax" type="number" min="1" max="10" class="filter-input w-20" :disabled="locked" />
                    </div>
                    <div class="flex-1 min-w-[140px]">
                        <label class="filter-label">Libellé min</label>
                        <input v-model="(field.settings as any).min_label" type="text" class="filter-input w-full" :disabled="locked" />
                    </div>
                    <div class="flex-1 min-w-[140px]">
                        <label class="filter-label">Libellé max</label>
                        <input v-model="(field.settings as any).max_label" type="text" class="filter-input w-full" :disabled="locked" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.field-card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    overflow: hidden;
    border-left: 4px solid #E5004C;
}
.field-card.layout {
    border-left-color: #1F3A4D;
    background: #fafbfc;
}
.field-card__toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: #f7f9fb;
    border-bottom: 1px solid #eef0f2;
}
.drag-handle {
    cursor: grab;
    color: #adb5bd;
    font-size: 20px;
}
.field-index {
    font-size: 11px;
    color: #515f74;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.learner-badge {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(229, 0, 76, 0.1);
    color: #E5004C;
    font-weight: 700;
}
.file-badge {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e8f1ff;
    color: #1d4ed8;
    font-weight: 700;
}
.file-preview-zone {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 2px dashed #d0d8e0;
    border-radius: 10px;
    background: #f7f9fb;
}
.file-preview-icon { font-size: 28px; color: #E5004C; }
.file-preview-title { font-size: 13px; font-weight: 600; color: #191c1e; }
.file-preview-hint { font-size: 12px; color: #515f74; margin-top: 2px; }
.type-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #515f74;
    font-weight: 500;
}
.spacer { flex: 1; }
.icon-btn {
    border: none;
    background: transparent;
    padding: 4px;
    border-radius: 6px;
    color: #515f74;
    cursor: pointer;
    display: inline-flex;
}
.icon-btn:hover:not(:disabled) { background: #fff5f8; color: #E5004C; }
.icon-btn.danger:hover:not(:disabled) { color: #ba1a1a; background: #fff5f5; }
.icon-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.field-card__body { padding: 14px 16px 16px; display: flex; flex-direction: column; gap: 12px; }
.type-row {
    display: grid;
    grid-template-columns: 1fr minmax(180px, 220px);
    gap: 12px;
    align-items: end;
}
@media (max-width: 720px) {
    .type-row { grid-template-columns: 1fr; }
}
.type-select-wrap { min-width: 0; }
.required-row {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #191c1e;
}
.filter-label {
    font-size: 12px; font-weight: 700; color: #191c1e;
    letter-spacing: 0.04em; text-transform: uppercase; display: block; margin-bottom: 6px;
}
.filter-input, .filter-select {
    width: 100%; padding: 10px 12px; border: 1.5px solid #e0e3e5; border-radius: 10px;
    font-size: 14px; color: #191c1e; background: #fafbfc; outline: none; font-family: inherit;
}
.filter-input:focus, .filter-select:focus {
    border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); background: #fff;
}
.filter-input:disabled, .filter-select:disabled { opacity: 0.6; }
.options-block, .scale-block {
    margin-top: 4px;
    padding: 12px 14px;
    border: 1px solid #eef0f2;
    border-radius: 12px;
    background: #fafbfc;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.btn-ghost {
    border: none; background: transparent; color: #E5004C;
    font-size: 13px; font-weight: 600; cursor: pointer; padding: 4px 0;
    font-family: inherit; text-align: left;
}
.btn-ghost:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
