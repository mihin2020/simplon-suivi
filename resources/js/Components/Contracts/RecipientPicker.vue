<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'

/**
 * Picks one recipient (single) or several (multiple) among possibly hundreds
 * of people: search first, a filter per group, and only a short list of
 * results at a time - never the whole directory at once.
 */
export interface RecipientOption { value: string; label: string; group?: string }

const props = withDefaults(defineProps<{
    options: RecipientOption[]
    multiple?: boolean
    /** Results shown at once; refine the search to see others. */
    limit?: number
    placeholder?: string
}>(), { multiple: false, limit: 8, placeholder: 'Rechercher par nom…' })

const single = defineModel<string>('modelValue', { default: '' })
const many = defineModel<string[]>('selected', { default: () => [] })

const search = ref('')
const group = ref<string | null>(null)
const highlighted = ref(0)
const editing = ref(!single.value)
const input = ref<HTMLInputElement | null>(null)

// Accent- and case-insensitive: « Kabore » finds « Kaboré ».
const normalize = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

const groups = computed(() => {
    const counts = new Map<string, number>()
    for (const option of props.options) {
        if (option.group) counts.set(option.group, (counts.get(option.group) ?? 0) + 1)
    }

    return [...counts.entries()].map(([name, count]) => ({ name, count }))
})

const matches = computed(() => {
    const terms = normalize(search.value.trim()).split(/\s+/).filter(Boolean)

    return props.options.filter(option =>
        (!group.value || option.group === group.value)
        && terms.every(term => normalize(option.label).includes(term)))
})
const shown = computed(() => matches.value.slice(0, props.limit))
const hidden = computed(() => matches.value.length - shown.value.length)

watch([search, group], () => { highlighted.value = 0 })

const selectedOption = computed(() => props.options.find(o => o.value === single.value) ?? null)
const isChecked = (value: string) => many.value.includes(value)

const choose = (option: RecipientOption) => {
    if (props.multiple) {
        many.value = isChecked(option.value) ? many.value.filter(v => v !== option.value) : [...many.value, option.value]
        return
    }
    single.value = option.value
    editing.value = false
    search.value = ''
}

const change = async () => {
    editing.value = true
    await nextTick()
    input.value?.focus()
}
const clear = () => { single.value = ''; change() }

// "Tout cocher" acts on every match of the search, not only the rows shown.
const allMatchesChecked = computed(() => matches.value.length > 0 && matches.value.every(o => isChecked(o.value)))
const toggleAllMatches = () => {
    const values = matches.value.map(o => o.value)
    many.value = allMatchesChecked.value
        ? many.value.filter(v => !values.includes(v))
        : [...new Set([...many.value, ...values])]
}
const selectedOptions = computed(() => props.options.filter(o => many.value.includes(o.value)))

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); highlighted.value = Math.min(highlighted.value + 1, shown.value.length - 1) }
    else if (event.key === 'ArrowUp') { event.preventDefault(); highlighted.value = Math.max(highlighted.value - 1, 0) }
    else if (event.key === 'Enter') {
        event.preventDefault()
        const option = shown.value[highlighted.value]
        if (option) choose(option)
    }
}
</script>

<template>
    <div class="picker">
        <!-- Single: the chosen person, with a way to change -->
        <div v-if="!multiple && selectedOption && !editing" class="chosen">
            <span class="avatar">{{ selectedOption.label.slice(0, 1).toUpperCase() }}</span>
            <span class="chosen-text">
                <strong>{{ selectedOption.label }}</strong>
                <small v-if="selectedOption.group">{{ selectedOption.group }}</small>
            </span>
            <button type="button" class="link" @click="change">Changer</button>
            <button type="button" class="icon" title="Retirer le destinataire" @click="clear">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <template v-else>
            <!-- Multiple: what is already selected -->
            <div v-if="multiple && selectedOptions.length" class="selected-chips">
                <span v-for="option in selectedOptions" :key="option.value" class="chip">
                    {{ option.label }}
                    <button type="button" :title="`Retirer ${option.label}`" @click="choose(option)"><span class="material-symbols-outlined">close</span></button>
                </span>
            </div>

            <div class="search">
                <span class="material-symbols-outlined">search</span>
                <input ref="input" v-model="search" type="search" :placeholder="placeholder" autocomplete="off" @keydown="onKey" />
                <span class="total">{{ options.length }} au total</span>
            </div>

            <div v-if="groups.length > 1" class="groups">
                <button type="button" class="group" :class="{ on: group === null }" @click="group = null">Tous <span>{{ options.length }}</span></button>
                <button v-for="g in groups" :key="g.name" type="button" class="group" :class="{ on: group === g.name }" @click="group = g.name">
                    {{ g.name }} <span>{{ g.count }}</span>
                </button>
            </div>

            <div class="results" role="listbox" :aria-multiselectable="multiple">
                <button
                    v-for="(option, index) in shown"
                    :key="option.value"
                    type="button"
                    role="option"
                    class="result"
                    :class="{ highlighted: index === highlighted, checked: multiple ? isChecked(option.value) : option.value === single }"
                    :aria-selected="multiple ? isChecked(option.value) : option.value === single"
                    @mouseenter="highlighted = index"
                    @click="choose(option)"
                >
                    <span v-if="multiple" class="check material-symbols-outlined">{{ isChecked(option.value) ? 'check_box' : 'check_box_outline_blank' }}</span>
                    <span class="avatar small">{{ option.label.slice(0, 1).toUpperCase() }}</span>
                    <span class="result-text">
                        <span>{{ option.label }}</span>
                        <small v-if="option.group && !group">{{ option.group }}</small>
                    </span>
                </button>
                <p v-if="matches.length === 0" class="empty">Aucune personne ne correspond.</p>
            </div>

            <div class="foot">
                <span v-if="hidden > 0" class="more">
                    + {{ hidden }} autre{{ hidden > 1 ? 's' : '' }} : précisez la recherche pour {{ hidden > 1 ? 'les' : 'la' }} trouver.
                </span>
                <button v-if="multiple && matches.length" type="button" class="link" @click="toggleAllMatches">
                    {{ allMatchesChecked ? 'Tout décocher' : `Tout cocher (${matches.length})` }}
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>
.picker { display: flex; flex-direction: column; gap: 10px; }
.chosen { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #E5004C; border-radius: 12px; background: #fff5f8; }
.chosen-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.chosen-text strong { font-size: 14px; color: #191c1e; }
.chosen-text small { font-size: 12px; color: #868e96; }
.avatar { width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; background: #1F3A4D; color: #fff; font-size: 14px; font-weight: 700; }
.avatar.small { width: 28px; height: 28px; font-size: 12px; background: #eef1f4; color: #1F3A4D; }
.search { display: flex; align-items: center; gap: 8px; padding: 0 12px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; }
.search:focus-within { border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229, 0, 76, 0.08); }
.search .material-symbols-outlined { font-size: 20px; color: #9aaabb; }
.search input { flex: 1; min-width: 0; padding: 11px 0; border: none; outline: none; background: transparent; font-size: 14px; }
.total { font-size: 12px; color: #9aaabb; white-space: nowrap; }
.groups { display: flex; gap: 6px; flex-wrap: wrap; }
.group { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #e0e3e5; border-radius: 99px; background: #fff; color: #515f74; font-size: 12.5px; font-weight: 600; cursor: pointer; }
.group span { padding: 0 6px; border-radius: 99px; background: #eef1f4; font-size: 11px; }
.group.on { border-color: #1F3A4D; background: #1F3A4D; color: #fff; }
.group.on span { background: rgba(255, 255, 255, 0.2); }
.results { display: flex; flex-direction: column; border: 1px solid #e8edf2; border-radius: 10px; overflow: hidden; }
.result { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: none; border-bottom: 1px solid #f5f6f8; background: #fff; text-align: left; cursor: pointer; }
.result:last-child { border-bottom: none; }
.result.highlighted { background: #f6f8fa; }
.result.checked { background: #fff5f8; }
.result-text { flex: 1; min-width: 0; display: flex; flex-direction: column; font-size: 14px; color: #374151; }
.result-text small { font-size: 11.5px; color: #9aaabb; }
.check { font-size: 20px; color: #E5004C; }
.empty { padding: 14px; text-align: center; font-size: 13px; color: #9aaabb; }
.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; min-height: 18px; }
.more { font-size: 12px; color: #868e96; }
.link { padding: 0; border: none; background: none; color: #E5004C; font-size: 13px; font-weight: 600; cursor: pointer; }
.icon { display: inline-flex; padding: 4px; border: none; border-radius: 50%; background: transparent; color: #868e96; cursor: pointer; }
.icon:hover { background: #ffe4ec; color: #E5004C; }
.selected-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 6px 4px 10px; border-radius: 99px; background: #fff5f8; border: 1px solid #f3c6d6; font-size: 12.5px; color: #1F3A4D; }
.chip button { display: inline-flex; padding: 0; border: none; background: none; color: #868e96; cursor: pointer; }
.chip .material-symbols-outlined { font-size: 15px; }
</style>
