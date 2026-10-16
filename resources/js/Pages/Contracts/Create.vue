<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ContractDocumentEditor from '@/Components/Contracts/ContractDocumentEditor.vue'
import RecipientPicker from '@/Components/Contracts/RecipientPicker.vue'
import ContractFontPicker from '@/Components/Contracts/ContractFontPicker.vue'
import LineSpacingPicker, { type LineSpacingOption } from '@/Components/Contracts/LineSpacingPicker.vue'
import LetterheadPicker from '@/Components/Contracts/LetterheadPicker.vue'
import type { ContractFontOption } from '@/composables/useContractFont'
import SignatoriesFields, { type Signatories } from '@/Components/Contracts/SignatoriesFields.vue'
import ProgressModal from '@/Components/UI/ProgressModal.vue'
import { postWithProgress } from '@/composables/useHttpProgress'

defineOptions({ layout: AdminLayout })

interface SubjectOption { value: string; label: string; group?: string }
interface TargetOption { value: string; label: string; color: string }
interface Letterhead {
    id: string
    name: string
    header_url: string | null
    footer_url: string | null
    header_height_mm: number | null
    footer_height_mm: number | null
}
type Source = 'upload' | 'ai'

const props = defineProps<{
    subjects: Record<string, SubjectOption[]>
    targets: TargetOption[]
    letterheads: Letterhead[]
    fonts: ContractFontOption[]
    lineSpacings: LineSpacingOption[]
    /** Set when the wizard is opened from a project: the type is the project's. */
    project: { id: string; name: string; target: string } | null
}>()

const steps = ['Type de contrat', 'Destinataire', 'Mise en page', 'Contenu']
const step = ref(props.project ? 2 : 1)

const form = useForm({
    contract_project_id: props.project?.id ?? null,
    target: props.project?.target ?? '',
    subject_id: '',
    name: '',
    body_html: '',
    signatories: null as Signatories | null,
    letterhead_id: '',
    letterhead_scope: 'all_pages',
    font: props.fonts[0]?.value ?? 'dejavu-serif',
    line_spacing: '1.5',
})

// ── Étape 1 : type ────────────────────────────────────────────────────────
const targetLabel = computed(() => props.targets.find(t => t.value === form.target)?.label ?? '')
const targetIcons: Record<string, string> = { trainer: 'school', cme: 'diversity_3', partner_convention: 'handshake' }

const chooseTarget = (value: string) => {
    if (form.target !== value) {
        form.subject_id = ''
        resetContent()
    }
    form.target = value
    step.value = 2
}

// ── Étape 2 : destinataire (un contrat = une personne) ────────────────────
const subjectOptions = computed<SubjectOption[]>(() => props.subjects[form.target] ?? [])
const selectedSubject = computed(() => subjectOptions.value.find(s => s.value === form.subject_id) ?? null)
const subjectCreation: Record<string, { href: string; label: string }> = {
    trainer: { href: '/users/create', label: 'Créer un formateur' },
    cme: { href: '/contracts/cmes/create', label: 'Créer un chargé de médiation emploi' },
    partner_convention: { href: '/partners/create', label: 'Créer un partenaire' },
}
const refreshingSubjects = ref(false)
// The creation page opens in another tab so the work in progress is not lost.
const refreshSubjects = () => router.reload({
    only: ['subjects'],
    onStart: () => { refreshingSubjects.value = true },
    onFinish: () => { refreshingSubjects.value = false },
})

// ── Étape 3 : contenu ─────────────────────────────────────────────────────
const source = ref<Source>('ai')
const busy = ref(false)
// What is running and how far it is; null percent = duration unknown.
const progress = ref<{ label: string; percent: number | null } | null>(null)
const contentError = ref<string | null>(null)
const contentNotice = ref<string | null>(null)
const prompt = ref('')
const questions = ref<Array<{ question: string; answer: string }>>([])
const questionsAsked = ref(false)
const instruction = ref('')
const refineOpen = ref(false)
const previousBodies = ref<string[]>([])

const hasDraft = computed(() => form.body_html.replace(/<[^>]*>/g, '').trim() !== '')
const contentReady = computed(() => hasDraft.value && form.name.trim() !== '')
// Tells the user what is still missing instead of a silently disabled button.
const missing = computed(() => {
    if (!hasDraft.value) return "Rédigez ou importez d'abord le contenu du contrat."
    if (form.name.trim() === '') return 'Renseignez le titre du contrat (au-dessus du contenu).'

    return null
})

function resetContent() {
    form.name = ''
    form.body_html = ''
    form.signatories = null
    prompt.value = ''
    questions.value = []
    questionsAsked.value = false
    instruction.value = ''
    refineOpen.value = false
    previousBodies.value = []
    contentError.value = null
    contentNotice.value = null
}

const errorMessage = (error: unknown, fallback: string) => {
    // fetch() helpers throw plain errors carrying the server message.
    if (error instanceof Error && !('response' in error)) return error.message || fallback
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    const first = response?.data?.errors ? Object.values(response.data.errors)[0]?.[0] : undefined

    return first ?? response?.data?.message ?? fallback
}

async function run(task: () => Promise<void>, fallback: string, label: string) {
    busy.value = true
    contentError.value = null
    contentNotice.value = null
    progress.value = { label, percent: null }
    try {
        await task()
    } catch (error: unknown) {
        contentError.value = errorMessage(error, fallback)
    } finally {
        busy.value = false
        progress.value = null
    }
}

/** Drafting and revising stream their progress: the bar follows the text actually received. */
const streamDraft = (payload: Record<string, unknown>, label: string) => postWithProgress<{ body_html: string; signatories?: Signatories }>(
    '/contract-templates/draft',
    { target: form.target, subject_id: form.subject_id || null, ...payload },
    percent => { progress.value = { label, percent } },
)

const applyProposal = (bodyHtml: string, signatories?: Signatories) => {
    if (hasDraft.value) previousBodies.value.push(form.body_html)
    form.body_html = bodyHtml
    if (signatories) form.signatories = { ...signatories }
    // The title is required to generate: start from the contract type so it is never left empty by oversight.
    if (!form.name.trim()) form.name = targetLabel.value
}

const draftRequest = (payload: Record<string, unknown>) => axios.post('/contract-templates/draft', {
    target: form.target,
    subject_id: form.subject_id || null,
    ...payload,
})

const onContractFile = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return

    run(async () => {
        const payload = new FormData()
        payload.append('model_file', file)
        payload.append('target', form.target)
        const { data } = await axios.post('/contract-templates/import', payload, {
            onUploadProgress: event => {
                const sent = event.total ? Math.round((event.loaded / event.total) * 100) : null
                progress.value = sent === 100 || sent === null
                    ? { label: 'Lecture du document…', percent: null }
                    : { label: `Envoi de « ${file.name} »`, percent: sent }
            },
        })
        // The title is printed apart: it comes out of the text into the name.
        if (!form.name) form.name = data.title || file.name.replace(/\.[^.]+$/, '')

        // The text is kept, the layout is redone in the house style. If the AI
        // is unavailable, the extracted text is used as is.
        try {
            const result = await streamDraft({ action: 'reformat', current_html: data.body_html }, 'Mise en forme du document importé')
            applyProposal(result.body_html, result.signatories ?? data.signatories)
            contentNotice.value = `« ${file.name} » importé et remis en forme. Relisez le contenu ci-dessous.`
        } catch (error: unknown) {
            applyProposal(data.body_html, data.signatories)
            contentNotice.value = `« ${file.name} » importé sans remise en forme automatique (${errorMessage(error, 'IA indisponible')}). Ajustez la mise en forme ci-dessous.`
        }
    }, "L'import a échoué. Vérifiez le fichier et réessayez.", 'Envoi du document')
}

const requestDraft = async () => {
    const result = await streamDraft({ action: 'draft', prompt: prompt.value, answers: questions.value }, "Rédaction du contrat par l'IA")
    applyProposal(result.body_html, result.signatories)
}

const askQuestions = () => run(async () => {
    const { data } = await draftRequest({ action: 'questions', prompt: prompt.value })
    questions.value = (data.questions as string[]).map(question => ({ question, answer: '' }))
    questionsAsked.value = true
    // Nothing to clarify: write the contract straight away.
    if (questions.value.length === 0) await requestDraft()
}, "L'IA n'a pas pu analyser la demande.", 'Analyse de votre demande…')

const generateDraft = () => run(requestDraft, "L'IA n'a pas pu rédiger le contrat.", "Rédaction du contrat par l'IA")

const revise = () => run(async () => {
    const result = await streamDraft({ action: 'revise', current_html: form.body_html, instruction: instruction.value }, 'Application de la modification')
    applyProposal(result.body_html, result.signatories)
    instruction.value = ''
}, "L'IA n'a pas pu appliquer la modification.", 'Application de la modification')

const undoProposal = () => {
    const previous = previousBodies.value.pop()
    if (previous !== undefined) form.body_html = previous
}

// ── Étape 4 : habillage, puis génération ──────────────────────────────────
const selectedFont = computed(() => props.fonts.find(f => f.value === form.font) ?? null)
const selectedLetterhead = computed(() => props.letterheads.find(l => l.id === form.letterhead_id) ?? null)

const submit = () => {
    form.transform(data => ({ ...data, name: data.name.trim(), subject_id: data.subject_id || null, letterhead_id: data.letterhead_id || null })).post('/contracts', {
        // Server errors may concern an earlier step: bring the user back to it.
        onError: errors => {
            if (errors.subject_id) step.value = 2
            else if (errors.letterhead_id || errors.font || errors.line_spacing) step.value = 3
        },
    })
}
</script>

<template>
    <Head title="Générer un contrat" />
    <div class="wrap">
        <div class="head">
            <Link :href="project ? `/contracts/projects/${project.id}` : '/contracts'" class="icon-back"><span class="material-symbols-outlined">arrow_back</span></Link>
            <div>
                <h1 class="title">Générer un contrat</h1>
                <p v-if="project" class="subtitle">Projet « {{ project.name }} » : ce contrat servira de base pour les autres personnes du projet.</p>
                <p v-else class="subtitle">Type, destinataire, en-tête et pied de page, puis contenu.</p>
            </div>
        </div>

        <ol class="stepper">
            <li v-for="(label, index) in steps" :key="label" :class="{ done: step > index + 1, current: step === index + 1 }">
                <button type="button" :disabled="index + 1 >= step || (project !== null && index === 0)" @click="step = index + 1">
                    <span class="step-num">{{ index + 1 }}</span>{{ label }}
                </button>
            </li>
        </ol>

        <div class="panel">
            <!-- 1. Type de contrat -->
            <template v-if="step === 1">
                <h2 class="panel-title">Quel type de contrat voulez-vous générer ?</h2>
                <div class="type-grid">
                    <button v-for="t in targets" :key="t.value" type="button" class="type-card" :class="{ on: form.target === t.value }" @click="chooseTarget(t.value)">
                        <span class="material-symbols-outlined type-ico">{{ targetIcons[t.value] ?? 'contract' }}</span>
                        <span class="type-name">{{ t.label }}</span>
                    </button>
                </div>
            </template>

            <!-- 2. Destinataire -->
            <template v-else-if="step === 2">
                <h2 class="panel-title">À qui s'adresse ce contrat ({{ targetLabel }}) ?</h2>
                <p class="hint">
                    Choisissez le destinataire pour que ses informations soient reprises dans le texte.
                    Ce n'est pas obligatoire : vous pouvez continuer sans, et indiquer son identité dans le contenu.
                </p>

                <div class="subject-tools">
                    <a v-if="subjectCreation[form.target]" :href="subjectCreation[form.target].href" target="_blank" rel="noopener" class="tool-link">
                        <span class="material-symbols-outlined" style="font-size:16px">person_add</span>
                        {{ subjectCreation[form.target].label }}
                    </a>
                    <button type="button" class="tool-link" :disabled="refreshingSubjects" @click="refreshSubjects">
                        <span class="material-symbols-outlined" style="font-size:16px">refresh</span>
                        {{ refreshingSubjects ? 'Actualisation…' : 'Actualiser la liste' }}
                    </button>
                </div>

                <p v-if="subjectOptions.length === 0" class="hint-warn">
                    Aucun destinataire ni utilisateur disponible pour ce type. Vous pouvez le créer (nouvel onglet) puis actualiser
                    la liste, ou continuer sans destinataire.
                </p>

                <RecipientPicker v-else v-model="form.subject_id" :options="subjectOptions" />
                <p v-if="form.errors.subject_id" class="error-msg">{{ form.errors.subject_id }}</p>

                <div class="actions">
                    <button v-if="!project" type="button" class="btn-secondary" @click="step = 1">Retour</button>
                    <span v-else />
                    <button type="button" class="btn-primary" @click="step = 3">
                        {{ form.subject_id ? 'Continuer' : 'Continuer sans destinataire' }}
                    </button>
                </div>
            </template>

            <!-- 3. En-tête & pied -->
            <template v-else-if="step === 3">
                <h2 class="panel-title">Mise en page</h2>
                <p class="hint">Choisie avant le contenu pour que les pages en tiennent compte. Tout reste modifiable ensuite, y compris après l'enregistrement. Les habillages se créent dans <Link href="/contracts/layouts" class="lnk">En-têtes &amp; pieds</Link>.</p>

                <div class="field">
                    <label class="label">Police du contrat</label>
                    <ContractFontPicker v-model="form.font" :fonts="fonts" />
                    <p v-if="form.errors.font" class="error-msg">{{ form.errors.font }}</p>
                </div>

                <div class="field">
                    <label class="label">Interligne</label>
                    <LineSpacingPicker v-model="form.line_spacing" :options="lineSpacings" />
                    <p v-if="form.errors.line_spacing" class="error-msg">{{ form.errors.line_spacing }}</p>
                </div>

                <div class="field">
                    <label class="label">En-tête et pied de page</label>
                    <LetterheadPicker v-model="form.letterhead_id" v-model:scope="form.letterhead_scope" :letterheads="letterheads" :error="form.errors.letterhead_id" />
                </div>

                <div class="actions">
                    <button type="button" class="btn-secondary" @click="step = 2">Retour</button>
                    <button type="button" class="btn-primary" @click="step = 4">
                        Continuer
                    </button>
                </div>
            </template>

            <!-- 4. Contenu -->
            <div v-else class="last-step">
                <h2 class="panel-title">Contenu : {{ targetLabel }}<template v-if="selectedSubject"> pour {{ selectedSubject.label }}</template></h2>

                <div class="source-tabs">
                    <button type="button" class="source" :class="{ on: source === 'ai' }" @click="source = 'ai'">
                        <span class="material-symbols-outlined">auto_awesome</span>Rédiger avec l'IA
                    </button>
                    <button type="button" class="source" :class="{ on: source === 'upload' }" @click="source = 'upload'">
                        <span class="material-symbols-outlined">upload_file</span>Importer un contrat
                    </button>
                </div>

                <p v-if="contentError || form.errors.name || form.errors.body_html" class="msg err">
                    {{ contentError || form.errors.name || form.errors.body_html }}
                </p>
                <p v-else-if="contentNotice" class="msg ok">{{ contentNotice }}</p>

                <!-- Import -->
                <div v-if="source === 'upload'" class="field">
                    <label class="import-zone" :class="{ 'is-busy': busy }">
                        <span class="material-symbols-outlined import-ico">{{ busy ? 'hourglass_top' : 'cloud_upload' }}</span>
                        <span class="import-title">{{ busy ? 'Lecture du document…' : 'Cliquez pour choisir un contrat' }}</span>
                        <span class="import-sub">Word (.docx), PDF, .txt ou .html (5 Mo max)</span>
                        <input type="file" accept=".docx,.pdf,.txt,.html,.htm" class="sr-only" :disabled="busy" @change="onContractFile" />
                    </label>
                    <p class="hint">Le texte du fichier est repris, puis remis dans la mise en forme des contrats (articles, paragraphes, signatures). Le contenu ne change pas.</p>
                </div>

                <!-- IA -->
                <div v-else class="ai-box">
                    <div class="field">
                        <label class="label">Décrivez le contrat et ses spécificités</label>
                        <textarea v-model="prompt" rows="4" class="input" placeholder="Ex : contrat de prestation de 3 mois à compter du 1er novembre 2026 pour une formation en développement web, 250 000 FCFA par mois, avec clause de confidentialité…" />
                    </div>

                    <div v-if="questionsAsked && questions.length" class="questions">
                        <p class="sublabel">Quelques précisions pour affiner (facultatives)</p>
                        <div v-for="(q, index) in questions" :key="index" class="field">
                            <label class="q-label">{{ q.question }}</label>
                            <input v-model="q.answer" type="text" class="input" />
                        </div>
                    </div>

                    <div class="row-actions">
                        <button v-if="!questionsAsked" type="button" class="btn-primary" :disabled="busy || prompt.trim().length < 10" @click="askQuestions">
                            <span v-if="busy" class="spinner" />
                            <span v-else class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>
                            {{ busy ? 'Analyse…' : 'Continuer' }}
                        </button>
                        <button v-else type="button" class="btn-primary" :disabled="busy || prompt.trim().length < 10" @click="generateDraft">
                            <span v-if="busy" class="spinner" />
                            <span v-else class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>
                            {{ busy ? 'Rédaction en cours…' : (hasDraft ? 'Régénérer le contrat' : 'Générer le contrat') }}
                        </button>
                    </div>
                </div>

                <!-- Contenu à relire / affiner -->
                <template v-if="hasDraft">
                    <div class="field">
                        <label class="label">Titre du contrat <span class="req">*</span></label>
                        <input v-model="form.name" type="text" class="input" :class="{ 'input-error': form.name.trim() === '' }" maxlength="255" placeholder="Ex : Contrat de prestation de formation" />
                    </div>

                    <div class="field">
                        <label class="label">Contenu proposé</label>
                        <p class="hint">Corrigez et mettez en forme directement sur les pages A4 : l'en-tête et le pied choisis y sont réservés, et le texte passe à la page suivante quand il dépasse.</p>
                        <ContractDocumentEditor
                            v-model="form.body_html"
                            :title="form.name"
                            :header-mm="selectedLetterhead?.header_height_mm"
                            :footer-mm="selectedLetterhead?.footer_height_mm"
                            :header-url="selectedLetterhead?.header_url"
                            :footer-url="selectedLetterhead?.footer_url"
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

                    <button v-if="!refineOpen" type="button" class="refine-toggle" @click="refineOpen = true">
                        <span class="material-symbols-outlined" style="font-size:18px">auto_fix_high</span>
                        Demander une modification à l'IA
                        <span class="optional">facultatif</span>
                    </button>

                    <div v-else class="refine">
                        <div class="refine-head">
                            <label class="label">Affiner avec l'IA <span class="optional">facultatif</span></label>
                            <button type="button" class="link-btn" @click="refineOpen = false">Fermer</button>
                        </div>
                        <p class="hint">
                            Si le contenu vous convient, validez directement. Sinon, décrivez la modification :
                            elle s'applique au contenu ci-dessus (importé ou rédigé), le reste du texte est conservé.
                        </p>
                        <textarea v-model="instruction" rows="4" class="input" placeholder="Ex : ajoute une clause de non-concurrence d'un an, reformule l'article 3 plus simplement, et précise que les frais de déplacement sont à la charge de l'organisme." />
                        <div class="refine-actions">
                            <button v-if="previousBodies.length" type="button" class="link-btn" :disabled="busy" @click="undoProposal">
                                ↩ Revenir à la version précédente ({{ previousBodies.length }})
                            </button>
                            <button type="button" class="btn-secondary push" :disabled="busy || !instruction.trim()" @click="revise">
                                <span v-if="busy" class="spinner dark" />
                                <span v-else class="material-symbols-outlined" style="font-size:18px">auto_fix_high</span>
                                {{ busy ? 'Application en cours…' : 'Appliquer au contenu' }}
                            </button>
                        </div>
                    </div>
                </template>

                <p v-if="missing" class="hint-warn missing">{{ missing }}</p>
                <div class="actions">
                    <button type="button" class="btn-secondary" @click="step = 3">Retour</button>
                    <button type="button" class="btn-primary" :disabled="!contentReady || busy || form.processing" @click="submit">
                        <span v-if="form.processing" class="spinner" />
                        <span v-else class="material-symbols-outlined" style="font-size:18px">task_alt</span>
                        Valider et générer le contrat
                    </button>
                </div>
            </div>
        </div>

        <ProgressModal
            :open="progress !== null"
            :title="progress?.percent === null ? 'Traitement en cours' : 'L\'IA rédige le contrat'"
            :label="progress?.label"
            :percent="progress?.percent ?? null"
        />
    </div>
</template>

<style scoped>
.wrap { max-width: 960px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.head { display: flex; align-items: center; gap: 16px; }
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid #1F3A4D; color: #1F3A4D; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #1F3A4D; color: #fff; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 14px; color: #515f74; margin-top: 2px; }

.stepper { display: flex; gap: 6px; list-style: none; padding: 0; margin: 0; flex-wrap: wrap; }
.stepper li { flex: 1; min-width: 150px; }
.stepper button { width: 100%; display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; border: 1.5px solid #e8edf2; background: #fff; font-size: 13px; font-weight: 600; color: #9aaabb; cursor: default; }
.stepper .done button { color: #1F3A4D; cursor: pointer; }
.stepper .current button { border-color: #E5004C; color: #E5004C; }
.step-num { width: 22px; height: 22px; border-radius: 50%; background: #e8edf2; color: #515f74; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stepper .current .step-num { background: #E5004C; color: #fff; }
.stepper .done .step-num { background: #1F3A4D; color: #fff; }

.panel { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; padding: 28px; display: flex; flex-direction: column; gap: 20px; }
.last-step { display: flex; flex-direction: column; gap: 20px; }
.panel-title { font-size: 17px; font-weight: 700; color: #191c1e; }
.type-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
.type-card { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 20px; border: 1.5px solid #e0e3e5; border-radius: 14px; background: #fafbfc; cursor: pointer; text-align: left; }
.type-card:hover, .type-card.on { border-color: #E5004C; background: #fff5f8; }
.type-ico { font-size: 28px; color: #E5004C; }
.type-name { font-size: 15px; font-weight: 700; color: #191c1e; }

.source-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.source { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 600; color: #515f74; cursor: pointer; }
.source .material-symbols-outlined { font-size: 18px; }
.source.on { border-color: #1F3A4D; background: #1F3A4D; color: #fff; }

.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 13px; font-weight: 700; color: #191c1e; }
.sublabel { font-size: 12px; font-weight: 600; color: #515f74; }
.q-label { font-size: 13px; color: #374151; }
.req { color: #E5004C; }
.input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; font-size: 14px; color: #191c1e; background: #fafbfc; outline: none; font-family: inherit; }
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229,0,76,0.08); background: #fff; }
textarea.input { resize: vertical; line-height: 1.5; }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }
.hint { font-size: 12px; color: #868e96; line-height: 1.5; }
.hint-warn { font-size: 13px; color: #b45309; }
.missing { text-align: right; }
.msg { font-size: 13px; padding: 10px 12px; border-radius: 8px; }
.msg.err { background: #ffeded; color: #ba1a1a; }
.msg.ok { background: #ecfdf3; color: #166534; }
.lnk { color: #E5004C; text-decoration: underline; }
.link-btn { background: none; border: none; color: #E5004C; font-size: 12px; font-weight: 600; cursor: pointer; padding: 0; }
.link-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.import-zone { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 26px 16px; border: 2px dashed #c3ccd6; border-radius: 12px; background: #fafbfc; cursor: pointer; text-align: center; }
.import-zone:hover { border-color: #E5004C; background: #fff5f8; }
.import-zone.is-busy { opacity: 0.6; pointer-events: none; }
.import-ico { font-size: 34px; color: #E5004C; }
.import-title { font-size: 14px; font-weight: 700; color: #1F3A4D; }
.import-sub { font-size: 12px; color: #868e96; }
.ai-box, .refine, .questions { display: flex; flex-direction: column; gap: 12px; }
.ai-box { border: 1px solid #f0f2f5; border-radius: 12px; padding: 18px; background: #fafbfc; }
.row-actions { display: flex; gap: 10px; }
.refine-actions { display: flex; align-items: center; gap: 12px; }
.refine-head { display: flex; align-items: center; justify-content: space-between; }
.refine-toggle { align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border: 1.5px dashed #c3ccd6; border-radius: 10px; background: #fff; color: #1F3A4D; font-size: 13px; font-weight: 600; cursor: pointer; }
.refine-toggle:hover { border-color: #E5004C; color: #E5004C; }
.optional { margin-left: 6px; padding: 1px 8px; border-radius: 99px; background: #f1f3f5; color: #868e96; font-size: 11px; font-weight: 600; }
.push { margin-left: auto; }

.subject-tools { display: flex; gap: 14px; flex-wrap: wrap; }
.tool-link { display: inline-flex; align-items: center; gap: 4px; background: none; border: none; padding: 0; font-size: 13px; font-weight: 600; color: #E5004C; cursor: pointer; text-decoration: none; }
.tool-link:disabled { opacity: 0.5; cursor: not-allowed; }

.radio { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #374151; cursor: pointer; }
.radio input { width: 17px; height: 17px; accent-color: #E5004C; }

.actions { display: flex; justify-content: space-between; gap: 10px; padding-top: 20px; border-top: 1px solid #f0f2f5; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 11px 24px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 11px 20px; background: transparent; color: #515f74; border-radius: 10px; font-size: 14px; font-weight: 500; border: 1.5px solid #e0e3e5; cursor: pointer; }
.btn-secondary:hover:not(:disabled) { background: #f2f4f6; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.spinner.dark { border-color: rgba(31,58,77,0.25); border-top-color: #1F3A4D; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
