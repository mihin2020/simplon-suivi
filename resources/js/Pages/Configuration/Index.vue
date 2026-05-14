<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }
interface EducationLevel { id: string; name: string }
interface AgeRange { id: number; name: string; age_min: number; age_max: number; order: number }

interface AiConfig {
    provider: string
    model: string
    base_url: string
    configured: boolean
}

const props = defineProps<{
    trainerProfiles: Profile[]
    educationLevels: EducationLevel[]
    ageRanges: AgeRange[]
    aiConfig: AiConfig
}>()

const AI_PROVIDERS = [
    { key: 'claude',   name: 'Claude (Anthropic)',  placeholder: 'sk-ant-api03-...', modelPlaceholder: 'claude-opus-4-5',  hasBaseUrl: false },
    { key: 'openai',   name: 'OpenAI (ChatGPT)',    placeholder: 'sk-...',           modelPlaceholder: 'gpt-4o-mini',      hasBaseUrl: false },
    { key: 'deepseek', name: 'DeepSeek',            placeholder: 'sk-...',           modelPlaceholder: 'deepseek-chat',    hasBaseUrl: false },
    { key: 'grok',     name: 'Grok (xAI)',          placeholder: 'xai-...',          modelPlaceholder: 'grok-3-mini',      hasBaseUrl: false },
    { key: 'gemini',   name: 'Gemini (Google)',     placeholder: 'AIza...',          modelPlaceholder: 'gemini-2.0-flash', hasBaseUrl: false },
    { key: 'custom',   name: 'Personnalisé (autre)', placeholder: 'Votre clé API',   modelPlaceholder: 'nom-du-modèle',    hasBaseUrl: true  },
]

// ── Assistant IA ──
const showApiKey = ref(false)
const aiKeyForm = useForm({
    ai_provider: props.aiConfig.provider || 'openai',
    ai_api_key:  '',
    ai_model:    props.aiConfig.model || '',
    ai_base_url: props.aiConfig.base_url || '',
})

const selectedProvider = computed(() => AI_PROVIDERS.find(p => p.key === aiKeyForm.ai_provider) ?? AI_PROVIDERS[0])

const submitAiKey = () => {
    aiKeyForm.post('/configuration/ai-key', {
        onSuccess: () => aiKeyForm.ai_api_key = '',
    })
}

const createForm = useForm({ name: '' })
const editingId  = ref<string | null>(null)
const editForm   = useForm({ name: '' })

const startEdit = (p: Profile) => {
    editingId.value = p.id
    editForm.name   = p.name
}
const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
}
const submitCreate = () => {
    createForm.post('/trainer-profiles', { onSuccess: () => createForm.reset() })
}
const submitEdit = (id: string) => {
    editForm.put(`/trainer-profiles/${id}`, { onSuccess: () => cancelEdit() })
}
const destroy = (p: Profile) => {
    if (confirm(`Supprimer le profil « ${p.name} » ?`)) {
        router.delete(`/trainer-profiles/${p.id}`)
    }
}

// ── Education Levels ──
const eduCreateForm = useForm({ name: '' })
const eduEditingId = ref<string | null>(null)
const eduEditForm = useForm({ name: '' })
const showEduErrorModal = ref(false)
const eduErrorMessage = ref('')

const startEduEdit = (e: EducationLevel) => {
    eduEditingId.value = e.id
    eduEditForm.name = e.name
}
const cancelEduEdit = () => {
    eduEditingId.value = null
    eduEditForm.reset()
}
const submitEduCreate = () => {
    eduCreateForm.post('/education-levels', { onSuccess: () => eduCreateForm.reset() })
}
const submitEduEdit = (id: string) => {
    eduEditForm.put(`/education-levels/${id}`, { onSuccess: () => cancelEduEdit() })
}
const destroyEdu = (e: EducationLevel) => {
    if (confirm(`Supprimer le niveau d'études « ${e.name} » ?`)) {
        router.delete(`/education-levels/${e.id}`, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                if (errors.message) {
                    eduErrorMessage.value = errors.message
                    showEduErrorModal.value = true
                }
            }
        })
    }
}
const closeEduErrorModal = () => {
    showEduErrorModal.value = false
    eduErrorMessage.value = ''
}

// ── Tranches d'âge ──
const ageCreateForm = useForm({ age_min: 18, age_max: 25 })
const ageEditingId = ref<number | null>(null)
const ageEditForm = useForm({ age_min: 0, age_max: 0 })
const showAgeErrorModal = ref(false)
const ageErrorMessage = ref('')

const startAgeEdit = (a: AgeRange) => {
    ageEditingId.value = a.id
    ageEditForm.age_min = a.age_min
    ageEditForm.age_max = a.age_max
}
const cancelAgeEdit = () => {
    ageEditingId.value = null
    ageEditForm.reset()
}
const submitAgeCreate = () => {
    ageCreateForm.post('/age-ranges', { onSuccess: () => ageCreateForm.reset() })
}
const submitAgeEdit = (id: number) => {
    ageEditForm.put(`/age-ranges/${id}`, { onSuccess: () => cancelAgeEdit() })
}
const destroyAge = (a: AgeRange) => {
    if (confirm(`Supprimer la tranche d'âge « ${a.name} » ?`)) {
        router.delete(`/age-ranges/${a.id}`, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                if (errors.message) {
                    ageErrorMessage.value = errors.message
                    showAgeErrorModal.value = true
                }
            }
        })
    }
}
const closeAgeErrorModal = () => {
    showAgeErrorModal.value = false
    ageErrorMessage.value = ''
}
</script>

<template>
    <div class="max-w-4xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div>
            <h1 class="text-h1 font-bold text-on-surface">Configuration</h1>
            <p class="text-body-md text-secondary mt-xs">Paramètres globaux de l'application.</p>
        </div>

        <!-- ── Section : Profils Formateurs ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">person_badge</span>
                    <div>
                        <h2 class="config-section-title">Profils Formateurs</h2>
                        <p class="config-section-sub">Types de formateurs disponibles lors de l'invitation.</p>
                    </div>
                </div>
                <span class="count-pill">{{ trainerProfiles.length }}</span>
            </div>

            <div class="config-section-body">
                <!-- Formulaire ajout -->
                <form @submit.prevent="submitCreate" class="add-form">
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': createForm.errors.name }"
                        placeholder="Ex : Formateur principal, Vacataire..."
                        autofocus
                    />
                    <button type="submit" class="btn-add" :disabled="createForm.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter
                    </button>
                </form>
                <p v-if="createForm.errors.name" class="error-msg">{{ createForm.errors.name }}</p>

                <!-- Liste -->
                <div v-if="trainerProfiles.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#ddd">person_badge</span>
                    <p class="text-body-sm text-secondary mt-xs">Aucun profil. Ajoutez-en un ci-dessus.</p>
                </div>

                <ul v-else class="item-list">
                    <li v-for="p in trainerProfiles" :key="p.id" class="item-row">
                        <form v-if="editingId === p.id" @submit.prevent="submitEdit(p.id)" class="flex gap-sm flex-1">
                            <input v-model="editForm.name" type="text" class="input flex-1"
                                :class="{ 'input-error': editForm.errors.name }" />
                            <button type="submit" class="btn-icon-ok">
                                <span class="material-symbols-outlined" style="font-size:18px">check</span>
                            </button>
                            <button type="button" class="btn-icon-cancel" @click="cancelEdit">
                                <span class="material-symbols-outlined" style="font-size:18px">close</span>
                            </button>
                        </form>
                        <template v-else>
                            <div class="flex items-center gap-sm flex-1">
                                <span class="material-symbols-outlined" style="font-size:16px;color:#adb5bd">label</span>
                                <span class="item-name">{{ p.name }}</span>
                            </div>
                            <div class="flex gap-xs">
                                <button class="icon-btn" title="Modifier" @click="startEdit(p)">
                                    <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                </button>
                                <button class="icon-btn danger" title="Supprimer" @click="destroy(p)">
                                    <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                </button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Section : Niveaux d'études ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">school</span>
                    <div>
                        <h2 class="config-section-title">Niveaux d'études</h2>
                        <p class="config-section-sub">Niveaux d'études disponibles pour les apprenants.</p>
                    </div>
                </div>
                <span class="count-pill">{{ educationLevels.length }}</span>
            </div>

            <div class="config-section-body">
                <!-- Formulaire ajout -->
                <form @submit.prevent="submitEduCreate" class="add-form">
                    <input
                        v-model="eduCreateForm.name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': eduCreateForm.errors.name }"
                        placeholder="Ex : Bac+2, Bac+3, Master..."
                    />
                    <button type="submit" class="btn-add" :disabled="eduCreateForm.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter
                    </button>
                </form>
                <p v-if="eduCreateForm.errors.name" class="error-msg">{{ eduCreateForm.errors.name }}</p>

                <!-- Liste -->
                <div v-if="educationLevels.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#ddd">school</span>
                    <p class="text-body-sm text-secondary mt-xs">Aucun niveau d'études. Ajoutez-en un ci-dessus.</p>
                </div>

                <ul v-else class="item-list">
                    <li v-for="e in educationLevels" :key="e.id" class="item-row">
                        <form v-if="eduEditingId === e.id" @submit.prevent="submitEduEdit(e.id)" class="flex gap-sm flex-1">
                            <input v-model="eduEditForm.name" type="text" class="input flex-1"
                                :class="{ 'input-error': eduEditForm.errors.name }" />
                            <button type="submit" class="btn-icon-ok">
                                <span class="material-symbols-outlined" style="font-size:18px">check</span>
                            </button>
                            <button type="button" class="btn-icon-cancel" @click="cancelEduEdit">
                                <span class="material-symbols-outlined" style="font-size:18px">close</span>
                            </button>
                        </form>
                        <template v-else>
                            <div class="flex items-center gap-sm flex-1">
                                <span class="material-symbols-outlined" style="font-size:16px;color:#adb5bd">label</span>
                                <span class="item-name">{{ e.name }}</span>
                            </div>
                            <div class="flex gap-xs">
                                <button class="icon-btn" title="Modifier" @click="startEduEdit(e)">
                                    <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                </button>
                                <button class="icon-btn danger" title="Supprimer" @click="destroyEdu(e)">
                                    <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                </button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Section : Tranches d'âge ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">elderly</span>
                    <div>
                        <h2 class="config-section-title">Tranches d'âge</h2>
                        <p class="config-section-sub">Catégories d'âge calculées automatiquement à partir de la date de naissance.</p>
                    </div>
                </div>
                <span class="count-pill">{{ ageRanges.length }}</span>
            </div>

            <div class="config-section-body">
                <!-- Formulaire ajout -->
                <form @submit.prevent="submitAgeCreate" class="add-form" style="flex-wrap:wrap; gap:8px;">
                    <input
                        v-model.number="ageCreateForm.age_min"
                        type="number"
                        min="0" max="150"
                        class="input"
                        :class="{ 'input-error': ageCreateForm.errors.age_min }"
                        placeholder="Âge min"
                        style="flex:1; min-width:110px;"
                    />
                    <input
                        v-model.number="ageCreateForm.age_max"
                        type="number"
                        min="0" max="150"
                        class="input"
                        :class="{ 'input-error': ageCreateForm.errors.age_max }"
                        placeholder="Âge max"
                        style="flex:1; min-width:110px;"
                    />
                    <button type="submit" class="btn-add" :disabled="ageCreateForm.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter
                    </button>
                </form>
                <p v-if="ageCreateForm.errors.age_min" class="error-msg">{{ ageCreateForm.errors.age_min }}</p>
                <p v-if="ageCreateForm.errors.age_max" class="error-msg">{{ ageCreateForm.errors.age_max }}</p>

                <!-- Liste -->
                <div v-if="ageRanges.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#ddd">elderly</span>
                    <p class="text-body-sm text-secondary mt-xs">Aucune tranche d'âge. Ajoutez-en une ci-dessus.</p>
                </div>

                <ul v-else class="item-list">
                    <li v-for="a in ageRanges" :key="a.id" class="item-row">
                        <form v-if="ageEditingId === a.id" @submit.prevent="submitAgeEdit(a.id)" class="flex gap-sm flex-1" style="flex-wrap:wrap;">
                            <input v-model.number="ageEditForm.age_min" type="number" min="0" max="150" class="input"
                                style="flex:1; min-width:100px;" placeholder="Âge min"
                                :class="{ 'input-error': ageEditForm.errors.age_min }" />
                            <input v-model.number="ageEditForm.age_max" type="number" min="0" max="150" class="input"
                                style="flex:1; min-width:100px;" placeholder="Âge max"
                                :class="{ 'input-error': ageEditForm.errors.age_max }" />
                            <button type="submit" class="btn-icon-ok">
                                <span class="material-symbols-outlined" style="font-size:18px">check</span>
                            </button>
                            <button type="button" class="btn-icon-cancel" @click="cancelAgeEdit">
                                <span class="material-symbols-outlined" style="font-size:18px">close</span>
                            </button>
                        </form>
                        <template v-else>
                            <div class="flex items-center gap-sm flex-1">
                                <span class="material-symbols-outlined" style="font-size:16px;color:#adb5bd">elderly</span>
                                <span class="item-name">{{ a.age_max >= 150 ? `${a.age_min} ans et +` : `${a.age_min} - ${a.age_max} ans` }}</span>
                            </div>
                            <div class="flex gap-xs">
                                <button class="icon-btn" title="Modifier" @click="startAgeEdit(a)">
                                    <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                </button>
                                <button class="icon-btn danger" title="Supprimer" @click="destroyAge(a)">
                                    <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                </button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Modal d'erreur Tranches d'âge -->
        <div v-if="showAgeErrorModal" class="modal-overlay" @click.self="closeAgeErrorModal">
            <div class="modal-box">
                <div class="modal-icon-warning">
                    <span class="material-symbols-outlined" style="font-size:32px">warning</span>
                </div>
                <h3 class="modal-title">Suppression impossible</h3>
                <p class="modal-message">{{ ageErrorMessage }}</p>
                <button class="modal-btn" @click="closeAgeErrorModal">OK</button>
            </div>
        </div>

        <!-- ── Section : Assistant IA ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">smart_toy</span>
                    <div>
                        <h2 class="config-section-title">Assistant IA</h2>
                        <p class="config-section-sub">Connectez n'importe quel provider IA pour activer le chatbot.</p>
                    </div>
                </div>
                <span
                    class="count-pill"
                    :style="aiConfig.configured ? 'background:#dcfce7;color:#166534' : 'background:#fef9c3;color:#713f12'"
                >
                    {{ aiConfig.configured ? 'Configuré' : 'Non configuré' }}
                </span>
            </div>

            <div class="config-section-body">

                <!-- Statut -->
                <div class="flex items-center gap-sm mb-md p-sm rounded-lg" :class="aiConfig.configured ? 'bg-green-50' : 'bg-amber-50'">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="aiConfig.configured ? 'bg-green-500' : 'bg-amber-400'"></span>
                    <p class="text-body-sm" :class="aiConfig.configured ? 'text-green-800' : 'text-amber-800'">
                        {{ aiConfig.configured
                            ? `Clé API enregistrée — Provider actif : ${selectedProvider.name}`
                            : 'Aucune clé API. Sélectionnez un provider et saisissez votre clé.'
                        }}
                    </p>
                </div>

                <form @submit.prevent="submitAiKey" class="flex flex-col gap-sm">

                    <!-- Sélecteur provider -->
                    <div class="grid grid-cols-3 gap-xs">
                        <button
                            v-for="p in AI_PROVIDERS"
                            :key="p.key"
                            type="button"
                            @click="aiKeyForm.ai_provider = p.key"
                            class="provider-btn"
                            :class="aiKeyForm.ai_provider === p.key ? 'provider-btn-active' : 'provider-btn-inactive'"
                        >
                            {{ p.name }}
                        </button>
                    </div>
                    <p v-if="aiKeyForm.errors.ai_provider" class="error-msg">{{ aiKeyForm.errors.ai_provider }}</p>

                    <!-- Clé API -->
                    <div>
                        <label class="label">Clé API</label>
                        <div class="relative">
                            <input
                                v-model="aiKeyForm.ai_api_key"
                                :type="showApiKey ? 'text' : 'password'"
                                class="input w-full pr-10"
                                :class="{ 'input-error': aiKeyForm.errors.ai_api_key }"
                                :placeholder="selectedProvider.placeholder"
                            />
                            <button
                                type="button"
                                class="absolute right-sm top-1/2 -translate-y-1/2 text-secondary hover:text-on-surface"
                                @click="showApiKey = !showApiKey"
                            >
                                <span class="material-symbols-outlined" style="font-size:18px">
                                    {{ showApiKey ? 'visibility_off' : 'visibility' }}
                                </span>
                            </button>
                        </div>
                        <p v-if="aiKeyForm.errors.ai_api_key" class="error-msg">{{ aiKeyForm.errors.ai_api_key }}</p>
                    </div>

                    <!-- Modèle (optionnel) -->
                    <div>
                        <label class="label">Modèle <span class="text-secondary font-normal">(optionnel — laissez vide pour le défaut)</span></label>
                        <input
                            v-model="aiKeyForm.ai_model"
                            type="text"
                            class="input w-full"
                            :placeholder="selectedProvider.modelPlaceholder"
                        />
                    </div>

                    <!-- URL de base (custom uniquement) -->
                    <div v-if="selectedProvider.hasBaseUrl">
                        <label class="label">URL de base de l'API</label>
                        <input
                            v-model="aiKeyForm.ai_base_url"
                            type="url"
                            class="input w-full"
                            :class="{ 'input-error': aiKeyForm.errors.ai_base_url }"
                            placeholder="https://api.mon-provider.com/v1/chat/completions"
                        />
                        <p v-if="aiKeyForm.errors.ai_base_url" class="error-msg">{{ aiKeyForm.errors.ai_base_url }}</p>
                        <p class="text-[11px] text-secondary mt-xs">Compatible avec tout provider au format OpenAI (DeepSeek local, Ollama, etc.)</p>
                    </div>

                    <div class="flex items-center justify-between pt-xs">
                        <p class="text-[11px] text-secondary">
                            La clé est chiffrée en base et jamais exposée.
                        </p>
                        <button type="submit" class="btn-add" :disabled="aiKeyForm.processing || !aiKeyForm.ai_api_key">
                            <span class="material-symbols-outlined" style="font-size:18px">save</span>
                            {{ aiConfig.configured ? 'Mettre à jour' : 'Enregistrer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal d'erreur pour suppression impossible -->
        <div v-if="showEduErrorModal" class="modal-overlay" @click="closeEduErrorModal">
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <span class="material-symbols-outlined modal-icon">error</span>
                    <h3 class="modal-title">Suppression impossible</h3>
                </div>
                <div class="modal-body">
                    <p class="modal-text">{{ eduErrorMessage }}</p>
                </div>
                <div class="modal-footer">
                    <button @click="closeEduErrorModal" class="btn-close">Fermer</button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Section card */
.config-section {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    overflow: hidden;
}

.config-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}

.section-icon {
    font-size: 22px;
    color: #1F3A4D;
}

.config-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #191c1e;
}

.config-section-sub {
    font-size: 12px;
    color: #9aaabb;
    margin-top: 2px;
}

.count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    background: #eceef0;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}

.config-section-body {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Add form */
.add-form {
    display: flex;
    gap: 8px;
}

.label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #40484c;
    margin-bottom: 6px;
}

.provider-btn {
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid;
    transition: all 0.15s;
    text-align: center;
}
.provider-btn-active {
    background: #E5004C;
    color: #fff;
    border-color: #E5004C;
}
.provider-btn-inactive {
    background: #fff;
    color: #40484c;
    border-color: #e0e3e5;
}
.provider-btn-inactive:hover {
    border-color: #E5004C;
    color: #E5004C;
}

.input {
    padding: 9px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    flex: 1;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 9px 16px;
    background: #E5004C;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
}
.btn-add:hover:not(:disabled) { background: #c40042; }
.btn-add:disabled { opacity: 0.6; cursor: not-allowed; }

/* List */
.item-list {
    display: flex;
    flex-direction: column;
    border: 1px solid #f0f1f3;
    border-radius: 8px;
    overflow: hidden;
}

.item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid #f0f1f3;
    transition: background 0.12s;
}
.item-row:last-child { border-bottom: none; }
.item-row:hover { background: #fafbfc; }

.item-name {
    font-size: 14px;
    color: #191c1e;
}

.btn-icon-ok {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 7px;
    background: #dcfce7; color: #166534; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-icon-ok:hover { background: #bbf7d0; }

.btn-icon-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 7px;
    background: #f3f4f6; color: #6b7280; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-icon-cancel:hover { background: #e5e7eb; }

.icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 5px; border-radius: 6px; background: none; border: none;
    color: #9aaabb; cursor: pointer; transition: color 0.15s, background 0.15s;
}
.icon-btn:hover { color: #E5004C; background: #fdf0f4; }
.icon-btn.danger:hover { color: #ba1a1a; background: #fff5f5; }

.empty-state {
    display: flex; flex-direction: column; align-items: center;
    padding: 24px; text-align: center;
}

.gap-xs { gap: 4px; }
.gap-sm { gap: 8px; }
.mt-xs  { margin-top: 4px; }

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px;
    border-bottom: 1px solid #f0f1f3;
}
.modal-icon {
    font-size: 24px;
    color: #dc2626;
}
.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #191c1e;
    margin: 0;
}
.modal-body {
    padding: 20px 24px;
}
.modal-text {
    font-size: 14px;
    color: #515f74;
    margin: 0;
    line-height: 1.5;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding: 12px 24px 20px;
}
.btn-close {
    padding: 8px 16px;
    background: #1F3A4D;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-close:hover { background: #17303f; }
</style>
