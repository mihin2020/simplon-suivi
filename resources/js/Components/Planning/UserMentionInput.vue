<script setup lang="ts">
import { computed, ref } from 'vue'

export interface MentionUser {
    id: string
    first_name: string
    last_name: string
    full_name?: string
    email: string
}

const props = defineProps<{
    modelValue: string[]
    users: MentionUser[]
    placeholder?: string
    error?: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string[]]
}>()

const query = ref('')
const open = ref(false)

const displayName = (user: MentionUser) =>
    user.full_name || `${user.first_name} ${user.last_name}`.trim()

const selected = computed(() =>
    props.users.filter(user => props.modelValue.includes(user.id))
)

const filtered = computed(() => {
    const raw = query.value.trim()
    const q = raw.startsWith('@') ? raw.slice(1).trim().toLowerCase() : raw.toLowerCase()

    return props.users.filter((user) => {
        if (props.modelValue.includes(user.id)) return false
        if (!q) return true
        const haystack = `${displayName(user)} ${user.email}`.toLowerCase()
        return haystack.includes(q)
    })
})

const add = (user: MentionUser) => {
    if (props.modelValue.includes(user.id)) return
    emit('update:modelValue', [...props.modelValue, user.id])
    query.value = ''
}

const remove = (id: string) => {
    emit('update:modelValue', props.modelValue.filter(userId => userId !== id))
}

const onInput = () => {
    open.value = true
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        event.preventDefault()
        if (filtered.value[0]) add(filtered.value[0])
        return
    }
    if (event.key === 'Backspace' && query.value === '' && props.modelValue.length) {
        remove(props.modelValue[props.modelValue.length - 1])
    }
    if (event.key === 'Escape') {
        open.value = false
    }
}

const closeSoon = () => {
    window.setTimeout(() => { open.value = false }, 150)
}

const initials = (user: MentionUser) =>
    `${user.first_name.charAt(0)}${user.last_name.charAt(0)}`.toUpperCase()
</script>

<template>
    <div class="mention">
        <div class="mention-box" :class="{ 'has-error': error, open }" @click="open = true">
            <span
                v-for="user in selected"
                :key="user.id"
                class="chip"
            >
                <span class="chip-avatar">{{ initials(user) }}</span>
                @{{ displayName(user) }}
                <button type="button" class="chip-remove" title="Retirer" @click.stop="remove(user.id)">
                    <span class="material-symbols-outlined" style="font-size:14px">close</span>
                </button>
            </span>
            <input
                v-model="query"
                type="text"
                class="mention-input"
                :placeholder="selected.length ? 'Ajouter avec @…' : (placeholder ?? 'Saisir @ pour lister les responsables')"
                @focus="open = true"
                @input="onInput"
                @keydown="onKeydown"
                @blur="closeSoon"
            />
        </div>
        <div v-if="open" class="mention-list">
            <div v-if="users.length === 0" class="mention-empty">
                Aucun administrateur actif n’est disponible.
            </div>
            <div v-else-if="filtered.length === 0" class="mention-empty">
                Aucun utilisateur pour « {{ query }} ».
            </div>
            <button
                v-for="user in filtered"
                :key="user.id"
                type="button"
                class="mention-row"
                @mousedown.prevent="add(user)"
            >
                <span class="chip-avatar">{{ initials(user) }}</span>
                <span class="mention-meta">
                    <span class="mention-name">@{{ displayName(user) }}</span>
                    <span class="mention-email">{{ user.email }}</span>
                </span>
            </button>
        </div>
        <p v-if="error" class="error-msg">{{ error }}</p>
    </div>
</template>

<style scoped>
.mention { position: relative; }
.mention-box {
    display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    min-height: 42px; padding: 6px 8px;
    border: 1.5px solid #e0e3e5; border-radius: 8px; background: #fff;
}
.mention-box.open, .mention-box:focus-within { border-color: #1F3A4D; }
.mention-box.has-error { border-color: #E5004C; }
.mention-input {
    flex: 1; min-width: 140px; border: none; outline: none;
    font-size: 13px; color: #191c1e; background: transparent; padding: 4px;
}
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 3px 8px 3px 4px;
    background: #fff0f4; border: 1px solid #ffc0d0;
    border-radius: 99px; font-size: 12px; font-weight: 600; color: #1F3A4D;
}
.chip-avatar {
    width: 22px; height: 22px; border-radius: 50%;
    background: #1F3A4D; color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 700; flex-shrink: 0;
}
.chip-remove {
    display: inline-flex; width: 16px; height: 16px; border: none;
    border-radius: 50%; background: #ffc0d0; color: #ba1a1a;
    cursor: pointer; padding: 0; align-items: center; justify-content: center;
}
.chip-remove:hover { background: #E5004C; color: #fff; }
.mention-list {
    position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 4px);
    background: #fff; border: 1px solid #e0e3e5; border-radius: 10px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
    max-height: 220px; overflow-y: auto; padding: 4px;
}
.mention-row {
    width: 100%; display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border: none; background: transparent;
    border-radius: 8px; cursor: pointer; text-align: left;
}
.mention-row:hover { background: #f6f8fa; }
.mention-meta { display: flex; flex-direction: column; min-width: 0; }
.mention-name { font-size: 13px; font-weight: 600; color: #191c1e; }
.mention-email { font-size: 11px; color: #9aaabb; }
.mention-empty { padding: 12px; font-size: 12px; color: #9aaabb; }
.error-msg { margin-top: 4px; font-size: 12px; color: #E5004C; }
</style>
