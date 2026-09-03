<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Can from '@/Components/Can.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

interface Conductor { id: string; first_name: string; last_name: string }
interface InterviewUser { id: string; first_name: string; last_name: string; email: string }
interface CustomField { label: string; value: string }
interface Interview {
    id: string
    conducted_at: string
    subject: string
    notes: string | null
    recommendation: string | null
    next_follow_up_at: string | null
    is_important: boolean
    custom_fields?: CustomField[]
    conductor: Conductor | null
}
interface Formation {
    id: string
    name: string
    pivot: { status: string }
}

const props = defineProps<{
    learnerId: string
    learnerName: string
    currentStatus: string
    interviews: Interview[]
    interviewUsers: InterviewUser[]
    currentUserId?: string
    hasStage: boolean
    hasEmployment: boolean
}>()

const emit = defineEmits<{ switchTab: [tab: 'stage' | 'employment' | 'formations'] }>()

const today = new Date().toISOString().slice(0, 10)
const toDateInput = (value?: string | null) => value ? value.slice(0, 10) : ''
const fmt = (value: string | null) => {
    if (!value) return '—'
    const [y, m, d] = value.slice(0, 10).split('-')
    return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString('fr-FR', {
        day: 'numeric', month: 'long', year: 'numeric',
    })
}
const conductorName = (user?: Conductor | InterviewUser | null) =>
    user ? `${user.first_name} ${user.last_name}`.trim() : '—'

const stripHtml = (value?: string | null) => {
    if (!value) return ''
    return value
        .replace(/<br\s*\/?>/gi, ' ')
        .replace(/<\/p>/gi, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
}

const excerpt = (value?: string | null, max = 72) => {
    const plain = stripHtml(value)
    if (!plain) return ''
    return plain.length > max ? `${plain.slice(0, max).trim()}…` : plain
}

const hasRichContent = (value?: string | null) => Boolean(stripHtml(value))

const timelineRef = ref<HTMLElement | null>(null)
const scrollToHistory = () => {
    timelineRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const formOpen = ref(false)
const editing = ref<Interview | null>(null)
const viewing = ref<Interview | null>(null)
const deleteTarget = ref<Interview | null>(null)
const deleting = ref(false)

const form = useForm({
    conducted_at: today,
    subject: '',
    notes: '',
    recommendation: '',
    next_follow_up_at: '',
    is_important: false,
    conducted_by: props.currentUserId ?? '',
    custom_fields: [] as CustomField[],
})

const emptyForm = () => ({
    conducted_at: today,
    subject: '',
    notes: '',
    recommendation: '',
    next_follow_up_at: '',
    is_important: false,
    conducted_by: props.currentUserId ?? '',
    custom_fields: [] as CustomField[],
})

const cloneFields = (fields?: CustomField[]) =>
    (fields ?? []).map(field => ({ label: field.label, value: field.value }))

const addCustomField = () => {
    if (form.custom_fields.length >= 20) return
    form.custom_fields.push({ label: '', value: '' })
}

const removeCustomField = (index: number) => {
    form.custom_fields.splice(index, 1)
}

const openCreate = () => {
    editing.value = null
    form.clearErrors()
    form.defaults(emptyForm())
    form.reset()
    formOpen.value = true
}

const openEdit = (interview: Interview) => {
    viewing.value = null
    editing.value = interview
    form.clearErrors()
    form.defaults({
        conducted_at: toDateInput(interview.conducted_at),
        subject: interview.subject,
        notes: interview.notes ?? '',
        recommendation: interview.recommendation ?? '',
        next_follow_up_at: toDateInput(interview.next_follow_up_at),
        is_important: interview.is_important,
        conducted_by: interview.conductor?.id ?? props.currentUserId ?? '',
        custom_fields: cloneFields(interview.custom_fields),
    })
    form.reset()
    formOpen.value = true
}

const closeForm = () => { formOpen.value = false; editing.value = null }

const submit = () => {
    // Keep only rows with a label so validation stays simple.
    form.custom_fields = form.custom_fields
        .map(field => ({ label: field.label.trim(), value: field.value.trim() }))
        .filter(field => field.label !== '')

    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['interviews', 'flash'] as string[],
        onSuccess: closeForm,
    }
    if (editing.value) {
        form.put(`/learners/${props.learnerId}/interviews/${editing.value.id}`, options)
        return
    }
    form.post(`/learners/${props.learnerId}/interviews`, options)
}

const confirmDelete = () => {
    if (!deleteTarget.value) return
    deleting.value = true
    router.delete(`/learners/${props.learnerId}/interviews/${deleteTarget.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['interviews', 'flash'],
        onFinish: () => { deleting.value = false; deleteTarget.value = null },
    })
}

const upcoming = computed(() =>
    props.interviews
        .filter(item => item.next_follow_up_at && item.next_follow_up_at.slice(0, 10) >= today)
        .sort((a, b) => (a.next_follow_up_at ?? '').localeCompare(b.next_follow_up_at ?? ''))
        .slice(0, 2)
)
const important = computed(() => props.interviews.filter(item => item.is_important).slice(0, 2))
const latest = computed(() => props.interviews.slice(0, 2))
const remainingLatest = computed(() => Math.max(0, props.interviews.length - latest.value.length))
const remainingImportant = computed(() => Math.max(0, props.interviews.filter(i => i.is_important).length - important.value.length))
const remainingUpcoming = computed(() => {
    const total = props.interviews.filter(item => item.next_follow_up_at && item.next_follow_up_at.slice(0, 10) >= today).length
    return Math.max(0, total - upcoming.value.length)
})
</script>

<template>
    <div class="interviews">
        <div class="follow-grid">
            <article class="follow-card">
                <p class="follow-label">Statut actuel</p>
                <p class="follow-value">{{ currentStatus }}</p>
                <button type="button" class="follow-link" @click="emit('switchTab', 'formations')">Voir le parcours</button>
            </article>
            <article class="follow-card">
                <p class="follow-label">Prochains suivis</p>
                <p v-if="upcoming.length === 0" class="follow-empty">Aucun suivi planifié</p>
                <ul v-else class="follow-list">
                    <li v-for="item in upcoming" :key="item.id" class="follow-row">
                        <span class="follow-date">{{ fmt(item.next_follow_up_at) }}</span>
                        <span class="follow-title" :title="item.subject">{{ item.subject }}</span>
                    </li>
                </ul>
                <button v-if="remainingUpcoming > 0" type="button" class="follow-more" @click="scrollToHistory">
                    +{{ remainingUpcoming }} autre(s) · voir l’historique
                </button>
            </article>
            <article class="follow-card">
                <p class="follow-label">Dernières actions</p>
                <p v-if="latest.length === 0" class="follow-empty">Aucun entretien</p>
                <ul v-else class="follow-list">
                    <li v-for="item in latest" :key="item.id" class="follow-row">
                        <span class="follow-date">{{ fmt(item.conducted_at) }}</span>
                        <span class="follow-title" :title="item.subject">{{ item.subject }}</span>
                        <span v-if="excerpt(item.recommendation)" class="follow-excerpt">{{ excerpt(item.recommendation) }}</span>
                    </li>
                </ul>
                <button v-if="remainingLatest > 0" type="button" class="follow-more" @click="scrollToHistory">
                    +{{ remainingLatest }} autre(s) · voir l’historique
                </button>
            </article>
            <article class="follow-card">
                <p class="follow-label">Stage & insertion</p>
                <div class="follow-links">
                    <button type="button" class="follow-link" @click="emit('switchTab', 'stage')">
                        Stage{{ hasStage ? ' · renseigné' : '' }}
                    </button>
                    <button type="button" class="follow-link" @click="emit('switchTab', 'employment')">
                        Emploi{{ hasEmployment ? ' · renseigné' : '' }}
                    </button>
                </div>
            </article>
        </div>

        <div v-if="important.length" class="important-box">
            <div class="important-head">
                <p class="important-title">Observations importantes</p>
                <button v-if="remainingImportant > 0" type="button" class="follow-more" @click="scrollToHistory">
                    +{{ remainingImportant }} · historique
                </button>
            </div>
            <button
                v-for="item in important"
                :key="item.id"
                type="button"
                class="important-item"
                @click="viewing = item"
            >
                <span class="important-date">{{ fmt(item.conducted_at) }}</span>
                <span class="important-text">{{ excerpt(item.notes || item.subject, 110) }}</span>
            </button>
        </div>

        <div ref="timelineRef">
        <div class="toolbar">
            <div>
                <h2 class="title">Historique des entretiens</h2>
                <p class="hint">{{ interviews.length }} entretien(s) · l’ajout n’écrase jamais les précédents</p>
            </div>
            <Can permission="learners.update">
                <button type="button" class="btn-add" @click="openCreate">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Ajouter un entretien
                </button>
            </Can>
        </div>

        <div v-if="interviews.length === 0" class="empty">
            <span class="material-symbols-outlined empty-icon">forum</span>
            <p>Aucun entretien enregistré pour {{ learnerName }}.</p>
            <Can permission="learners.update">
                <button type="button" class="btn-add" @click="openCreate">Ajouter un entretien</button>
            </Can>
        </div>

        <ol v-else class="timeline">
            <li v-for="interview in interviews" :key="interview.id" class="timeline-item" :class="{ important: interview.is_important }">
                <div class="dot"></div>
                <article class="card">
                    <header class="card-head">
                        <div>
                            <p class="date">{{ fmt(interview.conducted_at) }}</p>
                            <h3>{{ interview.subject }}</h3>
                            <p class="meta">Réalisé par {{ conductorName(interview.conductor) }}</p>
                        </div>
                        <div class="actions">
                            <button type="button" class="icon-btn view" title="Consulter" @click="viewing = interview">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                            <Can permission="learners.update">
                                <button type="button" class="icon-btn edit" title="Modifier" @click="openEdit(interview)">
                                    <span class="material-symbols-outlined">edit</span>
                                </button>
                                <button type="button" class="icon-btn del" title="Supprimer" @click="deleteTarget = interview">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </Can>
                        </div>
                    </header>
                    <div v-if="hasRichContent(interview.recommendation)" class="reco">
                        <span class="reco-label">Prochaine action</span>
                        <div class="rich-preview" v-html="interview.recommendation" />
                    </div>
                    <ul v-if="(interview.custom_fields ?? []).length" class="custom-preview">
                        <li v-for="(field, idx) in (interview.custom_fields ?? []).slice(0, 3)" :key="`${interview.id}-cf-${idx}`">
                            <strong>{{ field.label }}</strong>
                            <span v-if="field.value"> — {{ excerpt(field.value, 60) }}</span>
                        </li>
                    </ul>
                    <p v-if="interview.next_follow_up_at" class="next">Prochain suivi : {{ fmt(interview.next_follow_up_at) }}</p>
                </article>
            </li>
        </ol>
        </div>
    </div>

    <Teleport to="body">
        <Transition name="modal">
            <div v-if="formOpen" class="backdrop" @click.self="!form.processing && closeForm()">
                <div class="modal">
                    <div class="modal-head">
                        <h2>{{ editing ? 'Modifier l’entretien' : 'Ajouter un entretien' }}</h2>
                        <button type="button" class="close" :disabled="form.processing" @click="closeForm"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <form class="modal-body" @submit.prevent="submit">
                        <div v-if="form.processing" class="save-overlay" aria-live="polite">
                            <span class="spinner" />
                            <p>Enregistrement en cours…</p>
                        </div>
                        <label class="field">
                            <span>Date de l’entretien *</span>
                            <input v-model="form.conducted_at" type="date" class="input" :disabled="form.processing" />
                            <small v-if="form.errors.conducted_at" class="error">{{ form.errors.conducted_at }}</small>
                        </label>
                        <label class="field">
                            <span>Objet / motif *</span>
                            <input v-model="form.subject" type="text" class="input" placeholder="Point de suivi pédagogique" :disabled="form.processing" />
                            <small v-if="form.errors.subject" class="error">{{ form.errors.subject }}</small>
                        </label>
                        <label class="field">
                            <span>Réalisé par</span>
                            <select v-model="form.conducted_by" class="input" :disabled="form.processing">
                                <option v-for="user in interviewUsers" :key="user.id" :value="user.id">{{ conductorName(user) }}</option>
                            </select>
                        </label>
                        <div class="field">
                            <span>Notes / compte rendu</span>
                            <TiptapEditor v-model="form.notes" min-height="140px" placeholder="Rédigez le compte rendu…" />
                        </div>
                        <div class="field">
                            <span>Prochaine action / recommandation</span>
                            <TiptapEditor v-model="form.recommendation" min-height="100px" placeholder="Actions à prévoir…" />
                        </div>

                        <div class="custom-fields">
                            <div class="custom-fields-head">
                                <div>
                                    <p class="custom-title">Points / champs personnalisés</p>
                                    <p class="custom-hint">Ajoutez librement des points d’échange (ex. motivation, blocages, objectifs…)</p>
                                </div>
                                <button type="button" class="btn-add-field" :disabled="form.processing || form.custom_fields.length >= 20" @click="addCustomField">
                                    <span class="material-symbols-outlined" style="font-size:16px">add</span>
                                    Ajouter
                                </button>
                            </div>
                            <div v-if="form.custom_fields.length === 0" class="custom-empty">Aucun point ajouté pour le moment.</div>
                            <div v-for="(field, index) in form.custom_fields" :key="index" class="custom-row">
                                <input
                                    v-model="field.label"
                                    type="text"
                                    class="input"
                                    placeholder="Libellé (ex. Motivation)"
                                    :disabled="form.processing"
                                    maxlength="120"
                                />
                                <textarea
                                    v-model="field.value"
                                    class="input textarea"
                                    rows="2"
                                    placeholder="Contenu du point…"
                                    :disabled="form.processing"
                                    maxlength="2000"
                                />
                                <button type="button" class="icon-btn del" title="Retirer" :disabled="form.processing" @click="removeCustomField(index)">
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                            </div>
                            <small v-if="form.errors.custom_fields" class="error">{{ form.errors.custom_fields }}</small>
                        </div>

                        <label class="field">
                            <span>Date du prochain suivi</span>
                            <input v-model="form.next_follow_up_at" type="date" class="input" :disabled="form.processing" />
                            <small v-if="form.errors.next_follow_up_at" class="error">{{ form.errors.next_follow_up_at }}</small>
                        </label>
                        <label class="check">
                            <input v-model="form.is_important" type="checkbox" :disabled="form.processing" />
                            Marquer comme observation importante
                        </label>
                        <div class="modal-foot">
                            <button type="button" class="btn-ghost" :disabled="form.processing" @click="closeForm">Annuler</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner spinner-sm" />
                                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <Transition name="modal">
            <div v-if="viewing" class="backdrop" @click.self="viewing = null">
                <div class="modal">
                    <div class="modal-head">
                        <h2>Entretien du {{ fmt(viewing.conducted_at) }}</h2>
                        <button type="button" class="close" @click="viewing = null"><span class="material-symbols-outlined">close</span></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Objet :</strong> {{ viewing.subject }}</p>
                        <p><strong>Réalisé par :</strong> {{ conductorName(viewing.conductor) }}</p>
                        <div v-if="hasRichContent(viewing.notes)" class="view-block">
                            <strong>Compte rendu :</strong>
                            <div class="rich-content" v-html="viewing.notes" />
                        </div>
                        <div v-if="hasRichContent(viewing.recommendation)" class="view-block">
                            <strong>Recommandations :</strong>
                            <div class="rich-content" v-html="viewing.recommendation" />
                        </div>
                        <div v-if="(viewing.custom_fields ?? []).length" class="view-block">
                            <strong>Points personnalisés :</strong>
                            <dl class="custom-view">
                                <template v-for="(field, idx) in viewing.custom_fields" :key="`view-cf-${idx}`">
                                    <dt>{{ field.label }}</dt>
                                    <dd>{{ field.value || '—' }}</dd>
                                </template>
                            </dl>
                        </div>
                        <p v-if="viewing.next_follow_up_at"><strong>Prochain suivi :</strong> {{ fmt(viewing.next_follow_up_at) }}</p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <ConfirmModal
        :show="Boolean(deleteTarget)"
        title="Supprimer cet entretien ?"
        :message="deleteTarget ? `L’entretien « ${deleteTarget.subject} » du ${fmt(deleteTarget.conducted_at)} sera retiré de l’historique.` : ''"
        :loading="deleting"
        @cancel="deleteTarget = null"
        @confirm="confirmDelete"
    />
</template>

<style scoped>
.interviews { display: flex; flex-direction: column; gap: 16px; }
.follow-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.follow-card { background: #fff; border: 1px solid #e8edf2; border-radius: 12px; padding: 14px 16px; }
.follow-label { margin: 0; font-size: 11px; font-weight: 700; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.follow-value { margin: 6px 0 8px; font-size: 15px; font-weight: 700; color: #1F3A4D; }
.follow-empty { margin: 8px 0 0; font-size: 13px; color: #9aaabb; }
.follow-list { margin: 8px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
.follow-row { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.follow-date { font-size: 11px; font-weight: 700; color: #E5004C; }
.follow-title { font-size: 13px; font-weight: 600; color: #191c1e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.follow-excerpt { font-size: 12px; color: #6b7280; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.follow-more { margin-top: 8px; border: none; background: transparent; color: #1F3A4D; font-size: 12px; font-weight: 600; cursor: pointer; padding: 0; text-align: left; }
.follow-more:hover { color: #E5004C; }
.follow-link { border: none; background: transparent; color: #E5004C; font-size: 12px; font-weight: 600; cursor: pointer; padding: 0; text-align: left; }
.follow-links { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
.important-box { background: #fff8e8; border: 1px solid #fde68a; border-radius: 12px; padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.important-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.important-title { margin: 0; font-size: 12px; font-weight: 700; color: #92400e; }
.important-item { display: flex; flex-direction: column; gap: 2px; width: 100%; border: none; background: transparent; text-align: left; cursor: pointer; padding: 0; }
.important-date { font-size: 11px; font-weight: 700; color: #92400e; }
.important-text { font-size: 13px; color: #78350f; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.toolbar { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; }
.title { margin: 0; font-size: 16px; font-weight: 700; color: #191c1e; }
.hint { margin: 4px 0 0; font-size: 12px; color: #9aaabb; }
.btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #E5004C; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-add:hover { background: #c0003e; }
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 40px 16px; background: #fff; border: 1px solid #e8edf2; border-radius: 12px; color: #9aaabb; }
.empty-icon { font-size: 36px; color: #d0d5db; }
.timeline { list-style: none; margin: 0; padding: 0 0 0 14px; border-left: 2px solid #e8edf2; display: flex; flex-direction: column; gap: 12px; }
.timeline-item { position: relative; padding-left: 18px; }
.dot { position: absolute; left: -9px; top: 18px; width: 14px; height: 14px; border-radius: 50%; background: #1F3A4D; border: 3px solid #fff; box-shadow: 0 0 0 2px #1F3A4D; }
.timeline-item.important .dot { background: #E5004C; box-shadow: 0 0 0 2px #E5004C; }
.card { background: #fff; border: 1px solid #e8edf2; border-radius: 12px; padding: 14px 16px; }
.card-head { display: flex; justify-content: space-between; gap: 12px; }
.date { margin: 0; font-size: 12px; font-weight: 700; color: #E5004C; }
.card h3 { margin: 2px 0 4px; font-size: 15px; color: #191c1e; }
.meta { margin: 0; font-size: 12px; color: #6b7280; }
.reco, .next { margin: 8px 0 0; font-size: 13px; color: #1F3A4D; }
.reco-label { display: block; font-size: 11px; font-weight: 700; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
.rich-preview, .rich-content :deep(p) { margin: 0 0 0.4rem; }
.rich-preview :deep(ul), .rich-content :deep(ul) { list-style: disc; padding-left: 1.2rem; margin: 0.25rem 0; }
.rich-preview :deep(ol), .rich-content :deep(ol) { list-style: decimal; padding-left: 1.2rem; margin: 0.25rem 0; }
.rich-preview { max-height: 4.5em; overflow: hidden; font-size: 13px; color: #1F3A4D; }
.view-block { display: flex; flex-direction: column; gap: 6px; }
.rich-content { font-size: 13px; color: #191c1e; line-height: 1.5; }
.actions { display: flex; gap: 2px; }
.icon-btn { width: 30px; height: 30px; border: none; background: transparent; border-radius: 8px; cursor: pointer; color: #6b7280; display: inline-flex; align-items: center; justify-content: center; }
.icon-btn .material-symbols-outlined { font-size: 18px; }
.icon-btn.view:hover { color: #1F3A4D; background: #eef4f8; }
.icon-btn.edit:hover { color: #2563eb; background: #eff6ff; }
.icon-btn.del:hover { color: #dc2626; background: #fef2f2; }
.backdrop {
    position: fixed; inset: 0; z-index: 80;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal { width: 100%; max-width: 640px; background: #fff; border-radius: 16px; max-height: 86vh; overflow: hidden; display: flex; flex-direction: column; position: relative; }
.modal-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid #f0f2f5; }
.modal-head h2 { margin: 0; font-size: 17px; }
.close { width: 34px; height: 34px; border: none; border-radius: 50%; background: #f2f4f6; cursor: pointer; }
.modal-body { padding: 18px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; position: relative; }
.save-overlay {
    position: absolute; inset: 0; z-index: 5;
    background: rgba(255,255,255,0.78);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
    backdrop-filter: blur(1px);
}
.save-overlay p { margin: 0; font-size: 13px; font-weight: 600; color: #1F3A4D; }
.spinner {
    width: 28px; height: 28px; border-radius: 50%;
    border: 3px solid #e5e7eb; border-top-color: #E5004C;
    animation: spin 0.7s linear infinite;
}
.spinner-sm { width: 14px; height: 14px; border-width: 2px; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #1F3A4D; }
.input { width: 100%; border: 1.5px solid #e0e3e5; border-radius: 8px; padding: 9px 10px; font-size: 13px; font-weight: 500; }
.input:focus { outline: none; border-color: #1F3A4D; }
.input:disabled, .textarea:disabled { opacity: 0.7; cursor: not-allowed; }
.textarea { resize: vertical; min-height: 56px; font-family: inherit; }
.custom-fields { display: flex; flex-direction: column; gap: 10px; padding: 12px; background: #f8fafc; border: 1px solid #e8edf2; border-radius: 10px; }
.custom-fields-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.custom-title { margin: 0; font-size: 13px; font-weight: 700; color: #1F3A4D; }
.custom-hint { margin: 2px 0 0; font-size: 12px; font-weight: 500; color: #6b7280; }
.custom-empty { font-size: 12px; color: #9aaabb; }
.custom-row { display: grid; grid-template-columns: 1fr; gap: 6px; position: relative; padding-right: 36px; }
.custom-row .icon-btn { position: absolute; top: 0; right: 0; }
.btn-add-field {
    display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;
    border: 1px solid #d0d7de; background: #fff; color: #1F3A4D;
    border-radius: 8px; padding: 6px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
}
.btn-add-field:disabled { opacity: 0.5; cursor: not-allowed; }
.custom-preview { margin: 8px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 4px; }
.custom-preview li { font-size: 12px; color: #374151; }
.custom-preview strong { color: #1F3A4D; }
.custom-view { margin: 0; display: grid; grid-template-columns: minmax(120px, 160px) 1fr; gap: 6px 12px; font-size: 13px; }
.custom-view dt { font-weight: 700; color: #1F3A4D; }
.custom-view dd { margin: 0; color: #374151; white-space: pre-wrap; }
.check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1F3A4D; }
.error { color: #E5004C; font-weight: 500; }
.modal-foot { display: flex; justify-content: flex-end; gap: 8px; }
.btn-ghost { padding: 8px 14px; border: 1px solid #e0e3e5; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-primary { padding: 8px 16px; background: #1F3A4D; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary:disabled, .btn-ghost:disabled { opacity: 0.65; cursor: not-allowed; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
@media (max-width: 1023px) { .follow-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 767px) { .follow-grid, .toolbar, .card-head, .custom-fields-head { display: flex; flex-direction: column; } .custom-view { grid-template-columns: 1fr; } }
</style>
