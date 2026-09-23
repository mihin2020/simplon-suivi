<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }
interface EducationLevel { id: string; name: string }
interface AgeRange { id: number; name: string; age_min: number; age_max: number; order: number }
interface Vulnerability { id: string; name: string }
interface LastDiploma { id: string; name: string }
interface ContractTypeItem { id: string; name: string }
interface AiConfig { provider: string; model: string; base_url: string; configured: boolean }
interface AttendanceSettings { absenceAlertThreshold: number | null }
const props = defineProps<{
    trainerProfiles: Profile[]
    educationLevels: EducationLevel[]
    ageRanges: AgeRange[]
    vulnerabilities: Vulnerability[]
    lastDiplomas: LastDiploma[]
    internshipContractTypes: ContractTypeItem[]
    employmentContractTypes: ContractTypeItem[]
    aiConfig: AiConfig
    attendanceSettings: AttendanceSettings
}>()

// ── Navigation par onglets ──
const activeTab = ref<'referentiels' | 'ia'>('referentiels')

// ── Toast ──
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout>
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    clearTimeout(toastTimer)
    toast.value = { message, type }
    toastTimer = setTimeout(() => { toast.value = null }, 3500)
}

// ── Modale de confirmation unifiée ──
const confirmDialog = ref<{ visible: boolean; title: string; message: string; onConfirm: () => void }>({
    visible: false, title: '', message: '', onConfirm: () => {}
})
const askConfirm = (title: string, message: string, onConfirm: () => void) => {
    confirmDialog.value = { visible: true, title, message, onConfirm }
}
const doConfirm = () => { confirmDialog.value.onConfirm(); confirmDialog.value.visible = false }

// ── Modale d'erreur unifiée ──
const errorDialog = ref<{ visible: boolean; message: string }>({ visible: false, message: '' })
const showError = (message: string) => { errorDialog.value = { visible: true, message } }

// ── AI Providers ──
const AI_PROVIDERS = [
    { key: 'claude',   name: 'Claude',   sub: 'Anthropic',  placeholder: 'sk-ant-api03-...', modelPlaceholder: 'claude-opus-4-5',  hasBaseUrl: false },
    { key: 'openai',   name: 'OpenAI',   sub: 'ChatGPT',    placeholder: 'sk-...',           modelPlaceholder: 'gpt-4o-mini',      hasBaseUrl: false },
    { key: 'deepseek', name: 'DeepSeek', sub: 'DeepSeek',   placeholder: 'sk-...',           modelPlaceholder: 'deepseek-chat',    hasBaseUrl: false },
    { key: 'grok',     name: 'Grok',     sub: 'xAI',        placeholder: 'xai-...',          modelPlaceholder: 'grok-3-mini',      hasBaseUrl: false },
    { key: 'gemini',   name: 'Gemini',   sub: 'Google',     placeholder: 'AIza...',          modelPlaceholder: 'gemini-2.0-flash', hasBaseUrl: false },
    { key: 'custom',   name: 'Custom',   sub: 'Compatible OpenAI', placeholder: 'Votre clé API', modelPlaceholder: 'nom-du-modèle', hasBaseUrl: true },
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
        onSuccess: () => { aiKeyForm.ai_api_key = ''; showToast('Configuration IA mise à jour') },
    })
}
const showDeactivateModal = ref(false)
const deactivateForm = useForm({})
const confirmDeactivate = () => {
    deactivateForm.delete('/configuration/ai-key', {
        onSuccess: () => { showDeactivateModal.value = false; showToast('Assistant IA désactivé') },
    })
}

// ── Profils Formateurs ──
const createForm = useForm({ name: '' })
const editingId  = ref<string | null>(null)
const editForm   = useForm({ name: '' })

const startEdit  = (p: Profile) => { editingId.value = p.id; editForm.name = p.name }
const cancelEdit = () => { editingId.value = null; editForm.reset() }
const submitCreate = () => {
    createForm.post('/trainer-profiles', {
        onSuccess: () => { createForm.reset(); showToast('Profil ajouté') }
    })
}
const submitEdit = (id: string) => {
    editForm.put(`/trainer-profiles/${id}`, {
        onSuccess: () => { cancelEdit(); showToast('Profil modifié') }
    })
}
const destroy = (p: Profile) => {
    askConfirm('Supprimer le profil', `Supprimer « ${p.name} » ? Cette action est irréversible.`, () => {
        router.delete(`/trainer-profiles/${p.id}`, {
            onSuccess: () => showToast('Profil supprimé'),
            onError: (e) => { if (e.message) showError(e.message) }
        })
    })
}

// ── Niveaux d'études ──
const eduCreateForm = useForm({ name: '' })
const eduEditingId  = ref<string | null>(null)
const eduEditForm   = useForm({ name: '' })

const startEduEdit  = (e: EducationLevel) => { eduEditingId.value = e.id; eduEditForm.name = e.name }
const cancelEduEdit = () => { eduEditingId.value = null; eduEditForm.reset() }
const submitEduCreate = () => {
    eduCreateForm.post('/education-levels', {
        onSuccess: () => { eduCreateForm.reset(); showToast('Niveau ajouté') }
    })
}
const submitEduEdit = (id: string) => {
    eduEditForm.put(`/education-levels/${id}`, {
        onSuccess: () => { cancelEduEdit(); showToast('Niveau modifié') }
    })
}
const destroyEdu = (e: EducationLevel) => {
    askConfirm('Supprimer le niveau', `Supprimer « ${e.name} » ?`, () => {
        router.delete(`/education-levels/${e.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Niveau supprimé'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Tranches d'âge ──
const ageCreateForm = useForm({ age_min: 18, age_max: 25 })
const ageEditingId  = ref<number | null>(null)
const ageEditForm   = useForm({ age_min: 0, age_max: 0 })

const startAgeEdit  = (a: AgeRange) => { ageEditingId.value = a.id; ageEditForm.age_min = a.age_min; ageEditForm.age_max = a.age_max }
const cancelAgeEdit = () => { ageEditingId.value = null; ageEditForm.reset() }
const submitAgeCreate = () => {
    ageCreateForm.post('/age-ranges', {
        onSuccess: () => { ageCreateForm.reset(); showToast('Tranche ajoutée') }
    })
}
const submitAgeEdit = (id: number) => {
    ageEditForm.put(`/age-ranges/${id}`, {
        onSuccess: () => { cancelAgeEdit(); showToast('Tranche modifiée') }
    })
}
const destroyAge = (a: AgeRange) => {
    askConfirm('Supprimer la tranche', `Supprimer « ${a.name} » ?`, () => {
        router.delete(`/age-ranges/${a.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Tranche supprimée'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Vulnérabilités ──
const vulnCreateForm = useForm({ name: '' })
const vulnEditingId  = ref<string | null>(null)
const vulnEditForm   = useForm({ name: '' })

const startVulnEdit  = (v: Vulnerability) => { vulnEditingId.value = v.id; vulnEditForm.name = v.name }
const cancelVulnEdit = () => { vulnEditingId.value = null; vulnEditForm.reset() }
const submitVulnCreate = () => {
    vulnCreateForm.post('/vulnerabilities', {
        onSuccess: () => { vulnCreateForm.reset(); showToast('Vulnérabilité ajoutée') }
    })
}
const submitVulnEdit = (id: string) => {
    vulnEditForm.put(`/vulnerabilities/${id}`, {
        onSuccess: () => { cancelVulnEdit(); showToast('Vulnérabilité modifiée') }
    })
}
const destroyVuln = (v: Vulnerability) => {
    askConfirm('Supprimer la vulnérabilité', `Supprimer « ${v.name} » ?`, () => {
        router.delete(`/vulnerabilities/${v.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Vulnérabilité supprimée'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Diplômes ──
const diplCreateForm = useForm({ name: '' })
const diplEditingId  = ref<string | null>(null)
const diplEditForm   = useForm({ name: '' })

const startDiplEdit  = (d: LastDiploma) => { diplEditingId.value = d.id; diplEditForm.name = d.name }
const cancelDiplEdit = () => { diplEditingId.value = null; diplEditForm.reset() }
const submitDiplCreate = () => {
    diplCreateForm.post('/last-diplomas', {
        onSuccess: () => { diplCreateForm.reset(); showToast('Diplôme ajouté') }
    })
}
const submitDiplEdit = (id: string) => {
    diplEditForm.put(`/last-diplomas/${id}`, {
        onSuccess: () => { cancelDiplEdit(); showToast('Diplôme modifié') }
    })
}
const destroyDipl = (d: LastDiploma) => {
    askConfirm('Supprimer le diplôme', `Supprimer « ${d.name} » ?`, () => {
        router.delete(`/last-diplomas/${d.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Diplôme supprimé'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Types de contrat stage ──
const stageTypeCreateForm = useForm({ name: '', context: 'internship' })
const stageTypeEditingId = ref<string | null>(null)
const stageTypeEditForm = useForm({ name: '' })

const startStageTypeEdit = (item: ContractTypeItem) => { stageTypeEditingId.value = item.id; stageTypeEditForm.name = item.name }
const cancelStageTypeEdit = () => { stageTypeEditingId.value = null; stageTypeEditForm.reset() }
const submitStageTypeCreate = () => {
    stageTypeCreateForm.post('/contract-types', {
        onSuccess: () => { stageTypeCreateForm.reset(); stageTypeCreateForm.context = 'internship'; showToast('Type de stage ajouté') },
        onError: (errors) => { if (errors.message) showError(errors.message) }
    })
}
const submitStageTypeEdit = (id: string) => {
    stageTypeEditForm.put(`/contract-types/${id}`, {
        onSuccess: () => { cancelStageTypeEdit(); showToast('Type de stage modifié') },
        onError: (errors) => { if (errors.message) showError(errors.message) }
    })
}
const destroyStageType = (item: ContractTypeItem) => {
    askConfirm('Supprimer le type', `Supprimer « ${item.name} » ?`, () => {
        router.delete(`/contract-types/${item.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Type de stage supprimé'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Types de contrat emploi ──
const employmentTypeCreateForm = useForm({ name: '', context: 'employment' })
const employmentTypeEditingId = ref<string | null>(null)
const employmentTypeEditForm = useForm({ name: '' })

const startEmploymentTypeEdit = (item: ContractTypeItem) => { employmentTypeEditingId.value = item.id; employmentTypeEditForm.name = item.name }
const cancelEmploymentTypeEdit = () => { employmentTypeEditingId.value = null; employmentTypeEditForm.reset() }
const submitEmploymentTypeCreate = () => {
    employmentTypeCreateForm.post('/contract-types', {
        onSuccess: () => { employmentTypeCreateForm.reset(); employmentTypeCreateForm.context = 'employment'; showToast('Type d\'emploi ajouté') },
        onError: (errors) => { if (errors.message) showError(errors.message) }
    })
}
const submitEmploymentTypeEdit = (id: string) => {
    employmentTypeEditForm.put(`/contract-types/${id}`, {
        onSuccess: () => { cancelEmploymentTypeEdit(); showToast('Type d\'emploi modifié') },
        onError: (errors) => { if (errors.message) showError(errors.message) }
    })
}
const destroyEmploymentType = (item: ContractTypeItem) => {
    askConfirm('Supprimer le type', `Supprimer « ${item.name} » ?`, () => {
        router.delete(`/contract-types/${item.id}`, {
            preserveState: true, preserveScroll: true,
            onSuccess: () => showToast('Type d\'emploi supprimé'),
            onError: (errors) => { if (errors.message) showError(errors.message) }
        })
    })
}

// ── Présences ──
const attendanceSettingsForm = useForm({
    absence_alert_threshold: props.attendanceSettings.absenceAlertThreshold ?? '' as number | '',
})
const submitAttendanceSettings = () => {
    attendanceSettingsForm.transform(data => ({
        absence_alert_threshold: data.absence_alert_threshold === '' ? null : Number(data.absence_alert_threshold),
    })).post('/configuration/attendance-settings', {
        onSuccess: () => showToast('Paramètres de présences enregistrés'),
        onError: (errors) => {
            const first = Object.values(errors)[0]
            if (typeof first === 'string') showError(first)
        },
    })
}
</script>

<template>
    <Head title="Configuration" />
    <div class="cfg-page">

        <!-- ── Toast ── -->
        <Teleport to="body">
            <Transition name="toast">
                <div v-if="toast" class="toast" :class="toast.type === 'error' ? 'toast-error' : 'toast-success'">
                    <span class="material-symbols-outlined" style="font-size:19px">
                        {{ toast.type === 'error' ? 'error' : 'check_circle' }}
                    </span>
                    {{ toast.message }}
                </div>
            </Transition>
        </Teleport>

        <!-- ── Page header ── -->
        <div class="cfg-header">
            <div class="cfg-header-icon">
                <span class="material-symbols-outlined">settings</span>
            </div>
            <div>
                <h1 class="cfg-title">Configuration</h1>
                <p class="cfg-subtitle">Paramètres globaux et données métier de l'application</p>
            </div>
        </div>

        <!-- ── Onglets ── -->
        <div class="tabs-bar">
            <button class="tab-btn" :class="{ 'tab-active': activeTab === 'referentiels' }" @click="activeTab = 'referentiels'">
                <span class="material-symbols-outlined tab-icon">tune</span>
                Données métier
            </button>
            <button class="tab-btn" :class="{ 'tab-active': activeTab === 'ia' }" @click="activeTab = 'ia'">
                <span class="material-symbols-outlined tab-icon">smart_toy</span>
                Assistant IA
                <span class="tab-badge" :class="aiConfig.configured ? 'badge-ok' : 'badge-warn'">
                    {{ aiConfig.configured ? 'Actif' : 'Inactif' }}
                </span>
            </button>
        </div>

        <!-- ══════════ ONGLET DONNÉES MÉTIER ══════════ -->
        <div v-if="activeTab === 'referentiels'" class="cfg-grid">

            <!-- Profils Formateurs -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#fff0f4;color:#E5004C">
                        <span class="material-symbols-outlined">person_badge</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Profils Formateurs</h2>
                        <p class="cfg-card-sub">Types disponibles lors de l'invitation d'un formateur.</p>
                    </div>
                    <span class="count-pill">{{ trainerProfiles.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitCreate" class="add-row">
                        <input v-model="createForm.name" type="text" class="add-input" :class="{ 'input-error': createForm.errors.name }" placeholder="Ex : Formateur principal, Vacataire..." />
                        <button type="submit" class="add-btn" :disabled="createForm.processing || !createForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <p v-if="createForm.errors.name" class="err">{{ createForm.errors.name }}</p>
                    <div v-if="trainerProfiles.length === 0" class="empty-inline">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dde1e5">inbox</span>
                        <span>Aucun profil — ajoutez-en un ci-dessus.</span>
                    </div>
                    <ul v-else class="ref-list">
                        <li v-for="p in trainerProfiles" :key="p.id" class="ref-item">
                            <form v-if="editingId === p.id" @submit.prevent="submitEdit(p.id)" class="edit-row">
                                <input v-model="editForm.name" type="text" class="add-input" :class="{ 'input-error': editForm.errors.name }" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ p.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startEdit(p)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroy(p)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Niveaux d'études -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#dbeafe;color:#1d4ed8">
                        <span class="material-symbols-outlined">school</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Niveaux d'études</h2>
                        <p class="cfg-card-sub">Niveaux d'études disponibles pour les apprenants.</p>
                    </div>
                    <span class="count-pill">{{ educationLevels.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitEduCreate" class="add-row">
                        <input v-model="eduCreateForm.name" type="text" class="add-input" :class="{ 'input-error': eduCreateForm.errors.name }" placeholder="Ex : Bac+2, Bac+3, Master..." />
                        <button type="submit" class="add-btn" :disabled="eduCreateForm.processing || !eduCreateForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <p v-if="eduCreateForm.errors.name" class="err">{{ eduCreateForm.errors.name }}</p>
                    <div v-if="educationLevels.length === 0" class="empty-inline">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dde1e5">inbox</span>
                        <span>Aucun niveau — ajoutez-en un ci-dessus.</span>
                    </div>
                    <ul v-else class="ref-list">
                        <li v-for="e in educationLevels" :key="e.id" class="ref-item">
                            <form v-if="eduEditingId === e.id" @submit.prevent="submitEduEdit(e.id)" class="edit-row">
                                <input v-model="eduEditForm.name" type="text" class="add-input" :class="{ 'input-error': eduEditForm.errors.name }" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelEduEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ e.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startEduEdit(e)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyEdu(e)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tranches d'âge -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#fef3c7;color:#b45309">
                        <span class="material-symbols-outlined">people</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Tranches d'âge</h2>
                        <p class="cfg-card-sub">Catégories calculées depuis la date de naissance.</p>
                    </div>
                    <span class="count-pill">{{ ageRanges.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitAgeCreate" class="add-row add-row-age">
                        <div class="age-inputs">
                            <div class="age-field">
                                <label class="age-lbl">Min</label>
                                <input v-model.number="ageCreateForm.age_min" type="number" min="0" max="150" class="add-input age-num" :class="{ 'input-error': ageCreateForm.errors.age_min }" placeholder="18" />
                            </div>
                            <span class="age-sep">–</span>
                            <div class="age-field">
                                <label class="age-lbl">Max</label>
                                <input v-model.number="ageCreateForm.age_max" type="number" min="0" max="150" class="add-input age-num" :class="{ 'input-error': ageCreateForm.errors.age_max }" placeholder="25" />
                            </div>
                            <span class="age-unit">ans</span>
                        </div>
                        <button type="submit" class="add-btn" :disabled="ageCreateForm.processing">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <p v-if="ageCreateForm.errors.age_min" class="err">{{ ageCreateForm.errors.age_min }}</p>
                    <p v-if="ageCreateForm.errors.age_max" class="err">{{ ageCreateForm.errors.age_max }}</p>
                    <div v-if="ageRanges.length === 0" class="empty-inline">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dde1e5">inbox</span>
                        <span>Aucune tranche — ajoutez-en une ci-dessus.</span>
                    </div>
                    <ul v-else class="ref-list">
                        <li v-for="a in ageRanges" :key="a.id" class="ref-item">
                            <form v-if="ageEditingId === a.id" @submit.prevent="submitAgeEdit(a.id)" class="edit-row edit-row-age">
                                <input v-model.number="ageEditForm.age_min" type="number" min="0" max="150" class="add-input age-num" placeholder="Min" :class="{ 'input-error': ageEditForm.errors.age_min }" autofocus />
                                <span class="age-sep">–</span>
                                <input v-model.number="ageEditForm.age_max" type="number" min="0" max="150" class="add-input age-num" placeholder="Max" :class="{ 'input-error': ageEditForm.errors.age_max }" />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelAgeEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="age-chip">{{ a.age_max >= 150 ? `${a.age_min}+` : `${a.age_min}–${a.age_max}` }}</span>
                                <span class="ref-name">{{ a.age_max >= 150 ? `${a.age_min} ans et plus` : `${a.age_min} à ${a.age_max} ans` }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startAgeEdit(a)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyAge(a)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Vulnérabilités -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#ffdad6;color:#ba1a1a">
                        <span class="material-symbols-outlined">health_and_safety</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Vulnérabilités</h2>
                        <p class="cfg-card-sub">Types de vulnérabilités pour les apprenants (PDI, etc.).</p>
                    </div>
                    <span class="count-pill">{{ vulnerabilities.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitVulnCreate" class="add-row">
                        <input v-model="vulnCreateForm.name" type="text" class="add-input" :class="{ 'input-error': vulnCreateForm.errors.name }" placeholder="Ex : PDI, Réfugié, Handicap..." />
                        <button type="submit" class="add-btn" :disabled="vulnCreateForm.processing || !vulnCreateForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <p v-if="vulnCreateForm.errors.name" class="err">{{ vulnCreateForm.errors.name }}</p>
                    <div v-if="vulnerabilities.length === 0" class="empty-inline">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dde1e5">inbox</span>
                        <span>Aucune vulnérabilité — ajoutez-en une ci-dessus.</span>
                    </div>
                    <ul v-else class="ref-list">
                        <li v-for="v in vulnerabilities" :key="v.id" class="ref-item">
                            <form v-if="vulnEditingId === v.id" @submit.prevent="submitVulnEdit(v.id)" class="edit-row">
                                <input v-model="vulnEditForm.name" type="text" class="add-input" :class="{ 'input-error': vulnEditForm.errors.name }" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelVulnEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ v.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startVulnEdit(v)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyVuln(v)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Derniers diplômes -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#d1fae5;color:#065f46">
                        <span class="material-symbols-outlined">workspace_premium</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Derniers diplômes</h2>
                        <p class="cfg-card-sub">Diplômes obtenus par les apprenants avant la formation.</p>
                    </div>
                    <span class="count-pill">{{ lastDiplomas.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitDiplCreate" class="add-row">
                        <input v-model="diplCreateForm.name" type="text" class="add-input" :class="{ 'input-error': diplCreateForm.errors.name }" placeholder="Ex : Bac, BTS, Licence, Master..." />
                        <button type="submit" class="add-btn" :disabled="diplCreateForm.processing || !diplCreateForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <p v-if="diplCreateForm.errors.name" class="err">{{ diplCreateForm.errors.name }}</p>
                    <div v-if="lastDiplomas.length === 0" class="empty-inline">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dde1e5">inbox</span>
                        <span>Aucun diplôme — ajoutez-en un ci-dessus.</span>
                    </div>
                    <ul v-else class="ref-list">
                        <li v-for="d in lastDiplomas" :key="d.id" class="ref-item">
                            <form v-if="diplEditingId === d.id" @submit.prevent="submitDiplEdit(d.id)" class="edit-row">
                                <input v-model="diplEditForm.name" type="text" class="add-input" :class="{ 'input-error': diplEditForm.errors.name }" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelDiplEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ d.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startDiplEdit(d)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyDipl(d)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Présences -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#fee2e2;color:#991b1b">
                        <span class="material-symbols-outlined">fact_check</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Présences</h2>
                        <p class="cfg-card-sub">Alerte visuelle dans le récapitulatif des absences (AJ + AN).</p>
                    </div>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitAttendanceSettings" class="attendance-settings-form">
                        <label class="cfg-field-label" for="absence-threshold">Seuil d'alerte (nombre d'absences)</label>
                        <div class="add-row">
                            <input
                                id="absence-threshold"
                                v-model="attendanceSettingsForm.absence_alert_threshold"
                                type="number"
                                min="1"
                                max="999"
                                class="add-input"
                                :class="{ 'input-error': attendanceSettingsForm.errors.absence_alert_threshold }"
                                placeholder="Ex : 5 — laisser vide pour désactiver"
                            />
                            <button type="submit" class="add-btn" :disabled="attendanceSettingsForm.processing">
                                <span class="material-symbols-outlined" style="font-size:17px">save</span>
                                Enregistrer
                            </button>
                        </div>
                        <p v-if="attendanceSettingsForm.errors.absence_alert_threshold" class="err">
                            {{ attendanceSettingsForm.errors.absence_alert_threshold }}
                        </p>
                        <p class="cfg-hint">
                            À partir de ce seuil, la ligne de l'apprenant apparaît en rouge dans le récapitulatif complet des présences.
                        </p>
                    </form>
                </div>
            </div>

            <!-- Types de contrat de stage -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#fff0f4;color:#E5004C">
                        <span class="material-symbols-outlined">business_center</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Types de contrat de stage</h2>
                        <p class="cfg-card-sub">Options disponibles lors de l'ajout d'un stage.</p>
                    </div>
                    <span class="count-pill">{{ internshipContractTypes.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitStageTypeCreate" class="add-row">
                        <input v-model="stageTypeCreateForm.name" type="text" class="add-input" placeholder="Ex : Contrat de stage, Stage étudiant..." />
                        <button type="submit" class="add-btn" :disabled="stageTypeCreateForm.processing || !stageTypeCreateForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <ul v-if="internshipContractTypes.length" class="ref-list">
                        <li v-for="item in internshipContractTypes" :key="item.id" class="ref-item">
                            <form v-if="stageTypeEditingId === item.id" @submit.prevent="submitStageTypeEdit(item.id)" class="edit-row">
                                <input v-model="stageTypeEditForm.name" type="text" class="add-input" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelStageTypeEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ item.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startStageTypeEdit(item)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyStageType(item)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                    <div v-else class="empty-inline">Aucun type — ajoutez-en un ci-dessus.</div>
                </div>
            </div>

            <!-- Types de contrat d'emploi -->
            <div class="cfg-card">
                <div class="cfg-card-head">
                    <div class="cfg-card-icon" style="background:#dbeafe;color:#1d4ed8">
                        <span class="material-symbols-outlined">work</span>
                    </div>
                    <div class="cfg-card-info">
                        <h2 class="cfg-card-title">Types de contrat d'emploi</h2>
                        <p class="cfg-card-sub">Options disponibles lors de l'ajout d'un emploi.</p>
                    </div>
                    <span class="count-pill">{{ employmentContractTypes.length }}</span>
                </div>
                <div class="cfg-card-body">
                    <form @submit.prevent="submitEmploymentTypeCreate" class="add-row">
                        <input v-model="employmentTypeCreateForm.name" type="text" class="add-input" placeholder="Ex : CDI, CDD, Freelance..." />
                        <button type="submit" class="add-btn" :disabled="employmentTypeCreateForm.processing || !employmentTypeCreateForm.name.trim()">
                            <span class="material-symbols-outlined" style="font-size:17px">add</span> Ajouter
                        </button>
                    </form>
                    <ul v-if="employmentContractTypes.length" class="ref-list">
                        <li v-for="item in employmentContractTypes" :key="item.id" class="ref-item">
                            <form v-if="employmentTypeEditingId === item.id" @submit.prevent="submitEmploymentTypeEdit(item.id)" class="edit-row">
                                <input v-model="employmentTypeEditForm.name" type="text" class="add-input" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelEmploymentTypeEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="ref-dot"></span>
                                <span class="ref-name">{{ item.name }}</span>
                                <div class="ref-actions">
                                    <button class="ref-btn" title="Modifier" @click="startEmploymentTypeEdit(item)"><span class="material-symbols-outlined" style="font-size:16px">edit</span></button>
                                    <button class="ref-btn danger" title="Supprimer" @click="destroyEmploymentType(item)"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
                                </div>
                            </template>
                        </li>
                    </ul>
                    <div v-else class="empty-inline">Aucun type — ajoutez-en un ci-dessus.</div>
                </div>
            </div>

        </div>

        <!-- ══════════ ONGLET IA ══════════ -->
        <div v-if="activeTab === 'ia'" class="ia-layout">

            <!-- Statut global -->
            <div class="ia-status-banner" :class="aiConfig.configured ? 'ia-ok' : 'ia-warn'">
                <div class="ia-status-dot" :class="aiConfig.configured ? 'dot-ok' : 'dot-warn'"></div>
                <div>
                    <p class="ia-status-title">
                        {{ aiConfig.configured ? 'Assistant IA opérationnel' : 'Assistant IA non configuré' }}
                    </p>
                    <p class="ia-status-sub">
                        {{ aiConfig.configured
                            ? `Provider actif : ${selectedProvider.name} (${selectedProvider.sub})`
                            : 'Sélectionnez un provider et saisissez votre clé API pour activer le chatbot.' }}
                    </p>
                </div>
            </div>

            <div class="ia-cols">
                <!-- Sélecteur provider -->
                <div class="ia-col-left">
                    <h3 class="ia-section-title">Choisir un provider</h3>
                    <p class="ia-col-hint">Un seul provider actif à la fois. Enregistrer une nouvelle clé remplace la précédente.</p>
                    <div class="provider-grid">
                        <button
                            v-for="p in AI_PROVIDERS"
                            :key="p.key"
                            type="button"
                            class="provider-card"
                            :class="[
                                aiKeyForm.ai_provider === p.key ? 'provider-card-selected' : 'provider-card-inactive',
                                aiConfig.configured && aiConfig.provider === p.key ? 'provider-card-live' : ''
                            ]"
                            @click="aiKeyForm.ai_provider = p.key"
                        >
                            <div class="provider-card-top">
                                <span class="provider-name">{{ p.name }}</span>
                                <span v-if="aiConfig.configured && aiConfig.provider === p.key" class="provider-live-badge">
                                    <span class="provider-live-dot"></span> Actif
                                </span>
                            </div>
                            <span class="provider-sub">{{ p.sub }}</span>
                            <span v-if="aiKeyForm.ai_provider === p.key && !(aiConfig.configured && aiConfig.provider === p.key)" class="provider-check material-symbols-outlined">check_circle</span>
                        </button>
                    </div>
                </div>

                <!-- Formulaire clé -->
                <div class="ia-col-right">
                    <h3 class="ia-section-title">Paramètres de connexion</h3>
                    <form @submit.prevent="submitAiKey" class="ia-form">

                        <div class="ia-field">
                            <label class="ia-label">Clé API <span class="req">*</span></label>
                            <div class="key-wrap">
                                <input
                                    v-model="aiKeyForm.ai_api_key"
                                    :type="showApiKey ? 'text' : 'password'"
                                    class="ia-input"
                                    :class="{ 'input-error': aiKeyForm.errors.ai_api_key }"
                                    :placeholder="selectedProvider.placeholder"
                                />
                                <button type="button" class="eye-btn" @click="showApiKey = !showApiKey" :title="showApiKey ? 'Masquer' : 'Afficher'">
                                    <span class="material-symbols-outlined" style="font-size:18px">{{ showApiKey ? 'visibility_off' : 'visibility' }}</span>
                                </button>
                            </div>
                            <p v-if="aiKeyForm.errors.ai_api_key" class="err">{{ aiKeyForm.errors.ai_api_key }}</p>
                            <p class="ia-hint"><span class="material-symbols-outlined" style="font-size:12px">lock</span> Chiffrée en base, jamais exposée côté client.</p>
                        </div>

                        <div class="ia-field">
                            <label class="ia-label">Modèle <span class="ia-optional">optionnel</span></label>
                            <input v-model="aiKeyForm.ai_model" type="text" class="ia-input" :placeholder="selectedProvider.modelPlaceholder" />
                            <p class="ia-hint">Laissez vide pour utiliser le modèle par défaut du provider.</p>
                        </div>

                        <div v-if="selectedProvider.hasBaseUrl" class="ia-field">
                            <label class="ia-label">URL de base de l'API</label>
                            <input v-model="aiKeyForm.ai_base_url" type="url" class="ia-input" :class="{ 'input-error': aiKeyForm.errors.ai_base_url }" placeholder="https://api.mon-provider.com/v1/chat/completions" />
                            <p v-if="aiKeyForm.errors.ai_base_url" class="err">{{ aiKeyForm.errors.ai_base_url }}</p>
                            <p class="ia-hint">Compatible OpenAI (DeepSeek local, Ollama, LM Studio…)</p>
                        </div>

                        <div class="ia-footer">
                            <button type="submit" class="ia-save-btn" :disabled="aiKeyForm.processing || !aiKeyForm.ai_api_key">
                                <span v-if="aiKeyForm.processing" class="spin-sm"></span>
                                <span v-else class="material-symbols-outlined" style="font-size:17px">save</span>
                                {{ aiKeyForm.processing ? 'Enregistrement...' : (aiConfig.configured ? 'Mettre à jour' : 'Enregistrer la configuration') }}
                            </button>
                            <button v-if="aiConfig.configured" type="button" class="ia-deactivate-btn" @click="showDeactivateModal = true">
                                <span class="material-symbols-outlined" style="font-size:17px">power_settings_new</span>
                                Désactiver
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Modale de confirmation ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirmDialog.visible" class="modal-overlay" @click.self="confirmDialog.visible = false">
                    <div class="modal-box">
                        <div class="modal-box-head">
                            <div class="modal-warn-icon"><span class="material-symbols-outlined" style="font-size:20px">warning</span></div>
                            <h3 class="modal-box-title">{{ confirmDialog.title }}</h3>
                        </div>
                        <p class="modal-box-msg">{{ confirmDialog.message }}</p>
                        <div class="modal-box-foot">
                            <button class="modal-cancel" @click="confirmDialog.visible = false">Annuler</button>
                            <button class="modal-delete" @click="doConfirm">
                                <span class="material-symbols-outlined" style="font-size:16px">delete</span> Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modale désactivation IA ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDeactivateModal" class="modal-overlay" @click.self="showDeactivateModal = false">
                    <div class="modal-box">
                        <div class="modal-box-head">
                            <div class="modal-warn-icon"><span class="material-symbols-outlined" style="font-size:20px">power_settings_new</span></div>
                            <h3 class="modal-box-title">Désactiver l'assistant IA</h3>
                        </div>
                        <p class="modal-box-msg">La clé API et la configuration seront effacées. Le chatbot ne sera plus disponible. Vous pourrez reconfigurer un provider à tout moment.</p>
                        <div class="modal-box-foot">
                            <button class="modal-cancel" @click="showDeactivateModal = false">Annuler</button>
                            <button class="modal-delete" @click="confirmDeactivate" :disabled="deactivateForm.processing">
                                <span class="material-symbols-outlined" style="font-size:16px">power_settings_new</span>
                                {{ deactivateForm.processing ? 'Désactivation...' : 'Désactiver' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modale d'erreur ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="errorDialog.visible" class="modal-overlay" @click.self="errorDialog.visible = false">
                    <div class="modal-box">
                        <div class="modal-box-head">
                            <div class="modal-err-icon"><span class="material-symbols-outlined" style="font-size:20px">error</span></div>
                            <h3 class="modal-box-title">Suppression impossible</h3>
                        </div>
                        <p class="modal-box-msg">{{ errorDialog.message }}</p>
                        <div class="modal-box-foot">
                            <button class="modal-ok" @click="errorDialog.visible = false">Compris</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </div>
</template>

<style scoped>
/* ── Page layout ─────────────────────────────────────────────────────────── */
.cfg-page { max-width: 1100px; margin: 0 auto; }

/* ── Header ──────────────────────────────────────────────────────────────── */
.cfg-header {
    display: flex; align-items: center; gap: 16px; margin-bottom: 24px;
}
.cfg-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.cfg-header-icon .material-symbols-outlined { font-size: 24px; }
.cfg-title    { font-size: 22px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.cfg-subtitle { font-size: 13px; color: #515f74; margin-top: 2px; }

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.tabs-bar {
    display: flex; gap: 4px; padding: 4px;
    background: #f2f4f6; border-radius: 10px;
    margin-bottom: 24px; width: fit-content;
}
.tab-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; border-radius: 7px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    border: none; background: transparent; color: #515f74;
    transition: all 0.15s;
}
.tab-btn:hover:not(.tab-active) { background: #e8eaec; color: #191c1e; }
.tab-active { background: #fff; color: #191c1e; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.tab-icon { font-size: 17px; }
.tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 5px;
    background: #e0e3e5; color: #515f74;
    border-radius: 99px; font-size: 11px; font-weight: 700;
}
.tab-active .tab-count { background: #fff0f4; color: #E5004C; }
.tab-badge {
    display: inline-flex; padding: 2px 8px; border-radius: 99px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
}
.badge-ok   { background: #d1fae5; color: #065f46; }
.badge-warn { background: #fef3c7; color: #b45309; }

/* ── Referentiels grid ───────────────────────────────────────────────────── */
.cfg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 16px;
}

/* ── Card ────────────────────────────────────────────────────────────────── */
.cfg-card {
    background: #fff; border-radius: 12px;
    border: 1px solid #e0e3e5; overflow: hidden;
    transition: box-shadow 0.15s;
}
.cfg-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }

.cfg-card-head {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; border-bottom: 1px solid #f2f4f6;
    background: #fafbfc;
}
.cfg-card-icon {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 9px; flex-shrink: 0;
}
.cfg-card-icon .material-symbols-outlined { font-size: 20px; }
.cfg-card-info { flex: 1; min-width: 0; }
.cfg-card-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.cfg-card-sub   { font-size: 12px; color: #515f74; margin-top: 1px; }

.count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 7px;
    background: #eceef0; border-radius: 99px;
    font-size: 11px; font-weight: 700; color: #515f74; flex-shrink: 0;
}

.cfg-card-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; }
.cfg-field-label {
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}
.cfg-hint {
    font-size: 12px;
    color: #9aaabb;
    line-height: 1.45;
    margin: 0;
}

/* ── Add row ─────────────────────────────────────────────────────────────── */
.add-row { display: flex; gap: 8px; align-items: center; }
.add-row-age { flex-wrap: wrap; }
.add-input {
    flex: 1; padding: 9px 12px;
    border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; color: #191c1e; background: #fafafa;
    outline: none; font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.add-input:focus { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229,0,76,0.07); }
.input-error { border-color: #ba1a1a !important; }

.add-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 9px 14px; background: #E5004C; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; white-space: nowrap; transition: all 0.15s; flex-shrink: 0;
}
.add-btn:hover:not(:disabled) { background: #c40042; transform: translateY(-1px); }
.add-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

/* ── Age inputs ──────────────────────────────────────────────────────────── */
.age-inputs { display: flex; align-items: center; gap: 6px; flex: 1; }
.age-field  { display: flex; flex-direction: column; gap: 2px; }
.age-lbl    { font-size: 10px; font-weight: 600; color: #515f74; text-transform: uppercase; letter-spacing: 0.3px; }
.age-num    { width: 70px; flex: none; text-align: center; }
.age-sep    { font-size: 16px; color: #adb5bd; font-weight: 300; margin-top: 14px; }
.age-unit   { font-size: 12px; color: #515f74; font-weight: 600; margin-top: 14px; }
.age-chip {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 46px; height: 24px; padding: 0 8px;
    background: #fef3c7; color: #b45309;
    border-radius: 6px; font-size: 12px; font-weight: 700; flex-shrink: 0;
}

/* ── Ref list ────────────────────────────────────────────────────────────── */
.ref-list { display: flex; flex-direction: column; border: 1px solid #f2f4f6; border-radius: 8px; overflow: hidden; margin: 0; padding: 0; list-style: none; }
.ref-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-bottom: 1px solid #f2f4f6;
    transition: background 0.12s;
}
.ref-item:last-child { border-bottom: none; }
.ref-item:hover { background: #fafbfc; }
.ref-item:hover .ref-actions { opacity: 1; }

.ref-dot { width: 6px; height: 6px; border-radius: 50%; background: #c7cdd4; flex-shrink: 0; }
.ref-name { flex: 1; font-size: 13px; color: #191c1e; font-weight: 500; }
.ref-actions { display: flex; gap: 3px; opacity: 0; transition: opacity 0.15s; }

.ref-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    background: transparent; color: #9aaabb;
    border: none; cursor: pointer; transition: all 0.15s;
}
.ref-btn:hover        { background: #f2f4f6; color: #E5004C; }
.ref-btn.danger:hover { background: #ffdad6; color: #ba1a1a; }

/* ── Edit row ────────────────────────────────────────────────────────────── */
.edit-row     { display: flex; align-items: center; gap: 6px; flex: 1; }
.edit-row-age { flex-wrap: wrap; }

.btn-ok {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 7px;
    background: #d1fae5; color: #065f46; border: none; cursor: pointer; transition: background 0.15s;
}
.btn-ok:hover { background: #a7f3d0; }

.btn-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 7px;
    background: #f2f4f6; color: #515f74; border: none; cursor: pointer; transition: background 0.15s;
}
.btn-cancel:hover { background: #e0e3e5; }

/* ── Empty inline ────────────────────────────────────────────────────────── */
.empty-inline {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 14px; background: #fafbfc;
    border: 1px dashed #e0e3e5; border-radius: 8px;
    font-size: 12px; color: #9aaabb;
}

/* ── Error text ──────────────────────────────────────────────────────────── */
.err { font-size: 12px; color: #ba1a1a; margin: 0; }

/* ── IA tab layout ───────────────────────────────────────────────────────── */
.ia-layout { display: flex; flex-direction: column; gap: 20px; }

.ia-status-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 20px; border-radius: 10px; border: 1px solid transparent;
}
.ia-ok   { background: #d1fae5; border-color: #6ee7b7; }
.ia-warn { background: #fef3c7; border-color: #fde68a; }

.ia-status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dot-ok   { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); }
.dot-warn { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.2); }

.ia-status-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.ia-status-sub   { font-size: 12px; color: #515f74; margin-top: 2px; }

.ia-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.ia-col-left, .ia-col-right {
    background: #fff; border-radius: 12px;
    border: 1px solid #e0e3e5; padding: 20px;
}

.ia-section-title { font-size: 13px; font-weight: 700; color: #191c1e; margin: 0 0 14px; text-transform: uppercase; letter-spacing: 0.5px; }

/* Provider cards */
.provider-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.provider-card {
    position: relative; display: flex; flex-direction: column; align-items: flex-start;
    gap: 2px; padding: 10px 12px; border-radius: 9px;
    cursor: pointer; transition: all 0.15s; border: 2px solid;
    text-align: left;
}
.provider-card-selected  { border-color: #E5004C; background: #fff0f4; }
.provider-card-inactive  { border-color: #e0e3e5; background: #fafbfc; }
.provider-card-inactive:hover { border-color: #adb5bd; background: #fff; }
.provider-card-live      { border-color: #10b981 !important; background: #f0fdf4 !important; box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }

.provider-card-top { display: flex; align-items: center; justify-content: space-between; gap: 4px; width: 100%; }
.provider-name  { font-size: 13px; font-weight: 700; color: #191c1e; }
.provider-sub   { font-size: 11px; color: #515f74; }
.provider-check { position: absolute; top: 8px; right: 8px; font-size: 16px; color: #E5004C; }

.provider-live-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 7px; border-radius: 99px;
    background: #d1fae5; color: #065f46;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
    flex-shrink: 0;
}
.provider-live-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #10b981;
    animation: pulse-dot 1.5s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.75); }
}

.ia-col-hint { font-size: 11px; color: #9aaabb; margin: -8px 0 12px; line-height: 1.5; }

/* IA form */
.ia-form { display: flex; flex-direction: column; gap: 16px; }
.ia-field { display: flex; flex-direction: column; gap: 5px; }
.ia-label { font-size: 12px; font-weight: 600; color: #515f74; }
.ia-optional { font-size: 11px; font-weight: 400; color: #9aaabb; }
.req { color: #E5004C; }

.ia-input {
    padding: 10px 13px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; color: #191c1e; background: #fafafa;
    outline: none; font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ia-input:focus { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229,0,76,0.07); }

.key-wrap { position: relative; display: flex; align-items: center; }
.key-wrap .ia-input { flex: 1; padding-right: 42px; }
.eye-btn {
    position: absolute; right: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    background: transparent; color: #9aaabb; border: none; cursor: pointer;
    transition: all 0.15s;
}
.eye-btn:hover { color: #515f74; background: #f2f4f6; }

.ia-hint {
    display: flex; align-items: center; gap: 3px;
    font-size: 11px; color: #9aaabb;
}

.ia-footer { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding-top: 4px; }
.ia-deactivate-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; background: transparent; color: #6b7280;
    border: 1.5px solid #d1d5db; border-radius: 9px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.ia-deactivate-btn:hover { background: #fff0f4; color: #E5004C; border-color: #E5004C; }
.ia-save-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 20px; background: #E5004C; color: #fff;
    border: none; border-radius: 9px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.ia-save-btn:hover:not(:disabled) { background: #c40042; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(229,0,76,0.3); }
.ia-save-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.spin-sm {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Provider selector ──────────────────────────────────────────────────── */
.provider-selector { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 16px 20px; }
.provider-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.provider-card-big {
    display: flex; flex-direction: column; gap: 8px;
    padding: 14px 16px; border-radius: 10px; cursor: pointer; transition: all 0.15s;
    border: 2px solid;
}
.provider-card-selected { border-color: #E5004C; background: #fff0f4; }
.provider-card-inactive { border-color: #e0e3e5; background: #fafbfc; }
.provider-card-inactive:hover { border-color: #adb5bd; background: #fff; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0; }

/* ── WhatsApp steps ──────────────────────────────────────────────────────── */
.wa-steps { display: flex; flex-direction: column; gap: 10px; margin: 12px 0 16px; }
.wa-step {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: #3c4043; line-height: 1.5;
}
.wa-step-num {
    display: flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    background: #25d366; color: #fff; font-size: 11px; font-weight: 700; margin-top: 1px;
}
.wa-link { color: #1d4ed8; text-decoration: underline; }
.wa-link:hover { color: #1e40af; }
.wa-info-box {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 12px; background: #eff6ff; border: 1px solid #dbeafe;
    border-radius: 8px; font-size: 12px; color: #1e40af; line-height: 1.5;
}
.wa-info-box p { margin: 0; }
.ia-input code, .ia-hint code {
    font-family: monospace; background: #f0f2f5; padding: 1px 5px;
    border-radius: 4px; font-size: 12px; color: #1F3A4D;
}

/* ── Modal transitions ───────────────────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Modal overlay / box ─────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(25,28,30,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
    backdrop-filter: blur(2px);
}
.modal-box {
    background: #fff; border-radius: 14px; width: 100%; max-width: 420px;
    padding: 24px; box-shadow: 0 24px 48px -12px rgba(0,0,0,0.22);
    display: flex; flex-direction: column; gap: 14px;
}
.modal-box-head { display: flex; align-items: center; gap: 12px; }
.modal-warn-icon {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 9px;
    background: #fff3cd; color: #b45309; flex-shrink: 0;
}
.modal-err-icon {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 9px;
    background: #ffdad6; color: #ba1a1a; flex-shrink: 0;
}
.modal-box-title { font-size: 16px; font-weight: 700; color: #191c1e; }
.modal-box-msg   { font-size: 14px; color: #515f74; line-height: 1.6; margin: 0; }

.modal-box-foot { display: flex; justify-content: flex-end; gap: 8px; }

.modal-cancel {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; background: transparent; color: #515f74;
    border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s;
}
.modal-cancel:hover { background: #f2f4f6; }

.modal-delete {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; background: #ba1a1a; color: #fff;
    border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.modal-delete:hover { background: #9b1616; }

.modal-ok {
    display: inline-flex; align-items: center;
    padding: 9px 20px; background: #1F3A4D; color: #fff;
    border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.modal-ok:hover { background: #17303f; }

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-radius: 10px;
    font-size: 14px; font-weight: 500;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    min-width: 240px; max-width: 360px;
}
.toast-success { background: #1F3A4D; color: #fff; }
.toast-error   { background: #ba1a1a; color: #fff; }

.toast-enter-active { transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from   { opacity: 0; transform: translateY(16px) scale(0.95); }
.toast-leave-to     { opacity: 0; transform: translateY(8px); }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .cfg-grid  { grid-template-columns: 1fr; }
    .ia-cols   { grid-template-columns: 1fr; }
    .provider-grid { grid-template-columns: 1fr 1fr 1fr; }
}
@media (max-width: 600px) {
    .tabs-bar  { width: 100%; }
    .tab-btn   { flex: 1; justify-content: center; }
    .provider-grid { grid-template-columns: 1fr 1fr; }
    .ref-actions { opacity: 1; }
}
</style>
