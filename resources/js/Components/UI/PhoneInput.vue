<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import {
    detectCountry,
    flagUrl,
    formatInternational,
    nationalNumber,
    PHONE_COUNTRIES,
    type PhoneCountry,
} from '@/Support/phoneCountries'

const props = withDefaults(defineProps<{
    modelValue?: string | null
    id?: string
    placeholder?: string
    disabled?: boolean
    error?: boolean
}>(), {
    modelValue: '',
    placeholder: '70 00 00 00',
    disabled: false,
    error: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const country = ref<PhoneCountry>(detectCountry(props.modelValue))
const local = ref(nationalNumber(props.modelValue, country.value))
const open = ref(false)
const search = ref('')

watch(() => props.modelValue, (value) => {
    const nextCountry = detectCountry(value)
    const nextLocal = nationalNumber(value, nextCountry)
    if (nextCountry.iso !== country.value.iso) {
        country.value = nextCountry
    }
    if (nextLocal !== local.value) {
        local.value = nextLocal
    }
})

const filteredCountries = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return PHONE_COUNTRIES
    return PHONE_COUNTRIES.filter((c) =>
        c.name.toLowerCase().includes(q)
        || c.dial.includes(q)
        || c.iso.toLowerCase().includes(q),
    )
})

const sync = () => {
    emit('update:modelValue', formatInternational(country.value, local.value))
}

const selectCountry = (c: PhoneCountry) => {
    country.value = c
    open.value = false
    search.value = ''
    sync()
}

const onLocalInput = (e: Event) => {
    local.value = (e.target as HTMLInputElement).value.replace(/[^\d\s]/g, '')
    sync()
}

const closeOnOutside = (e: MouseEvent) => {
    const root = (e.target as HTMLElement)?.closest?.('.phone-input')
    if (!root) open.value = false
}

watch(open, (isOpen) => {
    if (isOpen) {
        setTimeout(() => document.addEventListener('click', closeOnOutside), 0)
    } else {
        document.removeEventListener('click', closeOnOutside)
    }
})

onUnmounted(() => {
    document.removeEventListener('click', closeOnOutside)
})
</script>

<template>
    <div class="phone-input" :class="{ 'phone-error': error, 'phone-disabled': disabled }">
        <button
            type="button"
            class="phone-flag"
            :disabled="disabled"
            :aria-expanded="open"
            :title="country.name"
            @click="open = !open"
        >
            <img
                class="phone-flag-img"
                :src="flagUrl(country.iso, 40)"
                :alt="country.name"
                width="22"
                height="16"
                loading="lazy"
                decoding="async"
            >
            <span class="phone-dial">+{{ country.dial }}</span>
            <span class="material-symbols-outlined phone-caret">expand_more</span>
        </button>

        <input
            :id="id"
            type="tel"
            class="phone-number"
            :value="local"
            :placeholder="placeholder"
            :disabled="disabled"
            inputmode="tel"
            autocomplete="tel-national"
            @input="onLocalInput"
        >

        <div v-if="open" class="phone-dropdown">
            <input
                v-model="search"
                type="search"
                class="phone-search"
                placeholder="Pays ou indicatif…"
                autofocus
            >
            <ul class="phone-list">
                <li v-for="c in filteredCountries" :key="`${c.iso}-${c.dial}`">
                    <button
                        type="button"
                        class="phone-option"
                        :class="{ active: c.iso === country.iso && c.dial === country.dial }"
                        @click="selectCountry(c)"
                    >
                        <img
                            class="phone-flag-img"
                            :src="flagUrl(c.iso, 40)"
                            :alt="c.name"
                            width="22"
                            height="16"
                            loading="lazy"
                            decoding="async"
                        >
                        <span class="phone-option-name">{{ c.name }}</span>
                        <span class="phone-option-dial">+{{ c.dial }}</span>
                    </button>
                </li>
                <li v-if="filteredCountries.length === 0" class="phone-empty">Aucun pays trouvé</li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.phone-input {
    position: relative;
    display: flex;
    align-items: stretch;
    width: 100%;
    border: 1.5px solid #e0e3e5;
    border-radius: 8px;
    background: #fff;
    transition: border-color .15s;
}
.phone-input:focus-within { border-color: #E5004C; }
.phone-error { border-color: #ba1a1a; }
.phone-disabled { opacity: .65; pointer-events: none; }

.phone-flag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 10px;
    border: 0;
    border-right: 1px solid #e8eef2;
    background: #f8fafc;
    border-radius: 8px 0 0 8px;
    cursor: pointer;
    color: #1F3A4D;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.phone-flag:hover { background: #f1f5f9; }
.phone-flag-img {
    width: 22px;
    height: 16px;
    object-fit: cover;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
    flex-shrink: 0;
    display: block;
}
.phone-dial { font-variant-numeric: tabular-nums; }
.phone-caret { font-size: 18px; color: #94a3b8; }

.phone-number {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: none;
    background: transparent;
    padding: 10px 12px;
    font-size: 14px;
    color: #191c1e;
}
.phone-number::placeholder { color: #9aaabb; }

.phone-dropdown {
    position: absolute;
    z-index: 40;
    top: calc(100% + 6px);
    left: 0;
    width: min(100%, 340px);
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(31, 58, 77, .12);
    overflow: hidden;
}
.phone-search {
    width: 100%;
    border: 0;
    border-bottom: 1px solid #e8eef2;
    padding: 10px 12px;
    font-size: 13px;
    outline: none;
}
.phone-list {
    list-style: none;
    margin: 0;
    padding: 4px;
    max-height: 240px;
    overflow-y: auto;
}
.phone-option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font-size: 13px;
    color: #1F3A4D;
}
.phone-option:hover,
.phone-option.active { background: #fff0f4; }
.phone-option-name { flex: 1; min-width: 0; }
.phone-option-dial { color: #64748b; font-variant-numeric: tabular-nums; }
.phone-empty {
    padding: 14px;
    text-align: center;
    color: #9aaabb;
    font-size: 13px;
}
</style>
