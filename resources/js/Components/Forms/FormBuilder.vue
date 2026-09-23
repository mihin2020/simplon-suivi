<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Sortable from 'sortablejs'
import FormFieldCard, { type BuilderField } from '@/Components/Forms/FormFieldCard.vue'

const props = defineProps<{
    modelValue: BuilderField[]
    fieldTypes: Array<{ value: string; label: string; icon: string; requires_options: boolean }>
    learnerAttributes: Array<{ key: string; label: string; type: string; required?: boolean }>
    locked?: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [BuilderField[]]
}>()

const listEl = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null

const sync = (next: BuilderField[]) => emit('update:modelValue', next)

const getScrollParent = (): HTMLElement | Document => {
    const main = document.querySelector('main.flex-1.overflow-y-auto') as HTMLElement | null
    return main ?? document
}

const initSortable = () => {
    sortable?.destroy()
    sortable = null
    if (!listEl.value || props.locked) return

    sortable = Sortable.create(listEl.value, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        forceFallback: true,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        scroll: getScrollParent(),
        forceAutoScrollFallback: true,
        bubbleScroll: true,
        scrollSensitivity: 100,
        scrollSpeed: 25,
        onEnd: (evt) => {
            if (evt.oldIndex == null || evt.newIndex == null || evt.oldIndex === evt.newIndex) return
            const copy = [...props.modelValue]
            const [moved] = copy.splice(evt.oldIndex, 1)
            copy.splice(evt.newIndex, 0, moved)
            sync(copy)
        },
    })
}

onMounted(() => initSortable())

watch(listEl, () => initSortable())
watch(() => props.locked, () => initSortable())
watch(() => props.modelValue.length, () => {
    // Keep Sortable in sync after add/remove
    requestAnimationFrame(() => initSortable())
})

onBeforeUnmount(() => {
    sortable?.destroy()
    sortable = null
})

const addCustom = (type = 'short_text') => {
    const meta = props.fieldTypes.find(t => t.value === type)
    const field: BuilderField = {
        type,
        label: type === 'file' ? 'Joindre un fichier' : (meta?.label ?? 'Nouvelle question'),
        help_text: type === 'file' ? 'PDF, image ou Word — max 5 Mo' : '',
        is_required: false,
        learner_attribute: null,
        options: meta?.requires_options
            ? [{ label: 'Option 1', value: 'option_1' }, { label: 'Option 2', value: 'option_2' }]
            : null,
        settings: type === 'linear_scale'
            ? { min: 1, max: 5, min_label: '', max_label: '' }
            : type === 'file'
                ? { accept: '.pdf,.jpg,.jpeg,.png,.doc,.docx', max_size_kb: 5120 }
                : null,
        _key: `new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    }
    sync([...props.modelValue, field])
}

const addLearnerField = (attr: { key: string; label: string; type: string; required?: boolean }) => {
    if (props.modelValue.some(f => f.learner_attribute === attr.key)) return
    sync([...props.modelValue, {
        type: attr.type,
        label: attr.label,
        help_text: attr.key === 'photo_path' ? 'JPG, PNG ou WebP — max 2 Mo' : '',
        is_required: attr.required ?? ['first_name', 'last_name'].includes(attr.key),
        learner_attribute: attr.key,
        options: attr.key === 'gender'
            ? [{ label: 'Homme', value: 'male' }, { label: 'Femme', value: 'female' }]
            : null,
        settings: attr.key === 'photo_path' || attr.type === 'file'
            ? {
                accept: attr.key === 'photo_path'
                    ? '.jpg,.jpeg,.png,.webp'
                    : '.pdf,.jpg,.jpeg,.png,.doc,.docx',
                max_size_kb: attr.key === 'photo_path' ? 2048 : 5120,
            }
            : null,
        _key: `learner-${attr.key}-${Date.now()}`,
    }])
}

const addSection = () => {
    const field: BuilderField = {
        type: 'section_header',
        label: 'Nouvelle section',
        help_text: '',
        is_required: false,
        learner_attribute: null,
        options: null,
        settings: null,
        _key: `section-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    }
    sync([...props.modelValue, field])
}

const removeField = (index: number) => {
    const copy = [...props.modelValue]
    copy.splice(index, 1)
    sync(copy)
}

const duplicateField = (index: number) => {
    const source = props.modelValue[index]
    const copy: BuilderField = {
        ...JSON.parse(JSON.stringify(source)),
        id: undefined,
        learner_attribute: null,
        _key: `dup-${Date.now()}`,
    }
    const next = [...props.modelValue]
    next.splice(index + 1, 0, copy)
    sync(next)
}

defineExpose({
    addCustom,
    addSection,
})
</script>

<template>
    <div class="builder-layout">
        <aside class="palette-side">
            <div class="palette-sticky">
                <p class="palette-title">Champs apprenant</p>
                <div class="chips">
                    <button
                        v-for="attr in learnerAttributes"
                        :key="attr.key"
                        type="button"
                        class="chip"
                        :class="{ active: modelValue.some(f => f.learner_attribute === attr.key) }"
                        :disabled="locked || modelValue.some(f => f.learner_attribute === attr.key)"
                        @click="addLearnerField(attr)"
                    >
                        {{ attr.label }}
                    </button>
                </div>

                <p class="palette-title mt">Types de questions</p>
                <div class="type-list">
                    <button
                        v-for="t in fieldTypes"
                        :key="t.value"
                        type="button"
                        class="type-btn"
                        :disabled="locked"
                        @click="addCustom(t.value)"
                    >
                        <span class="material-symbols-outlined" style="font-size:18px">{{ t.icon }}</span>
                        <span>{{ t.label }}</span>
                    </button>
                </div>
            </div>
        </aside>

        <div class="fields-main">
            <div ref="listEl" class="fields-list">
                <FormFieldCard
                    v-for="(field, index) in modelValue"
                    :key="field._key"
                    :field="field"
                    :index="index"
                    :field-types="fieldTypes"
                    :locked="locked"
                    @remove="removeField(index)"
                    @duplicate="duplicateField(index)"
                />
            </div>
            <p v-if="modelValue.length === 0" class="empty">
                Ajoutez une question ou une section pour commencer.
            </p>

            <div class="quick-add">
                <button type="button" class="quick-btn" :disabled="locked" @click="addCustom('short_text')">
                    <span class="material-symbols-outlined">add</span>
                    Question
                </button>
                <button type="button" class="quick-btn section" :disabled="locked" @click="addSection">
                    <span class="material-symbols-outlined">view_agenda</span>
                    Nouvelle section (page)
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.builder-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 16px;
    align-items: start;
}
@media (max-width: 900px) {
    .builder-layout { grid-template-columns: 1fr; }
    .palette-sticky { position: static !important; max-height: none !important; }
}
.palette-side { min-width: 0; }
.palette-sticky {
    position: sticky;
    top: 12px;
    max-height: calc(100vh - 140px);
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 14px;
    scrollbar-width: thin;
}
.palette-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: #1F3A4D; margin-bottom: 10px;
}
.palette-title.mt { margin-top: 16px; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
    font-size: 11px; padding: 4px 10px; border-radius: 99px;
    border: 1px solid #e0e3e5; background: #fff; cursor: pointer;
    font-family: inherit; color: #191c1e; font-weight: 500;
}
.chip.active, .chip:disabled {
    background: rgba(229,0,76,0.08); border-color: #E5004C; color: #E5004C; cursor: default;
}
.type-list { display: flex; flex-direction: column; gap: 6px; }
.type-btn {
    display: flex; align-items: center; gap: 8px; width: 100%;
    font-size: 13px; padding: 8px 10px; border-radius: 8px;
    border: 1px solid #eef0f2; background: #fafbfc; cursor: pointer;
    font-family: inherit; color: #191c1e; font-weight: 500; text-align: left;
}
.type-btn:hover:not(:disabled) {
    border-color: #E5004C; color: #E5004C; background: #fff5f8;
}
.type-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.fields-main { min-width: 0; display: flex; flex-direction: column; gap: 12px; }
.fields-list { display: flex; flex-direction: column; gap: 14px; }
.empty {
    text-align: center; color: #adb5bd; padding: 48px 20px;
    background: #fff; border: 1px dashed #e0e3e5; border-radius: 12px; font-size: 14px;
}
.quick-add {
    display: flex; flex-wrap: wrap; gap: 10px;
    padding: 12px; background: #fff; border: 1px dashed #d0d8e0; border-radius: 12px;
}
.quick-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e0e3e5;
    background: #fafbfc; color: #1F3A4D; font-size: 13px; font-weight: 700;
    cursor: pointer; font-family: inherit;
}
.quick-btn:hover:not(:disabled) { border-color: #E5004C; color: #E5004C; background: #fff5f8; }
.quick-btn.section { border-color: #1F3A4D33; }
.quick-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.quick-btn .material-symbols-outlined { font-size: 20px; }
:deep(.sortable-ghost) { opacity: 0.35; }
:deep(.sortable-chosen) { box-shadow: 0 8px 24px rgba(31,58,77,0.12); }
:deep(.sortable-drag) { opacity: 1; }
</style>
