<script setup lang="ts">
interface Option {
    id: string
    name: string
}

defineProps<{ options: Option[] }>()

const selected = defineModel<string[]>({ required: true })

function toggle(id: string) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter(v => v !== id)
        : [...selected.value, id]
}
</script>

<template>
    <div class="chips" role="group">
        <button
            v-for="option in options"
            :key="option.id"
            type="button"
            class="chip"
            :class="{ 'chip-on': selected.includes(option.id) }"
            :aria-pressed="selected.includes(option.id)"
            @click="toggle(option.id)"
        >
            <span class="material-symbols-outlined chip-icon">{{ selected.includes(option.id) ? 'check_circle' : 'add_circle' }}</span>
            {{ option.name }}
        </button>
    </div>
</template>

<style scoped>
.chips { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px 7px 10px;
    border: 1.5px solid #d9dee3; border-radius: 99px; background: #fff;
    font-size: 13px; font-weight: 600; color: #515f74; cursor: pointer; transition: all 0.12s;
}
.chip:hover { border-color: #E5004C; color: #E5004C; }
.chip-on { background: #E5004C; border-color: #E5004C; color: #fff; }
.chip-on:hover { color: #fff; background: #c0003e; }
.chip-icon { font-size: 18px; }
</style>
