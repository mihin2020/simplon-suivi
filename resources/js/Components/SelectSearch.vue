<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'

interface Option {
    value: string
    label: string
    [key: string]: any
}

const props = defineProps<{
    modelValue: string
    options: Option[]
    placeholder?: string
    label?: string
    clearable?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()

const isOpen = ref(false)
const searchQuery = ref('')
const selectRef = ref<HTMLDivElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)

const selectedLabel = computed(() => {
    const option = props.options.find(o => o.value === props.modelValue)
    return option?.label || props.placeholder || 'Sélectionner...'
})

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options
    const query = searchQuery.value.toLowerCase()
    return props.options.filter(o =>
        o.label.toLowerCase().includes(query)
    )
})

function toggle() {
    isOpen.value = !isOpen.value
    if (isOpen.value) {
        nextTick(() => {
            searchInputRef.value?.focus()
        })
    }
}

function select(option: Option) {
    emit('update:modelValue', option.value)
    isOpen.value = false
    searchQuery.value = ''
}

function clear() {
    emit('update:modelValue', '')
    searchQuery.value = ''
}

function onBlur(e: FocusEvent) {
    // Delay to allow click on option
    setTimeout(() => {
        if (!selectRef.value?.contains(document.activeElement)) {
            isOpen.value = false
            searchQuery.value = ''
        }
    }, 150)
}

// Close on click outside
watch(isOpen, (open) => {
    if (open) {
        const handler = (e: MouseEvent) => {
            if (!selectRef.value?.contains(e.target as Node)) {
                isOpen.value = false
                searchQuery.value = ''
                document.removeEventListener('click', handler)
            }
        }
        setTimeout(() => document.addEventListener('click', handler), 0)
    }
})
</script>

<template>
    <div ref="selectRef" class="select-search" @blur="onBlur">
        <label v-if="label" class="select-label">{{ label }}</label>

        <div class="select-trigger" @click.stop="toggle" :class="{ 'is-open': isOpen }">
            <span class="selected-text" :class="{ 'placeholder': !modelValue }">
                {{ selectedLabel }}
            </span>
            <div class="select-actions">
                <button
                    v-if="clearable && modelValue"
                    type="button"
                    class="clear-btn"
                    @click.stop="clear"
                >
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
                <span class="material-symbols-outlined dropdown-icon" :class="{ 'rotate': isOpen }">
                    expand_more
                </span>
            </div>
        </div>

        <Transition name="dropdown">
            <div v-if="isOpen" class="dropdown-panel">
                <div class="search-box">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        class="search-input"
                        placeholder="Rechercher..."
                        @click.stop
                    >
                </div>

                <div class="options-list">
                    <div
                        v-if="filteredOptions.length === 0"
                        class="no-results"
                    >
                        Aucun résultat
                    </div>

                    <button
                        v-for="option in filteredOptions"
                        :key="option.value"
                        type="button"
                        class="option-item"
                        :class="{ 'selected': option.value === modelValue }"
                        @click.stop="select(option)"
                    >
                        <span class="option-label">{{ option.label }}</span>
                        <span
                            v-if="option.value === modelValue"
                            class="material-symbols-outlined check-icon"
                        >
                            check
                        </span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.select-search {
    position: relative;
    width: 100%;
}

.select-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #515f74;
    margin-bottom: 6px;
}

.select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    background: #fafafa;
    cursor: pointer;
    transition: all 0.15s;
    min-height: 44px;
}

.select-trigger:hover {
    border-color: #cbd5e1;
    background: #fff;
}

.select-trigger.is-open {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}

.selected-text {
    font-size: 14px;
    color: #191c1e;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-text.placeholder {
    color: #9aaabb;
}

.select-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.clear-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: #9aaabb;
    cursor: pointer;
    transition: all 0.15s;
}

.clear-btn:hover {
    background: #f2f4f6;
    color: #515f74;
}

.dropdown-icon {
    font-size: 20px;
    color: #515f74;
    transition: transform 0.2s;
}

.dropdown-icon.rotate {
    transform: rotate(180deg);
}

.dropdown-panel {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    overflow: hidden;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f0;
}

.search-icon {
    font-size: 18px;
    color: #9aaabb;
    flex-shrink: 0;
}

.search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #191c1e;
    background: transparent;
    padding: 0;
}

.search-input::placeholder {
    color: #9aaabb;
}

.options-list {
    max-height: 240px;
    overflow-y: auto;
    padding: 4px;
}

.option-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: all 0.12s;
}

.option-item:hover {
    background: #fff5f8;
}

.option-item.selected {
    background: #fff0f4;
    color: #E5004C;
}

.option-label {
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.check-icon {
    font-size: 18px;
    color: #E5004C;
    flex-shrink: 0;
}

.no-results {
    padding: 16px;
    text-align: center;
    font-size: 13px;
    color: #9aaabb;
}

/* Animations */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.15s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
