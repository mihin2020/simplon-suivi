<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
    modelValue: string[]
    placeholder?: string
    error?: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string[]]
}>()

const draft = ref('')

const add = (raw: string) => {
    const value = raw.trim()
    if (!value) return
    if (props.modelValue.some(tag => tag.toLowerCase() === value.toLowerCase())) {
        draft.value = ''
        return
    }
    emit('update:modelValue', [...props.modelValue, value])
    draft.value = ''
}

const remove = (index: number) => {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault()
        add(draft.value)
        return
    }
    if (event.key === 'Backspace' && draft.value === '' && props.modelValue.length) {
        remove(props.modelValue.length - 1)
    }
}
</script>

<template>
    <div>
        <div class="tag-box" :class="{ 'has-error': error }">
            <span v-for="(tag, index) in modelValue" :key="`${tag}-${index}`" class="tag">
                {{ tag }}
                <button type="button" class="tag-remove" title="Retirer" @click="remove(index)">
                    <span class="material-symbols-outlined" style="font-size:14px">close</span>
                </button>
            </span>
            <input
                v-model="draft"
                type="text"
                class="tag-input"
                :placeholder="modelValue.length ? 'Ajouter une ressource…' : (placeholder ?? 'Saisir une ressource puis Entrée')"
                @keydown="onKeydown"
                @blur="add(draft)"
            />
        </div>
        <p v-if="error" class="error-msg">{{ error }}</p>
    </div>
</template>

<style scoped>
.tag-box {
    display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    min-height: 42px; padding: 6px 8px;
    border: 1.5px solid #e0e3e5; border-radius: 8px; background: #fff;
}
.tag-box:focus-within { border-color: #1F3A4D; }
.tag-box.has-error { border-color: #E5004C; }
.tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px;
    background: #f0f4f8; border: 1px solid #d5dee6;
    border-radius: 99px; font-size: 12px; font-weight: 600; color: #1F3A4D;
}
.tag-remove {
    display: inline-flex; width: 16px; height: 16px; border: none;
    border-radius: 50%; background: #d5dee6; color: #1F3A4D;
    cursor: pointer; padding: 0; align-items: center; justify-content: center;
}
.tag-remove:hover { background: #1F3A4D; color: #fff; }
.tag-input {
    flex: 1; min-width: 160px; border: none; outline: none;
    font-size: 13px; color: #191c1e; background: transparent; padding: 4px;
}
.error-msg { margin-top: 4px; font-size: 12px; color: #E5004C; }
</style>
