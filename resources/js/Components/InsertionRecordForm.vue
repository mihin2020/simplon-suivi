<script setup lang="ts">
import { computed } from 'vue'
import ContractFileUpload from '@/Components/ContractFileUpload.vue'

interface ContractTypeItem { id: string; name: string }
interface WorkModeOption { value: string; label: string; icon: string }

interface InsertionForm {
    internship_company: string
    internship_start_date: string
    internship_end_date: string
    internship_contract_type: string
    internship_work_mode: string
    internship_paid: boolean
    internship_contract_file: File | null
    employment_company: string
    employment_position: string
    employment_start_date: string
    employment_contract_type: string
    employment_work_mode: string
    employment_contract_file: File | null
    status_notes: string
    processing: boolean
    errors: Record<string, string>
}

const props = defineProps<{
    kind: 'internship' | 'employment'
    form: InsertionForm
    visible: boolean
    isEditing: boolean
    contractTypes: ContractTypeItem[]
    workModes: WorkModeOption[]
    canManageContractTypes: boolean
    existingContractUrl?: string | null
    existingContractName?: string | null
    showExistingContract: boolean
}>()

const emit = defineEmits<{
    submit: []
    cancel: []
    'manage-types': []
    'remove-existing': []
}>()

const isInternship = () => props.kind === 'internship'

const title = () => {
    if (isInternship()) {
        return props.isEditing ? 'Modifier le stage' : 'Nouveau stage'
    }
    return props.isEditing ? 'Modifier l\'emploi' : 'Nouvel emploi'
}

const subtitle = () => {
    if (isInternship()) {
        return 'Renseignez les informations du stage et joignez la convention si disponible.'
    }
    return 'Renseignez les informations de l\'emploi et joignez le contrat si disponible.'
}

const submitLabel = () => {
    if (props.form.processing) return 'Enregistrement...'
    if (isInternship()) {
        return props.isEditing ? 'Enregistrer les modifications' : 'Enregistrer le stage'
    }
    return props.isEditing ? 'Enregistrer les modifications' : 'Enregistrer l\'emploi'
}

const workMode = computed({
    get: () => (props.kind === 'internship'
        ? props.form.internship_work_mode
        : props.form.employment_work_mode),
    set: (value: string) => {
        if (props.kind === 'internship') {
            props.form.internship_work_mode = value
        } else {
            props.form.employment_work_mode = value
        }
    },
})
</script>

<template>
    <Transition name="form-panel">
        <div v-if="visible" class="insertion-form-panel" :class="`panel-${kind}`">
            <div class="panel-header">
                <div class="panel-header-left">
                    <div class="panel-icon">
                        <span class="material-symbols-outlined">
                            {{ kind === 'internship' ? 'business_center' : 'work' }}
                        </span>
                    </div>
                    <div>
                        <h3 class="panel-title">{{ title() }}</h3>
                        <p class="panel-subtitle">{{ subtitle() }}</p>
                    </div>
                </div>
                <button type="button" class="panel-close" title="Fermer" @click="emit('cancel')">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="panel-body" @submit.prevent="emit('submit')">
                <!-- Section 1 -->
                <section class="form-section">
                    <h4 class="section-heading">
                        <span class="section-num">1</span>
                        {{ kind === 'internship' ? 'Entreprise & période' : 'Entreprise & poste' }}
                    </h4>
                    <div class="fields-grid">
                        <div class="field">
                            <label class="field-label">Entreprise <span class="req">*</span></label>
                            <input
                                v-if="kind === 'internship'"
                                v-model="form.internship_company"
                                type="text"
                                class="field-input"
                                placeholder="Nom de l'entreprise d'accueil"
                                required
                            />
                            <input
                                v-else
                                v-model="form.employment_company"
                                type="text"
                                class="field-input"
                                placeholder="Nom de l'employeur"
                                required
                            />
                        </div>

                        <div v-if="kind === 'employment'" class="field">
                            <label class="field-label">Poste occupé</label>
                            <input
                                v-model="form.employment_position"
                                type="text"
                                class="field-input"
                                placeholder="Ex : Développeur web junior"
                            />
                        </div>

                        <div class="field">
                            <div class="field-label-row">
                                <label class="field-label">Type de contrat <span class="req">*</span></label>
                                <button
                                    v-if="canManageContractTypes"
                                    type="button"
                                    class="link-btn"
                                    @click="emit('manage-types')"
                                >
                                    Gérer les types
                                </button>
                            </div>
                            <select
                                v-if="kind === 'internship'"
                                v-model="form.internship_contract_type"
                                class="field-input"
                                required
                            >
                                <option value="">Sélectionner un type...</option>
                                <option v-for="t in contractTypes" :key="t.id" :value="t.name">{{ t.name }}</option>
                            </select>
                            <select
                                v-else
                                v-model="form.employment_contract_type"
                                class="field-input"
                                required
                            >
                                <option value="">Sélectionner un type...</option>
                                <option v-for="t in contractTypes" :key="t.id" :value="t.name">{{ t.name }}</option>
                            </select>
                        </div>

                        <div class="field">
                            <label class="field-label">
                                {{ kind === 'internship' ? 'Date de début' : 'Date de prise de poste' }}
                                <span v-if="kind === 'internship'" class="req">*</span>
                            </label>
                            <input
                                v-if="kind === 'internship'"
                                v-model="form.internship_start_date"
                                type="date"
                                class="field-input"
                                required
                            />
                            <input
                                v-else
                                v-model="form.employment_start_date"
                                type="date"
                                class="field-input"
                            />
                        </div>

                        <div v-if="kind === 'internship'" class="field">
                            <label class="field-label">Date de fin <span class="field-hint">(optionnel)</span></label>
                            <input v-model="form.internship_end_date" type="date" class="field-input">
                        </div>
                    </div>
                </section>

                <!-- Section 2 -->
                <section class="form-section">
                    <h4 class="section-heading">
                        <span class="section-num">2</span>
                        Modalité & document
                    </h4>
                    <div class="mode-doc-row">
                        <div class="mode-col">
                            <label class="field-label">Modalité <span class="req">*</span></label>
                            <div class="mode-pills">
                                <label
                                    v-for="m in workModes"
                                    :key="m.value"
                                    class="mode-pill"
                                    :class="{ active: workMode === m.value }"
                                >
                                    <input
                                        v-model="workMode"
                                        type="radio"
                                        :value="m.value"
                                        class="sr-only"
                                        required
                                    />
                                    <span class="material-symbols-outlined pill-icon">{{ m.icon }}</span>
                                    <span>{{ m.label }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="doc-col">
                            <ContractFileUpload
                                v-if="kind === 'internship'"
                                v-model="form.internship_contract_file"
                                label="Contrat ou convention de stage"
                                :existing-url="existingContractUrl"
                                :existing-name="existingContractName"
                                :show-existing="showExistingContract"
                                @remove-existing="emit('remove-existing')"
                            />
                            <ContractFileUpload
                                v-else
                                v-model="form.employment_contract_file"
                                label="Contrat d'emploi"
                                :existing-url="existingContractUrl"
                                :existing-name="existingContractName"
                                :show-existing="showExistingContract"
                                @remove-existing="emit('remove-existing')"
                            />
                        </div>
                    </div>
                </section>

                <!-- Section 3 -->
                <section class="form-section form-section-last">
                    <h4 class="section-heading">
                        <span class="section-num">3</span>
                        Compléments
                    </h4>

                    <label v-if="kind === 'internship'" class="toggle-row">
                        <input v-model="form.internship_paid" type="checkbox" class="toggle-input">
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-text">
                            <span class="toggle-title">Stage rémunéré</span>
                            <span class="toggle-desc">L'apprenant perçoit une gratification ou un salaire</span>
                        </span>
                    </label>

                    <div class="field">
                        <label class="field-label">Notes internes</label>
                        <textarea
                            v-model="form.status_notes"
                            class="field-textarea"
                            rows="3"
                            :placeholder="kind === 'internship' ? 'Commentaires, contacts RH, remarques...' : 'Commentaires sur l\'emploi...'"
                        ></textarea>
                    </div>
                </section>

                <div class="panel-footer">
                    <button type="button" class="btn-ghost" @click="emit('cancel')">Annuler</button>
                    <button type="submit" class="btn-save" :disabled="form.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">save</span>
                        {{ submitLabel() }}
                    </button>
                </div>
            </form>
        </div>
    </Transition>
</template>

<style scoped>
.insertion-form-panel {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
}
.panel-internship { border-top: 3px solid #E5004C; }
.panel-employment { border-top: 3px solid #1F3A4D; }

.panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #fafbfc 0%, #fff 100%);
    border-bottom: 1px solid #eef0f2;
}
.panel-header-left {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}
.panel-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.panel-internship .panel-icon { background: #fff0f4; color: #E5004C; }
.panel-employment .panel-icon { background: #e8edf2; color: #1F3A4D; }
.panel-title {
    font-size: 17px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
}
.panel-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin: 4px 0 0;
    line-height: 1.4;
}
.panel-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid #e0e3e5;
    border-radius: 10px;
    background: #fff;
    color: #515f74;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.panel-close:hover { background: #f5f7f9; color: #191c1e; }

.panel-body { padding: 0 24px 24px; }
.form-section {
    padding: 20px 0;
    border-bottom: 1px solid #f0f1f3;
}
.form-section-last { border-bottom: none; padding-bottom: 8px; }
.section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 16px;
}
.section-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
}
.panel-internship .section-num { background: #E5004C; }
.panel-employment .section-num { background: #1F3A4D; }

.fields-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
@media (min-width: 768px) {
    .fields-grid { grid-template-columns: 1fr 1fr; }
}

.field { display: flex; flex-direction: column; gap: 6px; }
.field-label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
}
.field-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.field-hint { font-weight: 500; color: #9aaabb; }
.req { color: #E5004C; }
.link-btn {
    border: none;
    background: none;
    padding: 0;
    font-size: 11px;
    font-weight: 600;
    color: #E5004C;
    cursor: pointer;
    text-decoration: underline;
}
.link-btn:hover { color: #c0003e; }
.field-input,
.field-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #e0e3e5;
    border-radius: 10px;
    font-size: 14px;
    color: #191c1e;
    background: #fafbfc;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.field-input:focus,
.field-textarea:focus {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.field-textarea { resize: vertical; min-height: 80px; }

.mode-doc-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    align-items: start;
}
@media (min-width: 768px) {
    .mode-doc-row { grid-template-columns: 1fr 1fr; }
}
.mode-col, .doc-col { min-width: 0; }

.mode-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.mode-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    border: 1.5px solid #e0e3e5;
    border-radius: 12px;
    background: #fafbfc;
    font-size: 13px;
    font-weight: 600;
    color: #515f74;
    cursor: pointer;
    transition: all 0.15s;
    flex: 1;
    min-width: 140px;
    justify-content: center;
}
.mode-pill:hover { border-color: #c8cdd3; background: #fff; }
.mode-pill.active {
    border-color: #E5004C;
    background: #fff5f8;
    color: #E5004C;
    box-shadow: 0 0 0 1px rgba(229, 0, 76, 0.15);
}
.panel-employment .mode-pill.active {
    border-color: #1F3A4D;
    background: #f0f4f8;
    color: #1F3A4D;
    box-shadow: 0 0 0 1px rgba(31, 58, 77, 0.15);
}
.pill-icon { font-size: 20px; }

.toggle-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    background: #fafbfc;
    border: 1px solid #eef0f2;
    border-radius: 12px;
    cursor: pointer;
    margin-bottom: 14px;
}
.toggle-input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-track {
    position: relative;
    width: 44px;
    height: 24px;
    background: #d1d5db;
    border-radius: 99px;
    flex-shrink: 0;
    margin-top: 2px;
    transition: background 0.2s;
}
.toggle-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}
.toggle-input:checked + .toggle-track { background: #E5004C; }
.toggle-input:checked + .toggle-track .toggle-thumb { transform: translateX(20px); }
.toggle-text { display: flex; flex-direction: column; gap: 2px; }
.toggle-title { font-size: 13px; font-weight: 600; color: #191c1e; }
.toggle-desc { font-size: 12px; color: #9aaabb; }

.panel-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 16px;
    margin-top: 8px;
    border-top: 1px solid #f0f1f3;
}
.btn-ghost {
    padding: 10px 18px;
    border: 1.5px solid #e0e3e5;
    border-radius: 10px;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
    color: #515f74;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-ghost:hover { background: #f5f7f9; }
.btn-save {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    background: #E5004C;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.panel-employment .btn-save { background: #1F3A4D; }
.btn-save:hover:not(:disabled) { filter: brightness(1.05); }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.form-panel-enter-active,
.form-panel-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.form-panel-enter-from,
.form-panel-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
