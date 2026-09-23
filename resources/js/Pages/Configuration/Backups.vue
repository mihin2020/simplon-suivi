<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface BackupRow {
    id: string
    type: string
    type_label: string
    status: string
    status_label: string
    progress: number
    progress_label: string | null
    disk_path: string | null
    size_bytes: number | null
    size_label: string | null
    remote_synced: boolean
    remote_error: string | null
    error_message: string | null
    triggered_by: string | null
    started_at: string | null
    finished_at: string | null
    created_at: string | null
    is_downloadable: boolean
}

interface PaginatedBackups {
    data: BackupRow[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
}

interface Settings {
    auto_enabled: boolean
    interval_days: number
    run_at: string
    retention_count: number
    notify_email: string | null
    remote_enabled: boolean
}

interface Filters {
    type: string
    status: string
    date_from: string
    date_to: string
    search: string
}

const props = defineProps<{
    backups: PaginatedBackups
    filters: Filters
    settings: Settings
    remoteConfigured: boolean
}>()

const filterType = ref(props.filters.type || '')
const filterStatus = ref(props.filters.status || '')
const filterDateFrom = ref(props.filters.date_from || '')
const filterDateTo = ref(props.filters.date_to || '')
const filterSearch = ref(props.filters.search || '')

watch(
    () => props.filters,
    (f) => {
        filterType.value = f.type || ''
        filterStatus.value = f.status || ''
        filterDateFrom.value = f.date_from || ''
        filterDateTo.value = f.date_to || ''
        filterSearch.value = f.search || ''
    },
)

let filterDebounce: ReturnType<typeof setTimeout> | null = null

const applyFilters = () => {
    router.get('/configuration/backups', {
        type: filterType.value || undefined,
        status: filterStatus.value || undefined,
        date_from: filterDateFrom.value || undefined,
        date_to: filterDateTo.value || undefined,
        search: filterSearch.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['backups', 'filters'],
    })
}

watch([filterType, filterStatus, filterDateFrom, filterDateTo], () => {
    applyFilters()
})

watch(filterSearch, () => {
    if (filterDebounce) clearTimeout(filterDebounce)
    filterDebounce = setTimeout(() => applyFilters(), 350)
})

const hasActiveFilters = computed(() =>
    !!(filterType.value || filterStatus.value || filterDateFrom.value || filterDateTo.value || filterSearch.value),
)

const clearFilters = () => {
    filterType.value = ''
    filterStatus.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''
    filterSearch.value = ''
    router.get('/configuration/backups', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['backups', 'filters'],
    })
}

const page = usePage()
const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success)
const flashError = computed(() => (page.props.flash as { error?: string } | undefined)?.error)
const flashRunningId = computed(() => (page.props.flash as { backup_running_id?: string } | undefined)?.backup_running_id)

const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout>
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    clearTimeout(toastTimer)
    toast.value = { message, type }
    toastTimer = setTimeout(() => { toast.value = null }, 4000)
}

watch([flashSuccess, flashError], () => {
    if (flashSuccess.value) showToast(flashSuccess.value, 'success')
    if (flashError.value) showToast(flashError.value, 'error')
}, { immediate: true })

const settingsForm = useForm({
    auto_enabled: props.settings.auto_enabled,
    interval_days: props.settings.interval_days ?? 1,
    run_at: props.settings.run_at,
    retention_count: props.settings.retention_count,
    notify_email: props.settings.notify_email ?? '',
    remote_enabled: props.settings.remote_enabled,
})

const submitSettings = () => {
    settingsForm.put('/configuration/backups/settings', {
        preserveScroll: true,
        onSuccess: () => showToast('Paramètres enregistrés'),
    })
}

const liveProgress = ref(0)
const liveLabel = ref('Préparation…')
const showProgressOverlay = ref(false)
const trackingId = ref<string | null>(null)
const serverProgress = ref(0)

let pollTimer: ReturnType<typeof setInterval> | null = null
let creepTimer: ReturnType<typeof setInterval> | null = null

const stopPoll = () => {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
    if (creepTimer) {
        clearInterval(creepTimer)
        creepTimer = null
    }
}

const beginTracking = (id: string, label = 'Démarrage de la sauvegarde…') => {
    trackingId.value = id
    showProgressOverlay.value = true
    liveProgress.value = Math.max(liveProgress.value, 2)
    serverProgress.value = Math.max(serverProgress.value, 2)
    liveLabel.value = label
    startPoll()
    startCreep()
}

/** Soft animation so the bar never looks frozen between server updates. */
const startCreep = () => {
    if (creepTimer) return
    creepTimer = setInterval(() => {
        if (!showProgressOverlay.value || !trackingId.value) return
        const cap = Math.min(95, serverProgress.value + 12)
        if (liveProgress.value < cap) {
            liveProgress.value = Math.min(cap, liveProgress.value + 1)
        }
    }, 400)
}

const pollProgress = async () => {
    const id = trackingId.value
    if (!id) {
        stopPoll()
        return
    }

    try {
        const res = await fetch(`/configuration/backups/${id}/progress?_=${Date.now()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        })
        if (!res.ok) return

        const data = await res.json() as {
            status: string
            progress: number
            progress_label: string | null
            status_label: string
            error_message: string | null
        }

        const next = Math.max(0, data.progress ?? 0)
        serverProgress.value = Math.max(serverProgress.value, next)
        liveProgress.value = Math.max(liveProgress.value, next)
        if (data.progress_label || data.status_label) {
            liveLabel.value = data.progress_label || data.status_label
        }
        showProgressOverlay.value = true

        if (data.status === 'success') {
            stopPoll()
            liveProgress.value = 100
            serverProgress.value = 100
            liveLabel.value = 'Terminé'
            showToast('Backup terminé avec succès')
            setTimeout(() => {
                showProgressOverlay.value = false
                trackingId.value = null
                liveProgress.value = 0
                serverProgress.value = 0
                router.reload({ only: ['backups'], preserveScroll: true })
            }, 800)
            return
        }

        if (data.status === 'failed') {
            stopPoll()
            showProgressOverlay.value = false
            trackingId.value = null
            liveProgress.value = 0
            serverProgress.value = 0
            showToast(data.error_message || 'Le backup a échoué', 'error')
            router.reload({ only: ['backups'], preserveScroll: true })
        }
    } catch {
        // keep polling
    }
}

const startPoll = () => {
    if (pollTimer) return
    void pollProgress()
    pollTimer = setInterval(() => { void pollProgress() }, 500)
}

const runForm = useForm({})
const runBackup = () => {
    showProgressOverlay.value = true
    liveProgress.value = 1
    liveLabel.value = 'Lancement…'

    runForm.post('/configuration/backups', {
        preserveScroll: true,
        onError: () => {
            showProgressOverlay.value = false
            liveProgress.value = 0
            trackingId.value = null
        },
        onSuccess: (page) => {
            const flash = page.props.flash as { backup_running_id?: string } | undefined
            const id = flash?.backup_running_id
            if (id) {
                beginTracking(id)
                return
            }
            const row = props.backups.data.find(b => b.status === 'pending' || b.status === 'running')
            if (row) beginTracking(row.id, row.progress_label || 'En cours…')
        },
    })
}

const confirmDialog = ref<{ visible: boolean; title: string; message: string; onConfirm: () => void }>({
    visible: false, title: '', message: '', onConfirm: () => {},
})
const askConfirm = (title: string, message: string, onConfirm: () => void) => {
    confirmDialog.value = { visible: true, title, message, onConfirm }
}
const doConfirm = () => {
    confirmDialog.value.onConfirm()
    confirmDialog.value.visible = false
}

const destroyBackup = (row: BackupRow) => {
    askConfirm(
        'Supprimer la sauvegarde',
        `Supprimer définitivement « ${row.disk_path ?? row.id} » (local et distant) ?`,
        () => {
            router.delete(`/configuration/backups/${row.id}`, {
                preserveScroll: true,
                onSuccess: () => showToast('Sauvegarde supprimée'),
            })
        },
    )
}

const formatDate = (iso: string | null) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

const hasPending = computed(() =>
    props.backups.data.some(b => b.status === 'pending' || b.status === 'running'),
)

const activeBackup = computed(() =>
    props.backups.data.find(b => b.status === 'pending' || b.status === 'running') ?? null,
)

// Resume overlay after refresh / redirect if a backup is still running
watch(flashRunningId, (id) => {
    if (id) beginTracking(id)
}, { immediate: true })

watch(activeBackup, (row) => {
    if (row && !trackingId.value) {
        beginTracking(row.id, row.progress_label || row.status_label)
    }
}, { immediate: true })

onMounted(() => {
    if (trackingId.value || hasPending.value) {
        const id = trackingId.value || activeBackup.value?.id
        if (id) beginTracking(id)
    }
})
onUnmounted(() => stopPoll())
</script>

<template>
    <Head title="Sauvegardes" />
    <div class="bk-page">
        <Teleport to="body">
            <Transition name="toast">
                <div v-if="toast" class="toast" :class="toast.type === 'error' ? 'toast-error' : 'toast-success'">
                    <span class="material-symbols-outlined" style="font-size:19px">
                        {{ toast.type === 'error' ? 'error' : 'check_circle' }}
                    </span>
                    {{ toast.message }}
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <div v-if="showProgressOverlay" class="bk-progress-overlay">
                <div class="bk-progress-card">
                    <div class="bk-progress-spin">
                        <span class="material-symbols-outlined">cloud_sync</span>
                    </div>
                    <h3 class="bk-progress-title">Sauvegarde en cours</h3>
                    <p class="bk-progress-label">{{ liveLabel }}</p>
                    <div class="bk-progress-track">
                        <div class="bk-progress-fill" :style="{ width: `${Math.min(100, liveProgress)}%` }" />
                    </div>
                    <div class="bk-progress-pct">{{ Math.min(100, liveProgress) }}%</div>
                </div>
            </div>
        </Teleport>

        <div class="bk-header">
            <div class="bk-header-row">
                <Link href="/configuration" class="icon-back" title="Retour">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="bk-header-icon">
                    <span class="material-symbols-outlined">cloud_download</span>
                </div>
                <div>
                    <h1 class="bk-title">Sauvegardes</h1>
                    <p class="bk-subtitle">Base de données + fichiers — stockage serveur et copie cloud optionnelle</p>
                </div>
                <button
                    type="button"
                    class="bk-run"
                    :disabled="runForm.processing || hasPending"
                    @click="runBackup"
                >
                    <span class="material-symbols-outlined" style="font-size:18px">play_arrow</span>
                    {{ hasPending ? 'Backup en cours…' : 'Lancer un backup maintenant' }}
                </button>
            </div>
        </div>

        <div class="bk-banner">
            <span class="material-symbols-outlined">info</span>
            <div>
                Les archives sont enregistrées de façon sécurisée sur le serveur.
                Vous pouvez les télécharger depuis cette page.
                Un e-mail de <strong>notification</strong> (sans le fichier) peut être envoyé après chaque sauvegarde.
                La copie cloud, si activée, protège vos données en cas de panne du serveur.
            </div>
        </div>

        <div class="bk-grid">
            <section class="bk-card">
                <h2 class="bk-card-title">Paramètres</h2>
                <form class="bk-form" @submit.prevent="submitSettings">
                    <label class="bk-check">
                        <input v-model="settingsForm.auto_enabled" type="checkbox" />
                        Activer les backups automatiques
                    </label>

                    <div class="bk-row">
                        <label>
                            Fréquence
                            <div class="bk-interval">
                                <span class="bk-interval-prefix">Tous les</span>
                                <input
                                    v-model.number="settingsForm.interval_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="bk-input bk-interval-input"
                                    required
                                />
                                <span class="bk-interval-suffix">jour(s)</span>
                            </div>
                            <p v-if="settingsForm.errors.interval_days" class="bk-err">{{ settingsForm.errors.interval_days }}</p>
                            <p class="bk-hint">Ex. 1 = chaque jour, 2 = tous les 2 jours, 7 = chaque semaine…</p>
                        </label>
                        <label>
                            Heure d'exécution
                            <input v-model="settingsForm.run_at" type="time" class="bk-input" required />
                        </label>
                        <label>
                            Rétention (N derniers)
                            <input v-model.number="settingsForm.retention_count" type="number" min="1" max="90" class="bk-input" required />
                        </label>
                    </div>

                    <label>
                        E-mail de notification
                        <input
                            v-model="settingsForm.notify_email"
                            type="email"
                            class="bk-input"
                            placeholder="admin@exemple.com"
                        />
                    </label>
                    <p v-if="settingsForm.errors.notify_email" class="bk-err">{{ settingsForm.errors.notify_email }}</p>

                    <label v-if="remoteConfigured" class="bk-check">
                        <input v-model="settingsForm.remote_enabled" type="checkbox" />
                        Copier aussi vers le cloud (secours hors serveur)
                    </label>
                    <p v-else class="bk-hint">
                        La copie cloud n’est pas encore activée sur cet environnement.
                        Les sauvegardes restent disponibles en téléchargement sur cette page.
                    </p>

                    <button type="submit" class="bk-save" :disabled="settingsForm.processing">
                        Enregistrer les paramètres
                    </button>
                </form>
            </section>

            <section class="bk-card bk-card-wide">
                <div class="bk-hist-head">
                    <h2 class="bk-card-title" style="margin:0">Historique</h2>
                    <span class="bk-hist-count">{{ backups.total }} sauvegarde(s)</span>
                </div>

                <div class="bk-filters">
                    <div class="bk-search-wrap">
                        <span class="material-symbols-outlined bk-search-icon">search</span>
                        <input
                            v-model="filterSearch"
                            type="text"
                            class="bk-input bk-search"
                            placeholder="Rechercher (fichier, auteur…)"
                        />
                        <button
                            v-if="filterSearch"
                            type="button"
                            class="bk-search-clear"
                            title="Effacer"
                            @click="filterSearch = ''"
                        >
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>
                    <select v-model="filterType" class="bk-input bk-filter-select">
                        <option value="">Tous les types</option>
                        <option value="manual">Manuel</option>
                        <option value="auto">Automatique</option>
                    </select>
                    <select v-model="filterStatus" class="bk-input bk-filter-select">
                        <option value="">Tous les statuts</option>
                        <option value="success">Réussi</option>
                        <option value="failed">Échoué</option>
                        <option value="running">En cours</option>
                        <option value="pending">En attente</option>
                    </select>
                    <input v-model="filterDateFrom" type="date" class="bk-input bk-filter-date" title="Du" />
                    <input v-model="filterDateTo" type="date" class="bk-input bk-filter-date" title="Au" />
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="bk-clear-filters"
                        @click="clearFilters"
                    >
                        Réinitialiser
                    </button>
                </div>

                <div v-if="backups.data.length === 0" class="bk-empty">
                    {{ hasActiveFilters ? 'Aucun résultat pour ces filtres.' : 'Aucune sauvegarde pour le moment.' }}
                </div>
                <div v-else class="bk-table-wrap">
                    <table class="bk-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Taille</th>
                                <th>Cloud</th>
                                <th>Par</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in backups.data" :key="row.id">
                                <td>{{ formatDate(row.created_at) }}</td>
                                <td>{{ row.type_label }}</td>
                                <td>
                                    <span class="bk-badge" :data-status="row.status">{{ row.status_label }}</span>
                                    <div v-if="row.status === 'running' || row.status === 'pending'" class="bk-mini-progress">
                                        <div class="bk-mini-fill" :style="{ width: `${row.id === activeBackup?.id ? liveProgress : row.progress}%` }" />
                                    </div>
                                    <p v-if="row.error_message" class="bk-err-inline">{{ row.error_message }}</p>
                                </td>
                                <td>{{ row.size_label ?? '—' }}</td>
                                <td>
                                    <span v-if="row.remote_synced" class="bk-badge" data-status="success">Sync</span>
                                    <span v-else-if="row.remote_error" class="bk-badge" data-status="failed" :title="row.remote_error">Échec</span>
                                    <span v-else class="bk-muted">—</span>
                                </td>
                                <td>{{ row.triggered_by ?? '—' }}</td>
                                <td class="bk-actions">
                                    <a
                                        v-if="row.is_downloadable"
                                        class="bk-icon-btn"
                                        :href="`/configuration/backups/${row.id}/download`"
                                        title="Télécharger"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:18px">download</span>
                                    </a>
                                    <button
                                        type="button"
                                        class="bk-icon-btn danger"
                                        title="Supprimer"
                                        @click="destroyBackup(row)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="backups.total > 0" class="bk-pager-bar">
                    <span class="bk-pager-meta">
                        {{ backups.from ?? 0 }}–{{ backups.to ?? 0 }} sur {{ backups.total }}
                    </span>
                    <div class="bk-pager">
                        <template v-for="(link, i) in backups.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="bk-page-link"
                                :class="{ active: link.active }"
                                preserve-scroll
                                preserve-state
                                v-html="link.label"
                            />
                            <span v-else class="bk-page-link disabled" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="confirmDialog.visible" class="modal-overlay" @click.self="confirmDialog.visible = false">
                <div class="modal-box">
                    <h3 class="modal-title">{{ confirmDialog.title }}</h3>
                    <p class="modal-msg">{{ confirmDialog.message }}</p>
                    <div class="modal-actions">
                        <button type="button" class="btn-ghost" @click="confirmDialog.visible = false">Annuler</button>
                        <button type="button" class="btn-danger" @click="doConfirm">Supprimer</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.bk-page { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
.bk-header { margin-bottom: .25rem; }
.bk-header-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; transition: background .15s, color .15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.bk-header-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: #fff0f4; color: #E5004C;
    display: grid; place-items: center;
}
.bk-title { margin: 0; font-size: 1.5rem; color: #1F3A4D; }
.bk-subtitle { margin: .15rem 0 0; color: #64748b; font-size: .9rem; }
.bk-run {
    margin-left: auto; display: inline-flex; align-items: center; gap: .4rem;
    background: #E5004C; color: #fff; border: 0; border-radius: 10px;
    padding: .65rem 1rem; font-weight: 600; cursor: pointer;
}
.bk-run:disabled { opacity: .55; cursor: not-allowed; }
.bk-interval {
    display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
}
.bk-interval-prefix, .bk-interval-suffix { font-size: .875rem; color: #64748b; white-space: nowrap; }
.bk-interval-input { width: 88px; }
.bk-banner {
    margin: 1.25rem 0; display: flex; gap: .75rem; align-items: flex-start;
    background: #f0f7fb; border: 1px solid #d6e8f2; border-radius: 12px;
    padding: .9rem 1rem; color: #1F3A4D; font-size: .875rem; line-height: 1.45;
}
.bk-banner .material-symbols-outlined { color: #1F3A4D; }
.bk-banner code { font-size: .8rem; background: #e2eef5; padding: .05rem .3rem; border-radius: 4px; }
.bk-grid { display: grid; gap: 1.25rem; }
.bk-card {
    background: #fff; border: 1px solid #e8eef2; border-radius: 14px; padding: 1.25rem;
}
.bk-card-title { margin: 0 0 1rem; font-size: 1.05rem; color: #1F3A4D; }
.bk-form { display: flex; flex-direction: column; gap: .85rem; }
.bk-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .75rem; }
.bk-form label { display: flex; flex-direction: column; gap: .35rem; font-size: .85rem; color: #334155; }
.bk-check { flex-direction: row !important; align-items: center; gap: .5rem !important; }
.bk-check.muted { opacity: .65; }
.bk-input {
    border: 1px solid #d6dee6; border-radius: 8px; padding: .55rem .7rem; font-size: .9rem;
}
.bk-hint { margin: 0; font-size: .8rem; color: #64748b; line-height: 1.4; }
.bk-hint code { font-size: .75rem; }
.bk-err { color: #E5004C; font-size: .8rem; margin: 0; }
.bk-err-inline { margin: .25rem 0 0; color: #b91c1c; font-size: .75rem; max-width: 280px; }
.bk-save {
    align-self: flex-start; background: #1F3A4D; color: #fff; border: 0;
    border-radius: 8px; padding: .55rem 1rem; font-weight: 600; cursor: pointer;
}
.bk-empty { color: #94a3b8; padding: 1.5rem 0; text-align: center; }
.bk-table-wrap { overflow-x: auto; }
.bk-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.bk-table th, .bk-table td { text-align: left; padding: .65rem .5rem; border-bottom: 1px solid #eef2f6; vertical-align: top; }
.bk-table th { color: #64748b; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .02em; }
.bk-badge {
    display: inline-block; padding: .15rem .5rem; border-radius: 999px;
    font-size: .75rem; font-weight: 600; background: #e2e8f0; color: #334155;
}
.bk-badge[data-status="success"] { background: #d1fae5; color: #065f46; }
.bk-badge[data-status="failed"] { background: #fee2e2; color: #991b1b; }
.bk-badge[data-status="running"], .bk-badge[data-status="pending"] { background: #fef3c7; color: #92400e; }
.bk-mini-progress {
    margin-top: .35rem; height: 4px; background: #e2e8f0; border-radius: 99px; overflow: hidden; max-width: 120px;
}
.bk-mini-fill { height: 100%; background: #E5004C; transition: width .35s ease; }
.bk-progress-overlay {
    position: fixed; inset: 0; z-index: 100; background: rgba(15, 23, 42, .45);
    display: grid; place-items: center; padding: 1rem; backdrop-filter: blur(2px);
}
.bk-progress-card {
    background: #fff; border-radius: 16px; padding: 1.5rem 1.75rem; width: min(420px, 100%);
    box-shadow: 0 20px 50px rgba(0,0,0,.18); text-align: center;
}
.bk-progress-spin {
    width: 56px; height: 56px; margin: 0 auto .85rem; border-radius: 14px;
    background: #fff0f4; color: #E5004C; display: grid; place-items: center;
    animation: bk-pulse 1.2s ease-in-out infinite;
}
.bk-progress-spin .material-symbols-outlined { font-size: 28px; }
@keyframes bk-pulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.06); opacity: .85; } }
.bk-progress-title { margin: 0; color: #1F3A4D; font-size: 1.15rem; }
.bk-progress-label { margin: .4rem 0 1rem; color: #64748b; font-size: .9rem; min-height: 1.2em; }
.bk-progress-track { height: 10px; background: #e8eef2; border-radius: 99px; overflow: hidden; }
.bk-progress-fill { height: 100%; background: linear-gradient(90deg, #E5004C, #ff4d7a); border-radius: 99px; transition: width .4s ease; }
.bk-progress-pct { margin-top: .65rem; font-weight: 700; color: #1F3A4D; font-size: 1.25rem; }
.bk-muted { color: #94a3b8; }
.bk-actions { display: flex; gap: .25rem; }
.bk-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #1F3A4D; cursor: pointer; text-decoration: none;
}
.bk-icon-btn.danger { color: #E5004C; }
.bk-icon-btn:hover { background: #f8fafc; }
.bk-hist-head {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    margin-bottom: 1rem; flex-wrap: wrap;
}
.bk-hist-count { font-size: .85rem; color: #64748b; }
.bk-filters {
    display: flex; flex-wrap: wrap; gap: .55rem; align-items: center;
    margin-bottom: 1rem;
}
.bk-search-wrap {
    position: relative; flex: 1 1 220px; min-width: 180px;
}
.bk-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    font-size: 18px; color: #94a3b8; pointer-events: none;
}
.bk-search { padding-left: 2.1rem !important; padding-right: 2rem !important; width: 100%; }
.bk-search-clear {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    border: 0; background: transparent; color: #94a3b8; cursor: pointer; display: grid; place-items: center;
}
.bk-filter-select { flex: 0 1 150px; min-width: 130px; }
.bk-filter-date { flex: 0 1 140px; min-width: 120px; }
.bk-clear-filters {
    border: 1px solid #e2e8f0; background: #fff; color: #1F3A4D;
    border-radius: 8px; padding: .5rem .75rem; font-size: .85rem; cursor: pointer;
}
.bk-clear-filters:hover { background: #f8fafc; }
.bk-pager-bar {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    flex-wrap: wrap; margin-top: 1rem; padding-top: .85rem; border-top: 1px solid #eef2f6;
}
.bk-pager-meta { font-size: .85rem; color: #64748b; }
.bk-pager { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: 0; }
.bk-page-link {
    padding: .3rem .55rem; border-radius: 6px; border: 1px solid #e2e8f0;
    text-decoration: none; color: #334155; font-size: .8rem;
}
.bk-page-link.active { background: #E5004C; color: #fff; border-color: #E5004C; }
.bk-page-link.disabled { opacity: .45; }
.toast {
    position: fixed; top: 1.25rem; right: 1.25rem; z-index: 80;
    display: flex; align-items: center; gap: .5rem;
    padding: .75rem 1rem; border-radius: 10px; color: #fff; font-size: .9rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
}
.toast-success { background: #065f46; }
.toast-error { background: #991b1b; }
.toast-enter-active, .toast-leave-active { transition: all .2s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
    display: grid; place-items: center; z-index: 90; padding: 1rem;
}
.modal-box { background: #fff; border-radius: 14px; padding: 1.25rem; max-width: 420px; width: 100%; }
.modal-title { margin: 0 0 .5rem; color: #1F3A4D; }
.modal-msg { margin: 0 0 1rem; color: #64748b; font-size: .9rem; }
.modal-actions { display: flex; justify-content: flex-end; gap: .5rem; }
.btn-ghost { background: #f1f5f9; border: 0; border-radius: 8px; padding: .5rem .9rem; cursor: pointer; }
.btn-danger { background: #E5004C; color: #fff; border: 0; border-radius: 8px; padding: .5rem .9rem; cursor: pointer; }
</style>
