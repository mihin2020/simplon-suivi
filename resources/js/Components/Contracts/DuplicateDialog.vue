<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ProgressBar from '@/Components/UI/ProgressBar.vue'
import RecipientPicker from '@/Components/Contracts/RecipientPicker.vue'
import { useDownload } from '@/composables/useHttpProgress'

interface SubjectOption { value: string; label: string; group?: string }
type Mode = 'people' | 'excel'

const props = defineProps<{
    open: boolean
    contractId: string
    /** Name of the person the source contract was written for, if any. */
    sourceName?: string | null
    subjects: SubjectOption[]
    /** Recipients who already have this contract: hidden from the list. */
    excludedIds?: string[]
    /** Columns of this contract's Excel model, once detected. */
    fillFields?: string[]
    fillFieldsReady?: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const mode = ref<Mode>('people')

// ── Personnes de la plateforme ────────────────────────────────────────────
const form = useForm({ subject_ids: [] as string[] })
const available = computed(() => props.subjects.filter(s => !(props.excludedIds ?? []).includes(s.value)))

const submitPeople = () => form.post(`/contracts/${props.contractId}/duplicate`, { onSuccess: () => emit('close') })

// ── Fichier Excel ─────────────────────────────────────────────────────────
const model = useDownload()
const modelUrl = computed(() => `/contracts/${props.contractId}/fill-model`)
const modelLabel = computed(() => model.phase.value === 'preparing'
    ? (props.fillFieldsReady ? 'Préparation du modèle…' : 'Préparation du modèle : repérage des informations propres à la personne…')
    : 'Téléchargement du modèle')

const excel = useForm<{ file: File | null }>({ file: null })
const onExcel = (event: Event) => {
    const input = event.target as HTMLInputElement
    excel.file = input.files?.[0] ?? null
    input.value = ''
}
const submitExcel = () => excel.post(`/contracts/${props.contractId}/fill-model`, { forceFormData: true, onSuccess: () => emit('close') })

watch(() => props.open, open => {
    if (open) {
        mode.value = 'people'
        form.reset()
        form.clearErrors()
        excel.reset()
        excel.clearErrors()
    }
})
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="overlay" @click.self="emit('close')">
            <div class="dialog">
                <h3 class="dialog-title">Générer ce contrat pour d'autres personnes</h3>

                <div class="modes">
                    <button type="button" class="mode" :class="{ on: mode === 'people' }" @click="mode = 'people'">
                        <span class="material-symbols-outlined">group</span>Choisir sur la plateforme
                    </button>
                    <button type="button" class="mode" :class="{ on: mode === 'excel' }" @click="mode = 'excel'">
                        <span class="material-symbols-outlined">table_view</span>Remplir un fichier Excel
                    </button>
                </div>

                <!-- Personnes déjà enregistrées -->
                <form v-if="mode === 'people'" class="body" @submit.prevent="submitPeople">
                    <p class="hint">
                        Un contrat distinct est créé pour chaque personne cochée, avec le même texte et le même habillage.
                        <template v-if="sourceName">
                            Les informations de <strong>{{ sourceName }}</strong> présentes dans le texte (nom, adresse, pièce d'identité…)
                            sont remplacées par celles de chaque personne.
                        </template>
                        <template v-else>
                            Ce contrat n'a pas de destinataire enregistré : le texte est copié tel quel. Pour l'adapter à
                            chaque personne, utilisez plutôt le fichier Excel.
                        </template>
                    </p>

                    <p v-if="available.length === 0" class="empty">
                        Aucune personne enregistrée pour ce type de contrat. Utilisez le fichier Excel pour générer des contrats
                        pour des personnes qui ne sont pas sur la plateforme.
                    </p>

                    <RecipientPicker v-else v-model:selected="form.subject_ids" :options="available" multiple :limit="10" />

                    <p v-if="form.errors.subject_ids" class="error-msg">{{ form.errors.subject_ids }}</p>
                    <ProgressBar v-if="form.processing" :label="`Génération de ${form.subject_ids.length} contrat(s)…`" :percent="null" />

                    <div class="actions">
                        <button type="button" class="btn-secondary" :disabled="form.processing" @click="emit('close')">Annuler</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing || form.subject_ids.length === 0">
                            {{ form.processing ? 'Génération…' : form.subject_ids.length > 1 ? `Générer ${form.subject_ids.length} contrats` : 'Générer le contrat' }}
                        </button>
                    </div>
                </form>

                <!-- Fichier Excel propre à ce contrat -->
                <form v-else class="body" @submit.prevent="submitExcel">
                    <div class="excel-step">
                        <span class="step-num">1</span>
                        <div class="step-body">
                            <p class="step-title">Téléchargez le modèle de ce contrat</p>
                            <p class="hint">
                                Chaque contrat a son propre modèle : une colonne par information qui change d'une personne à l'autre.
                                <template v-if="fillFieldsReady && fillFields?.length">Colonnes : <strong>{{ fillFields.join(', ') }}</strong>.</template>
                                <template v-else>Il est préparé automatiquement après l'enregistrement du contrat.</template>
                            </p>
                            <button type="button" class="btn-secondary" :disabled="model.isBusy()" @click="model.download(modelUrl, 'modele_contrat.xlsx')">
                                <span class="material-symbols-outlined" style="font-size:18px">{{ model.phase.value === 'done' ? 'check_circle' : 'download' }}</span>
                                {{ model.phase.value === 'done' ? 'Modèle téléchargé' : model.isBusy() ? 'En cours…' : 'Télécharger le modèle Excel' }}
                            </button>
                            <ProgressBar v-if="model.isBusy()" :label="modelLabel" :percent="model.phase.value === 'downloading' ? model.percent.value : null" />
                            <p v-if="model.error.value" class="error-msg">{{ model.error.value }}</p>
                        </div>
                    </div>

                    <div class="excel-step">
                        <span class="step-num">2</span>
                        <div class="step-body">
                            <p class="step-title">Remplissez une ligne par personne</p>
                            <p class="hint">La ligne grisée reprend le contrat d'origine en exemple : remplacez-la ou supprimez-la. Ne modifiez pas les colonnes. La dernière colonne « Email du destinataire » permet d'envoyer ensuite chaque contrat à sa personne.</p>
                        </div>
                    </div>

                    <div class="excel-step">
                        <span class="step-num">3</span>
                        <div class="step-body">
                            <p class="step-title">Déposez le fichier rempli</p>
                            <label class="drop" :class="{ 'has-file': excel.file }">
                                <input type="file" accept=".xlsx,.xls,.csv" class="sr-only" @change="onExcel" />
                                <span class="material-symbols-outlined drop-ico">{{ excel.file ? 'description' : 'cloud_upload' }}</span>
                                <span class="drop-title">{{ excel.file ? excel.file.name : 'Cliquez pour choisir le fichier Excel' }}</span>
                                <span class="drop-sub">.xlsx, .xls ou .csv (200 lignes max)</span>
                            </label>
                            <p v-if="excel.errors.file" class="error-msg">{{ excel.errors.file }}</p>
                            <ProgressBar
                                v-if="excel.processing"
                                :label="excel.progress && excel.progress.percentage !== undefined && excel.progress.percentage < 100 ? 'Envoi du fichier' : 'Génération des contrats…'"
                                :percent="excel.progress && excel.progress.percentage !== undefined && excel.progress.percentage < 100 ? excel.progress.percentage : null"
                            />
                        </div>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn-secondary" :disabled="excel.processing" @click="emit('close')">Annuler</button>
                        <button type="submit" class="btn-primary" :disabled="excel.processing || !excel.file">
                            {{ excel.processing ? 'Génération…' : 'Générer les contrats' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 50; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
.dialog { background: #fff; border-radius: 16px; padding: 24px; width: 100%; max-width: 580px; max-height: 92vh; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
.dialog-title { font-size: 18px; font-weight: 700; color: #191c1e; }
.modes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.mode { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 600; color: #515f74; cursor: pointer; }
.mode .material-symbols-outlined { font-size: 18px; }
.mode.on { border-color: #1F3A4D; background: #1F3A4D; color: #fff; }
.body { display: flex; flex-direction: column; gap: 14px; }
.hint { font-size: 13px; color: #515f74; line-height: 1.5; }
.empty { padding: 14px; font-size: 13px; color: #868e96; text-align: center; background: #fafbfc; border-radius: 10px; }
.excel-step { display: flex; gap: 12px; }
.step-num { width: 24px; height: 24px; border-radius: 50%; background: #1F3A4D; color: #fff; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.step-body { flex: 1; display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.step-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.drop { align-self: stretch; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 18px; border: 2px dashed #c3ccd6; border-radius: 12px; background: #fafbfc; cursor: pointer; text-align: center; }
.drop:hover { border-color: #E5004C; background: #fff5f8; }
.drop.has-file { border-style: solid; border-color: #1F3A4D; }
.drop-ico { font-size: 30px; color: #E5004C; }
.drop-title { font-size: 14px; font-weight: 700; color: #1F3A4D; word-break: break-all; }
.drop-sub { font-size: 12px; color: #868e96; }
.error-msg { font-size: 13px; color: #ba1a1a; }
.actions { display: flex; justify-content: flex-end; gap: 10px; }
.btn-primary { padding: 10px 20px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; background: #fff; color: #515f74; border-radius: 10px; font-size: 14px; border: 1.5px solid #e0e3e5; cursor: pointer; }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
</style>
