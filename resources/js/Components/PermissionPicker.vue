<script setup lang="ts">
import { computed, reactive, ref } from 'vue'

interface Permission {
    id: number
    name: string
    slug: string
    group: string | null
}

interface Submodule {
    label: string | null
    perms: Permission[]
}

interface ModuleGroup {
    key: string
    icon: string
    submodules: Submodule[]
    allPerms: Permission[]
}

const props = defineProps<{
    permissions: Permission[]
    modelValue: number[]
}>()

const emit = defineEmits<{ 'update:modelValue': [number[]] }>()

const search = ref('')

const moduleIcons: Record<string, string> = {
    'Utilisateurs':   'people',
    'Projets':        'folder',
    'Formations':     'school',
    'Apprenants':     'groups',
    'Formateurs':     'co_present',
    'Présences':      'event_available',
    'Partenaires':    'handshake',
    'Statistiques':   'bar_chart',
    'Référentiels':   'menu_book',
    'Communication':  'mail',
    'WhatsApp':       'forum',
    'Dépenses':       'payments',
    'Configuration':  'settings',
    'Campus':         'apartment',
}

const allModules = computed<ModuleGroup[]>(() => {
    const map = new Map<string, ModuleGroup>()
    for (const p of props.permissions) {
        const raw = (p.group ?? 'Autres').trim()
        const [moduleLabel, subLabel] = raw.split('—').map(s => s.trim())

        if (!map.has(moduleLabel)) {
            map.set(moduleLabel, { key: moduleLabel, icon: moduleIcons[moduleLabel] ?? 'tune', submodules: [], allPerms: [] })
        }
        const mod = map.get(moduleLabel)!
        mod.allPerms.push(p)

        let sub = mod.submodules.find(s => s.label === (subLabel ?? null))
        if (!sub) {
            sub = { label: subLabel ?? null, perms: [] }
            mod.submodules.push(sub)
        }
        sub.perms.push(p)
    }
    return Array.from(map.values())
})

const filteredModules = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return allModules.value

    return allModules.value
        .map(mod => ({
            ...mod,
            submodules: mod.submodules
                .map(sub => ({ ...sub, perms: sub.perms.filter(p => p.name.toLowerCase().includes(q)) }))
                .filter(sub => sub.perms.length > 0),
        }))
        .filter(mod => mod.submodules.length > 0)
})

const closedModules = reactive<Record<string, boolean>>({})
const isOpen = (key: string) => !closedModules[key]
const toggleModule = (key: string) => { closedModules[key] = isOpen(key) }

const isSelected = (id: number) => props.modelValue.includes(id)

const togglePermission = (id: number) => {
    if (isSelected(id)) {
        emit('update:modelValue', props.modelValue.filter(i => i !== id))
    } else {
        emit('update:modelValue', [...props.modelValue, id])
    }
}

const selectAll = () => emit('update:modelValue', props.permissions.map(p => p.id))
const deselectAll = () => emit('update:modelValue', [])

const selectedCountInModule = (mod: ModuleGroup) => mod.allPerms.filter(p => isSelected(p.id)).length
</script>

<template>
    <div class="perm-panel">
        <div class="perm-panel-header">
            <div>
                <h2 class="section-title" style="margin-bottom:0">Permissions</h2>
                <p class="perm-count">{{ modelValue.length }} / {{ permissions.length }} sélectionnée{{ modelValue.length > 1 ? 's' : '' }}</p>
            </div>
            <div class="perm-bulk-actions">
                <button type="button" @click="selectAll" class="perm-bulk-btn">Tout cocher</button>
                <button type="button" @click="deselectAll" class="perm-bulk-btn">Tout décocher</button>
            </div>
        </div>

        <div class="perm-search">
            <span class="material-symbols-outlined perm-search-icon">search</span>
            <input v-model="search" type="text" placeholder="Rechercher une permission..." class="perm-search-input" />
        </div>

        <div class="perm-modules">
            <div v-for="mod in filteredModules" :key="mod.key" class="perm-module-card">
                <button type="button" class="perm-module-header" @click="toggleModule(mod.key)">
                    <span class="perm-module-icon">
                        <span class="material-symbols-outlined" style="font-size:18px">{{ mod.icon }}</span>
                    </span>
                    <span class="perm-module-title">{{ mod.key }}</span>
                    <span class="perm-module-badge">{{ selectedCountInModule(mod) }} / {{ mod.allPerms.length }}</span>
                    <span class="material-symbols-outlined perm-module-chevron" :class="{ 'perm-module-chevron-open': isOpen(mod.key) }">expand_more</span>
                </button>

                <div v-show="isOpen(mod.key)" class="perm-module-body">
                    <div v-for="sub in mod.submodules" :key="sub.label ?? '_default'" class="perm-submodule">
                        <p v-if="sub.label" class="perm-submodule-title">{{ sub.label }}</p>
                        <div class="perm-chips">
                            <div
                                v-for="p in sub.perms"
                                :key="p.id"
                                class="perm-chip"
                                :class="{ 'perm-chip-selected': isSelected(p.id) }"
                                @click.prevent="togglePermission(p.id)"
                            >
                                <span class="perm-chip-check">
                                    <span v-if="isSelected(p.id)" class="material-symbols-outlined" style="font-size:12px">check</span>
                                </span>
                                <span class="perm-chip-name">{{ p.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="filteredModules.length === 0" class="perm-empty">Aucune permission ne correspond à la recherche.</p>
        </div>
    </div>
</template>

<style scoped>
.perm-panel { border: 1px solid #e0e3e5; border-radius: 12px; overflow: hidden; }
.perm-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}
.section-title { font-size: 16px; font-weight: 600; color: #191c1e; }
.perm-count { font-size: 13px; color: #6b7280; margin: 4px 0 0; }
.perm-bulk-actions { display: flex; gap: 8px; }
.perm-bulk-btn {
    font-size: 12px;
    font-weight: 600;
    color: #E5004C;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    cursor: pointer;
    padding: 6px 12px;
    transition: all 0.15s;
}
.perm-bulk-btn:hover { background: #fff5f8; border-color: #E5004C; }

.perm-search {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 24px 0;
    padding: 8px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    background: #fff;
}
.perm-search-icon { font-size: 18px; color: #9aaabb; }
.perm-search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 13px;
    color: #191c1e;
}

.perm-modules { display: grid; gap: 10px; padding: 16px 24px 20px; }
.perm-empty { font-size: 13px; color: #9aaabb; text-align: center; padding: 16px 0; }

.perm-module-card { border: 1px solid #f3f4f6; border-radius: 10px; overflow: hidden; background: #fff; }
.perm-module-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #fafbfc;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
}
.perm-module-header:hover { background: #f3f4f6; }
.perm-module-icon {
    display: flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.perm-module-title { font-size: 13px; font-weight: 700; color: #191c1e; text-transform: uppercase; letter-spacing: 0.03em; flex: 1; }
.perm-module-badge { font-size: 11px; font-weight: 600; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 99px; flex-shrink: 0; }
.perm-module-chevron { font-size: 18px; color: #9aaabb; transition: transform 0.15s; flex-shrink: 0; }
.perm-module-chevron-open { transform: rotate(180deg); }

.perm-module-body { padding: 14px; display: grid; gap: 14px; }
.perm-submodule-title { font-size: 11px; font-weight: 700; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 8px; }

.perm-chips { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
.perm-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    background: #fff;
}
.perm-chip:hover { border-color: #16a34a; background: #f0fdf4; }
.perm-chip-selected { border-color: #16a34a; background: #f0fdf4; box-shadow: 0 1px 2px rgba(22,163,74,0.08); }
.perm-chip-check {
    width: 18px; height: 18px;
    border: 2px solid #e0e3e5; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.15s;
    color: #fff; font-size: 12px;
}
.perm-chip-selected .perm-chip-check { background: #16a34a; border-color: #16a34a; }
.perm-chip-name { font-size: 13px; font-weight: 500; color: #374151; }
.perm-chip-selected .perm-chip-name { color: #16a34a; font-weight: 600; }
</style>
