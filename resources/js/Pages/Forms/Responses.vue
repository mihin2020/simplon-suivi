<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface Answer {
    id?: string
    field_id: string
    label: string | null
    learner_attribute: string | null
    value: string | null
    is_file: boolean
    is_image?: boolean
    download_url?: string | null
}

interface ResponseItem {
    id: string
    email: string | null
    status: string
    status_label: string
    status_color: string
    submitted_at: string | null
    reviewed_at: string | null
    review_note: string | null
    reviewer: string | null
    learner: { id: string; first_name: string; last_name: string; email: string } | null
    answers: Answer[]
}

interface Paginated {
    data: ResponseItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
}

type ConfirmAction = 'select' | 'shortlist' | 'unshortlist' | 'reject' | 'deselect' | 'unreject' | 'enroll'

const props = defineProps<{
    form: {
        id: string
        title: string
        status: string
        project?: { id: string; name: string }
        formation?: { id: string; name: string }
    }
    responses: Paginated
    filters: { status?: string; search?: string }
    statuses: Array<{ value: string; label: string; color: string }>
}>()

const page = usePage()
const currentUserName = computed(() => {
    const user = page.props.auth as { user?: { full_name?: string } } | undefined
    return user?.user?.full_name?.trim() || 'Vous'
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const selected = ref<string[]>([])
const detail = ref<ResponseItem | null>(null)
const reviewNote = ref('')
const confirmNote = ref('')
const actionBusy = ref(false)
const rows = ref<ResponseItem[]>([...props.responses.data])

const confirmModal = ref<{
    action: ConfirmAction
    ids: string[]
} | null>(null)

watch(() => props.responses.data, (data) => {
    rows.value = [...data]
    if (detail.value) {
        detail.value = data.find(r => r.id === detail.value!.id) ?? null
    }
})

watch(status, () => applyFilters())

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})

const applyFilters = () => {
    selected.value = []
    router.get(`/forms/${props.form.id}/responses`, {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['responses', 'filters'],
        showProgress: false,
    })
}

const setStatusFilter = (value: string) => {
    if (value === '') {
        status.value = ''
        return
    }
    status.value = status.value === value ? '' : value
}

const exportImportUrl = computed(() => {
    const base = `/forms/${props.form.id}/responses/export-import`
    return status.value ? `${base}?status=${encodeURIComponent(status.value)}` : base
})

const statusMeta = (value: string) => {
    const found = props.statuses.find(s => s.value === value)
    return {
        status: value,
        status_label: found?.label ?? value,
        status_color: found?.color ?? 'gray',
    }
}

const allIds = computed(() => rows.value.map(r => r.id))
const allSelected = computed(() => allIds.value.length > 0 && allIds.value.every(id => selected.value.includes(id)))

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...allIds.value]
}

const toggleOne = (id: string) => {
    selected.value = selected.value.includes(id)
        ? selected.value.filter(x => x !== id)
        : [...selected.value, id]
}

const actionable = (r: ResponseItem) => !['enrolled', 'rejected'].includes(r.status)

const selectedSubmittedOnly = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && r.status === 'submitted')
        .map(r => r.id)
)

const selectedShortlisted = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && r.status === 'shortlisted')
        .map(r => r.id)
)

const selectedSubmitted = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && ['submitted', 'shortlisted'].includes(r.status))
        .map(r => r.id)
)

const selectedSelected = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && r.status === 'selected')
        .map(r => r.id)
)

const selectedRejectable = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && actionable(r))
        .map(r => r.id)
)

const selectedRejected = computed(() =>
    rows.value
        .filter(r => selected.value.includes(r.id) && r.status === 'rejected')
        .map(r => r.id)
)

const patchRows = (ids: string[], patch: Partial<ResponseItem>) => {
    const idSet = new Set(ids)
    rows.value = rows.value.map(r => idSet.has(r.id) ? { ...r, ...patch } : r)
    if (detail.value && idSet.has(detail.value.id)) {
        detail.value = { ...detail.value, ...patch }
    }
}

const displayName = (r: ResponseItem) => {
    const first = r.answers.find(a => a.learner_attribute === 'first_name')?.value
    const last = r.answers.find(a => a.learner_attribute === 'last_name')?.value
    if (first || last) return `${first ?? ''} ${last ?? ''}`.trim()
    return r.email ?? 'Candidat'
}

const confirmCopy = computed(() => {
    if (!confirmModal.value) return { title: '', body: '', confirmLabel: '', danger: false, withNote: false }
    const count = confirmModal.value.ids.length
    const names = rows.value
        .filter(r => confirmModal.value!.ids.includes(r.id))
        .slice(0, 3)
        .map(displayName)
    const who = names.length
        ? names.join(', ') + (count > 3 ? ` et ${count - 3} autre(s)` : '')
        : `${count} candidature(s)`

    switch (confirmModal.value.action) {
        case 'select':
            return {
                title: 'Confirmer la sélection',
                body: `Sélectionner ${who} ? Vous pourrez annuler ensuite via « Désélectionner ».`,
                confirmLabel: 'Sélectionner',
                danger: false,
                withNote: true,
            }
        case 'shortlist':
            return {
                title: 'Confirmer la présélection',
                body: `Présélectionner ${who} ?`,
                confirmLabel: 'Présélectionner',
                danger: false,
                withNote: true,
            }
        case 'unshortlist':
            return {
                title: 'Annuler la présélection',
                body: `Remettre ${who} en statut « Soumise » ?`,
                confirmLabel: 'Annuler la présélection',
                danger: false,
                withNote: false,
            }
        case 'reject':
            return {
                title: 'Confirmer le refus',
                body: `Refuser ${who} ? Vous pourrez rétablir la candidature ensuite.`,
                confirmLabel: 'Refuser',
                danger: true,
                withNote: true,
            }
        case 'deselect':
            return {
                title: 'Annuler la sélection',
                body: `Remettre ${who} en statut « Soumise » ?`,
                confirmLabel: 'Désélectionner',
                danger: false,
                withNote: false,
            }
        case 'unreject':
            return {
                title: 'Annuler le refus',
                body: `Rétablir ${who} en statut « Soumise » ?`,
                confirmLabel: 'Rétablir',
                danger: false,
                withNote: false,
            }
        case 'enroll':
            return {
                title: 'Confirmer l’inscription',
                body: `Inscrire ${who} à la formation ? Cette action crée l’apprenant.`,
                confirmLabel: 'Inscrire',
                danger: false,
                withNote: false,
            }
    }
})

const askConfirm = (action: ConfirmAction, ids?: string[]) => {
    const resolved = ids ?? (
        action === 'select' ? selectedSubmitted.value
            : action === 'shortlist' ? selectedSubmittedOnly.value
                : action === 'unshortlist' ? selectedShortlisted.value
                    : action === 'reject' ? selectedRejectable.value
                        : action === 'deselect' ? selectedSelected.value
                            : action === 'unreject' ? selectedRejected.value
                                : selectedSelected.value
    )
    if (!resolved.length || actionBusy.value) return
    confirmNote.value = reviewNote.value
    confirmModal.value = { action, ids: [...resolved] }
}

const closeConfirm = () => {
    confirmModal.value = null
    confirmNote.value = ''
}

const postAction = (
    url: string,
    ids: string[],
    optimistic: Partial<ResponseItem>,
    note: string | null = null,
    withNote = false,
) => {
    if (!ids.length || actionBusy.value) return

    const snapshot = rows.value
        .filter(r => ids.includes(r.id))
        .map(r => ({ ...r }))

    patchRows(ids, {
        ...optimistic,
        ...(withNote ? { review_note: note } : {}),
    })
    selected.value = selected.value.filter(id => !ids.includes(id))
    closeConfirm()

    actionBusy.value = true
    router.post(url, {
        response_ids: ids,
        ...(withNote ? { review_note: note } : {}),
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['responses'],
        showProgress: false,
        async: true,
        onError: () => {
            const byId = new Map(snapshot.map(r => [r.id, r]))
            rows.value = rows.value.map(r => byId.get(r.id) ?? r)
            if (detail.value && byId.has(detail.value.id)) {
                detail.value = byId.get(detail.value.id)!
            }
        },
        onFinish: () => {
            actionBusy.value = false
            reviewNote.value = ''
        },
    })
}

const executeConfirm = () => {
    if (!confirmModal.value) return
    const { action, ids } = confirmModal.value
    const note = confirmNote.value.trim() || null

    if (action === 'select') {
        postAction(`/forms/${props.form.id}/responses/select`, ids, {
            ...statusMeta('selected'),
            reviewed_at: new Date().toISOString(),
            reviewer: currentUserName.value,
        }, note, true)
        return
    }
    if (action === 'shortlist') {
        postAction(`/forms/${props.form.id}/responses/shortlist`, ids, {
            ...statusMeta('shortlisted'),
            reviewed_at: new Date().toISOString(),
            reviewer: currentUserName.value,
        }, note, true)
        return
    }
    if (action === 'unshortlist') {
        postAction(`/forms/${props.form.id}/responses/unshortlist`, ids, {
            ...statusMeta('submitted'),
            reviewed_at: null,
            reviewer: null,
            review_note: null,
        })
        return
    }
    if (action === 'reject') {
        postAction(`/forms/${props.form.id}/responses/reject`, ids, {
            ...statusMeta('rejected'),
            reviewed_at: new Date().toISOString(),
            reviewer: currentUserName.value,
        }, note, true)
        return
    }
    if (action === 'deselect') {
        postAction(`/forms/${props.form.id}/responses/deselect`, ids, {
            ...statusMeta('submitted'),
            reviewed_at: null,
            reviewer: null,
            review_note: null,
        })
        return
    }
    if (action === 'unreject') {
        postAction(`/forms/${props.form.id}/responses/unreject`, ids, {
            ...statusMeta('submitted'),
            reviewed_at: null,
            reviewer: null,
            review_note: null,
        })
        return
    }
    postAction(`/forms/${props.form.id}/responses/enroll`, ids, {
        ...statusMeta('enrolled'),
        reviewed_at: new Date().toISOString(),
        reviewer: currentUserName.value,
    })
}

const badgeClass = (color: string) => {
    const map: Record<string, string> = {
        gray: 'badge-gray', blue: 'badge-blue', green: 'badge-green',
        red: 'badge-red', purple: 'badge-purple',
    }
    return map[color] ?? 'badge-gray'
}

const openDetail = (r: ResponseItem) => {
    detail.value = r
    reviewNote.value = r.review_note ?? ''
}
</script>

<template>
    <Head :title="`Réponses — ${form.title}`" />
    <div class="max-w-[1100px] mx-auto space-y-xl">
        <div class="flex items-start justify-between gap-md flex-wrap">
            <div class="page-title-row">
                <Link :href="`/forms/${form.id}/edit`" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <h1 class="page-title">Réponses</h1>
                    <p class="page-subtitle">
                        {{ form.title }} · {{ form.project?.name }} · {{ form.formation?.name }}
                        · {{ responses.total }} candidature(s)
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <Can permission="forms.export">
                    <a :href="`/forms/${form.id}/responses/export`" class="btn-secondary">Exporter Excel</a>
                    <a :href="exportImportUrl" class="btn-secondary" title="Fichier compatible avec l’import apprenants">
                        Export pour import
                    </a>
                    <a :href="`/forms/${form.id}/responses/export-pdf`" class="btn-secondary">Exporter PDF</a>
                </Can>
                <Can permission="forms.stats">
                    <Link :href="`/forms/${form.id}/stats`" class="btn-secondary">Statistiques</Link>
                </Can>
                <Link
                    v-if="form.formation?.id"
                    :href="`/learners/import?formation=${form.formation.id}`"
                    class="btn-secondary"
                >
                    Importer apprenants
                </Link>
                <Link :href="`/forms/${form.id}/edit`" class="btn-secondary">Éditeur</Link>
            </div>
        </div>

        <div class="status-chips">
            <button
                type="button"
                class="status-chip"
                :class="{ active: status === '' }"
                @click="setStatusFilter('')"
            >
                Tous
            </button>
            <button
                v-for="s in statuses"
                :key="s.value"
                type="button"
                class="status-chip"
                :class="[badgeClass(s.color), { active: status === s.value }]"
                @click="setStatusFilter(s.value)"
            >
                {{ s.label }}
            </button>
        </div>

        <div class="filter-bar">
            <div class="filter-field grow">
                <label class="filter-label">Recherche e-mail</label>
                <input v-model="search" type="search" class="filter-input" placeholder="email@exemple.com" />
            </div>
            <div class="filter-field">
                <label class="filter-label">Statut</label>
                <select v-model="status" class="filter-select">
                    <option value="">Tous</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="selected.length" class="selection-bar">
            <span>{{ selected.length }} cochée(s)</span>
            <Can permission="forms.select">
                <button v-if="selectedSubmittedOnly.length" type="button" class="btn-banner" :disabled="actionBusy" @click="askConfirm('shortlist')">
                    Présélectionner
                </button>
                <button v-if="selectedSubmitted.length" type="button" class="btn-banner" :disabled="actionBusy" @click="askConfirm('select')">
                    Sélectionner
                </button>
                <button v-if="selectedSelected.length" type="button" class="btn-banner" :disabled="actionBusy" @click="askConfirm('enroll')">
                    Inscrire
                </button>
                <button v-if="selectedShortlisted.length" type="button" class="btn-banner-ghost" :disabled="actionBusy" @click="askConfirm('unshortlist')">
                    Annuler présélection
                </button>
                <button v-if="selectedSelected.length" type="button" class="btn-banner-ghost" :disabled="actionBusy" @click="askConfirm('deselect')">
                    Désélectionner
                </button>
                <button v-if="selectedRejectable.length" type="button" class="btn-banner-ghost" :disabled="actionBusy" @click="askConfirm('reject')">
                    Refuser
                </button>
                <button v-if="selectedRejected.length" type="button" class="btn-banner-ghost" :disabled="actionBusy" @click="askConfirm('unreject')">
                    Annuler le refus
                </button>
            </Can>
        </div>

        <div v-if="rows.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:52px;color:#e0e3e5">inbox</span>
            <h2 class="font-semibold text-on-surface mt-md">Aucune candidature</h2>
            <p class="text-body-md text-secondary mt-xs">Les réponses apparaîtront ici après publication du lien.</p>
        </div>

        <div v-else class="table-card">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-check">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll" />
                            </th>
                            <th>Candidat</th>
                            <th>E-mail</th>
                            <th>Statut</th>
                            <th>Décision</th>
                            <th>Soumis le</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id">
                            <td>
                                <input
                                    type="checkbox"
                                    :checked="selected.includes(r.id)"
                                    :disabled="r.status === 'enrolled'"
                                    @change="toggleOne(r.id)"
                                />
                            </td>
                            <td class="row-title">{{ displayName(r) }}</td>
                            <td class="row-sub">{{ r.email }}</td>
                            <td>
                                <span class="status-badge" :class="badgeClass(r.status_color)">{{ r.status_label }}</span>
                            </td>
                            <td class="row-sub">
                                <template v-if="r.reviewer">
                                    <span v-if="r.status === 'selected' || r.status === 'enrolled'">Sélectionné par {{ r.reviewer }}</span>
                                    <span v-else-if="r.status === 'shortlisted'">Présélectionné par {{ r.reviewer }}</span>
                                    <span v-else-if="r.status === 'rejected'">Refusé par {{ r.reviewer }}</span>
                                    <span v-else>{{ r.reviewer }}</span>
                                    <div v-if="r.review_note" class="note-preview">{{ r.review_note }}</div>
                                </template>
                                <span v-else>—</span>
                            </td>
                            <td class="row-sub">
                                {{ r.submitted_at ? new Date(r.submitted_at).toLocaleString('fr-FR') : '—' }}
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="icon-action" title="Détail" @click="openDetail(r)">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </button>
                                    <Can permission="forms.select">
                                        <button
                                            v-if="r.status === 'submitted'"
                                            type="button"
                                            class="icon-action"
                                            title="Présélectionner"
                                            :disabled="actionBusy"
                                            @click="askConfirm('shortlist', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">bookmark_add</span>
                                        </button>
                                        <button
                                            v-if="['submitted', 'shortlisted'].includes(r.status)"
                                            type="button"
                                            class="icon-action ok"
                                            title="Sélectionner"
                                            :disabled="actionBusy"
                                            @click="askConfirm('select', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">how_to_reg</span>
                                        </button>
                                        <button
                                            v-if="r.status === 'shortlisted'"
                                            type="button"
                                            class="icon-action"
                                            title="Annuler la présélection"
                                            :disabled="actionBusy"
                                            @click="askConfirm('unshortlist', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">bookmark_remove</span>
                                        </button>
                                        <button
                                            v-if="r.status === 'selected'"
                                            type="button"
                                            class="icon-action"
                                            title="Désélectionner (annuler)"
                                            :disabled="actionBusy"
                                            @click="askConfirm('deselect', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">undo</span>
                                        </button>
                                        <button
                                            v-if="r.status === 'selected'"
                                            type="button"
                                            class="icon-action ok"
                                            title="Inscrire"
                                            :disabled="actionBusy"
                                            @click="askConfirm('enroll', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">school</span>
                                        </button>
                                        <button
                                            v-if="actionable(r)"
                                            type="button"
                                            class="icon-action danger"
                                            title="Refuser"
                                            :disabled="actionBusy"
                                            @click="askConfirm('reject', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">person_off</span>
                                        </button>
                                        <button
                                            v-if="r.status === 'rejected'"
                                            type="button"
                                            class="icon-action"
                                            title="Annuler le refus"
                                            :disabled="actionBusy"
                                            @click="askConfirm('unreject', [r.id])"
                                        >
                                            <span class="material-symbols-outlined">restart_alt</span>
                                        </button>
                                    </Can>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="detail" class="modal-backdrop" @click.self="detail = null">
                <div class="detail-box">
                    <div class="detail-head">
                        <div>
                            <h2>{{ displayName(detail) }}</h2>
                            <p>{{ detail.email }}</p>
                            <span class="status-badge" :class="badgeClass(detail.status_color)">{{ detail.status_label }}</span>
                        </div>
                        <button type="button" class="icon-back" @click="detail = null">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <dl class="detail-list">
                        <div v-for="a in detail.answers" :key="a.field_id" class="detail-item">
                            <dt>
                                {{ a.label }}
                                <span v-if="a.learner_attribute" class="learner-tag">Apprenant</span>
                            </dt>
                            <dd>
                                <div v-if="a.is_file && a.download_url" class="file-block">
                                    <a
                                        v-if="a.is_image"
                                        :href="a.download_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="image-preview-link"
                                    >
                                        <img :src="a.download_url" :alt="a.value || 'Image'" class="answer-image" />
                                    </a>
                                    <a
                                        :href="a.download_url"
                                        class="file-download"
                                        :download="a.value || undefined"
                                    >
                                        <span class="material-symbols-outlined">{{ a.is_image ? 'image' : 'download' }}</span>
                                        {{ a.value || 'Télécharger le fichier' }}
                                    </a>
                                </div>
                                <template v-else>{{ a.value || '—' }}</template>
                            </dd>
                        </div>
                    </dl>

                    <div v-if="detail.reviewer" class="review-meta">
                        <p v-if="detail.status === 'rejected'">
                            Refusé par <strong>{{ detail.reviewer }}</strong>
                            <span v-if="detail.reviewed_at"> · {{ new Date(detail.reviewed_at).toLocaleString('fr-FR') }}</span>
                        </p>
                        <p v-else-if="detail.status === 'shortlisted'">
                            Présélectionné par <strong>{{ detail.reviewer }}</strong>
                            <span v-if="detail.reviewed_at"> · {{ new Date(detail.reviewed_at).toLocaleString('fr-FR') }}</span>
                        </p>
                        <p v-else>
                            Sélectionné / traité par <strong>{{ detail.reviewer }}</strong>
                            <span v-if="detail.reviewed_at"> · {{ new Date(detail.reviewed_at).toLocaleString('fr-FR') }}</span>
                        </p>
                        <p v-if="detail.review_note" class="motif">Motif : {{ detail.review_note }}</p>
                    </div>

                    <div v-if="detail.learner" class="enrolled-note">
                        Inscrit comme apprenant :
                        <Link :href="`/learners/${detail.learner.id}`">
                            {{ detail.learner.first_name }} {{ detail.learner.last_name }}
                        </Link>
                    </div>

                    <Can v-if="detail.status !== 'enrolled'" permission="forms.select">
                        <div class="detail-actions">
                            <div class="detail-btns">
                                <button
                                    v-if="detail.status === 'submitted'"
                                    type="button"
                                    class="btn-icon-label"
                                    :disabled="actionBusy"
                                    @click="askConfirm('shortlist', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">bookmark_add</span>
                                    Présélectionner
                                </button>
                                <button
                                    v-if="['submitted', 'shortlisted'].includes(detail.status)"
                                    type="button"
                                    class="btn-icon-label ok"
                                    :disabled="actionBusy"
                                    @click="askConfirm('select', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">how_to_reg</span>
                                    Sélectionner
                                </button>
                                <button
                                    v-if="detail.status === 'shortlisted'"
                                    type="button"
                                    class="btn-icon-label"
                                    :disabled="actionBusy"
                                    @click="askConfirm('unshortlist', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">bookmark_remove</span>
                                    Annuler présélection
                                </button>
                                <button
                                    v-if="detail.status === 'selected'"
                                    type="button"
                                    class="btn-icon-label"
                                    :disabled="actionBusy"
                                    @click="askConfirm('deselect', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">undo</span>
                                    Désélectionner
                                </button>
                                <button
                                    v-if="detail.status === 'selected'"
                                    type="button"
                                    class="btn-icon-label ok"
                                    :disabled="actionBusy"
                                    @click="askConfirm('enroll', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">school</span>
                                    Inscrire
                                </button>
                                <button
                                    v-if="actionable(detail)"
                                    type="button"
                                    class="btn-icon-label danger"
                                    :disabled="actionBusy"
                                    @click="askConfirm('reject', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">person_off</span>
                                    Refuser
                                </button>
                                <button
                                    v-if="detail.status === 'rejected'"
                                    type="button"
                                    class="btn-icon-label"
                                    :disabled="actionBusy"
                                    @click="askConfirm('unreject', [detail.id])"
                                >
                                    <span class="material-symbols-outlined">restart_alt</span>
                                    Annuler le refus
                                </button>
                            </div>
                        </div>
                    </Can>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="confirmModal" class="modal-backdrop confirm-layer" @click.self="closeConfirm">
                <div class="confirm-box">
                    <h2>{{ confirmCopy.title }}</h2>
                    <p>{{ confirmCopy.body }}</p>
                    <div v-if="confirmCopy.withNote" class="confirm-note">
                        <label class="filter-label">Motif (optionnel)</label>
                        <textarea
                            v-model="confirmNote"
                            rows="2"
                            class="filter-input"
                            placeholder="Motif de sélection ou de refus…"
                        />
                    </div>
                    <div class="confirm-actions">
                        <button type="button" class="btn-cancel" :disabled="actionBusy" @click="closeConfirm">
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="btn-confirm"
                            :class="{ danger: confirmCopy.danger }"
                            :disabled="actionBusy"
                            @click="executeConfirm"
                        >
                            {{ confirmCopy.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-title-row { display: flex; align-items: flex-start; gap: 14px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    background: #fff; border: 1px solid #e0e3e5; color: #1F3A4D;
    text-decoration: none; transition: all 0.15s; cursor: pointer;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; line-height: 1.25; }
.page-subtitle { font-size: 14px; color: #515f74; margin-top: 4px; }
.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-secondary {
    display: inline-flex; align-items: center; padding: 8px 14px;
    border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
    text-decoration: none; border: 1.5px solid #e0e3e5; color: #515f74; background: #fff;
    font-family: inherit;
}
.status-chips {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.status-chip {
    display: inline-flex; align-items: center; padding: 6px 12px;
    border-radius: 999px; border: 1.5px solid #e0e3e5; background: #fff;
    font-size: 12px; font-weight: 700; color: #515f74; cursor: pointer;
    font-family: inherit; text-transform: uppercase; letter-spacing: 0.03em;
}
.status-chip.active { border-color: #1F3A4D; background: #1F3A4D; color: #fff; }
.status-chip.badge-blue.active { background: #1d4ed8; border-color: #1d4ed8; }
.status-chip.badge-green.active { background: #0f7a3d; border-color: #0f7a3d; }
.status-chip.badge-red.active { background: #b91c1c; border-color: #b91c1c; }
.status-chip.badge-purple.active { background: #7e22ce; border-color: #7e22ce; }
.filter-bar {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
    padding: 14px 16px; background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
}
.file-block { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.image-preview-link { display: block; }
.answer-image {
    max-width: 220px; max-height: 160px; object-fit: cover;
    border-radius: 10px; border: 1px solid #e0e3e5; background: #f8fafc;
}
.filter-field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
.filter-field.grow { flex: 1; min-width: 220px; }
.filter-label {
    font-size: 12px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 6px;
}
.filter-input, .filter-select {
    width: 100%; padding: 8px 12px; border: 1.5px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fafbfc; outline: none; font-family: inherit;
}
.selection-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    background: #1F3A4D; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px;
}
.btn-banner {
    padding: 8px 14px; border-radius: 8px; border: none; background: #fff; color: #1F3A4D;
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer;
}
.btn-banner-ghost {
    padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.35);
    background: transparent; color: #fff; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer;
}
.empty-state {
    display: flex; flex-direction: column; align-items: center;
    padding: 60px 40px; background: #fff; border: 1px solid #e0e3e5; border-radius: 16px;
}
.table-card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    padding: 12px 16px; font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.05em; text-align: left;
}
.data-table td { padding: 14px 16px; border-top: 1px solid #eef0f2; vertical-align: middle; }
.w-check { width: 40px; }
.row-title { font-size: 14px; font-weight: 600; color: #191c1e; }
.row-sub { font-size: 12px; color: #515f74; }
.note-preview { margin-top: 2px; font-style: italic; color: #64748b; }
.status-badge {
    display: inline-flex; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.badge-gray { background: #f1f3f5; color: #515f74; }
.badge-blue { background: #e8f1ff; color: #1d4ed8; }
.badge-green { background: #e6f7ed; color: #0f7a3d; }
.badge-red { background: #fde8e8; color: #b91c1c; }
.badge-purple { background: #f3e8ff; color: #7e22ce; }
.row-actions { display: flex; flex-wrap: nowrap; gap: 4px; justify-content: flex-end; }
.icon-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px; border: none;
    background: transparent; color: #1F3A4D; cursor: pointer; padding: 0;
}
.icon-action .material-symbols-outlined { font-size: 20px; }
.icon-action:hover:not(:disabled) { background: #f1f5f9; }
.icon-action.ok { color: #0f7a3d; }
.icon-action.ok:hover:not(:disabled) { background: #e6f7ed; }
.icon-action.danger { color: #ba1a1a; }
.icon-action.danger:hover:not(:disabled) { background: #fde8e8; }
.icon-action:disabled { opacity: 0.45; cursor: not-allowed; }
.modal-backdrop {
    position: fixed; inset: 0; z-index: 80;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; padding: 20px;
}
.confirm-layer { z-index: 90; }
.confirm-box {
    width: 100%; max-width: 440px; background: #fff; border-radius: 16px;
    padding: 22px 24px; box-shadow: 0 24px 64px rgba(0,0,0,0.2);
}
.confirm-box h2 { font-size: 18px; font-weight: 700; color: #191c1e; margin: 0 0 8px; }
.confirm-box p { font-size: 14px; color: #515f74; line-height: 1.5; margin: 0; }
.confirm-note { margin-top: 14px; }
.confirm-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.btn-cancel, .btn-confirm {
    padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; font-family: inherit; border: none;
}
.btn-cancel { background: #fff; border: 1.5px solid #e0e3e5; color: #515f74; }
.btn-confirm { background: #1F3A4D; color: #fff; }
.btn-confirm.danger { background: #ba1a1a; }
.btn-cancel:disabled, .btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }
.detail-box {
    width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
    background: #fff; border-radius: 16px; padding: 22px 24px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
}
.detail-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.detail-head h2 { font-size: 20px; font-weight: 700; color: #191c1e; }
.detail-head p { font-size: 13px; color: #515f74; margin: 4px 0 8px; }
.detail-list { display: flex; flex-direction: column; gap: 12px; }
.detail-item dt { font-size: 12px; font-weight: 700; color: #515f74; text-transform: uppercase; letter-spacing: 0.04em; }
.detail-item dd { margin-top: 4px; font-size: 14px; color: #191c1e; white-space: pre-wrap; }
.file-download {
    display: inline-flex; align-items: center; gap: 6px;
    color: #E5004C; font-weight: 600; text-decoration: none;
}
.file-download .material-symbols-outlined { font-size: 18px; }
.file-download:hover { text-decoration: underline; }
.learner-tag {
    margin-left: 6px; font-size: 10px; padding: 1px 6px; border-radius: 99px;
    background: rgba(229,0,76,0.1); color: #E5004C;
}
.review-meta { margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 10px; font-size: 13px; color: #475569; }
.motif { margin-top: 6px; font-style: italic; }
.enrolled-note { margin-top: 12px; font-size: 13px; }
.detail-actions { margin-top: 18px; border-top: 1px solid #eef0f2; padding-top: 16px; }
.detail-btns { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.btn-icon-label {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 12px; border-radius: 8px; border: 1.5px solid #e0e3e5;
    background: #fff; color: #1F3A4D; font-size: 13px; font-weight: 600;
    cursor: pointer; font-family: inherit;
}
.btn-icon-label .material-symbols-outlined { font-size: 18px; }
.btn-icon-label.ok { border-color: #bbf7d0; background: #e6f7ed; color: #0f7a3d; }
.btn-icon-label.danger { border-color: #fecaca; background: #fde8e8; color: #ba1a1a; }
.btn-icon-label:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
