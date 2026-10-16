<script setup lang="ts">
/** Line spacing, as a segmented control; each choice drawn with its own spacing. */
export interface LineSpacingOption { value: string; label: string }

defineProps<{ options: LineSpacingOption[] }>()
const model = defineModel<string>({ required: true })
</script>

<template>
    <div class="spacing" role="radiogroup" aria-label="Interligne">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            class="choice"
            :class="{ on: model === option.value }"
            :aria-checked="model === option.value"
            @click="model = option.value"
        >
            <span class="lines" :style="{ gap: `${(Number(option.value) - 1) * 8 + 2}px` }"><i /><i /><i /></span>
            {{ option.label }}
        </button>
    </div>
</template>

<style scoped>
.spacing { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #f2f4f6; border-radius: 10px; }
.choice { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border: none; border-radius: 7px; background: transparent; color: #515f74; font-size: 13px; font-weight: 600; cursor: pointer; }
.choice:hover:not(.on) { background: #e8eaec; color: #191c1e; }
.choice.on { background: #fff; color: #191c1e; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1); }
.lines { display: inline-flex; flex-direction: column; width: 16px; }
.lines i { display: block; height: 2px; border-radius: 1px; background: currentColor; }
.choice.on .lines i { background: #E5004C; }
</style>
