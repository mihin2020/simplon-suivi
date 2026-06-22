<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
    label: string
    modelValue: File | null
    existingUrl?: string | null
    existingName?: string | null
    showExisting?: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [File | null]
    'remove-existing': []
}>()

const isDragging = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)

const hasNewFile = computed(() => props.modelValue !== null)
const hasExisting = computed(() => !!(props.showExisting && props.existingUrl && !hasNewFile.value))

const onChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    emit('update:modelValue', file)
}

const onDrop = (e: DragEvent) => {
    isDragging.value = false
    const file = e.dataTransfer?.files?.[0] ?? null
    if (file) emit('update:modelValue', file)
}

const clearNewFile = () => {
    emit('update:modelValue', null)
    if (inputRef.value) inputRef.value.value = ''
}

const removeExisting = () => emit('remove-existing')
</script>

<template>
    <div class="contract-upload">
        <label class="upload-label">{{ label }}</label>

        <!-- Fichier existant -->
        <div v-if="hasExisting" class="existing-file">
            <a :href="existingUrl!" target="_blank" class="existing-link">
                <span class="material-symbols-outlined">description</span>
                <span class="existing-name">{{ existingName || 'Document actuel' }}</span>
            </a>
            <button type="button" class="existing-remove" @click="removeExisting">Retirer</button>
        </div>

        <!-- Fichier sélectionné -->
        <div v-else-if="hasNewFile" class="selected-file">
            <div class="selected-left">
                <span class="material-symbols-outlined selected-icon">draft</span>
                <div>
                    <p class="selected-name">{{ modelValue!.name }}</p>
                    <p class="selected-hint">Prêt à enregistrer</p>
                </div>
            </div>
            <button type="button" class="selected-clear" @click="clearNewFile" title="Retirer">
                <span class="material-symbols-outlined" style="font-size:18px">close</span>
            </button>
        </div>

        <!-- Zone de dépôt -->
        <label
            v-else
            class="drop-zone"
            :class="{ dragging: isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="inputRef"
                type="file"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                class="sr-only"
                @change="onChange"
            />
            <span class="material-symbols-outlined drop-icon">upload_file</span>
            <p class="drop-title">Déposer le contrat ici</p>
            <p class="drop-or">ou</p>
            <span class="drop-browse">Parcourir</span>
            <p class="drop-hint">PDF, Word, image · Max 5 Mo</p>
        </label>
    </div>
</template>

<style scoped>
.contract-upload {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.upload-label {
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
}
.drop-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 148px;
    padding: 16px;
    border: 2px dashed #d8dde3;
    border-radius: 12px;
    background: #fafbfc;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    text-align: center;
}
.drop-zone:hover,
.drop-zone.dragging {
    border-color: #E5004C;
    background: #fff5f8;
}
.drop-icon {
    font-size: 32px;
    color: #E5004C;
    margin-bottom: 4px;
}
.drop-title {
    font-size: 13px;
    font-weight: 600;
    color: #191c1e;
    margin: 0;
}
.drop-or {
    font-size: 11px;
    color: #adb5bd;
    margin: 0;
}
.drop-browse {
    display: inline-block;
    padding: 5px 14px;
    background: #E5004C;
    color: #fff;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.drop-hint {
    font-size: 10px;
    color: #adb5bd;
    margin: 4px 0 0;
}
.existing-file,
.selected-file {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 148px;
    padding: 14px;
    border: 1.5px solid #e8edf2;
    border-radius: 12px;
    background: #f8fafc;
}
.existing-link,
.selected-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    text-decoration: none;
    color: #1F3A4D;
}
.existing-link:hover { color: #E5004C; }
.existing-name,
.selected-name {
    font-size: 13px;
    font-weight: 600;
    word-break: break-all;
    margin: 0;
}
.selected-icon { font-size: 28px; color: #E5004C; flex-shrink: 0; }
.selected-hint { font-size: 11px; color: #9aaabb; margin: 2px 0 0; }
.existing-remove,
.selected-clear {
    border: none;
    background: #fff;
    color: #515f74;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
}
.existing-remove:hover { background: #ffdad6; color: #ba1a1a; }
.selected-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
}
.selected-clear:hover { background: #ffdad6; color: #ba1a1a; }
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
