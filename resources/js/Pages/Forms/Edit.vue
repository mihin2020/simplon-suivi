<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'
import FormBuilder from '@/Components/Forms/FormBuilder.vue'
import type { BuilderField } from '@/Components/Forms/FormFieldCard.vue'

defineOptions({ layout: AdminLayout })

interface FormModel {
    id: string
    title: string
    description: string | null
    status: string
    project_id: string
    formation_id: string
    settings: Record<string, unknown>
    fields: Array<Omit<BuilderField, '_key'> & { id?: string }>
    responses_count: number
    project?: { id: string; name: string }
    formation?: { id: string; name: string }
}

const props = defineProps<{
    form: FormModel
    publicUrl: string | null
    headerImageUrl: string | null
    canShortenLink: boolean
    projects: Array<{ id: string; name: string; formations: Array<{ id: string; project_id: string; name: string }> }>
    fieldTypes: Array<{ value: string; label: string; icon: string; requires_options: boolean }>
    learnerAttributes: Array<{ key: string; label: string; type: string }>
    statuses: Array<{ value: string; label: string; color: string }>
}>()

const tab = ref<'questions' | 'preview' | 'settings'>('questions')
const copied = ref(false)
const shortening = ref(false)
const headerUploading = ref(false)
const headerError = ref<string | null>(null)
const identitySaving = ref(false)
const confirmDelete = ref(false)
const localPublicUrl = ref<string | null>(props.publicUrl)
const localCanShorten = ref(props.canShortenLink)
const headerPreview = ref<string | null>(props.headerImageUrl)

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

const toDatetimeLocal = (value: unknown): string => {
    if (!value || typeof value !== 'string') return ''
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) {
        // already datetime-local-ish: 2026-09-15T18:00
        return value.length >= 16 ? value.slice(0, 16) : value
    }
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const metaForm = useForm({
    title: props.form.title,
    description: props.form.description ?? '',
    project_id: props.form.project_id,
    formation_id: props.form.formation_id,
    settings: {
        accept_responses: Boolean(props.form.settings?.accept_responses ?? true),
        one_per_email: Boolean(props.form.settings?.one_per_email ?? true),
        show_progress: Boolean(props.form.settings?.show_progress ?? true),
        confirmation_message: String(props.form.settings?.confirmation_message ?? ''),
        max_responses: props.form.settings?.max_responses ?? null,
        closes_at: toDatetimeLocal(props.form.settings?.closes_at),
    },
})

watch(() => props.publicUrl, (url) => { localPublicUrl.value = url })
watch(() => props.canShortenLink, (v) => { localCanShorten.value = v })
watch(() => props.headerImageUrl, (url) => {
    if (!headerUploading.value) {
        headerPreview.value = url
    }
})

const fields = ref<BuilderField[]>(
    props.form.fields.map((f, i) => ({
        ...f,
        is_required: Boolean(f.is_required),
        help_text: f.help_text ?? '',
        settings: f.settings ?? null,
        options: f.options ?? null,
        _key: f.id ?? `new-${i}`,
    }))
)

const fieldsForm = useForm({ fields: [] as Array<Record<string, unknown>> })

const formations = computed(() =>
    props.projects.find(p => p.id === metaForm.project_id)?.formations ?? []
)

const statusMeta = computed(() =>
    props.statuses.find(s => s.value === props.form.status) ?? { label: props.form.status, color: 'gray' }
)

const isLocked = computed(() => props.form.status === 'locked' || props.form.status === 'archived')

const saveMeta = () => {
    metaForm.transform((data) => ({
        ...data,
        settings: {
            ...data.settings,
            closes_at: data.settings.closes_at || null,
            max_responses: data.settings.max_responses || null,
        },
    })).put(`/forms/${props.form.id}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['form', 'headerImageUrl', 'publicUrl', 'canShortenLink', 'flash'],
        onSuccess: () => metaForm.transform((d) => d),
    })
}

const saveIdentity = async () => {
    if (identitySaving.value || isLocked.value) return
    identitySaving.value = true
    try {
        const res = await fetch(`/forms/${props.form.id}/identity`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                title: metaForm.title,
                description: metaForm.description || null,
            }),
        })
        if (!res.ok) {
            const data = await res.json().catch(() => ({})) as { errors?: Record<string, string[]> }
            if (data.errors?.title?.[0]) metaForm.setError('title', data.errors.title[0])
            if (data.errors?.description?.[0]) metaForm.setError('description', data.errors.description[0])
            return
        }
        metaForm.clearErrors()
        router.reload({ only: ['flash', 'form'], preserveScroll: true, preserveState: true })
    } finally {
        identitySaving.value = false
    }
}

const destroyForm = () => {
    router.delete(`/forms/${props.form.id}`)
}
const unarchiveForm = () => {
    router.post(`/forms/${props.form.id}/unarchive`, {}, {
        preserveScroll: true,
        only: ['form', 'publicUrl', 'canShortenLink', 'flash'],
    })
}

const uploadHeaderNow = async (file: File) => {
    headerError.value = null
    headerUploading.value = true
    headerPreview.value = URL.createObjectURL(file)

    const body = new FormData()
    body.append('header_image', file)

    try {
        const res = await fetch(`/forms/${props.form.id}/header-image`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body,
        })

        const data = await res.json().catch(() => ({})) as {
            header_image_url?: string
            message?: string
            errors?: Record<string, string[]>
            message_error?: string
        }

        if (!res.ok) {
            headerPreview.value = props.headerImageUrl
            headerError.value = data.errors?.header_image?.[0]
                ?? data.message
                ?? 'Impossible d’enregistrer l’image.'
            return
        }

        headerPreview.value = data.header_image_url ?? headerPreview.value
    } catch {
        headerPreview.value = props.headerImageUrl
        headerError.value = 'Impossible d’enregistrer l’image.'
    } finally {
        headerUploading.value = false
    }
}

const onHeaderChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    ;(e.target as HTMLInputElement).value = ''
    if (!file) return
    void uploadHeaderNow(file)
}

const removeHeader = async () => {
    headerError.value = null
    headerUploading.value = true
    try {
        const res = await fetch(`/forms/${props.form.id}/header-image`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        if (!res.ok) {
            headerError.value = 'Impossible de supprimer l’image.'
            return
        }
        headerPreview.value = null
    } finally {
        headerUploading.value = false
    }
}

const saveFields = () => {
    fieldsForm.fields = fields.value.map(({ _key, ...rest }) => rest)
    fieldsForm.put(`/forms/${props.form.id}/fields`, {
        preserveScroll: true,
        preserveState: true,
        only: ['form', 'flash'],
    })
}

const publish = () => router.post(`/forms/${props.form.id}/publish`, {}, {
    preserveScroll: true,
    only: ['form', 'publicUrl', 'canShortenLink', 'headerImageUrl', 'flash'],
})
const closeForm = () => router.post(`/forms/${props.form.id}/close`, {}, { preserveScroll: true, only: ['form', 'publicUrl', 'canShortenLink', 'flash'] })
const lockForm = () => router.post(`/forms/${props.form.id}/lock`, {}, { preserveScroll: true, only: ['form', 'publicUrl', 'canShortenLink', 'flash'] })
const duplicate = () => router.post(`/forms/${props.form.id}/duplicate`)

const displayPublicUrl = computed(() => {
    if (!localPublicUrl.value) return null
    if (localPublicUrl.value.startsWith('http')) return localPublicUrl.value
    return `${window.location.origin}${localPublicUrl.value}`
})

const qrImageUrl = computed(() => {
    if (!displayPublicUrl.value) return null
    return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(displayPublicUrl.value)}`
})

const qrDownloadUrl = computed(() => {
    if (!displayPublicUrl.value) return null
    return `https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=${encodeURIComponent(displayPublicUrl.value)}`
})

const showQr = ref(false)
const qrDownloading = ref(false)

const downloadQr = async () => {
    if (!qrDownloadUrl.value || qrDownloading.value) return
    qrDownloading.value = true
    try {
        const res = await fetch(qrDownloadUrl.value)
        if (!res.ok) throw new Error('qr download failed')
        const blob = await res.blob()
        const objectUrl = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = objectUrl
        a.download = `qr-${(metaForm.title || props.form.title || 'formulaire').toString().replace(/[^\w\-]+/g, '_').slice(0, 40)}.png`
        document.body.appendChild(a)
        a.click()
        a.remove()
        URL.revokeObjectURL(objectUrl)
    } finally {
        qrDownloading.value = false
    }
}

const shortenLink = async () => {
    if (shortening.value || !localCanShorten.value) return
    shortening.value = true
    try {
        const res = await fetch(`/forms/${props.form.id}/shorten-link`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        if (!res.ok) throw new Error('shorten failed')
        const data = await res.json() as { public_url: string | null; can_shorten_link: boolean }
        localPublicUrl.value = data.public_url
        localCanShorten.value = data.can_shorten_link
    } finally {
        shortening.value = false
    }
}

const copyLink = async () => {
    if (!displayPublicUrl.value) return
    await navigator.clipboard.writeText(displayPublicUrl.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

const previewAnswerable = computed(() =>
    fields.value.filter(f => !['section_header', 'paragraph'].includes(f.type))
)

const badgeClass = (color: string) => {
    const map: Record<string, string> = {
        gray: 'badge-gray', green: 'badge-green', orange: 'badge-orange', red: 'badge-red', slate: 'badge-slate',
    }
    return map[color] ?? 'badge-gray'
}
</script>

<template>
    <Head :title="`Éditer — ${form.title}`" />
    <div class="page-wrapper">
        <div class="header-row">
            <div class="page-title-row">
                <Link href="/forms" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <div class="title-line">
                        <h1 class="page-title">{{ metaForm.title || form.title }}</h1>
                        <span class="status-badge" :class="badgeClass(statusMeta.color)">{{ statusMeta.label }}</span>
                    </div>
                    <p class="page-subtitle">
                        {{ form.project?.name }} · {{ form.formation?.name }}
                        · {{ form.responses_count }} réponse(s)
                    </p>
                </div>
            </div>

            <div class="header-actions">
                <Can permission="forms.stats">
                    <Link :href="`/forms/${form.id}/stats`" class="btn-secondary">Statistiques</Link>
                </Can>
                <Can permission="forms.responses">
                    <Link :href="`/forms/${form.id}/responses`" class="btn-secondary">Réponses</Link>
                </Can>
                <Can permission="forms.create">
                    <button type="button" class="btn-secondary" @click="duplicate">Dupliquer</button>
                </Can>
                <Can permission="forms.publish">
                    <button v-if="form.status === 'archived'" type="button" class="btn-secondary" @click="unarchiveForm">Désarchiver</button>
                    <button v-else-if="form.status !== 'published'" type="button" class="btn-primary" @click="publish">Publier</button>
                    <button v-if="form.status === 'published'" type="button" class="btn-secondary" @click="closeForm">Fermer</button>
                    <button v-if="form.status !== 'locked' && form.status !== 'archived'" type="button" class="btn-ghost-danger" @click="lockForm">Verrouiller</button>
                </Can>
                <Can v-if="form.status !== 'archived'" permission="forms.delete">
                    <button type="button" class="btn-ghost-danger" @click="confirmDelete = true">Archiver</button>
                </Can>
            </div>
        </div>

        <div v-if="form.status === 'archived'" class="archive-edit-banner">
            <span class="material-symbols-outlined">inventory_2</span>
            <span>Ce formulaire est archivé. Cliquez sur <strong>Désarchiver</strong> pour le récupérer (brouillon).</span>
        </div>

        <div v-if="localPublicUrl" class="link-banner">
            <div class="min-w-0">
                <p class="link-label">Lien public</p>
                <p class="link-url">{{ displayPublicUrl }}</p>
                <p v-if="localCanShorten" class="link-hint">Lien long — raccourcissez-le avant de le partager.</p>
            </div>
            <div class="link-actions">
                <button
                    v-if="localCanShorten"
                    type="button"
                    class="btn-banner-ghost"
                    :disabled="shortening"
                    @click="shortenLink"
                >
                    {{ shortening ? '…' : 'Raccourcir' }}
                </button>
                <a :href="displayPublicUrl ?? localPublicUrl" target="_blank" rel="noopener" class="btn-banner-ghost">Ouvrir</a>
                <button type="button" class="btn-banner" @click="copyLink">{{ copied ? 'Copié' : 'Copier' }}</button>
                <button type="button" class="btn-banner-ghost" @click="showQr = !showQr">
                    {{ showQr ? 'Masquer QR' : 'QR code' }}
                </button>
            </div>
        </div>
        <div v-if="localPublicUrl && showQr && qrImageUrl" class="qr-panel">
            <img :src="qrImageUrl" alt="QR code du formulaire" width="180" height="180" />
            <div class="qr-panel-text">
                <p>Scannez pour ouvrir le formulaire public.</p>
                <button
                    type="button"
                    class="btn-secondary"
                    :disabled="qrDownloading"
                    @click="downloadQr"
                >
                    <span class="material-symbols-outlined" style="font-size:18px">download</span>
                    {{ qrDownloading ? 'Téléchargement…' : 'Télécharger le QR' }}
                </button>
            </div>
        </div>

        <div class="tabs">
            <button
                v-for="t in ([
                    ['questions', 'Questions'],
                    ['preview', 'Aperçu'],
                    ['settings', 'Paramètres'],
                ] as const)"
                :key="t[0]"
                type="button"
                class="tab"
                :class="{ active: tab === t[0] }"
                @click="tab = t[0]"
            >
                {{ t[1] }}
            </button>
        </div>

        <div v-if="tab === 'questions'" class="space-block">
            <!-- En-tête type Google Forms : image + titre + description -->
            <section class="form-meta-card" :class="{ locked: isLocked }">
                <div class="form-meta-banner">
                    <div v-if="headerPreview" class="form-meta-banner-img">
                        <img :src="headerPreview" alt="En-tête" />
                        <button
                            type="button"
                            class="form-meta-banner-remove"
                            :disabled="headerUploading || isLocked"
                            @click="removeHeader"
                        >
                            Supprimer
                        </button>
                    </div>
                    <label v-else class="form-meta-banner-drop" :class="{ disabled: headerUploading || isLocked }">
                        <span class="material-symbols-outlined">{{ headerUploading ? 'progress_activity' : 'add_photo_alternate' }}</span>
                        <span>{{ headerUploading ? 'Envoi…' : 'Ajouter une image d’en-tête' }}</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :disabled="headerUploading || isLocked"
                            @change="onHeaderChange"
                        />
                    </label>
                </div>
                <div class="form-meta-body">
                    <input
                        v-model="metaForm.title"
                        type="text"
                        class="form-meta-title"
                        placeholder="Titre du formulaire"
                        :disabled="isLocked"
                        maxlength="255"
                    />
                    <textarea
                        v-model="metaForm.description"
                        class="form-meta-desc"
                        rows="3"
                        placeholder="Description du formulaire"
                        :disabled="isLocked"
                        maxlength="5000"
                    />
                    <p class="form-meta-size-hint">Image d’en-tête : 1200 × 300 px (ratio 4:1)</p>
                    <p v-if="headerError" class="error-msg">{{ headerError }}</p>
                    <p v-if="metaForm.errors.title" class="error-msg">{{ metaForm.errors.title }}</p>
                    <p v-if="metaForm.errors.description" class="error-msg">{{ metaForm.errors.description }}</p>
                    <div class="form-meta-save">
                        <button
                            type="button"
                            class="btn-secondary"
                            :disabled="isLocked || identitySaving"
                            @click="saveIdentity"
                        >
                            <span v-if="identitySaving" class="spinner dark" />
                            {{ identitySaving ? 'Enregistrement…' : 'Enregistrer titre & description' }}
                        </button>
                    </div>
                </div>
            </section>

            <FormBuilder
                v-model="fields"
                :field-types="fieldTypes"
                :learner-attributes="learnerAttributes"
                :locked="isLocked"
            />
            <div class="save-row save-row-split">
                <p class="save-hint">Chaque <strong>section</strong> devient une page « Suivant » sur le lien public.</p>
                <button type="button" class="btn-primary" :disabled="isLocked || fieldsForm.processing" @click="saveFields">
                    <span v-if="fieldsForm.processing" class="spinner" />
                    {{ fieldsForm.processing ? 'Enregistrement…' : 'Enregistrer les questions' }}
                </button>
            </div>
            <p v-if="fieldsForm.errors.fields" class="error-msg">{{ fieldsForm.errors.fields }}</p>
        </div>

        <div v-else-if="tab === 'preview'" class="preview-shell">
            <section class="form-meta-card preview-only">
                <div class="form-meta-banner">
                    <div v-if="headerPreview" class="form-meta-banner-img">
                        <img :src="headerPreview" alt="En-tête" />
                        <button
                            type="button"
                            class="form-meta-banner-remove"
                            :disabled="headerUploading"
                            @click="removeHeader"
                        >
                            Supprimer
                        </button>
                    </div>
                    <label v-else class="form-meta-banner-drop" :class="{ disabled: headerUploading }">
                        <span class="material-symbols-outlined">{{ headerUploading ? 'progress_activity' : 'add_photo_alternate' }}</span>
                        <span>{{ headerUploading ? 'Envoi…' : 'Ajouter une image d’en-tête' }}</span>
                        <span class="form-meta-size-hint">1200 × 300 px (ratio 4:1)</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :disabled="headerUploading"
                            @change="onHeaderChange"
                        />
                    </label>
                </div>
                <div class="form-meta-body">
                    <h2 class="preview-title">{{ metaForm.title || form.title }}</h2>
                    <p v-if="metaForm.description" class="preview-desc">{{ metaForm.description }}</p>
                    <p v-if="headerError" class="error-msg">{{ headerError }}</p>
                </div>
            </section>
            <div class="preview-card">
                <template v-for="field in fields" :key="field._key">
                    <div v-if="field.type === 'section_header'" class="preview-section">
                        <h3>{{ field.label }}</h3>
                        <p v-if="field.help_text">{{ field.help_text }}</p>
                    </div>
                    <p v-else-if="field.type === 'paragraph'" class="preview-paragraph">{{ field.label }}</p>
                    <div v-else class="preview-field">
                        <label>
                            {{ field.label }}
                            <span v-if="field.is_required" class="req">*</span>
                        </label>
                        <p v-if="field.help_text" class="help">{{ field.help_text }}</p>
                        <div v-if="field.type === 'file'" class="preview-file">
                            <span class="material-symbols-outlined">upload_file</span>
                            Choisir un fichier…
                        </div>
                        <div v-else class="preview-control" />
                    </div>
                </template>
                <p v-if="previewAnswerable.length === 0" class="empty-hint">Aucune question à prévisualiser.</p>
            </div>
        </div>

        <form v-else class="form-card" @submit.prevent="saveMeta">
            <div class="field">
                <label class="label">Titre</label>
                <input v-model="metaForm.title" type="text" class="input" :disabled="isLocked" />
            </div>
            <div class="field">
                <label class="label">Description du formulaire</label>
                <textarea
                    v-model="metaForm.description"
                    rows="4"
                    class="input"
                    placeholder="Expliquez le but du formulaire aux candidats…"
                />
            </div>
            <div class="grid-2">
                <div class="field">
                    <label class="label">Projet</label>
                    <select v-model="metaForm.project_id" class="input" :disabled="isLocked" @change="metaForm.formation_id = ''">
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label">Formation</label>
                    <select v-model="metaForm.formation_id" class="input" :disabled="isLocked">
                        <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label class="label">Date limite (optionnelle)</label>
                <input v-model="metaForm.settings.closes_at" type="datetime-local" class="input" />
                <p class="form-meta-size-hint">
                    Si renseignée : à cette date le formulaire affiche « Temps écoulé » et n’accepte plus de réponses.
                    Laissez vide pour aucune limite.
                </p>
            </div>
            <div class="field">
                <label class="label">Nombre max. de réponses (optionnel)</label>
                <input
                    v-model="metaForm.settings.max_responses"
                    type="number"
                    min="1"
                    class="input"
                    placeholder="Illimité"
                />
                <p class="form-meta-size-hint">
                    Laissez vide pour illimité. Une fois le plafond atteint, les nouvelles candidatures sont refusées.
                </p>
            </div>
            <label class="check-row">
                <input v-model="metaForm.settings.accept_responses" type="checkbox" />
                Accepter les candidatures
            </label>
            <label class="check-row">
                <input v-model="metaForm.settings.one_per_email" type="checkbox" />
                Une seule candidature par e-mail
            </label>
            <label class="check-row">
                <input v-model="metaForm.settings.show_progress" type="checkbox" />
                Afficher la barre de progression
            </label>
            <div class="field">
                <label class="label">Message de confirmation (optionnel)</label>
                <textarea
                    v-model="metaForm.settings.confirmation_message"
                    rows="3"
                    class="input"
                    placeholder="Laissez vide pour le message court par défaut."
                />
            </div>
            <div class="save-row">
                <button type="submit" class="btn-primary" :disabled="metaForm.processing">
                    <span v-if="metaForm.processing" class="spinner" />
                    {{ metaForm.processing ? 'Enregistrement…' : 'Enregistrer' }}
                </button>
            </div>
        </form>

        <Teleport to="body">
            <div v-if="confirmDelete" class="modal-backdrop" @click.self="confirmDelete = false">
                <div class="modal-box">
                    <h3 class="modal-title">Archiver le formulaire</h3>
                    <p class="modal-body">
                        Voulez-vous archiver <strong>« {{ metaForm.title || form.title }} »</strong> ?
                        Vous pourrez le retrouver via le filtre « Archivés ».
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" @click="confirmDelete = false">Annuler</button>
                        <button type="button" class="btn-danger" @click="destroyForm">Archiver</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-wrapper { max-width: 1100px; margin: 0 auto; }
.header-row {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; margin-bottom: 20px;
}
.page-title-row { display: flex; align-items: flex-start; gap: 14px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: #fff; border: 1px solid #e0e3e5; color: #1F3A4D;
    text-decoration: none; transition: all 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.title-line { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; line-height: 1.25; }
.page-subtitle { font-size: 14px; color: #515f74; margin-top: 4px; }

.status-badge {
    display: inline-flex; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.badge-gray { background: #f1f3f5; color: #515f74; }
.badge-green { background: #e6f7ed; color: #0f7a3d; }
.badge-orange { background: #fff4e5; color: #b45309; }
.badge-red { background: #fde8e8; color: #b91c1c; }
.badge-slate { background: #e2e8f0; color: #475569; }

.header-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.archive-edit-banner {
    display: flex; align-items: center; gap: 10px;
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 16px; color: #475569; font-size: 13px;
}
.archive-edit-banner .material-symbols-outlined { color: #1F3A4D; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    border: none; cursor: pointer; transition: background 0.2s; text-decoration: none;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary {
    display: inline-flex; align-items: center; padding: 8px 14px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #e0e3e5; color: #515f74; background: #fff;
    text-decoration: none; cursor: pointer; transition: background 0.15s;
}
.btn-secondary:hover { background: #f2f4f6; }
.btn-ghost-danger {
    display: inline-flex; align-items: center; padding: 8px 14px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: none; background: transparent; color: #ba1a1a; cursor: pointer;
}
.spinner {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

.link-banner {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: 12px; background: #1F3A4D; color: #fff; border-radius: 12px; padding: 14px 16px;
    margin-bottom: 20px;
}
.link-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.75; font-weight: 700; }
.link-url { font-family: ui-monospace, monospace; font-size: 13px; margin-top: 2px; word-break: break-all; }
.link-hint { font-size: 12px; opacity: 0.8; margin-top: 6px; }
.link-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.qr-panel {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    padding: 16px; margin-top: -8px;
}
.qr-panel img {
    border-radius: 8px; border: 1px solid #eef0f2; background: #fff;
}
.qr-panel-text {
    display: flex; flex-direction: column; gap: 10px; align-items: flex-start;
}
.qr-panel-text p { font-size: 13px; color: #515f74; margin: 0; }
.qr-panel-text .btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-banner {
    padding: 8px 14px; border-radius: 8px; border: none; background: #fff; color: #1F3A4D;
    font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer;
}
.btn-banner-ghost {
    padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.35);
    color: #fff; text-decoration: none; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
}

.tabs { display: flex; gap: 4px; border-bottom: 1px solid #e0e3e5; margin-bottom: 20px; }
.tab {
    padding: 10px 16px; border: none; background: transparent; cursor: pointer;
    font-size: 13px; font-weight: 600; color: #515f74;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab.active { color: #E5004C; border-bottom-color: #E5004C; }

.space-block { display: flex; flex-direction: column; gap: 16px; }
.save-row { display: flex; justify-content: flex-end; }
.save-row-split {
    justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.save-hint { font-size: 12px; color: #80868b; margin: 0; }
.error-msg { font-size: 12px; color: #ba1a1a; }
.mt-sm { margin-top: 10px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }

.form-meta-card {
    background: #fff;
    border: 1px solid #dadce0;
    border-radius: 8px;
    border-top: 10px solid #E5004C;
    border-left: 6px solid #E5004C;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(60, 64, 67, 0.08);
}
.form-meta-card.locked { opacity: 0.85; }
.form-meta-banner { background: #f8f9fa; border-bottom: 1px solid #e8eaed; }
.form-meta-banner-img {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 1;
    background: #e8eaed;
}
.form-meta-banner-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.form-meta-banner-remove {
    position: absolute; top: 10px; right: 10px; border: none; border-radius: 8px;
    background: rgba(0,0,0,0.65); color: #fff; padding: 6px 10px; font-size: 12px;
    font-weight: 600; cursor: pointer;
}
.form-meta-banner-drop {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    width: 100%; aspect-ratio: 4 / 1; cursor: pointer; color: #5f6368; font-size: 13px; font-weight: 600;
}
.form-meta-banner-drop:hover { background: #fff5f8; color: #E5004C; }
.form-meta-banner-drop.disabled { opacity: 0.6; pointer-events: none; }
.form-meta-banner-drop .material-symbols-outlined { font-size: 28px; color: #E5004C; }
.form-meta-body { padding: 18px 20px 16px; display: flex; flex-direction: column; gap: 10px; }
.form-meta-title {
    width: 100%; border: none; border-bottom: 1px solid #dadce0; padding: 6px 0 10px;
    font-size: 28px; font-weight: 700; color: #202124; background: transparent; outline: none;
    font-family: inherit;
}
.form-meta-title:focus { border-bottom-color: #E5004C; box-shadow: 0 1px 0 #E5004C; }
.form-meta-title:disabled { opacity: 0.7; }
.form-meta-desc {
    width: 100%; border: none; border-bottom: 1px solid #dadce0; padding: 8px 0;
    font-size: 14px; color: #3c4043; background: transparent; outline: none; resize: vertical;
    font-family: inherit; line-height: 1.45; min-height: 72px;
}
.form-meta-desc:focus { border-bottom-color: #E5004C; box-shadow: 0 1px 0 #E5004C; }
.form-meta-desc:disabled { opacity: 0.7; }
.form-meta-desc::placeholder { color: #9aa0a6; }
.form-meta-size-hint { font-size: 12px; color: #80868b; }
.form-meta-save { display: flex; justify-content: flex-end; margin-top: 4px; }
.spinner.dark {
    border-color: rgba(31,58,77,0.25); border-top-color: #1F3A4D;
}
.preview-title { font-size: 22px; font-weight: 700; color: #202124; margin: 0; }
.preview-desc { margin: 0; color: #3c4043; white-space: pre-wrap; font-size: 14px; }

.preview-shell {
    border: 1px solid #e0e3e5; border-radius: 16px; overflow: hidden; background: #f0ebf8;
    padding: 16px; display: flex; flex-direction: column; gap: 12px;
}
.preview-card { display: flex; flex-direction: column; gap: 12px; }
.preview-section,
.preview-paragraph,
.preview-field {
    background: #fff; border: 1px solid #dadce0; border-radius: 8px; padding: 14px 16px;
    border-left: 6px solid transparent;
}
.preview-field { border-left-color: #E5004C33; }
.preview-section h3 { font-size: 16px; font-weight: 700; color: #1F3A4D; }
.preview-section p, .preview-paragraph, .help, .empty-hint { font-size: 13px; color: #515f74; }
.preview-field label { font-size: 13px; font-weight: 600; color: #191c1e; }
.req { color: #E5004C; }
.preview-control {
    margin-top: 6px; height: 40px; border: none; border-bottom: 1.5px solid #dadce0; border-radius: 0; background: transparent;
}
.preview-file {
    margin-top: 6px; display: flex; align-items: center; gap: 8px;
    padding: 12px 14px; border: 2px dashed #d0d8e0; border-radius: 10px;
    background: #f7f9fb; color: #515f74; font-size: 13px; font-weight: 500;
}
.preview-file .material-symbols-outlined { color: #E5004C; }

.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45);
    display: flex; align-items: center; justify-content: center; z-index: 80; padding: 16px;
}
.modal-box {
    width: 100%; max-width: 440px; background: #fff; border-radius: 16px; padding: 24px;
    box-shadow: 0 20px 48px rgba(0,0,0,0.18);
}
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; margin-bottom: 8px; }
.modal-body { font-size: 14px; color: #515f74; line-height: 1.5; margin-bottom: 20px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
.btn-cancel {
    padding: 8px 14px; border-radius: 8px; border: 1.5px solid #e0e3e5;
    background: #fff; color: #515f74; font-weight: 600; cursor: pointer;
}
.btn-danger {
    padding: 8px 14px; border-radius: 8px; border: none;
    background: #E5004C; color: #fff; font-weight: 600; cursor: pointer;
}

.form-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 16px;
    padding: 24px; display: flex; flex-direction: column; gap: 18px;
}
.grid-2 { display: grid; grid-template-columns: 1fr; gap: 16px; }
@media (min-width: 768px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
.field { display: flex; flex-direction: column; gap: 6px; }
.label {
    font-size: 12px; font-weight: 700; color: #191c1e;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.input {
    width: 100%; padding: 12px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px;
    font-size: 14px; color: #191c1e; background: #fafbfc; outline: none; font-family: inherit;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 4px rgba(229,0,76,0.08); background: #fff; }
.input:disabled { opacity: 0.6; }
.check-row {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 14px; color: #191c1e; font-weight: 500;
}
</style>
