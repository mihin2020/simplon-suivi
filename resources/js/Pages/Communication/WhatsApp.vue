<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SelectSearch from '@/Components/SelectSearch.vue'
import { ref, computed, watch } from 'vue'

defineOptions({ layout: AdminLayout })

interface Learner {
    id: string
    first_name: string
    last_name: string
    phone: string | null
    email: string | null
    formation?: { id: string; name: string; project: { id: string; name: string } }
}

interface Formation {
    id: string
    name: string
    project: { id: string; name: string }
    learners: Learner[]
}

interface WhatsAppConfig {
    provider: 'twilio' | 'meta'
    configured: boolean
    active_provider: 'twilio' | 'meta'
    twilio?: { configured: boolean; from: string }
    meta?: { configured: boolean; phone_number_id: string }
}

const props = defineProps<{
    formations: Formation[]
    whatsappConfig: WhatsAppConfig
}>()

const isConfigured = computed(() => props.whatsappConfig.configured)
const activeProvider = computed(() => props.whatsappConfig.active_provider)
const twilioFrom = computed(() => props.whatsappConfig.twilio?.from ?? '')

const page = usePage()
const flashMsg = ref<{ text: string; type: 'success' | 'error' | 'warning' } | null>(null)
let flashTimer: ReturnType<typeof setTimeout>

watch(() => page.props.flash, (flash: any) => {
    clearTimeout(flashTimer)
    if (flash?.success) { flashMsg.value = { text: flash.success, type: 'success' } }
    else if (flash?.error) { flashMsg.value = { text: flash.error, type: 'error' } }
    else if (flash?.warning) { flashMsg.value = { text: flash.warning, type: 'warning' } }
    if (flashMsg.value) flashTimer = setTimeout(() => { flashMsg.value = null }, 5000)
}, { deep: true })

const selectedProjectId = ref('')
const selectedFormationId = ref('')
const selectedLearnerIds = ref<string[]>([])
const message = ref('')
const generatedLinks = ref<{ learner: Learner; url: string; opened?: boolean }[]>([])
const sendMode = ref<'links' | 'api'>('links')
const sending = ref(false)

const selectedFormation = computed(() =>
    props.formations.find(f => f.id === selectedFormationId.value)
)

// Extraire les projets uniques des formations
const projects = computed(() => {
    const projectMap = new Map()
    props.formations.forEach(f => {
        if (!projectMap.has(f.project.id)) {
            projectMap.set(f.project.id, f.project)
        }
    })
    return Array.from(projectMap.values()).sort((a, b) => a.name.localeCompare(b.name))
})

// Options pour le select projet
const projectOptions = computed(() =>
    projects.value.map(p => ({
        value: p.id,
        label: p.name
    }))
)

// Formations filtrées par projet sélectionné
const filteredFormations = computed(() => {
    if (!selectedProjectId.value) return []
    return props.formations.filter(f => f.project.id === selectedProjectId.value)
})

// Options pour le SelectSearch formations
const formationOptions = computed(() =>
    filteredFormations.value.map(f => ({
        value: f.id,
        label: f.name
    }))
)

// Reset formation quand le projet change
watch(selectedProjectId, () => {
    selectedFormationId.value = ''
    selectedLearnerIds.value = []
})

const learnersWithPhone = computed(() => {
    if (!selectedFormation.value) return []
    return selectedFormation.value.learners.filter(l => l.phone)
})

function formatPhone(phone: string): string {
    let cleaned = phone.replace(/\D/g, '')
    if (cleaned.startsWith('0')) {
        cleaned = '226' + cleaned.substring(1)
    }
    if (!cleaned.startsWith('226') && cleaned.length <= 10) {
        cleaned = '226' + cleaned
    }
    return cleaned
}

function generateLinks() {
    const selectedLearners = learnersWithPhone.value.filter(l =>
        selectedLearnerIds.value.includes(l.id)
    )
    generatedLinks.value = selectedLearners.map(learner => {
        const phone = formatPhone(learner.phone!)
        const encodedMessage = encodeURIComponent(message.value)
        const url = `https://wa.me/${phone}?text=${encodedMessage}`
        return { learner, url }
    })
}

function openWhatsApp(url: string, index: number) {
    window.open(url, '_blank')
    setTimeout(() => {
        const link = generatedLinks.value[index]
        if (link) { link.opened = true }
    }, 500)
}

function toggleAll() {
    if (selectedLearnerIds.value.length === learnersWithPhone.value.length) {
        selectedLearnerIds.value = []
    } else {
        selectedLearnerIds.value = learnersWithPhone.value.map(l => l.id)
    }
}

function clearAll() {
    selectedFormationId.value = ''
    selectedLearnerIds.value = []
    message.value = ''
    generatedLinks.value = []
}

function sendViaApi() {
    if (selectedLearnerIds.value.length === 0 || !message.value.trim()) return
    sending.value = true
    const selectedLearners = learnersWithPhone.value.filter(l =>
        selectedLearnerIds.value.includes(l.id)
    )
    const recipients = selectedLearners.map(l => ({
        phone: formatPhone(l.phone!),
        name: `${l.first_name} ${l.last_name}`
    }))
    router.post('/communication/whatsapp/send', {
        recipients: recipients,
        message: message.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            sending.value = false
            message.value = ''
            selectedLearnerIds.value = []
        },
        onError: () => { sending.value = false }
    })
}
</script>

<template>
    <div class="page-wrap">
        <Head title="WhatsApp" />

        <Teleport to="body">
            <Transition name="toast">
                <div v-if="flashMsg" class="wa-toast" :class="`wa-toast-${flashMsg.type}`">
                    <span class="material-symbols-outlined" style="font-size:18px">
                        {{ flashMsg.type === 'success' ? 'check_circle' : flashMsg.type === 'warning' ? 'warning' : 'error' }}
                    </span>
                    {{ flashMsg.text }}
                </div>
            </Transition>
        </Teleport>

        <div class="page-header">
            <div class="header-left">
                <Link href="/communication/emails" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <h1 class="page-title">WhatsApp</h1>
                    <div class="page-meta">Envoi via WhatsApp Web ou API</div>
                </div>
            </div>
            <Link href="/configuration#whatsapp" @click.prevent="$inertia.visit('/configuration', { data: { tab: 'whatsapp' } })" class="cfg-link">
                <span class="material-symbols-outlined" style="font-size:16px">settings</span>
                Configurer
            </Link>
        </div>

        <div class="twilio-status-bar" :class="isConfigured ? 'twilio-ok' : 'twilio-warn'">
            <div class="twilio-dot" :class="isConfigured ? 'dot-ok' : 'dot-warn'"></div>
            <div class="twilio-status-text">
                <span v-if="isConfigured && activeProvider === 'twilio'">
                    <strong>Twilio actif</strong> — Numéro : <code>{{ twilioFrom }}</code>
                </span>
                <span v-else-if="isConfigured && activeProvider === 'meta'">
                    <strong>Meta Cloud API actif</strong> — Phone ID : <code>{{ whatsappConfig.meta?.phone_number_id }}</code>
                </span>
                <span v-else>
                    <strong>API WhatsApp non configurée</strong>
                    <Link href="/configuration" class="twilio-cfg-link">Configurer →</Link>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
            <div class="card">
                <h2 class="section-title flex items-center gap-xs">
                    <span class="material-symbols-outlined" style="font-size:18px">people</span>
                    Sélection des destinataires
                </h2>

                <div class="form-section">
                    <SelectSearch
                        v-model="selectedProjectId"
                        :options="projectOptions"
                        label="Projet"
                        placeholder="-- Choisir un projet --"
                        clearable
                    />
                </div>

                <div v-if="selectedProjectId" class="form-section">
                    <SelectSearch
                        v-model="selectedFormationId"
                        :options="formationOptions"
                        label="Formation"
                        placeholder="-- Choisir une formation --"
                        clearable
                    />
                </div>

                <div v-if="selectedFormation" class="mt-md">
                    <div class="flex items-center justify-between mb-sm">
                        <span class="text-body-sm text-secondary">{{ learnersWithPhone.length }} apprenant(s) avec numéro</span>
                        <button @click="toggleAll" class="text-link text-body-sm">
                            {{ selectedLearnerIds.length === learnersWithPhone.length ? 'Tout décocher' : 'Tout cocher' }}
                        </button>
                    </div>

                    <div class="learners-list">
                        <label v-for="learner in learnersWithPhone" :key="learner.id"
                               class="learner-item" :class="{ 'selected': selectedLearnerIds.includes(learner.id) }">
                            <input type="checkbox" :value="learner.id" v-model="selectedLearnerIds" class="checkbox">
                            <div class="learner-info">
                                <div class="learner-name">{{ learner.first_name }} {{ learner.last_name }}</div>
                                <div class="learner-phone">{{ learner.phone }}</div>
                            </div>
                        </label>

                        <div v-if="learnersWithPhone.length === 0" class="empty-state-sm">
                            <span class="material-symbols-outlined" style="font-size:24px;color:#cbd5e1">phone_disabled</span>
                            <p class="text-secondary text-body-sm mt-xs">Aucun apprenant avec numéro</p>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state-sm mt-md">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#cbd5e1">school</span>
                    <p class="text-secondary text-body-sm mt-xs">Sélectionnez une formation</p>
                </div>

                <div class="form-section mt-lg">
                    <label class="form-label flex items-center gap-xs">
                        <span class="material-symbols-outlined" style="font-size:16px">chat</span>
                        Message
                    </label>
                    <textarea v-model="message" rows="5" class="form-textarea" placeholder="Écrivez votre message WhatsApp ici..."></textarea>
                    <div class="text-body-xs text-secondary mt-xs">{{ message.length }} caractères</div>
                </div>

                <div class="send-mode-toggle mt-md">
                    <label class="mode-option" :class="{ active: sendMode === 'links' }">
                        <input type="radio" v-model="sendMode" value="links" class="sr-only">
                        <span class="material-symbols-outlined" style="font-size:16px">link</span>
                        <div>
                            <div class="mode-title">Liens wa.me</div>
                            <div class="mode-desc">Manuel, gratuit</div>
                        </div>
                    </label>
                    <label class="mode-option" :class="{ active: sendMode === 'api', 'mode-disabled': !isConfigured }"
                           :title="!isConfigured ? 'Configurez WhatsApp dans les Paramètres' : ''">
                        <input type="radio" v-model="sendMode" value="api" class="sr-only" :disabled="!isConfigured">
                        <span class="material-symbols-outlined" style="font-size:16px">send</span>
                        <div>
                            <div class="mode-title">Envoi direct (API)</div>
                            <div class="mode-desc">{{ isConfigured ? (activeProvider === 'meta' ? 'Meta Cloud' : 'Twilio') : 'Non configuré' }}</div>
                        </div>
                        <span v-if="!isConfigured" class="material-symbols-outlined" style="font-size:14px;color:#9aaabb">lock</span>
                    </label>
                </div>

                <button v-if="sendMode === 'links'" @click="generateLinks"
                        :disabled="selectedLearnerIds.length === 0 || !message.trim()"
                        class="btn-primary w-full justify-center mt-md">
                    <span class="material-symbols-outlined" style="font-size:18px">link</span>
                    Générer {{ selectedLearnerIds.length }} lien(s)
                </button>

                <button v-else @click="sendViaApi"
                        :disabled="selectedLearnerIds.length === 0 || !message.trim() || sending"
                        class="btn-twilio w-full justify-center mt-md">
                    <span class="material-symbols-outlined" style="font-size:18px">{{ sending ? 'sync' : 'send' }}</span>
                    {{ sending ? 'Envoi...' : `Envoyer à ${selectedLearnerIds.length}` }}
                </button>

                <div v-if="sendMode === 'api'" class="twilio-info">
                    <span class="material-symbols-outlined" style="font-size:14px">info</span>
                    <p>Envoi via {{ activeProvider === 'meta' ? 'Meta Cloud API' : 'Twilio' }}</p>
                </div>
            </div>

            <div class="card">
                <h2 class="section-title flex items-center gap-xs">
                    <span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>
                    Liens à ouvrir
                </h2>

                <div v-if="generatedLinks.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:48px;color:#cbd5e1">whatsapp</span>
                    <p class="text-secondary mt-sm">Sélectionnez des apprenants et rédigez un message</p>
                </div>

                <div v-else class="links-list">
                    <div class="links-header">
                        <span class="text-body-sm">{{ generatedLinks.length }} lien(s)</span>
                        <button @click="clearAll" class="text-link text-body-sm">Réinitialiser</button>
                    </div>

                    <div v-for="(link, index) in generatedLinks" :key="link.learner.id"
                         class="link-item" :class="{ 'opened': link.opened }">
                        <div class="link-number">{{ index + 1 }}</div>
                        <div class="link-info">
                            <div class="link-name">{{ link.learner.first_name }} {{ link.learner.last_name }}</div>
                            <div class="link-phone">{{ link.learner.phone }}</div>
                        </div>
                        <a :href="link.url" target="_blank" @click.prevent="openWhatsApp(link.url, index)"
                           class="btn-whatsapp" :class="{ 'opened': link.opened }">
                            <span class="material-symbols-outlined" style="font-size:16px">
                                {{ link.opened ? 'check' : 'open_in_new' }}
                            </span>
                            {{ link.opened ? 'Ouvert' : 'Ouvrir' }}
                        </a>
                    </div>

                    <div class="info-box mt-md">
                        <span class="material-symbols-outlined" style="font-size:16px;color:#3b82f6">info</span>
                        <p class="text-body-sm">Cliquez sur chaque bouton pour ouvrir WhatsApp avec le message pré-rempli.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-wrap { max-width: 1200px; margin: 0 auto; padding: 0 16px; display: flex; flex-direction: column; gap: 20px; }
.page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.header-left { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0; }
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; color: #515f74; transition: background 0.15s; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #eceef0; color: #191c1e; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; margin: 0; line-height: 1.2; }
.page-meta { font-size: 13px; color: #6b7280; margin-top: 2px; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }
.section-title { font-size: 11px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 20px; display: flex; align-items: center; gap: 6px; }
.form-section { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #515f74; margin-bottom: 6px; }
.form-select, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 14px; color: #191c1e; background: #fafafa; outline: none; transition: all 0.15s; }
.form-select:focus, .form-textarea:focus { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08); }
.form-textarea { resize: vertical; min-height: 100px; font-family: inherit; }

.learners-list { max-height: 300px; overflow-y: auto; border: 1px solid #e0e3e5; border-radius: 8px; background: #fafafa; }
.learner-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #f0f0f0; }
.learner-item:last-child { border-bottom: none; }
.learner-item:hover { background: #fff; }
.learner-item.selected { background: #fff5f8; }
.checkbox { width: 18px; height: 18px; accent-color: #E5004C; cursor: pointer; flex-shrink: 0; }
.learner-info { flex: 1; min-width: 0; }
.learner-name { font-size: 14px; font-weight: 500; color: #191c1e; }
.learner-phone { font-size: 12px; color: #6b7280; font-family: monospace; }

.btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 20px; background: #E5004C; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.15s; }
.btn-primary:hover:not(:disabled) { background: #c4003f; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-whatsapp { display: inline-flex; align-items: center; gap: 4px; padding: 8px 12px; background: #25d366; color: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.15s; flex-shrink: 0; }
.btn-whatsapp:hover:not(.opened) { background: #128c7e; }
.btn-whatsapp.opened { background: #6b7280; }

.links-list { display: flex; flex-direction: column; gap: 10px; }
.links-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.link-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: #fafafa; border: 1px solid #e0e3e5; border-radius: 8px; transition: all 0.15s; }
.link-item.opened { background: #f0fdf4; border-color: #86efac; }
.link-item:hover { border-color: #cbd5e1; }
.link-number { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: #E5004C; color: #fff; border-radius: 50%; font-size: 12px; font-weight: 700; flex-shrink: 0; }
.link-info { flex: 1; min-width: 0; }
.link-name { font-size: 14px; font-weight: 500; color: #191c1e; }
.link-phone { font-size: 12px; color: #6b7280; font-family: monospace; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; }
.empty-state-sm { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center; background: #fafafa; border-radius: 8px; }

.text-link { color: #E5004C; background: none; border: none; cursor: pointer; font-weight: 500; }
.text-link:hover { text-decoration: underline; }
.info-box { display: flex; align-items: flex-start; gap: 10px; padding: 12px; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; color: #1e40af; }

.send-mode-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.mode-option { display: flex; align-items: center; gap: 10px; padding: 12px; border: 2px solid #e0e3e5; border-radius: 8px; cursor: pointer; transition: all 0.15s; background: #fafafa; }
.mode-option:hover { border-color: #cbd5e1; background: #f9fafb; }
.mode-option.active { border-color: #E5004C; background: #fff5f8; }
.mode-title { font-size: 13px; font-weight: 600; color: #191c1e; }
.mode-desc { font-size: 11px; color: #6b7280; margin-top: 2px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0; }

.btn-twilio { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 20px; background: #25d366; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.15s; }
.btn-twilio:hover:not(:disabled) { background: #128c7e; transform: translateY(-1px); }
.btn-twilio:disabled { opacity: 0.5; cursor: not-allowed; }

.twilio-info { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; color: #92400e; font-size: 12px; margin-top: 12px; }
.twilio-info p { margin: 0; }

.twilio-status-bar { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
.twilio-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.twilio-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.twilio-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-ok { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.25); }
.dot-warn { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.25); }
.twilio-status-text code { font-family: monospace; background: rgba(0,0,0,0.07); padding: 1px 5px; border-radius: 4px; font-size: 12px; }
.twilio-cfg-link { color: #1d4ed8; font-weight: 600; text-decoration: underline; margin-left: 4px; }

.cfg-link { display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; border: 1px solid #e0e3e5; border-radius: 8px; font-size: 12px; font-weight: 600; color: #515f74; text-decoration: none; background: #fff; transition: all 0.15s; flex-shrink: 0; }
.cfg-link:hover { border-color: #25d366; color: #15803d; background: #f0fdf4; }

.mode-disabled { opacity: 0.55; cursor: not-allowed; }
.mode-disabled input { pointer-events: none; }

.wa-toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; z-index: 9999; box-shadow: 0 8px 24px rgba(0,0,0,0.15); min-width: 280px; max-width: 500px; }
.wa-toast-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.wa-toast-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.wa-toast-warning { background: #fef9c3; color: #92400e; border: 1px solid #fde047; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(12px); }

.grid { display: grid; gap: 24px; }
@media (min-width: 1024px) { .lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mt-xs { margin-top: 4px; }
.mt-sm { margin-top: 8px; }
.mt-md { margin-top: 16px; }
.mt-lg { margin-top: 24px; }
.mb-sm { margin-bottom: 8px; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-xs { gap: 4px; }
.w-full { width: 100%; }
.text-body-sm { font-size: 13px; }
.text-body-xs { font-size: 11px; }
.text-secondary { color: #6b7280; }
</style>
