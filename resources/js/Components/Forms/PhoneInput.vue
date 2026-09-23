<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

export interface PhoneCountry {
    code: string
    name: string
    dial: string
    flag: string
}

const props = withDefaults(defineProps<{
    modelValue?: string
    countries?: PhoneCountry[]
    required?: boolean
    disabled?: boolean
    id?: string
}>(), {
    modelValue: '',
    countries: () => [],
    required: false,
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [string]
}>()

const rootEl = ref<HTMLElement | null>(null)
const triggerEl = ref<HTMLElement | null>(null)
const dropdownEl = ref<HTMLElement | null>(null)
const open = ref(false)
const search = ref('')
const selectedCode = ref('BF')
const localNumber = ref('')
const dropdownStyle = ref<Record<string, string>>({})

const countries = computed(() =>
    props.countries.length
        ? props.countries
        : [{ code: 'BF', name: 'Burkina Faso', dial: '+226', flag: '🇧🇫' }]
)

const selected = computed(() =>
    countries.value.find(c => c.code === selectedCode.value) ?? countries.value[0]
)

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return countries.value
    return countries.value.filter(c =>
        c.name.toLowerCase().includes(q)
        || c.dial.includes(q)
        || c.code.toLowerCase().includes(q)
    )
})

const flagUrl = (code: string) =>
    `https://flagcdn.com/w40/${code.toLowerCase()}.png`

const parseIncoming = (value: string) => {
    const raw = (value || '').trim()
    if (!raw) {
        selectedCode.value = 'BF'
        localNumber.value = ''
        return
    }
    const match = [...countries.value].sort((a, b) => b.dial.length - a.dial.length)
        .find(c => raw.startsWith(c.dial))
    if (match) {
        selectedCode.value = match.code
        localNumber.value = raw.slice(match.dial.length).trim()
    } else {
        localNumber.value = raw.replace(/^\+/, '')
    }
}

watch(() => props.modelValue, (v) => parseIncoming(v || ''), { immediate: true })

const publish = () => {
    const digits = localNumber.value.replace(/[^\d\s-]/g, '').trim()
    if (!digits) {
        emit('update:modelValue', '')
        return
    }
    emit('update:modelValue', `${selected.value.dial} ${digits}`.trim())
}

watch([selectedCode, localNumber], publish)

const placeDropdown = () => {
    if (!triggerEl.value) return
    const rect = triggerEl.value.getBoundingClientRect()
    const width = Math.max(rect.width + (rootEl.value?.offsetWidth ?? 0) - triggerEl.value.offsetWidth, 280)
    const left = Math.min(rect.left, window.innerWidth - width - 8)
    const spaceBelow = window.innerHeight - rect.bottom
    const openUp = spaceBelow < 260 && rect.top > spaceBelow

    dropdownStyle.value = {
        position: 'fixed',
        left: `${Math.max(8, left)}px`,
        width: `${Math.min(width, window.innerWidth - 16)}px`,
        zIndex: '9999',
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 4}px`, top: 'auto' }
            : { top: `${rect.bottom + 4}px`, bottom: 'auto' }),
    }
}

const toggleOpen = async () => {
    if (props.disabled) return
    open.value = !open.value
    if (open.value) {
        await nextTick()
        placeDropdown()
        dropdownEl.value?.querySelector<HTMLInputElement>('.phone-search')?.focus()
    } else {
        search.value = ''
    }
}

const pick = (c: PhoneCountry) => {
    selectedCode.value = c.code
    open.value = false
    search.value = ''
}

const onDocClick = (e: MouseEvent) => {
    if (!open.value) return
    const target = e.target as Node
    if (rootEl.value?.contains(target) || dropdownEl.value?.contains(target)) return
    open.value = false
    search.value = ''
}

const onReposition = () => {
    if (open.value) placeDropdown()
}

onMounted(() => {
    document.addEventListener('click', onDocClick)
    window.addEventListener('resize', onReposition)
    window.addEventListener('scroll', onReposition, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick)
    window.removeEventListener('resize', onReposition)
    window.removeEventListener('scroll', onReposition, true)
})
</script>

<template>
    <div ref="rootEl" class="phone-input" :class="{ open }">
        <button
            ref="triggerEl"
            type="button"
            class="phone-flag"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click.stop="toggleOpen"
        >
            <img
                class="flag-img"
                :src="flagUrl(selected.code)"
                :alt="selected.name"
                width="22"
                height="16"
                loading="lazy"
            />
            <span class="dial">{{ selected.dial }}</span>
            <span class="material-symbols-outlined chev">expand_more</span>
        </button>
        <input
            :id="id"
            v-model="localNumber"
            type="tel"
            class="phone-number"
            :required="required"
            :disabled="disabled"
            placeholder="Numéro de téléphone"
            autocomplete="tel-national"
        />

        <Teleport to="body">
            <div
                v-if="open"
                ref="dropdownEl"
                class="phone-dropdown"
                :style="dropdownStyle"
                @click.stop
            >
                <input
                    v-model="search"
                    type="search"
                    class="phone-search"
                    placeholder="Rechercher un pays…"
                />
                <ul class="phone-list" role="listbox">
                    <li
                        v-for="c in filtered"
                        :key="c.code"
                        role="option"
                        :aria-selected="c.code === selectedCode"
                        :class="{ active: c.code === selectedCode }"
                        @click="pick(c)"
                    >
                        <img
                            class="flag-img"
                            :src="flagUrl(c.code)"
                            :alt="c.name"
                            width="22"
                            height="16"
                            loading="lazy"
                        />
                        <span class="name">{{ c.name }}</span>
                        <span class="dial-muted">{{ c.dial }}</span>
                    </li>
                    <li v-if="filtered.length === 0" class="empty">Aucun pays</li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.phone-input {
    position: relative;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #dadce0;
    background: transparent;
    z-index: 1;
}
.phone-flag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    padding: 0.55rem 0.5rem 0.55rem 0;
    cursor: pointer;
    font-family: inherit;
    color: #202124;
    flex-shrink: 0;
}
.phone-flag:disabled { opacity: 0.6; cursor: not-allowed; }
.flag-img {
    width: 22px;
    height: 16px;
    object-fit: cover;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
    display: block;
    flex-shrink: 0;
}
.dial { font-size: 0.95rem; font-weight: 600; }
.chev { font-size: 18px; color: #5f6368; }
.phone-number {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    padding: 0.55rem 0;
    font-size: 1rem;
    min-width: 0;
}
.phone-input:focus-within { border-bottom-color: #E5004C; box-shadow: 0 1px 0 #E5004C; }
</style>

<style>
.phone-dropdown {
    background: #fff;
    border: 1px solid #dadce0;
    border-radius: 10px;
    box-shadow: 0 12px 32px rgba(60, 64, 67, 0.22);
    overflow: hidden;
}
.phone-dropdown .phone-search {
    width: 100%;
    border: none;
    border-bottom: 1px solid #e8eaed;
    padding: 10px 12px;
    font-size: 14px;
    outline: none;
    font-family: inherit;
    box-sizing: border-box;
}
.phone-dropdown .phone-list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    max-height: 240px;
    overflow-y: auto;
}
.phone-dropdown .phone-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    cursor: pointer;
    font-size: 14px;
}
.phone-dropdown .phone-list li:hover,
.phone-dropdown .phone-list li.active { background: #fff5f8; }
.phone-dropdown .phone-list .name { flex: 1; color: #202124; }
.phone-dropdown .phone-list .dial-muted { color: #5f6368; font-size: 13px; }
.phone-dropdown .phone-list .empty { color: #80868b; cursor: default; justify-content: center; }
.phone-dropdown .flag-img {
    width: 22px;
    height: 16px;
    object-fit: cover;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
    display: block;
    flex-shrink: 0;
}
</style>
