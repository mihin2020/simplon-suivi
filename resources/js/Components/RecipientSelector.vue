<script setup lang="ts">
import { ref, watch, computed } from 'vue'

interface Recipient {
    id?: string
    email: string
    name?: string
}

interface Learner {
    id: string
    first_name: string
    last_name: string
    email: string
    phone?: string
    status?: string
}

const props = defineProps<{
    modelValue: Recipient[]
    projects?: { id: string; name: string }[]
}>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Recipient[]): void }>()

// Selection cascade state
const formations = ref<any[]>([])
const learners = ref<Learner[]>([])
const selectedLearnerIds = ref<string[]>([])

const selectedProjectId = ref('')
const selectedFormationId = ref('')
const loadingFormations = ref(false)
const loadingLearners = ref(false)
const showSelector = ref(false)

// Quick search (existing)
const query = ref('')
const searchResults = ref<Learner[]>([])
const selected = ref<Recipient[]>([...props.modelValue])

// Watch project change → load formations
watch(selectedProjectId, async (projectId: string) => {
    selectedFormationId.value = ''
    formations.value = []
    learners.value = []
    selectedLearnerIds.value = []
    
    if (!projectId) return
    
    loadingFormations.value = true
    try {
        const res = await fetch(`/api/projects/${projectId}/formations`)
        const data = await res.json()
        // formationsJson returns array directly
        formations.value = Array.isArray(data) ? data : (data.formations ?? data.data ?? [])
    } catch {
        formations.value = []
    } finally {
        loadingFormations.value = false
    }
})

// Watch formation change → load learners
watch(selectedFormationId, async (formationId: string) => {
    learners.value = []
    selectedLearnerIds.value = []
    
    if (!formationId) return
    
    loadingLearners.value = true
    try {
        const res = await fetch(`/api/learners/search?formation_id=${formationId}`)
        learners.value = await res.json()
    } catch {
        learners.value = []
    } finally {
        loadingLearners.value = false
    }
})

// Quick search
watch(query, async () => {
    if (query.value.length < 2) {
        searchResults.value = []
        return
    }
    const res = await fetch(`/api/learners/search?q=${encodeURIComponent(query.value)}`)
    searchResults.value = await res.json()
})

// Validation email
function isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

// Add manual email on Enter / comma
function onSearchKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        const value = query.value.trim().replace(/,$/, '')
        if (isValidEmail(value)) {
            e.preventDefault()
            addRecipient({ email: value, name: value })
        } else if (e.key === 'Enter' && searchResults.value.length === 1) {
            e.preventDefault()
            const l = searchResults.value[0]
            addRecipient({ id: l.id, email: l.email, name: getLearnerName(l) })
        }
    }
}

// Computed: all selected learners as recipients
const selectedLearners = computed(() => {
    return learners.value.filter((l: Learner) => selectedLearnerIds.value.includes(l.id))
})

// Actions
function addRecipient(recipient: Recipient) {
    if (!selected.value.find((r: Recipient) => r.email === recipient.email)) {
        selected.value.push(recipient)
        emit('update:modelValue', selected.value)
    }
    query.value = ''
    searchResults.value = []
}

function addSelectedLearners() {
    selectedLearners.value.forEach((learner: Learner) => {
        addRecipient({
            id: learner.id,
            email: learner.email,
            name: `${learner.first_name} ${learner.last_name}`
        })
    })
    // Reset cascade
    selectedLearnerIds.value = []
    showSelector.value = false
}

function remove(index: number) {
    selected.value.splice(index, 1)
    emit('update:modelValue', selected.value)
}

function toggleLearner(learnerId: string) {
    const idx = selectedLearnerIds.value.indexOf(learnerId)
    if (idx === -1) {
        selectedLearnerIds.value.push(learnerId)
    } else {
        selectedLearnerIds.value.splice(idx, 1)
    }
}

function selectAll() {
    selectedLearnerIds.value = learners.value.map((l: Learner) => l.id)
}

function unselectAll() {
    selectedLearnerIds.value = []
}

function getLearnerName(learner: Learner) {
    return `${learner.first_name} ${learner.last_name}`
}
</script>

<template>
    <div class="recipient-selector-root">
        <!-- Selected recipients -->
        <div class="recipient-chips">
            <span
                v-for="(r, i) in selected"
                :key="r.email"
                class="recipient-chip"
            >
                {{ r.name || r.email }}
                <button type="button" @click="remove(i)" class="remove-btn" title="Retirer">
                    <span class="material-symbols-outlined" style="font-size:14px">close</span>
                </button>
            </span>
            <span v-if="selected.length === 0" class="empty-text">Aucun destinataire sélectionné</span>
        </div>

        <!-- Quick search -->
        <div class="relative">
            <div class="search-box">
                <span class="material-symbols-outlined search-icon">search</span>
                <input
                    v-model="query"
                    type="text"
                    placeholder="Rechercher un apprenant ou saisir un email (Entrée pour ajouter)..."
                    class="search-input"
                    @keydown="onSearchKeydown"
                />
                <button
                    type="button"
                    @click="showSelector = !showSelector"
                    class="cascade-btn"
                    :class="{ active: showSelector }"
                >
                    <span class="material-symbols-outlined" style="font-size:16px">account_tree</span>
                    Par projet/formation
                </button>
            </div>
            
            <!-- Quick search results -->
            <div v-if="searchResults.length > 0 && query.length >= 2" class="dropdown">
                <div
                    v-for="learner in searchResults"
                    :key="learner.id"
                    @click="addRecipient({ id: learner.id, email: learner.email, name: getLearnerName(learner) })"
                    class="dropdown-item"
                >
                    <span class="font-medium">{{ learner.first_name }} {{ learner.last_name }}</span>
                    <span class="text-secondary ml-sm">{{ learner.email }}</span>
                </div>
            </div>
        </div>

        <!-- Cascade selector - Dropdown overlay -->
        <div v-if="showSelector" class="cascade-dropdown">
            <div class="cascade-dropdown-inner">
                <div class="dropdown-header">
                    <span class="material-symbols-outlined" style="font-size:16px">account_tree</span>
                    <span class="dropdown-title">Sélection par projet</span>
                    <button type="button" @click="showSelector = false" class="close-btn">
                        <span class="material-symbols-outlined" style="font-size:14px">close</span>
                    </button>
                </div>
                
                <div class="dropdown-body">
                    <!-- Step 1: Project -->
                    <div class="step">
                        <label class="step-label">1. Projet</label>
                        <select v-model="selectedProjectId" class="step-select">
                            <option value="">-- Sélectionner --</option>
                            <option v-for="p in (props.projects ?? [])" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <!-- Step 2: Formation -->
                    <div class="step" :class="{ disabled: !selectedProjectId }">
                        <label class="step-label">
                            2. Formation
                            <span v-if="loadingFormations" class="loading">...</span>
                        </label>
                        <select v-model="selectedFormationId" class="step-select" :disabled="!selectedProjectId || formations.length === 0">
                            <option value="">
                                {{ !selectedProjectId ? 'Choisir d\'abord un projet' : 
                                   formations.length === 0 ? 'Aucune formation' : '-- Sélectionner --' }}
                            </option>
                            <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                    </div>

                    <!-- Step 3: Learners -->
                    <div class="step" :class="{ disabled: !selectedFormationId }">
                        <label class="step-label">
                            3. Apprenants
                            <span v-if="loadingLearners" class="loading">...</span>
                            <span v-else-if="learners.length > 0" class="count">({{ selectedLearnerIds.length }}/{{ learners.length }})</span>
                        </label>
                        
                        <div v-if="selectedFormationId && learners.length === 0 && !loadingLearners" class="empty-learners">
                            Aucun apprenant
                        </div>
                        
                        <div v-if="learners.length > 0" class="learners-list">
                            <div class="learners-actions">
                                <button type="button" @click="selectAll" class="action-link">Tout</button>
                                <span class="sep">|</span>
                                <button type="button" @click="unselectAll" class="action-link">Aucun</button>
                            </div>
                            
                            <div class="learners-grid">
                                <label
                                    v-for="learner in learners"
                                    :key="learner.id"
                                    class="learner-item"
                                    :class="{ selected: selectedLearnerIds.includes(learner.id) }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="learner.id"
                                        :checked="selectedLearnerIds.includes(learner.id)"
                                        @change="toggleLearner(learner.id)"
                                        class="checkbox"
                                    />
                                    <div class="learner-info">
                                        <div class="learner-name">{{ learner.first_name }} {{ learner.last_name }}</div>
                                        <div class="learner-email">{{ learner.email }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add button -->
                <div v-if="selectedLearnerIds.length > 0" class="dropdown-footer">
                    <button type="button" @click="addSelectedLearners" class="btn-add">
                        <span class="material-symbols-outlined" style="font-size:14px">add</span>
                        Ajouter {{ selectedLearnerIds.length }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.recipient-selector-root {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.relative { position: relative; }

.recipient-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 40px;
    padding: 8px 12px;
    background: #fafafa;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
}
.recipient-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    background: #fff5f8;
    color: #E5004C;
    border: 1px solid #ffd1de;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 500;
}
.remove-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border: none;
    background: transparent;
    color: #E5004C;
    cursor: pointer;
    opacity: 0.7;
}
.remove-btn:hover { opacity: 1; }
.empty-text {
    color: #9aaabb;
    font-size: 13px;
    font-style: italic;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
}
.search-icon { color: #9aaabb; font-size: 18px; }
.search-input {
    flex: 1;
    min-width: 150px;
    border: none;
    outline: none;
    font-size: 14px;
    color: #191c1e;
    background: transparent;
    padding: 4px 0;
}
.cascade-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f2f4f6;
    color: #515f74;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s;
}
.cascade-btn:hover { background: #e0e3e5; color: #191c1e; }
.cascade-btn.active { background: #E5004C; color: #fff; }

.dropdown {
    position: absolute;
    z-index: 50;
    margin-top: 4px;
    width: 100%;
    max-height: 240px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.dropdown-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 14px;
    color: #191c1e;
    border-bottom: 1px solid #f5f5f5;
}
.dropdown-item:hover { background: #f9fafb; }
.dropdown-item:last-child { border-bottom: none; }

.cascade-dropdown {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 480px;
}
.cascade-dropdown-inner {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dropdown-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fafafa;
    border-bottom: 1px solid #e0e3e5;
    flex-shrink: 0;
}
.dropdown-title {
    font-size: 13px;
    font-weight: 600;
    color: #191c1e;
    flex: 1;
}
.close-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    background: transparent;
    color: #9aaabb;
    cursor: pointer;
    border-radius: 4px;
}
.close-btn:hover { background: #f2f4f6; color: #515f74; }

.dropdown-body {
    padding: 14px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.step {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.step.disabled { opacity: 0.5; }
.step-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.loading { font-size: 11px; color: #9aaabb; font-weight: 400; text-transform: none; }
.count { font-size: 11px; color: #E5004C; font-weight: 600; text-transform: none; }
.step-select {
    padding: 8px 10px;
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    font-size: 13px;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    cursor: pointer;
    width: 100%;
}
.step-select:focus {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 2px rgba(229, 0, 76, 0.08);
}
.step-select:disabled { opacity: 0.6; cursor: not-allowed; }

.empty-learners {
    padding: 16px;
    text-align: center;
    color: #9aaabb;
    font-size: 12px;
    background: #fafafa;
    border-radius: 6px;
}
.learners-list {
    border: 1px solid #e0e3e5;
    border-radius: 6px;
    overflow: hidden;
}
.learners-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: #f9fafb;
    border-bottom: 1px solid #e0e3e5;
}
.action-link {
    font-size: 11px;
    color: #E5004C;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    padding: 0;
}
.action-link:hover { text-decoration: underline; }
.sep { color: #e0e3e5; font-size: 11px; }
.learners-grid {
    max-height: 180px;
    overflow-y: auto;
    padding: 4px;
}
.learner-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
}
.learner-item:hover { background: #f9fafb; }
.learner-item.selected { background: #fff5f8; }
.checkbox {
    width: 16px;
    height: 16px;
    accent-color: #E5004C;
    cursor: pointer;
    flex-shrink: 0;
}
.learner-info { flex: 1; min-width: 0; }
.learner-name {
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
}
.learner-email {
    font-size: 11px;
    color: #9aaabb;
}

.dropdown-footer {
    padding: 10px 14px;
    background: #fafafa;
    border-top: 1px solid #e0e3e5;
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}
.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 7px 14px;
    background: #E5004C;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.btn-add:hover { background: #c4003f; }

.font-medium { font-weight: 500; }
.ml-sm { margin-left: 8px; }
.text-secondary { color: #6b7280; }
</style>
