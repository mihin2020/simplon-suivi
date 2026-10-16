<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

export interface EmailableContract {
    id: string
    reference: string
    title: string
    subject_name: string
    recipient_email: string | null
    /** Written for one person: sent to that person alone. */
    is_nominative: boolean
    emailed_at?: string | null
    emailed_to?: string | null
}

type Mode = 'each' | 'single'

const props = defineProps<{
    open: boolean
    contracts: EmailableContract[]
}>()

const emit = defineEmits<{ close: []; sent: [] }>()

const form = useForm({
    ids: [] as string[],
    mode: 'each' as Mode,
    recipients: {} as Record<string, string>,
    to: [] as string[],
    cc: [] as string[],
    subject: '',
    message: '',
})

const nominative = computed(() => props.contracts.filter(c => c.is_nominative))
const general = computed(() => props.contracts.filter(c => !c.is_nominative))

const isEmail = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())

// Nominative sending: every selected contract with the address it will go to (editable).
const rows = computed(() => props.contracts.map(c => {
    const email = (form.recipients[c.id] ?? '').trim()

    return { contract: c, email, valid: isEmail(email) }
}))
const alreadySent = computed(() => props.contracts.filter(c => c.emailed_at))
const fmtDate = (iso: string) => new Date(iso).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })
const readyRows = computed(() => rows.value.filter(r => r.valid))
const missingRows = computed(() => rows.value.filter(r => !r.valid))

// Address chips for the single email: type, then Enter / comma / Tab.
const toInput = ref('')
const ccInput = ref('')
const inputs = { to: toInput, cc: ccInput }
const showCc = ref(false)

const chipError = ref<{ to: string | null; cc: string | null }>({ to: null, cc: null })

/** Turn what was typed into address chips; anything invalid stays in the field, flagged. */
const addAddress = (list: 'to' | 'cc') => {
    const input = inputs[list]
    const parts = input.value.split(/[,;\s]+/).map(p => p.trim()).filter(Boolean)
    const invalid: string[] = []
    for (const part of parts) {
        if (!isEmail(part)) invalid.push(part)
        else if (!form[list].includes(part)) form[list].push(part)
    }
    input.value = invalid.join(' ')
    chipError.value[list] = invalid.length ? `Adresse invalide : ${invalid.join(', ')}` : null
}
const removeAddress = (list: 'to' | 'cc', email: string) => { form[list] = form[list].filter(e => e !== email) }
const onKey = (event: KeyboardEvent, list: 'to' | 'cc') => {
    const input = inputs[list]
    chipError.value[list] = null
    if (event.key === 'Enter') {
        // Enter adds the address (or does nothing on an empty field) - it never sends the email.
        event.preventDefault()
        if (input.value.trim()) addAddress(list)
    } else if ([',', ';'].includes(event.key) && input.value.trim()) {
        event.preventDefault()
        addAddress(list)
    } else if (event.key === 'Tab' && input.value.trim()) {
        addAddress(list)
    } else if (event.key === 'Backspace' && !input.value && form[list].length) {
        form[list].pop()
    }
}

watch(() => props.open, open => {
    if (!open) return
    form.reset()
    form.clearErrors()
    form.ids = props.contracts.map(c => c.id)
    form.recipients = Object.fromEntries(props.contracts.map(c => [c.id, c.recipient_email ?? '']))
    // Specific contracts go to their own person; general ones are sent to a list of addresses.
    form.mode = nominative.value.length > 0 ? 'each' : 'single'
    form.subject = props.contracts.length === 1 ? props.contracts[0].title : `Vos contrats (${props.contracts.length} documents)`
    form.message = 'Bonjour,\n\nVeuillez trouver ci-joint votre contrat. Merci de le relire, de le signer et de nous le retourner.\n\nCordialement,'
    toInput.value = ''
    ccInput.value = ''
    showCc.value = false
})

const sendBlocker = computed(() => {
    if (form.mode === 'each' && readyRows.value.length === 0) return 'Saisissez au moins une adresse dans la liste des contrats.'
    if (form.mode === 'single' && form.to.length === 0 && !isEmail(toInput.value)) return 'Ajoutez au moins une adresse dans « À ».'
    if (form.subject.trim() === '') return "Indiquez l'objet de l'email."
    if (form.message.trim() === '') return "Écrivez le texte de l'email."

    return null
})
const canSend = computed(() => sendBlocker.value === null)

const send = () => {
    if (toInput.value.trim()) addAddress('to')
    if (ccInput.value.trim()) addAddress('cc')
    form.transform(data => ({
        ...data,
        // Only well-formed addresses are sent; the others are reported as skipped.
        recipients: Object.fromEntries(Object.entries(data.recipients).filter(([, email]) => isEmail(email))),
    })).post('/contracts/bulk/email', {
        preserveScroll: true,
        onSuccess: () => { emit('sent'); emit('close') },
    })
}

const footHint = computed(() => form.mode === 'each'
    ? `${readyRows.value.length} email(s), chacun avec son propre contrat`
    : `1 email avec ${props.contracts.length} contrat(s) en pièce jointe`)
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="overlay" @click.self="emit('close')">
            <form class="dialog" @submit.prevent>
                <header class="head">
                    <div class="head-icon"><span class="material-symbols-outlined">forward_to_inbox</span></div>
                    <div class="head-text">
                        <h3>Envoyer par email</h3>
                        <p>{{ contracts.length }} contrat{{ contracts.length > 1 ? 's' : '' }} sélectionné{{ contracts.length > 1 ? 's' : '' }} · envoi en arrière-plan</p>
                    </div>
                    <button type="button" class="icon-close" title="Fermer" @click="emit('close')"><span class="material-symbols-outlined">close</span></button>
                </header>

                <div class="content">
                    <!-- 1. Destinataires -->
                    <section class="section">
                        <h4 class="section-title"><span class="num">1</span>Destinataires</h4>

                        <div class="modes">
                            <label class="mode" :class="{ on: form.mode === 'each' }">
                                <input v-model="form.mode" type="radio" value="each" />
                                <span class="mode-icon material-symbols-outlined">person</span>
                                <span class="mode-text">
                                    <strong>Chacun reçoit son contrat</strong>
                                    <small>Contrats nominatifs : un email par personne.</small>
                                </span>
                            </label>
                            <label class="mode" :class="{ on: form.mode === 'single' }">
                                <input v-model="form.mode" type="radio" value="single" />
                                <span class="mode-icon material-symbols-outlined">groups</span>
                                <span class="mode-text">
                                    <strong>Un même envoi pour tous</strong>
                                    <small>Contrat général : mêmes pièces jointes à plusieurs adresses.</small>
                                </span>
                            </label>
                        </div>

                        <!-- Nominative: one address per contract -->
                        <template v-if="form.mode === 'each'">
                            <div class="table">
                                <div class="table-head">
                                    <span>Contrat</span>
                                    <span>Adresse d'envoi</span>
                                </div>
                                <div v-for="row in rows" :key="row.contract.id" class="table-row" :class="{ missing: !row.valid }">
                                    <div class="who">
                                        <span class="who-name">{{ row.contract.subject_name !== '-' ? row.contract.subject_name : row.contract.title }}</span>
                                        <span class="who-ref">{{ row.contract.reference }}</span>
                                        <span v-if="row.contract.emailed_at" class="who-sent">
                                            <span class="material-symbols-outlined">send</span>Déjà envoyé le {{ fmtDate(row.contract.emailed_at) }}
                                        </span>
                                    </div>
                                    <div class="address">
                                        <span class="material-symbols-outlined status-icon">{{ row.valid ? 'check_circle' : 'error' }}</span>
                                        <input v-model="form.recipients[row.contract.id]" type="email" class="input" placeholder="Saisir l'adresse email" />
                                    </div>
                                </div>
                            </div>
                            <p v-if="missingRows.length" class="callout warn">
                                <span class="material-symbols-outlined">info</span>
                                {{ missingRows.length }} contrat{{ missingRows.length > 1 ? 's' : '' }} sans adresse valide ne {{ missingRows.length > 1 ? 'seront' : 'sera' }} pas envoyé{{ missingRows.length > 1 ? 's' : '' }}.
                                <template v-if="general.length"> Pour un contrat général, saisissez l'adresse de la personne qui doit le recevoir.</template>
                            </p>
                            <p v-if="alreadySent.length" class="callout info">
                                <span class="material-symbols-outlined">mark_email_read</span>
                                {{ alreadySent.length }} contrat{{ alreadySent.length > 1 ? 's ont' : ' a' }} déjà été envoyé{{ alreadySent.length > 1 ? 's' : '' }} : {{ alreadySent.length > 1 ? 'ils seront renvoyés' : 'il sera renvoyé' }}.
                            </p>
                            <p class="help">Les adresses saisies sont enregistrées sur les contrats pour les prochains envois.</p>
                        </template>

                        <!-- Same email for everybody -->
                        <div v-else class="field">
                            <label class="label">À</label>
                            <div class="chips-box" @click="($event.currentTarget as HTMLElement).querySelector('input')?.focus()">
                                <span v-for="email in form.to" :key="email" class="chip">
                                    {{ email }}
                                    <button type="button" title="Retirer" @click.stop="removeAddress('to', email)"><span class="material-symbols-outlined">close</span></button>
                                </span>
                                <input v-model="toInput" type="email" class="chip-input" :placeholder="form.to.length ? 'Ajouter une autre adresse' : 'nom@exemple.com'" @keydown="onKey($event, 'to')" @blur="addAddress('to')" />
                                <button v-if="toInput.trim()" type="button" class="chip-add" title="Ajouter cette adresse" @mousedown.prevent @click.stop="addAddress('to')">
                                    <span class="material-symbols-outlined">add</span>Ajouter
                                </button>
                            </div>
                            <p v-if="chipError.to" class="chip-error">{{ chipError.to }}</p>
                            <p v-else class="help">Entrée, virgule ou bouton « Ajouter » pour valider chaque adresse ; vous pouvez en ajouter plusieurs.</p>
                        </div>

                        <button v-if="!showCc" type="button" class="link" @click="showCc = true">
                            <span class="material-symbols-outlined">add</span>Ajouter des personnes en copie
                        </button>
                        <div v-else class="field">
                            <label class="label">Copie (Cc)</label>
                            <div class="chips-box" @click="($event.currentTarget as HTMLElement).querySelector('input')?.focus()">
                                <span v-for="email in form.cc" :key="email" class="chip">
                                    {{ email }}
                                    <button type="button" title="Retirer" @click.stop="removeAddress('cc', email)"><span class="material-symbols-outlined">close</span></button>
                                </span>
                                <input v-model="ccInput" type="email" class="chip-input" :placeholder="form.cc.length ? 'Ajouter une autre adresse' : 'nom@exemple.com'" @keydown="onKey($event, 'cc')" @blur="addAddress('cc')" />
                                <button v-if="ccInput.trim()" type="button" class="chip-add" title="Ajouter cette adresse" @mousedown.prevent @click.stop="addAddress('cc')">
                                    <span class="material-symbols-outlined">add</span>Ajouter
                                </button>
                            </div>
                            <p v-if="chipError.cc" class="chip-error">{{ chipError.cc }}</p>
                            <p v-else class="help">Entrée, virgule ou bouton « Ajouter » pour valider chaque adresse ; vous pouvez en ajouter plusieurs.</p>
                        </div>
                    </section>

                    <!-- 2. Message -->
                    <section class="section">
                        <h4 class="section-title"><span class="num">2</span>Message</h4>

                        <div class="field">
                            <label class="label" for="mail-subject">Objet</label>
                            <input id="mail-subject" v-model="form.subject" type="text" class="input" maxlength="255" placeholder="Objet de l'email" />
                        </div>

                        <div class="field">
                            <label class="label" for="mail-body">Texte de l'email</label>
                            <textarea id="mail-body" v-model="form.message" class="textarea" rows="8" placeholder="Votre message" />
                            <span class="counter">{{ form.message.length }} / 10 000</span>
                        </div>
                    </section>

                    <!-- 3. Pièces jointes -->
                    <section class="section">
                        <h4 class="section-title"><span class="num">3</span>Pièces jointes</h4>
                        <div class="attachments">
                            <span v-for="c in contracts" :key="c.id" class="attachment">
                                <span class="material-symbols-outlined pdf">picture_as_pdf</span>
                                <span class="attachment-text">
                                    <strong>{{ c.reference }}</strong>
                                    <small>{{ c.subject_name !== '-' ? c.subject_name : c.title }}</small>
                                </span>
                            </span>
                        </div>
                        <p class="help">{{ form.mode === 'each' ? 'Chaque personne ne reçoit que son propre contrat.' : 'Tous les contrats sont joints au même email.' }}</p>
                    </section>

                    <p v-for="(error, key) in form.errors" :key="key" class="callout error">
                        <span class="material-symbols-outlined">error</span>{{ error }}
                    </p>
                </div>

                <footer class="foot">
                    <span class="foot-hint" :class="{ blocked: sendBlocker }">{{ sendBlocker ?? footHint }}</span>
                    <button type="button" class="btn-secondary" :disabled="form.processing" @click="emit('close')">Annuler</button>
                    <button type="button" class="btn-primary" :disabled="!canSend || form.processing" @click="send">
                        <span v-if="form.processing" class="btn-spinner" />
                        <span v-else class="material-symbols-outlined">send</span>
                        {{ form.processing ? 'Envoi en cours…' : 'Envoyer' }}
                    </button>
                </footer>
            </form>
        </div>
    </Teleport>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(3px); }
.dialog { width: 100%; max-width: 720px; max-height: 94vh; display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25); }

.head { display: flex; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #eef1f4; }
.head-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: #fff0f4; color: #E5004C; flex-shrink: 0; }
.head-text { flex: 1; min-width: 0; }
.head-text h3 { font-size: 17px; font-weight: 700; color: #191c1e; }
.head-text p { font-size: 12.5px; color: #868e96; margin-top: 2px; }
.icon-close { display: inline-flex; padding: 6px; border: none; border-radius: 8px; background: transparent; color: #515f74; cursor: pointer; }
.icon-close:hover { background: #f2f4f6; }

.content { flex: 1; overflow-y: auto; padding: 4px 22px 18px; }
.section { display: flex; flex-direction: column; gap: 12px; padding: 18px 0; border-bottom: 1px solid #f0f2f5; }
.section:last-of-type { border-bottom: none; }
.section-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #1F3A4D; }
.num { width: 22px; height: 22px; border-radius: 50%; display: inline-grid; place-items: center; background: #1F3A4D; color: #fff; font-size: 12px; }

.modes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
@media (max-width: 600px) { .modes { grid-template-columns: 1fr; } }
.mode { position: relative; display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid #e0e3e5; border-radius: 12px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.mode input { position: absolute; opacity: 0; pointer-events: none; }
.mode.on { border-color: #E5004C; background: #fff5f8; }
.mode-icon { font-size: 22px; color: #9aaabb; }
.mode.on .mode-icon { color: #E5004C; }
.mode-text strong { display: block; font-size: 13.5px; color: #191c1e; }
.mode-text small { font-size: 12px; color: #868e96; line-height: 1.4; }

.table { border: 1px solid #e8edf2; border-radius: 12px; overflow: hidden; }
.table-head, .table-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr); gap: 12px; align-items: center; padding: 10px 14px; }
.table-head { background: #fafbfc; font-size: 11px; font-weight: 700; color: #868e96; text-transform: uppercase; letter-spacing: 0.04em; }
.table-row { border-top: 1px solid #f0f2f5; }
.table-row.missing { background: #fffbf3; }
.who { display: flex; flex-direction: column; min-width: 0; }
.who-name { font-size: 13.5px; font-weight: 600; color: #191c1e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.who-ref { font-size: 11.5px; color: #868e96; }
.address { display: flex; align-items: center; gap: 8px; }
.status-icon { font-size: 18px; color: #0a7b43; }
.table-row.missing .status-icon { color: #d97706; }
.who-sent { display: inline-flex; align-items: center; gap: 3px; margin-top: 2px; font-size: 11px; font-weight: 600; color: #1d4ed8; }
.who-sent .material-symbols-outlined { font-size: 13px; }
.callout.info { background: #e4edff; color: #1d4ed8; }

.field { position: relative; display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 700; color: #374151; }
.input { width: 100%; padding: 10px 12px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; font-size: 14px; color: #191c1e; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
.table-row.missing .input { border-color: #f5c27a; }
.input:focus, .textarea:focus, .chips-box:focus-within { border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229, 0, 76, 0.08); }
.textarea { width: 100%; min-height: 180px; padding: 12px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; font-family: inherit; font-size: 14px; line-height: 1.65; color: #191c1e; outline: none; resize: vertical; transition: border-color 0.15s, box-shadow 0.15s; }
.counter { align-self: flex-end; font-size: 11px; color: #9aaabb; }
.chips-box { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 44px; padding: 6px 10px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; cursor: text; transition: border-color 0.15s, box-shadow 0.15s; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 6px 4px 10px; border-radius: 99px; background: #eef1f4; font-size: 12.5px; color: #1F3A4D; }
.chip button { display: inline-flex; padding: 1px; border: none; border-radius: 50%; background: none; color: #868e96; cursor: pointer; }
.chip button:hover { background: #dfe4ea; }
.chip .material-symbols-outlined { font-size: 15px; }
.chip-input { flex: 1; min-width: 180px; padding: 4px 0; border: none; outline: none; font-size: 14px; background: transparent; }
.chip-add { display: inline-flex; align-items: center; gap: 2px; padding: 4px 10px; border: none; border-radius: 99px; background: #E5004C; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer; }
.chip-add .material-symbols-outlined { font-size: 16px; }
.chip-error { font-size: 12px; color: #ba1a1a; }
.link { align-self: flex-start; display: inline-flex; align-items: center; gap: 4px; padding: 0; border: none; background: none; color: #E5004C; font-size: 13px; font-weight: 600; cursor: pointer; }
.link .material-symbols-outlined { font-size: 18px; }

.attachments { display: flex; flex-wrap: wrap; gap: 8px; }
.attachment { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid #e8edf2; border-radius: 10px; background: #fafbfc; max-width: 100%; }
.pdf { font-size: 22px; color: #E5004C; }
.attachment-text { display: flex; flex-direction: column; min-width: 0; }
.attachment-text strong { font-size: 12.5px; color: #191c1e; }
.attachment-text small { font-size: 11.5px; color: #868e96; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }

.help { font-size: 12px; color: #868e96; }
.callout { display: flex; align-items: flex-start; gap: 6px; padding: 10px 12px; border-radius: 10px; font-size: 13px; line-height: 1.45; }
.callout .material-symbols-outlined { font-size: 18px; }
.callout.warn { background: #fff7e6; color: #8a5a00; }
.callout.error { margin-top: 8px; background: #ffeded; color: #ba1a1a; }

.foot { display: flex; align-items: center; gap: 10px; padding: 14px 22px; border-top: 1px solid #eef1f4; background: #fafbfc; }
.foot-hint { flex: 1; font-size: 12.5px; color: #515f74; }
.foot-hint.blocked { color: #b45309; font-weight: 600; }
.btn-secondary { padding: 10px 18px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; color: #515f74; font-size: 14px; font-weight: 500; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; border: none; border-radius: 10px; background: #E5004C; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary .material-symbols-outlined { font-size: 18px; }
.btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255, 255, 255, 0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
