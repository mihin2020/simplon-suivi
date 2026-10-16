<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'
import DuplicateDialog from '@/Components/Contracts/DuplicateDialog.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import ContractBulkBar from '@/Components/Contracts/ContractBulkBar.vue'
import RecipientCell from '@/Components/Contracts/RecipientCell.vue'
import EmailStatusBadge from '@/Components/Contracts/EmailStatusBadge.vue'
import ProgressModal from '@/Components/UI/ProgressModal.vue'
import { useDownload } from '@/composables/useHttpProgress'
import { projectDeletionMessage } from '@/Components/Contracts/projectDeletion'

defineOptions({ layout: AdminLayout })

interface Project { id: string; name: string; description: string | null; target: string; target_label: string }
interface ContractItem {
    id: string
    reference: string
    title: string
    subject_id: string | null
    subject_name: string
    recipient_email: string | null
    is_nominative: boolean
    emailed_at: string | null
    emailed_to: string | null
    email_count: number
    email_status: 'queued' | 'sent' | 'failed' | null
    status_label: string
    status_color: string
    fill_fields: string[]
    fill_fields_ready: boolean
}

const props = defineProps<{
    project: Project
    contracts: ContractItem[]
    subjects: Array<{ value: string; label: string }>
}>()

const statusColors: Record<string, string> = { amber: 'c-amber', blue: 'c-blue', emerald: 'c-emerald', gray: 'c-gray' }

// The first contract written in the project is the base the others are copied from.
const base = computed(() => props.contracts[0] ?? null)
const source = ref<ContractItem | null>(null)
const pdf = useDownload()

const selectedIds = ref<string[]>([])
const selectedList = computed(() => props.contracts.filter(c => selectedIds.value.includes(c.id)))
const allSelected = computed(() => props.contracts.length > 0 && selectedIds.value.length === props.contracts.length)

// While emails leave in the background, refresh the list so each badge moves
// from "being sent" to "sent". Stops by itself after a few minutes (no worker?).
const sending = computed(() => props.contracts.some(c => c.email_status === 'queued'))
let pollTimer: ReturnType<typeof setInterval> | undefined
let pollStarted = 0
const stopPolling = () => { clearInterval(pollTimer); pollTimer = undefined }
watch(sending, active => {
    if (active && !pollTimer) {
        pollStarted = Date.now()
        pollTimer = setInterval(() => {
            if (Date.now() - pollStarted > 5 * 60_000) return stopPolling()
            router.reload({ only: ['contracts'] })
        }, 4000)
    } else if (!active) {
        stopPolling()
    }
}, { immediate: true })
onBeforeUnmount(stopPolling)

const toggleAll = () => { selectedIds.value = allSelected.value ? [] : props.contracts.map(c => c.id) }
const pdfUrl = (c: ContractItem) => `/contracts/${c.id}/pdf/download`
const coveredIds = computed(() => props.contracts.map(c => c.subject_id).filter((id): id is string => id !== null))
const remaining = computed(() => props.subjects.filter(s => !coveredIds.value.includes(s.value)).length)

// ── Modifier / supprimer le projet ────────────────────────────────────────
const editing = ref(false)
const form = useForm({ name: props.project.name, description: props.project.description ?? '' })
const save = () => form.put(`/contracts/projects/${props.project.id}`, { preserveScroll: true, onSuccess: () => { editing.value = false } })

const confirmDelete = ref(false)
const deleting = ref(false)
const destroy = () => router.delete(`/contracts/projects/${props.project.id}`, {
    onStart: () => { deleting.value = true },
    onFinish: () => { deleting.value = false; confirmDelete.value = false },
})

// ── Dupliquer un contrat à l'identique, pour le modifier ensuite ─────────
const copying = ref<string | null>(null)
const copy = (c: ContractItem) => router.post(`/contracts/${c.id}/copy`, {}, {
    onStart: () => { copying.value = c.id },
    onFinish: () => { copying.value = null },
})
</script>

<template>
    <Head :title="project.name" />
    <div class="wrap">
        <div class="head">
            <Link href="/contracts/projects" class="icon-back"><span class="material-symbols-outlined">arrow_back</span></Link>
            <div class="head-text">
                <h1 class="title">{{ project.name }}</h1>
                <p class="subtitle">{{ project.target_label }} · {{ contracts.length }} contrat{{ contracts.length > 1 ? 's' : '' }}</p>
            </div>
            <Can permission="contracts.update">
                <button type="button" class="btn-secondary" @click="editing = !editing">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span> Modifier
                </button>
            </Can>
            <Can permission="contracts.delete">
                <button type="button" class="btn-danger-outline" title="Supprimer le projet et ses contrats" @click="confirmDelete = true">
                    <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                </button>
            </Can>
        </div>

        <form v-if="editing" class="panel" @submit.prevent="save">
            <div class="field">
                <label class="label">Nom du projet</label>
                <input v-model="form.name" type="text" class="input" maxlength="255" />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>
            <div class="field">
                <label class="label">Description</label>
                <textarea v-model="form.description" rows="3" class="input" maxlength="2000" />
            </div>
            <div class="row-end">
                <button type="button" class="btn-secondary" @click="editing = false">Annuler</button>
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.name.trim()">Enregistrer</button>
            </div>
        </form>
        <p v-else-if="project.description" class="description">{{ project.description }}</p>

        <!-- Étape 1 : pas encore de contrat -->
        <div v-if="!base" class="panel empty-state">
            <span class="material-symbols-outlined big-ico">edit_document</span>
            <h2 class="panel-title">Commencez par rédiger le contrat de base</h2>
            <p class="hint">
                Rédigez-le une fois, pour une première personne (ou sans destinataire). Vous le générerez ensuite
                en un clic pour toutes les autres.
            </p>
            <Can permission="contracts.create">
                <Link :href="`/contracts/create?project=${project.id}`" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">auto_awesome</span>
                    Rédiger le contrat
                </Link>
            </Can>
        </div>

        <!-- Étape 2 : générer pour les autres -->
        <template v-else>
            <div class="panel banner">
                <div>
                    <h2 class="panel-title">Générer pour d'autres personnes</h2>
                    <p class="hint">
                        À partir du contrat de base « {{ base.title }} »
                        <template v-if="base.subject_name !== '-'">({{ base.subject_name }})</template>.
                        {{ remaining }} personne{{ remaining > 1 ? 's' : '' }} sans contrat dans ce projet.
                    </p>
                </div>
                <Can permission="contracts.create">
                    <div class="banner-actions">
                        <Link :href="`/contracts/create?project=${project.id}`" class="btn-secondary">
                            <span class="material-symbols-outlined" style="font-size:18px">add</span> Rédiger un autre contrat
                        </Link>
                        <button type="button" class="btn-primary" @click="source = base">
                            <span class="material-symbols-outlined" style="font-size:18px">group_add</span> Générer pour plusieurs personnes
                        </button>
                    </div>
                </Can>
            </div>

            <p v-if="pdf.error.value" class="error-msg">{{ pdf.error.value }}</p>
            <ProgressModal
                :open="pdf.isBusy()"
                title="Téléchargement du contrat"
                :label="pdf.phase.value === 'preparing' ? 'Préparation du PDF…' : 'Réception du fichier'"
                :percent="pdf.phase.value === 'downloading' ? pdf.percent.value : null"
                icon="download"
            />
            <ContractBulkBar v-if="selectedList.length" :selected="selectedList" @clear="selectedIds = []" />

            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="check"><input type="checkbox" :checked="allSelected" title="Tout sélectionner" @change="toggleAll" /></th>
                            <th>Référence</th><th>Destinataire</th><th>Titre</th><th>Statut</th><th>Email</th><th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, index) in contracts" :key="c.id" :class="{ 'row-selected': selectedIds.includes(c.id) }">
                            <td class="check"><input v-model="selectedIds" type="checkbox" :value="c.id" /></td>
                            <td class="mono">{{ c.reference }}</td>
                            <td>
                                <RecipientCell :name="c.subject_name" :email="c.recipient_email" :nominative="c.is_nominative" />
                                <span v-if="index === 0" class="base-tag">contrat de base</span>
                            </td>
                            <td>{{ c.title }}</td>
                            <td><span class="badge" :class="statusColors[c.status_color]">{{ c.status_label }}</span></td>
                            <td><EmailStatusBadge :status="c.email_status" :emailed-at="c.emailed_at" :emailed-to="c.emailed_to" :count="c.email_count" /></td>
                            <td class="row-actions">
                                <Link :href="`/contracts/${c.id}`" class="icon-btn" title="Ouvrir"><span class="material-symbols-outlined">visibility</span></Link>
                                <Can permission="contracts.update">
                                    <Link :href="`/contracts/${c.id}/edit`" class="icon-btn" title="Modifier"><span class="material-symbols-outlined">edit</span></Link>
                                </Can>
                                <Can permission="contracts.create">
                                    <button type="button" class="icon-btn" title="Dupliquer ce contrat pour le modifier" :disabled="copying !== null" @click="copy(c)">
                                        <span class="material-symbols-outlined" :class="{ spin: copying === c.id }">{{ copying === c.id ? 'progress_activity' : 'content_copy' }}</span>
                                    </button>
                                    <button type="button" class="icon-btn" title="Générer pour d'autres personnes à partir de ce contrat" @click="source = c">
                                        <span class="material-symbols-outlined">group_add</span>
                                    </button>
                                </Can>
                                <button type="button" class="icon-btn" title="Télécharger le PDF" :disabled="pdf.isBusy()" @click="pdf.download(pdfUrl(c), `contrat_${c.reference}.pdf`)">
                                    <span class="material-symbols-outlined" :class="{ spin: pdf.isBusy(pdfUrl(c)) }">{{ pdf.isBusy(pdfUrl(c)) ? 'progress_activity' : 'download' }}</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <ConfirmModal
            :show="confirmDelete"
            title="Supprimer le projet et ses contrats"
            :message="projectDeletionMessage(project.name, contracts.length)"
            :confirm-label="contracts.length > 0 ? 'Tout supprimer' : 'Supprimer le projet'"
            :loading="deleting"
            @confirm="destroy"
            @cancel="confirmDelete = false"
        />

        <DuplicateDialog
            :open="source !== null"
            :contract-id="source?.id ?? ''"
            :source-name="source && source.subject_name !== '-' ? source.subject_name : null"
            :subjects="subjects"
            :excluded-ids="coveredIds"
            :fill-fields="source?.fill_fields"
            :fill-fields-ready="source?.fill_fields_ready"
            @close="source = null"
        />
    </div>
</template>

<style scoped>
.wrap { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.head-text { flex: 1; min-width: 200px; }
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid #1F3A4D; color: #1F3A4D; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #1F3A4D; color: #fff; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 13px; color: #515f74; margin-top: 2px; }
.description { font-size: 14px; color: #374151; line-height: 1.6; white-space: pre-line; background: #fff; border: 1px solid #e8edf2; border-radius: 14px; padding: 16px 18px; }
.panel { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.panel-title { font-size: 17px; font-weight: 700; color: #191c1e; }
.empty-state { align-items: center; text-align: center; padding: 40px 24px; }
.big-ico { font-size: 44px; color: #E5004C; }
.banner { flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; }
.banner-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.hint { font-size: 13px; color: #515f74; line-height: 1.5; max-width: 560px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 700; color: #191c1e; letter-spacing: 0.04em; text-transform: uppercase; }
.input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; font-size: 14px; background: #fafbfc; outline: none; font-family: inherit; }
.input:focus { border-color: #E5004C; background: #fff; }
.error-msg { font-size: 13px; color: #ba1a1a; }
.row-end { display: flex; justify-content: flex-end; gap: 10px; }
.table-card { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; font-size: 14px; }
.table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 700; color: #868e96; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #f0f2f5; }
.table td { padding: 12px 16px; border-bottom: 1px solid #f7f8fa; color: #374151; }
.table tr:last-child td { border-bottom: none; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; color: #515f74; }
.base-tag { margin-left: 6px; padding: 2px 8px; border-radius: 99px; background: #fff5f8; color: #E5004C; font-size: 10px; font-weight: 700; }
.badge { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.c-amber { background: #fff4e0; color: #b45309; }
.c-blue { background: #e4edff; color: #1d4ed8; }
.c-emerald { background: #e7f8ef; color: #0a7b43; }
.c-gray { background: #f1f3f5; color: #868e96; }
.row-actions { display: flex; gap: 2px; justify-content: flex-end; white-space: nowrap; }
.icon-btn { display: inline-flex; padding: 6px; border-radius: 8px; border: none; background: transparent; color: #515f74; cursor: pointer; text-decoration: none; }
.icon-btn:hover { background: #f2f4f6; color: #E5004C; }
.icon-btn .material-symbols-outlined { font-size: 20px; }
.icon-btn:disabled { cursor: progress; }
.spin { animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.download-box { padding: 12px 16px; border: 1px solid #f3c6d6; border-radius: 12px; background: #fff5f8; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: 1.5px solid #e0e3e5; border-radius: 10px; color: #515f74; font-size: 14px; font-weight: 500; text-decoration: none; background: #fff; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
.btn-danger-outline { display: inline-flex; padding: 9px 12px; border: 1.5px solid #fbc4c4; border-radius: 10px; color: #ba1a1a; background: #fff5f5; cursor: pointer; }
.check { width: 40px; text-align: center; }
.check input { width: 16px; height: 16px; accent-color: #E5004C; cursor: pointer; }
.row-selected td { background: #fff5f8; }
.error-msg { font-size: 13px; color: #ba1a1a; }
</style>
