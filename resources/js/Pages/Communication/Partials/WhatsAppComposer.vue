<script setup lang="ts">
import { ref, computed, watch, onUnmounted, nextTick } from 'vue'

interface Learner   { id: string; first_name: string; last_name: string; phone: string | null }
interface Formation { id: string; name: string; project: { id: string; name: string }; learners: Learner[] }

const props = defineProps<{
    formations: Formation[]
    connected: boolean
    processing: boolean
    sendEstimate: number
}>()

const emit = defineEmits<{
    (e: 'send', payload: {
        recipients: { phone: string; name: string; learner_id: string }[]
        message: string
        formationName: string | null
        projectName: string | null
        attachments: File[]
    }): void
}>()

// Ce composant est volontairement ISOLÉ du polling de statut du parent.
// Seule la prop `connected` le re-rend (rare) — les selects natifs restent
// donc ouverts pendant que l'utilisateur choisit projet/formation.

const selectedProjectId   = ref('')
const selectedFormationId = ref('')
const selectedLearnerIds  = ref<string[]>([])
const messageText         = ref('')
const textareaRef         = ref<HTMLTextAreaElement | null>(null)

const projects = computed(() => {
    const map = new Map<string, { id: string; name: string }>()
    props.formations.forEach(f => { if (!map.has(f.project.id)) map.set(f.project.id, f.project) })
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
})
const filteredFormations = computed(() =>
    selectedProjectId.value ? props.formations.filter(f => f.project.id === selectedProjectId.value) : [])
const selectedFormation  = computed(() => props.formations.find(f => f.id === selectedFormationId.value))
const learnersWithPhone  = computed(() => selectedFormation.value?.learners.filter(l => l.phone) ?? [])

watch(selectedProjectId,   () => { selectedFormationId.value = ''; selectedLearnerIds.value = [] })
watch(selectedFormationId, () => { selectedLearnerIds.value = [] })

function toggleAll() {
    selectedLearnerIds.value = selectedLearnerIds.value.length === learnersWithPhone.value.length
        ? [] : learnersWithPhone.value.map(l => l.id)
}

const riskLevel = computed(() => {
    const n = selectedLearnerIds.value.length
    if (n > 200) return 'critical'
    if (n > 80)  return 'high'
    if (n > 50)  return 'medium'
    return 'safe'
})

// ── Pièces jointes multiples ──────────────────────────────────────────────────
interface AttachItem { file: File; preview: string | null }
const attachments  = ref<AttachItem[]>([])
const fileInputRef = ref<HTMLInputElement | null>(null)

function pickFile() { fileInputRef.value?.click() }

function onFileChange(e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files ?? [])
    for (const file of files) {
        if (attachments.value.length >= 10) break
        const item: AttachItem = { file, preview: null }
        if (file.type.startsWith('image/')) {
            const reader = new FileReader()
            reader.onload = ev => { item.preview = ev.target?.result as string }
            reader.readAsDataURL(file)
        }
        attachments.value.push(item)
    }
    ;(e.target as HTMLInputElement).value = ''
}
function removeAttachment(idx: number) { attachments.value.splice(idx, 1) }

function fileIcon(f: File) {
    if (f.type.startsWith('image/')) return 'image'
    if (f.type.startsWith('audio/')) return 'mic'
    if (f.type.startsWith('video/')) return 'videocam'
    if (f.type === 'application/pdf') return 'picture_as_pdf'
    if (f.type.includes('word'))      return 'description'
    if (f.type.includes('excel') || f.type.includes('spreadsheet')) return 'table_chart'
    return 'attach_file'
}
function fmtSize(b: number) { return b < 1048576 ? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(1)+' MB' }

// ── Emoji ─────────────────────────────────────────────────────────────────────
const showEmoji = ref(false)
const EMOJIS = [
    { label: 'Expressions', list: ['😊','😀','😂','😅','🙂','😍','🤔','😎','😢','😤','🥺','😭','🤩','😇','🥳'] },
    { label: 'Gestes',      list: ['👋','👍','👏','🙏','💪','🤝','✌️','👌','🤙','💯','🫶'] },
    { label: 'Symboles',    list: ['❤️','✅','❌','⚠️','ℹ️','🔔','📢','🎉','🏆','⭐','💡','📌','🚀','🎯'] },
    { label: 'École',       list: ['📚','📝','🏫','🎓','💻','📊','📋','✏️','📅','🕐','📞','📧'] },
]
function insertEmoji(e: string) {
    const ta = textareaRef.value
    if (!ta) { messageText.value += e; return }
    const s = ta.selectionStart ?? messageText.value.length
    const end = ta.selectionEnd ?? s
    messageText.value = messageText.value.slice(0, s) + e + messageText.value.slice(end)
    nextTick(() => { ta.selectionStart = ta.selectionEnd = s + e.length; ta.focus() })
}
function onEmojiOutside(ev: MouseEvent) {
    const panel = document.getElementById('ep'), btn = document.getElementById('eb')
    if (panel && !panel.contains(ev.target as Node) && btn && !btn.contains(ev.target as Node)) showEmoji.value = false
}
watch(showEmoji, v => { if (v) document.addEventListener('mousedown', onEmojiOutside); else document.removeEventListener('mousedown', onEmojiOutside) })
onUnmounted(() => document.removeEventListener('mousedown', onEmojiOutside))

// ── Envoi ─────────────────────────────────────────────────────────────────────
const canSend = computed(() =>
    props.connected &&
    selectedLearnerIds.value.length > 0 &&
    riskLevel.value !== 'critical' &&
    (messageText.value.trim().length > 0 || attachments.value.length > 0)
)

function submit() {
    if (!canSend.value) return
    emit('send', {
        recipients: learnersWithPhone.value
            .filter(l => selectedLearnerIds.value.includes(l.id))
            .map(l => ({ phone: l.phone!, name: `${l.first_name} ${l.last_name}`, learner_id: l.id })),
        message: messageText.value,
        formationName: selectedFormation.value?.name ?? null,
        projectName: selectedFormation.value?.project.name ?? null,
        attachments: attachments.value.map(a => a.file),
    })
}

// Le parent appelle ceci après un envoi réussi pour vider le formulaire
function reset() {
    messageText.value = ''
    selectedLearnerIds.value = []
    attachments.value = []
}
defineExpose({ reset })
</script>

<template>
<div class="card" style="position:relative">
    <div class="card-head">
        <span class="material-symbols-outlined" style="font-size:15px;color:#25d366">edit_note</span>
        Nouveau message
    </div>

    <!-- Overlay envoi -->
    <div v-if="processing" class="sending-overlay">
        <div class="sending-box">
            <div class="spinner spinner-lg"></div>
            <p class="sending-title">Envoi en cours…</p>
            <p class="sending-sub">{{ selectedLearnerIds.length }} destinataire(s)</p>
            <div class="sending-bar-wrap">
                <div class="sending-bar" :style="{ width: selectedLearnerIds.length > 0 ? Math.min(100, (1 - sendEstimate / selectedLearnerIds.length) * 100) + '%' : '100%' }"></div>
            </div>
            <p class="sending-est">≈ {{ sendEstimate }}s restants</p>
        </div>
    </div>

    <div class="compose-body" :style="processing ? 'opacity:.4;pointer-events:none' : ''">

        <div class="field">
            <label class="field-label">Projet</label>
            <select v-model="selectedProjectId" class="native-select">
                <option value="">-- Choisir un projet --</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <div v-if="selectedProjectId" class="field">
            <label class="field-label">Formation</label>
            <select v-model="selectedFormationId" class="native-select">
                <option value="">-- Choisir une formation --</option>
                <option v-for="f in filteredFormations" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
        </div>

        <template v-if="selectedFormation">
            <div class="field">
                <div class="learners-header">
                    <label class="field-label" style="margin:0">
                        Destinataires
                        <span v-if="selectedLearnerIds.length" class="count-badge">{{ selectedLearnerIds.length }}</span>
                    </label>
                    <button type="button" @click="toggleAll" class="link-btn">
                        {{ selectedLearnerIds.length === learnersWithPhone.length ? 'Tout décocher' : 'Tout cocher' }}
                    </button>
                </div>
                <div class="learners-list">
                    <label v-for="l in learnersWithPhone" :key="l.id" class="learner-row" :class="{ checked: selectedLearnerIds.includes(l.id) }">
                        <input type="checkbox" :value="l.id" v-model="selectedLearnerIds" class="cb" />
                        <div class="av-sm">{{ l.first_name[0] }}{{ l.last_name[0] }}</div>
                        <div>
                            <div class="learner-name">{{ l.first_name }} {{ l.last_name }}</div>
                            <div class="learner-phone">{{ l.phone }}</div>
                        </div>
                    </label>
                    <div v-if="learnersWithPhone.length === 0" class="empty-sm">
                        <span class="material-symbols-outlined" style="font-size:20px;color:#d1d5db">phone_disabled</span>
                        <p>Aucun apprenant avec numéro</p>
                    </div>
                </div>
            </div>

            <div v-if="riskLevel !== 'safe'" class="risk-alert" :class="'risk-' + riskLevel">
                <span class="material-symbols-outlined" style="font-size:15px">{{ riskLevel === 'critical' ? 'block' : 'warning' }}</span>
                <span v-if="riskLevel === 'critical'"><strong>Interdit ({{ selectedLearnerIds.length }})</strong> — WhatsApp bannira votre numéro. Maximum 200.</span>
                <span v-else-if="riskLevel === 'high'"><strong>Risque élevé ({{ selectedLearnerIds.length }})</strong> — Déconseillé au-delà de 80.</span>
                <span v-else><strong>Risque modéré ({{ selectedLearnerIds.length }})</strong> — Restez vigilant.</span>
            </div>
        </template>

        <div v-else-if="!selectedProjectId" class="empty-sm">
            <span class="material-symbols-outlined" style="font-size:30px;color:#d1d5db">folder_open</span>
            <p>Sélectionnez un projet et une formation</p>
        </div>

        <!-- Galerie pièces jointes -->
        <div v-if="attachments.length > 0" class="attachments-gallery">
            <div v-for="(item, idx) in attachments" :key="idx" class="attach-thumb">
                <img v-if="item.preview" :src="item.preview" class="attach-thumb-img" :title="item.file.name" />
                <div v-else class="attach-thumb-doc">
                    <span class="material-symbols-outlined" style="font-size:22px;color:#6b7280">{{ fileIcon(item.file) }}</span>
                    <span class="attach-thumb-name">{{ item.file.name.length > 14 ? item.file.name.slice(0,12)+'…' : item.file.name }}</span>
                    <span class="attach-thumb-size">{{ fmtSize(item.file.size) }}</span>
                </div>
                <button type="button" class="attach-thumb-rm" @click="removeAttachment(idx)">
                    <span class="material-symbols-outlined" style="font-size:12px">close</span>
                </button>
            </div>
            <button v-if="attachments.length < 10" type="button" class="attach-add-btn" @click="pickFile" title="Ajouter un fichier">
                <span class="material-symbols-outlined" style="font-size:22px;color:#9aaabb">add_photo_alternate</span>
            </button>
        </div>

        <div class="field">
            <label class="field-label">Message</label>
            <div class="textarea-wrap">
                <textarea ref="textareaRef" v-model="messageText" rows="4" class="textarea"
                    :placeholder="attachments.length > 0 ? 'Légende (optionnelle)…' : 'Tapez votre message…'"></textarea>
                <div class="toolbar">
                    <div style="position:relative">
                        <button id="eb" type="button" @click="showEmoji = !showEmoji" class="tool-btn" :class="{ active: showEmoji }">
                            <span class="material-symbols-outlined" style="font-size:18px">sentiment_satisfied</span>
                        </button>
                        <div v-if="showEmoji" id="ep" class="emoji-panel">
                            <div v-for="g in EMOJIS" :key="g.label" class="emoji-group">
                                <p class="emoji-lbl">{{ g.label }}</p>
                                <div class="emoji-grid">
                                    <button v-for="e in g.list" :key="e" type="button" class="emoji-item" @click="insertEmoji(e)">{{ e }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="pickFile" class="tool-btn" :class="{ active: attachments.length > 0 }" :title="`Pièce jointe${attachments.length ? ' ('+attachments.length+')' : ''}`">
                        <span class="material-symbols-outlined" style="font-size:18px">attach_file</span>
                        <span v-if="attachments.length > 0" class="attach-count">{{ attachments.length }}</span>
                    </button>
                    <input ref="fileInputRef" type="file" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" @change="onFileChange" />
                    <span style="flex:1"></span>
                    <span class="char-count">{{ messageText.length }}/4096</span>
                </div>
            </div>
        </div>

        <button type="button" @click="submit" :disabled="!canSend || processing" class="btn-send">
            <span class="material-symbols-outlined" style="font-size:17px">send</span>
            <span v-if="!connected">WhatsApp non connecté</span>
            <span v-else-if="riskLevel === 'critical'">Trop de destinataires (max 200)</span>
            <span v-else-if="!selectedLearnerIds.length">Sélectionnez des destinataires</span>
            <span v-else-if="!messageText.trim() && !attachments.length">Rédigez un message</span>
            <span v-else>Envoyer à {{ selectedLearnerIds.length }} apprenant(s) · ≈{{ selectedLearnerIds.length }}s</span>
        </button>

    </div>
</div>
</template>

<style scoped>
.card { background:#fff;border:1px solid #e0e3e5;border-radius:14px;overflow:hidden; }
.card-head { display:flex;align-items:center;gap:7px;padding:12px 16px;background:#f8f9fa;border-bottom:1px solid #e0e3e5;font-size:11px;font-weight:700;color:#515f74;text-transform:uppercase;letter-spacing:.06em; }
.compose-body { padding:16px;display:flex;flex-direction:column;gap:14px; }

.sending-overlay { position:absolute;inset:0;background:rgba(255,255,255,.92);z-index:20;display:flex;align-items:center;justify-content:center;border-radius:0 0 14px 14px; }
.sending-box { display:flex;flex-direction:column;align-items:center;gap:10px;padding:28px;text-align:center; }
.sending-title { font-size:14px;font-weight:700;color:#191c1e;margin:0; }
.sending-sub   { font-size:12px;color:#6b7280;margin:0; }
.sending-bar-wrap { width:200px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden; }
.sending-bar { height:100%;background:#25d366;border-radius:99px;transition:width 1s linear; }
.sending-est { font-size:11px;color:#9aaabb;margin:0; }

.field { display:flex;flex-direction:column;gap:5px; }
.field-label { font-size:12px;font-weight:600;color:#515f74; }
.native-select { width:100%;padding:9px 32px 9px 12px;border:1px solid #e0e3e5;border-radius:8px;font-size:13px;color:#191c1e;background:#fafafa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 10px center/16px;appearance:none;outline:none;transition:border-color .15s; }
.native-select:focus { border-color:#25d366;box-shadow:0 0 0 3px rgba(37,211,102,.1); }

.learners-header { display:flex;align-items:center;justify-content:space-between; }
.count-badge { display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#25d366;color:#fff;border-radius:50%;font-size:10px;font-weight:700;margin-left:4px; }
.link-btn { background:none;border:none;font-size:11px;font-weight:500;color:#25d366;cursor:pointer; }
.link-btn:hover { text-decoration:underline; }
.learners-list { border:1px solid #e0e3e5;border-radius:8px;max-height:180px;overflow-y:auto;background:#fafafa; }
.learner-row { display:flex;align-items:center;gap:9px;padding:7px 11px;cursor:pointer;transition:background .12s;border-bottom:1px solid #f0f0f0; }
.learner-row:last-child { border-bottom:none; }
.learner-row.checked { background:#f0fdf4; }
.cb { width:14px;height:14px;accent-color:#25d366;flex-shrink:0;cursor:pointer; }
.av-sm { width:26px;height:26px;background:#dcfce7;color:#166534;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;flex-shrink:0; }
.learner-name  { font-size:12px;font-weight:500;color:#191c1e; }
.learner-phone { font-size:10px;color:#6b7280;font-family:monospace; }

.risk-alert { display:flex;align-items:flex-start;gap:8px;padding:9px 12px;border-radius:8px;font-size:12px; }
.risk-medium   { background:#fef9c3;border:1px solid #fde68a;color:#78350f; }
.risk-high     { background:#fff7ed;border:1px solid #fed7aa;color:#9a3412; }
.risk-critical { background:#fee2e2;border:1px solid #fca5a5;color:#991b1b; }

/* Galerie pièces jointes */
.attachments-gallery { display:flex;flex-wrap:wrap;gap:6px;padding:6px;border:1px solid #e0e3e5;border-radius:8px;background:#f8f9fa; }
.attach-thumb { position:relative;width:72px;height:72px;border-radius:7px;overflow:hidden;border:1px solid #e0e3e5;background:#fff;flex-shrink:0; }
.attach-thumb-img { width:100%;height:100%;object-fit:cover;display:block; }
.attach-thumb-doc { width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:4px; }
.attach-thumb-name { font-size:9px;color:#374151;font-weight:600;text-align:center;line-height:1.2;word-break:break-all; }
.attach-thumb-size { font-size:8px;color:#9aaabb; }
.attach-thumb-rm { position:absolute;top:3px;right:3px;width:17px;height:17px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0; }
.attach-add-btn { width:72px;height:72px;border:2px dashed #d1d5db;border-radius:7px;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:border-color .15s;flex-shrink:0; }
.attach-add-btn:hover { border-color:#25d366; }
.attach-count { position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;background:#E5004C;color:#fff;border-radius:99px;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 3px; }

.textarea-wrap { border:1px solid #e0e3e5;border-radius:8px;display:flex;flex-direction:column; }
.textarea-wrap:focus-within { border-color:#25d366;box-shadow:0 0 0 3px rgba(37,211,102,.1); }
.textarea { width:100%;padding:10px 12px;border:none;outline:none;font-size:13px;color:#191c1e;background:transparent;resize:none;font-family:inherit;box-sizing:border-box;line-height:1.5;border-radius:8px 8px 0 0; }
.toolbar  { display:flex;align-items:center;gap:4px;padding:5px 8px;border-top:1px solid #f0f0f0;background:#fafafa;border-radius:0 0 8px 8px; }
.tool-btn { position:relative;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:none;background:none;border-radius:6px;color:#6b7280;cursor:pointer;transition:all .15s; }
.tool-btn:hover { background:#e5e7eb;color:#191c1e; }
.tool-btn.active { background:#dcfce7;color:#166534; }
.char-count { font-size:10px;color:#9aaabb; }

.emoji-panel { position:absolute;bottom:calc(100% + 8px);left:0;z-index:200;background:#fff;border:1px solid #e0e3e5;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.14);padding:12px;width:280px;max-height:280px;overflow-y:auto; }
.emoji-group { margin-bottom:10px; }
.emoji-lbl   { font-size:10px;font-weight:700;color:#9aaabb;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px; }
.emoji-grid  { display:flex;flex-wrap:wrap;gap:2px; }
.emoji-item  { width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:17px;border:none;background:none;cursor:pointer;border-radius:5px;transition:background .1s; }
.emoji-item:hover { background:#f3f4f6; }

.btn-send { display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:11px;background:#25d366;color:#fff;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all .15s; }
.btn-send:hover:not(:disabled){ background:#128c7e; }
.btn-send:disabled { background:#9ca3af;cursor:not-allowed; }
.empty-sm { display:flex;flex-direction:column;align-items:center;gap:6px;padding:20px;text-align:center;font-size:12px;color:#9aaabb; }

.spinner { width:20px;height:20px;border:2px solid #e0e3e5;border-top-color:#25d366;border-radius:50%;animation:spin .8s linear infinite; }
.spinner-lg { width:36px;height:36px;border-width:3px; }
@keyframes spin { to{ transform:rotate(360deg); } }
</style>
