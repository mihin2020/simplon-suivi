<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'

interface Recipient {
    id?: string
    email: string
    name?: string
}

const props = defineProps<{ modelValue: Recipient[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Recipient[]): void }>()

const query = ref('')
const results = ref<any[]>([])
const selected = ref<Recipient[]>([...props.modelValue])
const showDropdown = ref(false)

const formations = ref<any[]>([])
const projects = ref<any[]>([])

const filterFormation = ref('')
const filterProject = ref('')
const filterStatus = ref('')

async function search() {
    const params = new URLSearchParams()
    if (query.value) params.append('q', query.value)
    if (filterFormation.value) params.append('formation_id', filterFormation.value)
    if (filterProject.value) params.append('project_id', filterProject.value)
    if (filterStatus.value) params.append('status', filterStatus.value)

    const res = await fetch(`/api/learners/search?${params.toString()}`)
    results.value = await res.json()
    showDropdown.value = true
}

function add(recipient: Recipient) {
    if (!selected.value.find(r => r.email === recipient.email)) {
        selected.value.push(recipient)
        emit('update:modelValue', selected.value)
    }
    query.value = ''
    showDropdown.value = false
}

function remove(index: number) {
    selected.value.splice(index, 1)
    emit('update:modelValue', selected.value)
}

async function addByFormation() {
    if (!filterFormation.value) return
    const params = new URLSearchParams()
    params.append('formation_id', filterFormation.value)
    const res = await fetch(`/api/learners/search?${params.toString()}`)
    const learners = await res.json()
    learners.forEach((l: any) => add({ id: l.id, email: l.email, name: `${l.first_name} ${l.last_name}` }))
}

async function addByProject() {
    if (!filterProject.value) return
    const params = new URLSearchParams()
    params.append('project_id', filterProject.value)
    const res = await fetch(`/api/learners/search?${params.toString()}`)
    const learners = await res.json()
    learners.forEach((l: any) => add({ id: l.id, email: l.email, name: `${l.first_name} ${l.last_name}` }))
}

watch(query, () => {
    if (query.value.length >= 2) search()
})

onMounted(async () => {
    const [fRes, pRes] = await Promise.all([fetch('/api/projects?per_page=100'), fetch('/api/formations?per_page=100')])
    // If these endpoints don't exist, ignore
    try { formations.value = (await fRes.json()).data ?? [] } catch { formations.value = [] }
    try { projects.value = (await pRes.json()).data ?? [] } catch { projects.value = [] }
})
</script>

<template>
    <div class="relative">
        <div class="flex flex-wrap gap-xs border border-surface-container-highest rounded-lg px-sm py-xs bg-surface min-h-[44px]">
            <span
                v-for="(r, i) in selected"
                :key="r.email"
                class="inline-flex items-center gap-xs bg-primary/10 text-primary text-label-sm rounded-full px-sm py-xs"
            >
                {{ r.name || r.email }}
                <button type="button" @click="remove(i)" class="hover:text-error">
                    <span class="material-symbols-outlined" style="font-size:14px">close</span>
                </button>
            </span>
            <input
                v-model="query"
                type="text"
                placeholder="Ajouter un destinataire..."
                class="flex-1 min-w-[120px] bg-transparent text-body-sm text-on-surface outline-none placeholder-on-surface-variant"
                @focus="showDropdown = true"
            />
        </div>

        <div v-if="showDropdown" class="absolute z-10 mt-1 w-full bg-surface border border-surface-container-highest rounded-lg shadow-lg max-h-64 overflow-y-auto">
            <div class="flex gap-sm p-sm border-b border-surface-container-low">
                <select v-model="filterProject" class="text-body-sm border border-surface-container-highest rounded px-sm py-xs bg-surface">
                    <option value="">Projet</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="filterFormation" class="text-body-sm border border-surface-container-highest rounded px-sm py-xs bg-surface">
                    <option value="">Formation</option>
                    <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <button type="button" @click="search" class="text-label-sm bg-primary text-on-primary px-sm py-xs rounded">Filtrer</button>
            </div>
            <div
                v-for="r in results"
                :key="r.email"
                @click="add({ id: r.id, email: r.email, name: `${r.first_name} ${r.last_name}` })"
                class="px-sm py-xs cursor-pointer hover:bg-surface-container-low text-body-sm text-on-surface"
            >
                <span class="font-medium">{{ r.first_name }} {{ r.last_name }}</span>
                <span class="text-on-surface-variant ml-sm">{{ r.email }}</span>
            </div>
            <div v-if="results.length === 0" class="px-sm py-md text-body-sm text-on-surface-variant text-center">
                Aucun résultat
            </div>
        </div>
    </div>
</template>
