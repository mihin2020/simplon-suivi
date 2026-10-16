<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ContractDocumentEditor from '@/Components/Contracts/ContractDocumentEditor.vue'
import SignatoriesFields, { type Signatories } from '@/Components/Contracts/SignatoriesFields.vue'
import ContractFontPicker from '@/Components/Contracts/ContractFontPicker.vue'
import LineSpacingPicker, { type LineSpacingOption } from '@/Components/Contracts/LineSpacingPicker.vue'
import LetterheadPicker, { type LetterheadOption } from '@/Components/Contracts/LetterheadPicker.vue'
import type { ContractFontOption } from '@/composables/useContractFont'
import { computed } from 'vue'

defineOptions({ layout: AdminLayout })

interface Contract {
    id: string
    reference: string
    title: string
    subject_name: string
    recipient_email: string | null
    subject_email: string | null
    target_label: string
    body_html: string | null
    signatories: Signatories | null
    header_height_mm: number | null
    footer_height_mm: number | null
    letterhead_scope: string | null
    letterhead_id: string | null
    font: string
    line_spacing: string
}

const props = defineProps<{ contract: Contract; letterheads: LetterheadOption[]; fonts: ContractFontOption[]; lineSpacings: LineSpacingOption[] }>()

const form = useForm({
    title: props.contract.title,
    body_html: props.contract.body_html ?? '',
    signatories: props.contract.signatories ? { ...props.contract.signatories } : null as Signatories | null,
    recipient_email: props.contract.recipient_email ?? '',
    letterhead_id: props.contract.letterhead_id ?? '',
    letterhead_scope: props.contract.letterhead_scope ?? 'all_pages',
    font: props.contract.font,
    line_spacing: props.contract.line_spacing,
})

const selectedFont = computed(() => props.fonts.find(f => f.value === form.font) ?? null)
const selectedLetterhead = computed(() => props.letterheads.find(l => l.id === form.letterhead_id) ?? null)

// What the pages show: the letterhead picked here, or, if it no longer exists
// in the library, the bands frozen on the contract (their room only).
const bands = computed(() => {
    const l = selectedLetterhead.value
    if (l) return { headerMm: l.header_height_mm, footerMm: l.footer_height_mm, headerUrl: l.header_url, footerUrl: l.footer_url }
    if (form.letterhead_id && form.letterhead_id === props.contract.letterhead_id) {
        return { headerMm: props.contract.header_height_mm, footerMm: props.contract.footer_height_mm, headerUrl: null, footerUrl: null }
    }

    return { headerMm: 0, footerMm: 0, headerUrl: null, footerUrl: null }
})

const submit = () => form.transform(data => ({ ...data, letterhead_id: data.letterhead_id || null })).put(`/contracts/${props.contract.id}`)
</script>

<template>
    <Head title="Modifier le contrat" />
    <div class="wrap">
        <div class="head">
            <Link :href="`/contracts/${contract.id}`" class="icon-back"><span class="material-symbols-outlined">arrow_back</span></Link>
            <div class="head-text">
                <h1 class="title">Modifier le document</h1>
                <p class="subtitle">{{ contract.reference }} · {{ contract.target_label }} · {{ contract.subject_name }}</p>
            </div>
            <a :href="`/contracts/${contract.id}/pdf`" target="_blank" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px">visibility</span> Aperçu PDF
            </a>
        </div>

        <form @submit.prevent="submit" class="panel">
            <div class="field">
                <label class="label">Titre du document <span class="req">*</span></label>
                <input v-model="form.title" type="text" class="input" :class="{ 'input-error': form.errors.title }" />
                <p v-if="form.errors.title" class="error-msg">{{ form.errors.title }}</p>
            </div>

            <div id="recipient-email" class="field">
                <label class="label">Email du destinataire</label>
                <input v-model="form.recipient_email" type="email" class="input" :class="{ 'input-error': form.errors.recipient_email }" :placeholder="contract.subject_email ?? 'adresse@exemple.com'" />
                <p class="hint">
                    Adresse à laquelle ce contrat est envoyé.
                    <template v-if="contract.subject_email">Laissez vide pour utiliser celle de la fiche ({{ contract.subject_email }}).</template>
                </p>
                <p v-if="form.errors.recipient_email" class="error-msg">{{ form.errors.recipient_email }}</p>
            </div>

            <section class="layout">
                <h2 class="section-title">Mise en page</h2>
                <div class="field">
                    <label class="label">En-tête et pied de page</label>
                    <LetterheadPicker v-model="form.letterhead_id" v-model:scope="form.letterhead_scope" :letterheads="letterheads" :error="form.errors.letterhead_id" />
                </div>
                <div class="field">
                    <label class="label">Police</label>
                    <ContractFontPicker v-model="form.font" :fonts="fonts" />
                    <p v-if="form.errors.font" class="error-msg">{{ form.errors.font }}</p>
                </div>
                <div class="field">
                    <label class="label">Interligne</label>
                    <LineSpacingPicker v-model="form.line_spacing" :options="lineSpacings" />
                    <p v-if="form.errors.line_spacing" class="error-msg">{{ form.errors.line_spacing }}</p>
                </div>
            </section>

            <div class="field">
                <label class="label">Corps du contrat</label>
                <p class="hint">Vous modifiez ce document précis, tel qu'il sera imprimé : en-tête, pied de page, police et interligne ci-dessus s'appliquent aux pages.</p>
                <ContractDocumentEditor
                    v-model="form.body_html"
                    :title="form.title"
                    :header-mm="bands.headerMm"
                    :footer-mm="bands.footerMm"
                    :header-url="bands.headerUrl"
                    :footer-url="bands.footerUrl"
                    :first-page-only="form.letterhead_scope === 'first_page'"
                    :signatories="form.signatories"
                    :font="selectedFont"
                    :line-spacing="form.line_spacing"
                />
            </div>

            <SignatoriesFields v-if="form.signatories" v-model="form.signatories" />
            <button v-else type="button" class="link-btn" @click="form.signatories = { left_title: 'Pour SIMPLON BURKINA FASO', left_name: 'M. BAGRE Dominique', right_title: '', right_name: '' }">
                + Ajouter un bloc de signatures sur deux colonnes
            </button>

            <div class="actions">
                <Link :href="`/contracts/${contract.id}`" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    <span v-else class="material-symbols-outlined" style="font-size:18px">check</span>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.wrap { max-width: 980px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
.head { display: flex; align-items: center; gap: 16px; }
.head-text { flex: 1; }
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid #1F3A4D; color: #1F3A4D; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #1F3A4D; color: #fff; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 13px; color: #515f74; margin-top: 2px; }
.panel { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; padding: 28px; display: flex; flex-direction: column; gap: 22px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 700; color: #191c1e; letter-spacing: 0.04em; text-transform: uppercase; }
.req { color: #E5004C; }
.hint { font-size: 12px; color: #9aaabb; }
.input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; font-size: 14px; color: #191c1e; background: #fafbfc; outline: none; }
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229,0,76,0.08); background: #fff; }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }
.actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid #f0f2f5; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 11px 24px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 11px 20px; background: transparent; color: #515f74; border-radius: 10px; font-size: 14px; font-weight: 500; border: 1.5px solid #e0e3e5; text-decoration: none; }
.btn-secondary:hover { background: #f2f4f6; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.layout { display: flex; flex-direction: column; gap: 18px; padding: 20px; border: 1px solid #eef1f4; border-radius: 12px; background: #fafbfc; }
.section-title { font-size: 15px; font-weight: 700; color: #1F3A4D; }
.link-btn { align-self: flex-start; padding: 0; border: none; background: none; color: #E5004C; font-size: 13px; font-weight: 600; cursor: pointer; }
</style>
