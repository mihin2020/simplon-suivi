<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'
import DuplicateDialog from '@/Components/Contracts/DuplicateDialog.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import Skeleton from '@/Components/UI/Skeleton.vue'
import ProgressModal from '@/Components/UI/ProgressModal.vue'
import { useDownload } from '@/composables/useHttpProgress'

defineOptions({ layout: AdminLayout })

interface Contract {
    id: string
    reference: string
    title: string
    target_label: string
    subject_name: string
    sent_to_email: string | null
    emailed_at: string | null
    emailed_to: string | null
    email_count: number
    status: string
    status_label: string
    status_color: string
    version: number
    project: { id: string; name: string } | null
    subject_id: string | null
    fill_fields: string[]
    fill_fields_ready: boolean
    pdf_url: string | null
    created_by: string | null
    created_at: string | null
}

const props = defineProps<{
    contract: Contract
    subjects: Array<{ value: string; label: string }>
}>()

const showDuplicate = ref(false)

// The PDF takes a moment the first time it is rendered: say so while it loads.
const frameLoading = ref(true)

const pdf = useDownload()
const downloadUrl = `/contracts/${props.contract.id}/pdf/download`
const downloadPdf = () => pdf.download(downloadUrl, `contrat_${props.contract.reference}.pdf`)
const backHref = props.contract.project ? `/contracts/projects/${props.contract.project.id}` : '/contracts'

const statusColors: Record<string, string> = { amber: 'c-amber', blue: 'c-blue', emerald: 'c-emerald', gray: 'c-gray' }

const showFinalize = ref(false)
const finalize = () => {
    router.post(`/contracts/${props.contract.id}/finalize`, {}, {
        preserveScroll: true,
        onFinish: () => { showFinalize.value = false },
    })
}

// Identical copy (same recipient, same text) opened in the editor.
const copying = ref(false)
const copy = () => router.post(`/contracts/${props.contract.id}/copy`, {}, {
    onStart: () => { copying.value = true },
    onFinish: () => { copying.value = false },
})

const showDelete = ref(false)
const deleting = ref(false)
const destroy = () => router.delete(`/contracts/${props.contract.id}`, {
    onStart: () => { deleting.value = true },
    onFinish: () => { deleting.value = false; showDelete.value = false },
})
</script>

<template>
    <Head :title="contract.title" />
    <div class="wrap">
        <div class="head">
            <Link :href="backHref" class="icon-back"><span class="material-symbols-outlined">arrow_back</span></Link>
            <div class="head-text">
                <h1 class="title">{{ contract.title }}</h1>
                <p class="subtitle">
                    {{ contract.reference }} · {{ contract.target_label }} · {{ contract.subject_name }}
                    <span class="badge" :class="statusColors[contract.status_color]">{{ contract.status_label }}</span>
                    <Link v-if="contract.project" :href="backHref" class="project-link">
                        <span class="material-symbols-outlined" style="font-size:15px">folder_open</span>{{ contract.project.name }}
                    </Link>
                </p>
            </div>
        </div>

        <div class="toolbar">
            <Can permission="contracts.update">
                <Link :href="`/contracts/${contract.id}/edit`" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span> Modifier
                </Link>
            </Can>
            <Can permission="contracts.create">
                <button type="button" class="btn-secondary" :disabled="copying" title="Créer une copie identique de ce contrat, puis la modifier" @click="copy">
                    <span class="material-symbols-outlined" style="font-size:18px">content_copy</span> {{ copying ? 'Duplication…' : 'Dupliquer' }}
                </button>
                <button type="button" class="btn-secondary" @click="showDuplicate = true">
                    <span class="material-symbols-outlined" style="font-size:18px">group_add</span> Générer pour d'autres personnes
                </button>
            </Can>
            <a :href="`/contracts/${contract.id}/pdf`" target="_blank" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px">open_in_new</span> Ouvrir le PDF
            </a>
            <button type="button" class="btn-secondary" :disabled="pdf.isBusy()" @click="downloadPdf">
                <span class="material-symbols-outlined" style="font-size:18px">{{ pdf.phase.value === 'done' ? 'check_circle' : 'download' }}</span>
                {{ pdf.phase.value === 'done' ? 'Téléchargé' : pdf.isBusy() ? 'Téléchargement…' : 'Télécharger' }}
            </button>
            <Can permission="contracts.update">
                <button type="button" class="btn-secondary" @click="showFinalize = true">
                    <span class="material-symbols-outlined" style="font-size:18px">task_alt</span>
                    {{ contract.status === 'draft' ? 'Marquer comme finalisé' : 'Archiver à nouveau le PDF' }}
                </button>
            </Can>
            <Can permission="contracts.delete">
                <button class="btn-danger-outline" @click="showDelete = true">
                    <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                </button>
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

        <div class="grid">
            <!-- The contract exactly as it prints: pages, letterhead, formatting -->
            <div class="doc-card">
                <div v-if="frameLoading" class="frame-loading" role="status" aria-label="Mise en page du contrat">
                    <div class="sheet-skeleton">
                        <Skeleton height="46px" radius="4px" />
                        <Skeleton width="55%" height="16px" style="margin: 28px auto 22px" />
                        <template v-for="block in 4" :key="block">
                            <Skeleton width="38%" height="12px" style="margin: 18px 0 10px" />
                            <Skeleton height="10px" />
                            <Skeleton height="10px" style="margin-top: 7px" />
                            <Skeleton :width="block % 2 ? '82%' : '64%'" height="10px" style="margin-top: 7px" />
                        </template>
                        <Skeleton height="30px" radius="4px" style="margin-top: auto" />
                    </div>
                </div>
                <iframe
                    :key="contract.version + contract.status"
                    :src="`/contracts/${contract.id}/pdf#toolbar=0&navpanes=0&view=FitH`"
                    class="doc-frame"
                    title="Aperçu du contrat"
                    @load="frameLoading = false"
                />
            </div>

            <!-- Panneau latéral -->
            <aside class="side">
                <div class="side-box">
                    <p class="side-title">Détails</p>
                    <dl class="meta">
                        <div><dt>Version</dt><dd>v{{ contract.version }}</dd></div>
                        <div><dt>Créé par</dt><dd>{{ contract.created_by || '-' }}</dd></div>
                    </dl>
                </div>

                <div class="side-box">
                    <p class="side-title">Envoi par email</p>
                    <p v-if="contract.sent_to_email" class="mail-ok">
                        <span class="material-symbols-outlined" style="font-size:18px">mark_email_read</span>{{ contract.sent_to_email }}
                    </p>
                    <p v-else class="mail-missing">
                        <span class="material-symbols-outlined" style="font-size:18px">unsubscribe</span>Aucune adresse connue
                    </p>
                    <p v-if="contract.emailed_at" class="mail-sent">
                        <span class="material-symbols-outlined" style="font-size:16px">send</span>
                        Envoyé le {{ new Date(contract.emailed_at).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) }}
                        à {{ contract.emailed_to }}<template v-if="contract.email_count > 1"> ({{ contract.email_count }} envois)</template>
                    </p>
                    <p v-else class="mail-never">Jamais envoyé par email.</p>
                    <Can permission="contracts.update">
                        <Link :href="`/contracts/${contract.id}/edit#recipient-email`" class="mail-edit">
                            {{ contract.sent_to_email ? 'Modifier l\'adresse' : 'Ajouter une adresse' }}
                        </Link>
                    </Can>
                </div>

            </aside>
        </div>
    </div>

    <DuplicateDialog
        :open="showDuplicate"
        :contract-id="contract.id"
        :source-name="contract.subject_id ? contract.subject_name : null"
        :subjects="subjects"
        :excluded-ids="contract.subject_id ? [contract.subject_id] : []"
        :fill-fields="contract.fill_fields"
        :fill-fields-ready="contract.fill_fields_ready"
        @close="showDuplicate = false"
    />

    <!-- Finalize modal -->
    <Teleport to="body">
        <div v-if="showFinalize" class="modal-backdrop" @click.self="showFinalize = false">
            <div class="modal-box">
                <div class="modal-icon"><span class="material-symbols-outlined" style="font-size:32px;color:#1d4ed8">task_alt</span></div>
                <h3 class="modal-title">Finaliser le contrat</h3>
                <p class="modal-body">Le contrat est marqué comme finalisé et son PDF est archivé. Il reste modifiable : le PDF archivé est mis à jour à chaque modification.</p>
                <div class="modal-actions">
                    <button class="btn-cancel" @click="showFinalize = false">Annuler</button>
                    <button class="btn-confirm" @click="finalize">Finaliser</button>
                </div>
            </div>
        </div>
    </Teleport>

    <ConfirmModal
        :show="showDelete"
        title="Supprimer le contrat"
        :message="`Le contrat ${contract.reference} sera retiré de la liste.`"
        :loading="deleting"
        @confirm="destroy"
        @cancel="showDelete = false"
    />
</template>

<style scoped>
.wrap { max-width: 1240px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.head { display: flex; align-items: center; gap: 16px; }
.head-text { flex: 1; }
.icon-back { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid #1F3A4D; color: #1F3A4D; flex-shrink: 0; text-decoration: none; }
.icon-back:hover { background: #1F3A4D; color: #fff; }
.title { font-size: 22px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 13px; color: #515f74; margin-top: 2px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.badge { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.c-amber { background: #fff4e0; color: #b45309; }
.c-blue { background: #e4edff; color: #1d4ed8; }
.c-emerald { background: #e7f8ef; color: #0a7b43; }
.c-gray { background: #f1f3f5; color: #868e96; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary.sm { padding: 8px 14px; font-size: 13px; width: 100%; justify-content: center; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border: 1.5px solid #e0e3e5; border-radius: 10px; color: #515f74; font-size: 14px; font-weight: 500; text-decoration: none; background: #fff; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
.btn-secondary:disabled { opacity: 0.7; cursor: progress; }
.btn-danger-outline { display: inline-flex; align-items: center; justify-content: center; padding: 9px 12px; border: 1.5px solid #fbc4c4; border-radius: 10px; color: #ba1a1a; background: #fff5f5; cursor: pointer; }
.grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 20px; align-items: start; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
.doc-card { position: relative; background: #525659; border: 1px solid #e8edf2; border-radius: 16px; overflow: hidden; }
.frame-loading { position: absolute; inset: 0; display: flex; justify-content: center; padding: 24px; background: #525659; overflow: hidden; }
.sheet-skeleton { width: min(100%, 560px); aspect-ratio: 210 / 297; display: flex; flex-direction: column; padding: 6% 8%; background: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); }
.download-box { padding: 12px 16px; border: 1px solid #f3c6d6; border-radius: 12px; background: #fff5f8; }
.doc-frame { display: block; width: 100%; height: 82vh; border: none; }
.project-link { display: inline-flex; align-items: center; gap: 4px; color: #E5004C; font-weight: 600; text-decoration: none; }
.side { display: flex; flex-direction: column; gap: 16px; }
.side-box { background: #fff; border: 1px solid #e8edf2; border-radius: 14px; padding: 16px; }
.side-title { font-size: 13px; font-weight: 700; color: #191c1e; }
.side-sub { font-size: 12px; color: #9aaabb; margin: 4px 0 10px; }
.meta { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.meta div { display: flex; justify-content: space-between; font-size: 13px; }
.meta dt { color: #9aaabb; }
.meta dd { color: #374151; font-weight: 500; }
.error-msg { font-size: 12px; color: #ba1a1a; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 16px; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
.modal-box { background: #fff; border-radius: 16px; padding: 28px; max-width: 420px; width: 100%; text-align: center; }
.modal-icon { width: 60px; height: 60px; border-radius: 50%; background: #e4edff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.modal-icon.del { background: #fff1f2; }
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; margin-bottom: 8px; }
.modal-body { font-size: 14px; color: #515f74; margin-bottom: 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: center; }
.btn-cancel { padding: 10px 20px; border-radius: 10px; border: 1.5px solid #e0e3e5; background: #fff; color: #515f74; font-weight: 500; cursor: pointer; }
.btn-confirm { padding: 10px 20px; border-radius: 10px; border: none; background: #1d4ed8; color: #fff; font-weight: 600; cursor: pointer; }
.btn-danger { padding: 10px 20px; border-radius: 10px; border: none; background: #E5004C; color: #fff; font-weight: 600; cursor: pointer; }
.mail-ok, .mail-missing { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 13px; word-break: break-all; }
.mail-ok { color: #0a7b43; }
.mail-missing { color: #b45309; font-weight: 600; }
.mail-sent { display: flex; align-items: flex-start; gap: 6px; margin-top: 10px; padding: 8px 10px; border-radius: 8px; background: #e4edff; color: #1d4ed8; font-size: 12.5px; line-height: 1.4; word-break: break-word; }
.mail-never { margin-top: 10px; font-size: 12.5px; color: #868e96; }
.mail-edit { display: inline-block; margin-top: 8px; font-size: 12px; font-weight: 600; color: #E5004C; text-decoration: none; }
</style>
