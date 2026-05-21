<script setup lang="ts">
import { Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Attachment {
    id: string
    file_path: string
    original_name: string
    mime_type: string
    size: number
}

interface Creator {
    id: string
    first_name: string
    last_name: string
}

interface Expense {
    id: string
    title: string
    amount: number
    expense_date: string
    spent_by: string
    description: string | null
    attachments: Attachment[]
    creator: Creator | null
    created_at: string
}

interface Project {
    id: string
    name: string
    budget: number | null
}

interface Formation {
    id: string
    name: string
    project: Project
}

const props = defineProps<{
    formation: Formation
    expenses: Expense[]
    totalSpent: number
    projectTotalSpent: number
    projectBudget: number
}>()

// ─── Formatage ────────────────────────────────────────────────────────────────
const formatFcfa = (amount: number): string =>
    new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'

const formatDate = (d: string): string =>
    new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })

const formatSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const isNew = (dateStr: string): boolean => {
    const d = new Date(dateStr)
    return (Date.now() - d.getTime()) < 1000 * 60 * 60 * 48
}

// ─── Stats ────────────────────────────────────────────────────────────────────
const remaining  = computed(() => Math.max(0, props.projectBudget - props.projectTotalSpent))
const isOverBudget = computed(() => props.projectTotalSpent > props.projectBudget && props.projectBudget > 0)
const percentUsed = computed(() => {
    if (!props.projectBudget) return 0
    return Math.min(100, Math.round((props.projectTotalSpent / props.projectBudget) * 100))
})
const progressColor = computed(() => {
    if (percentUsed.value >= 90) return 'bar-danger'
    if (percentUsed.value >= 70) return 'bar-warning'
    return 'bar-success'
})

// ─── Tri & Recherche ──────────────────────────────────────────────────────────
const search  = ref('')
const sortBy  = ref<'date_desc' | 'date_asc' | 'amount_desc' | 'amount_asc'>('date_desc')

const filteredExpenses = computed(() => {
    let list = [...props.expenses]
    const q = search.value.trim().toLowerCase()
    if (q) list = list.filter(e =>
        e.title.toLowerCase().includes(q) ||
        e.spent_by.toLowerCase().includes(q)
    )
    switch (sortBy.value) {
        case 'date_desc':  return list.sort((a, b) => b.expense_date.localeCompare(a.expense_date))
        case 'date_asc':   return list.sort((a, b) => a.expense_date.localeCompare(b.expense_date))
        case 'amount_desc':return list.sort((a, b) => b.amount - a.amount)
        case 'amount_asc': return list.sort((a, b) => a.amount - b.amount)
    }
})

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout>

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    clearTimeout(toastTimer)
    toast.value = { message, type }
    toastTimer = setTimeout(() => { toast.value = null }, 3500)
}

// ─── Montant live ─────────────────────────────────────────────────────────────
const amountInput = ref('')
const amountAfterThis = computed(() => {
    const v = parseInt(amountInput.value, 10)
    if (!v || v <= 0) return null
    const base = props.projectTotalSpent - (editingExpense.value?.amount ?? 0)
    return base + v
})
const willExceed = computed(() => {
    if (!amountAfterThis.value || !props.projectBudget) return false
    return amountAfterThis.value > props.projectBudget
})

watch(amountInput, (val) => {
    const n = parseInt(val, 10)
    form.amount = isNaN(n) ? 0 : n
})

// ─── Modal Formulaire ─────────────────────────────────────────────────────────
const showFormModal   = ref(false)
const editingExpense  = ref<Expense | null>(null)
const selectedFiles   = ref<File[]>([])

const form = useForm({
    title: '',
    amount: 0,
    expense_date: new Date().toISOString().split('T')[0],
    spent_by: '',
    description: '',
    files: [] as File[],
})

const openCreateModal = () => {
    editingExpense.value = null
    form.reset()
    form.expense_date = new Date().toISOString().split('T')[0]
    selectedFiles.value = []
    amountInput.value = ''
    showFormModal.value = true
}

const openEditModal = (expense: Expense) => {
    editingExpense.value = expense
    form.title        = expense.title
    form.amount       = expense.amount
    amountInput.value = String(expense.amount)
    form.expense_date = expense.expense_date.split('T')[0]
    form.spent_by     = expense.spent_by
    form.description  = expense.description ?? ''
    form.files        = []
    selectedFiles.value = []
    showFormModal.value = true
}

const closeFormModal = () => {
    showFormModal.value  = false
    editingExpense.value = null
    form.reset()
    selectedFiles.value  = []
    amountInput.value    = ''
}

// ─── Drag & drop ──────────────────────────────────────────────────────────────
const isDragging = ref(false)

const handleDragOver  = (e: DragEvent) => { e.preventDefault(); isDragging.value = true }
const handleDragLeave = ()              => { isDragging.value = false }
const handleDrop      = (e: DragEvent) => {
    e.preventDefault(); isDragging.value = false
    if (e.dataTransfer?.files) addFiles(Array.from(e.dataTransfer.files))
}

const onFilesSelected = (e: Event) => {
    const t = e.target as HTMLInputElement
    if (t.files) { addFiles(Array.from(t.files)); t.value = '' }
}

const addFiles = (files: File[]) => {
    selectedFiles.value = [...selectedFiles.value, ...files]
    form.files = selectedFiles.value
}

const removeFile = (index: number) => {
    selectedFiles.value.splice(index, 1)
    form.files = selectedFiles.value
}

// ─── Icônes fichiers ──────────────────────────────────────────────────────────
const fileIcon = (s: string): string => {
    s = s.toLowerCase()
    if (s.includes('pdf')) return 'picture_as_pdf'
    if (s.includes('image') || /\.(jpe?g|png|gif|webp)$/.test(s)) return 'image'
    if (s.includes('word') || /\.(docx?)$/.test(s)) return 'description'
    if (s.includes('sheet') || s.includes('excel') || /\.(xlsx?)$/.test(s)) return 'table_chart'
    return 'insert_drive_file'
}

const fileIconColor = (s: string): string => {
    s = s.toLowerCase()
    if (s.includes('pdf')) return 'icon-pdf'
    if (s.includes('image') || /\.(jpe?g|png|gif|webp)$/.test(s)) return 'icon-image'
    if (s.includes('word') || /\.(docx?)$/.test(s)) return 'icon-doc'
    if (s.includes('sheet') || s.includes('excel') || /\.(xlsx?)$/.test(s)) return 'icon-xls'
    return 'icon-default'
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    if (editingExpense.value) {
        form.post(`/expenses/${editingExpense.value.id}?_method=PUT`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { closeFormModal(); showToast('Dépense modifiée avec succès') },
        })
    } else {
        form.post(`/formations/${props.formation.id}/expenses`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { closeFormModal(); showToast('Dépense ajoutée avec succès') },
        })
    }
}

// ─── Confirmation modale ──────────────────────────────────────────────────────
const confirmDialog = ref<{
    visible: boolean
    title: string
    message: string
    onConfirm: () => void
}>({ visible: false, title: '', message: '', onConfirm: () => {} })

const askConfirm = (title: string, message: string, onConfirm: () => void) => {
    confirmDialog.value = { visible: true, title, message, onConfirm }
}

const doConfirm = () => {
    confirmDialog.value.onConfirm()
    confirmDialog.value.visible = false
}

// ─── Suppression ──────────────────────────────────────────────────────────────
const deleteExpense = (expense: Expense) => {
    askConfirm(
        'Supprimer la dépense',
        `Voulez-vous vraiment supprimer "${expense.title}" (${formatFcfa(expense.amount)}) ? Cette action est irréversible.`,
        () => router.delete(`/expenses/${expense.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Dépense supprimée'),
        })
    )
}

const deleteAttachment = (attachment: Attachment) => {
    askConfirm(
        'Supprimer le fichier',
        `Voulez-vous vraiment supprimer "${attachment.original_name}" ?`,
        () => router.delete(`/expense-attachments/${attachment.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Fichier supprimé'),
        })
    )
}

// ─── Modal Détail ─────────────────────────────────────────────────────────────
const showDetailModal = ref(false)
const detailExpense   = ref<Expense | null>(null)

const openDetail  = (e: Expense) => { detailExpense.value = e; showDetailModal.value = true }
const closeDetail = ()           => { showDetailModal.value = false; detailExpense.value = null }
</script>

<template>
    <div class="max-w-6xl mx-auto">

        <!-- Toast notification -->
        <Teleport to="body">
            <Transition name="toast">
                <div v-if="toast" class="toast" :class="toast.type === 'error' ? 'toast-error' : 'toast-success'">
                    <span class="material-symbols-outlined" style="font-size:20px">
                        {{ toast.type === 'error' ? 'error' : 'check_circle' }}
                    </span>
                    {{ toast.message }}
                </div>
            </Transition>
        </Teleport>

        <!-- En-tête -->
        <div class="header-section">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <Link href="/projects" class="bc-link">Projets</Link>
                <span class="material-symbols-outlined bc-sep">chevron_right</span>
                <Link :href="`/projects/${formation.project.id}`" class="bc-link">{{ formation.project.name }}</Link>
                <span class="material-symbols-outlined bc-sep">chevron_right</span>
                <Link :href="`/formations/${formation.id}`" class="bc-link">{{ formation.name }}</Link>
                <span class="material-symbols-outlined bc-sep">chevron_right</span>
                <span class="bc-current">Finance</span>
            </nav>

            <div class="flex items-center gap-md mt-md">
                <Link :href="`/formations/${formation.id}`" class="icon-back" title="Retour à la formation">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-sm">
                        <div class="header-icon-wrap">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                        <h1 class="page-title">Gestion financière</h1>
                    </div>
                    <p class="page-subtitle">{{ formation.name }}</p>
                </div>
                <button @click="openCreateModal" class="btn-primary btn-lg">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Nouvelle dépense
                </button>
            </div>
        </div>

        <!-- Alerte dépassement budget -->
        <div v-if="isOverBudget" class="alert-danger">
            <span class="material-symbols-outlined" style="font-size:20px">warning</span>
            <div>
                <strong>Budget dépassé !</strong>
                Les dépenses totales du projet ({{ formatFcfa(projectTotalSpent) }}) dépassent le budget alloué ({{ formatFcfa(projectBudget) }}).
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card stat-budget">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Budget projet</p>
                    <p class="stat-value">{{ formatFcfa(projectBudget) }}</p>
                    <p class="stat-sub">Budget total alloué</p>
                </div>
            </div>

            <div class="stat-card" :class="isOverBudget ? 'stat-over' : 'stat-spent'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">{{ isOverBudget ? 'trending_up' : 'trending_down' }}</span>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Dépensé (projet)</p>
                    <p class="stat-value">{{ formatFcfa(projectTotalSpent) }}</p>
                    <p class="stat-sub">Cette formation : {{ formatFcfa(totalSpent) }}</p>
                </div>
            </div>

            <div class="stat-card" :class="isOverBudget ? 'stat-over' : 'stat-remaining'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">{{ isOverBudget ? 'money_off' : 'savings' }}</span>
                </div>
                <div class="stat-content">
                    <p class="stat-label">{{ isOverBudget ? 'Dépassement' : 'Restant' }}</p>
                    <p class="stat-value">
                        {{ isOverBudget ? '- ' + formatFcfa(projectTotalSpent - projectBudget) : formatFcfa(remaining) }}
                    </p>
                </div>
            </div>

            <div class="stat-card stat-progress-card">
                <div class="stat-content w-full">
                    <div class="flex items-center justify-between mb-xs">
                        <p class="stat-label">Utilisation du budget</p>
                        <span class="stat-percent" :class="{ 'percent-danger': percentUsed >= 90, 'percent-warning': percentUsed >= 70 && percentUsed < 90 }">
                            {{ percentUsed }}%
                        </span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" :class="progressColor" :style="{ width: `${percentUsed}%` }"></div>
                    </div>
                    <p class="stat-sub mt-xs">{{ percentUsed >= 100 ? 'Budget épuisé' : percentUsed + '% consommé' }}</p>
                </div>
            </div>
        </div>

        <!-- Toolbar : recherche + tri -->
        <div v-if="expenses.length > 0" class="toolbar">
            <div class="search-wrap">
                <span class="material-symbols-outlined search-ico">search</span>
                <input
                    v-model="search"
                    type="text"
                    class="search-input"
                    placeholder="Rechercher par intitulé ou personne..."
                />
                <button v-if="search" @click="search = ''" class="search-clear" title="Effacer">
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
            </div>
            <div class="sort-wrap">
                <span class="material-symbols-outlined" style="font-size:16px;color:#515f74">sort</span>
                <select v-model="sortBy" class="sort-select">
                    <option value="date_desc">Date ↓ (récent)</option>
                    <option value="date_asc">Date ↑ (ancien)</option>
                    <option value="amount_desc">Montant ↓ (élevé)</option>
                    <option value="amount_asc">Montant ↑ (faible)</option>
                </select>
            </div>
        </div>

        <!-- Liste des dépenses -->
        <div class="card mt-sm">
            <div class="card-header">
                <div class="flex items-center gap-sm">
                    <h2 class="text-h2 font-semibold">Dépenses</h2>
                    <span class="count-badge">{{ filteredExpenses.length }}</span>
                    <span v-if="search" class="filter-tag">
                        « {{ search }} »
                        <button @click="search = ''" class="filter-tag-close">
                            <span class="material-symbols-outlined" style="font-size:13px">close</span>
                        </button>
                    </span>
                </div>
            </div>

            <!-- Empty total -->
            <div v-if="expenses.length === 0" class="empty-state">
                <div class="empty-illus">
                    <span class="material-symbols-outlined empty-icon">receipt_long</span>
                </div>
                <h3>Aucune dépense enregistrée</h3>
                <p>Commencez par ajouter votre première dépense pour cette formation.</p>
                <button @click="openCreateModal" class="btn-primary mt-md">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Ajouter une dépense
                </button>
            </div>

            <!-- Empty filtered -->
            <div v-else-if="filteredExpenses.length === 0" class="empty-state">
                <span class="material-symbols-outlined empty-icon">search_off</span>
                <h3>Aucun résultat</h3>
                <p>Aucune dépense ne correspond à « {{ search }} ».</p>
                <button @click="search = ''" class="btn-secondary mt-md">Effacer la recherche</button>
            </div>

            <div v-else class="expenses-list">
                <div v-for="expense in filteredExpenses" :key="expense.id" class="expense-row">
                    <div class="expense-main" @click="openDetail(expense)">
                        <div class="expense-icon-wrap">
                            <span class="material-symbols-outlined">receipt</span>
                        </div>
                        <div class="expense-info">
                            <div class="expense-title-row">
                                <h3 class="expense-title">{{ expense.title }}</h3>
                                <span v-if="isNew(expense.created_at)" class="badge-new">Nouveau</span>
                            </div>
                            <div class="expense-meta">
                                <span class="meta-item">
                                    <span class="material-symbols-outlined" style="font-size:13px">person</span>
                                    {{ expense.spent_by }}
                                </span>
                                <span class="meta-sep">·</span>
                                <span class="meta-item">
                                    <span class="material-symbols-outlined" style="font-size:13px">calendar_today</span>
                                    {{ formatDate(expense.expense_date) }}
                                </span>
                                <span v-if="expense.attachments.length" class="meta-sep">·</span>
                                <span v-if="expense.attachments.length" class="meta-item meta-files">
                                    <span class="material-symbols-outlined" style="font-size:13px">attach_file</span>
                                    {{ expense.attachments.length }} pièce{{ expense.attachments.length > 1 ? 's' : '' }}
                                </span>
                            </div>
                        </div>
                        <div class="expense-amount">{{ formatFcfa(expense.amount) }}</div>
                    </div>
                    <div class="expense-actions">
                        <button @click.stop="openDetail(expense)" class="action-btn" title="Voir détails">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                        <button @click.stop="openEditModal(expense)" class="action-btn" title="Modifier">
                            <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                        </button>
                        <button @click.stop="deleteExpense(expense)" class="action-btn action-danger" title="Supprimer">
                            <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Formulaire -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showFormModal" class="modal-overlay" @click.self="closeFormModal">
                    <div class="modal-container">
                        <div class="modal-header">
                            <div class="flex items-center gap-sm">
                                <div class="modal-header-icon">
                                    <span class="material-symbols-outlined" style="font-size:20px">
                                        {{ editingExpense ? 'edit' : 'add_circle' }}
                                    </span>
                                </div>
                                <h2>{{ editingExpense ? 'Modifier la dépense' : 'Nouvelle dépense' }}</h2>
                            </div>
                            <button @click="closeFormModal" class="modal-close" title="Fermer">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="modal-body">
                            <div class="form-grid">
                                <!-- Intitulé -->
                                <div class="field col-span-2">
                                    <label class="label">Intitulé <span class="required">*</span></label>
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.title }"
                                        placeholder="Ex : Achat matériel formation"
                                        required
                                        autofocus
                                    />
                                    <p v-if="form.errors.title" class="error-msg">{{ form.errors.title }}</p>
                                </div>

                                <!-- Montant -->
                                <div class="field">
                                    <label class="label">Montant (FCFA) <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-prefix">FCFA</span>
                                        <input
                                            v-model="amountInput"
                                            type="number"
                                            class="input input-with-prefix"
                                            :class="{ 'input-error': form.errors.amount || willExceed }"
                                            placeholder="0"
                                            min="0"
                                            step="1"
                                            required
                                        />
                                    </div>
                                    <p v-if="willExceed" class="warn-msg">
                                        <span class="material-symbols-outlined" style="font-size:13px">warning</span>
                                        Cela porterait le total à {{ formatFcfa(amountAfterThis!) }}, dépassant le budget.
                                    </p>
                                    <p v-if="form.errors.amount" class="error-msg">{{ form.errors.amount }}</p>
                                </div>

                                <!-- Date -->
                                <div class="field">
                                    <label class="label">Date <span class="required">*</span></label>
                                    <input
                                        v-model="form.expense_date"
                                        type="date"
                                        class="input"
                                        :class="{ 'input-error': form.errors.expense_date }"
                                        required
                                    />
                                    <p v-if="form.errors.expense_date" class="error-msg">{{ form.errors.expense_date }}</p>
                                </div>

                                <!-- Dépensé par -->
                                <div class="field col-span-2">
                                    <label class="label">Personne ayant dépensé <span class="required">*</span></label>
                                    <input
                                        v-model="form.spent_by"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.spent_by }"
                                        placeholder="Ex : Jean Dupont"
                                        required
                                    />
                                    <p v-if="form.errors.spent_by" class="error-msg">{{ form.errors.spent_by }}</p>
                                </div>

                                <!-- Description -->
                                <div class="field col-span-2">
                                    <label class="label">Description / Notes</label>
                                    <textarea v-model="form.description" class="input" rows="3" placeholder="Détails complémentaires..."></textarea>
                                </div>

                                <!-- Pièces existantes (édition) -->
                                <div v-if="editingExpense?.attachments.length" class="field col-span-2">
                                    <label class="label">Pièces existantes</label>
                                    <div class="existing-files">
                                        <div v-for="att in editingExpense.attachments" :key="att.id" class="existing-file">
                                            <span class="material-symbols-outlined file-icon" :class="fileIconColor(att.mime_type)">
                                                {{ fileIcon(att.mime_type) }}
                                            </span>
                                            <span class="file-name">{{ att.original_name }}</span>
                                            <span class="file-size-sm">{{ formatSize(att.size) }}</span>
                                            <button type="button" @click="deleteAttachment(att)" class="file-remove" title="Supprimer">
                                                <span class="material-symbols-outlined" style="font-size:15px">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload zone -->
                                <div class="field col-span-2">
                                    <label class="label">{{ editingExpense ? 'Ajouter des pièces' : 'Pièces justificatives' }}</label>
                                    <div
                                        class="upload-zone"
                                        :class="{ dragging: isDragging }"
                                        @dragover="handleDragOver"
                                        @dragleave="handleDragLeave"
                                        @drop="handleDrop"
                                    >
                                        <input
                                            type="file"
                                            id="files-input"
                                            multiple
                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                            @change="onFilesSelected"
                                            class="hidden"
                                        />
                                        <label for="files-input" class="upload-label">
                                            <span class="material-symbols-outlined upload-icon" :class="{ 'upload-icon-active': isDragging }">cloud_upload</span>
                                            <span class="upload-text">
                                                {{ isDragging ? 'Relâchez pour ajouter' : 'Glissez-déposez ou' }}
                                                <span v-if="!isDragging" class="upload-link"> cliquez pour parcourir</span>
                                            </span>
                                            <span class="upload-hint">PDF, Images, Word, Excel · 10 Mo max par fichier</span>
                                        </label>
                                    </div>

                                    <div v-if="selectedFiles.length" class="files-preview">
                                        <div v-for="(file, index) in selectedFiles" :key="index" class="file-chip">
                                            <span class="material-symbols-outlined file-icon" :class="fileIconColor(file.name)">
                                                {{ fileIcon(file.name) }}
                                            </span>
                                            <div class="file-chip-info">
                                                <span class="file-chip-name">{{ file.name }}</span>
                                                <span class="file-chip-size">{{ formatSize(file.size) }}</span>
                                            </div>
                                            <button type="button" @click="removeFile(index)" class="file-remove" title="Retirer">
                                                <span class="material-symbols-outlined" style="font-size:15px">close</span>
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.files" class="error-msg">{{ form.errors.files }}</p>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" @click="closeFormModal" class="btn-secondary">Annuler</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner-sm"></span>
                                    <span v-else class="material-symbols-outlined" style="font-size:17px">
                                        {{ editingExpense ? 'save' : 'add' }}
                                    </span>
                                    {{ form.processing ? 'Enregistrement...' : (editingExpense ? 'Enregistrer' : 'Ajouter la dépense') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal Détail -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDetailModal && detailExpense" class="modal-overlay" @click.self="closeDetail">
                    <div class="modal-container modal-detail">
                        <div class="modal-header">
                            <div>
                                <h2>{{ detailExpense.title }}</h2>
                                <p class="modal-subtitle">Détails de la dépense</p>
                            </div>
                            <button @click="closeDetail" class="modal-close" title="Fermer">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Montant hero -->
                            <div class="detail-hero">
                                <span class="detail-amount">{{ formatFcfa(detailExpense.amount) }}</span>
                            </div>

                            <!-- Infos -->
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                                        Date
                                    </span>
                                    <span class="detail-value">{{ formatDate(detailExpense.expense_date) }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <span class="material-symbols-outlined" style="font-size:14px">person</span>
                                        Dépensé par
                                    </span>
                                    <span class="detail-value">{{ detailExpense.spent_by }}</span>
                                </div>
                                <div v-if="detailExpense.creator" class="detail-item">
                                    <span class="detail-label">
                                        <span class="material-symbols-outlined" style="font-size:14px">manage_accounts</span>
                                        Créé par
                                    </span>
                                    <span class="detail-value">{{ detailExpense.creator.first_name }} {{ detailExpense.creator.last_name }}</span>
                                </div>
                            </div>

                            <!-- Description -->
                            <div v-if="detailExpense.description" class="detail-description">
                                <p class="detail-label">
                                    <span class="material-symbols-outlined" style="font-size:14px">notes</span>
                                    Description
                                </p>
                                <p class="detail-desc-text">{{ detailExpense.description }}</p>
                            </div>

                            <!-- Pièces -->
                            <div v-if="detailExpense.attachments.length" class="detail-attachments">
                                <p class="detail-label mb-sm">
                                    <span class="material-symbols-outlined" style="font-size:14px">attach_file</span>
                                    Pièces justificatives
                                    <span class="count-badge ml-xs">{{ detailExpense.attachments.length }}</span>
                                </p>
                                <div class="attachments-grid">
                                    <a
                                        v-for="att in detailExpense.attachments"
                                        :key="att.id"
                                        :href="`/storage/${att.file_path}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="attachment-card"
                                    >
                                        <span class="material-symbols-outlined attachment-icon" :class="fileIconColor(att.mime_type)">
                                            {{ fileIcon(att.mime_type) }}
                                        </span>
                                        <div class="attachment-info">
                                            <span class="attachment-name">{{ att.original_name }}</span>
                                            <span class="attachment-size">{{ formatSize(att.size) }}</span>
                                        </div>
                                        <span class="material-symbols-outlined open-icon">open_in_new</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button @click="closeDetail" class="btn-secondary">Fermer</button>
                            <button @click="() => { closeDetail(); openEditModal(detailExpense!) }" class="btn-primary">
                                <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                Modifier
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal Confirmation -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirmDialog.visible" class="modal-overlay" @click.self="confirmDialog.visible = false">
                    <div class="modal-container modal-confirm">
                        <div class="modal-header">
                            <div class="flex items-center gap-sm">
                                <div class="confirm-icon">
                                    <span class="material-symbols-outlined" style="font-size:22px">warning</span>
                                </div>
                                <h2>{{ confirmDialog.title }}</h2>
                            </div>
                        </div>
                        <div class="modal-body">
                            <p class="confirm-message">{{ confirmDialog.message }}</p>
                        </div>
                        <div class="modal-footer">
                            <button @click="confirmDialog.visible = false" class="btn-secondary">Annuler</button>
                            <button @click="doConfirm" class="btn-danger">
                                <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* === Simplon BF — Design System — Expenses === */

/* ── Helpers ─────────────────────────────────────────────────────────────── */
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.flex-1 { flex: 1; }
.w-full { width: 100%; }
.gap-sm { gap: 8px; }
.gap-md { gap: 16px; }
.mt-sm  { margin-top: 8px; }
.mt-md  { margin-top: 12px; }
.mt-xl  { margin-top: 24px; }
.mb-xs  { margin-bottom: 6px; }
.mb-sm  { margin-bottom: 10px; }
.ml-xs  { margin-left: 6px; }
.font-semibold { font-weight: 600; }
.text-h2 { font-size: 18px; line-height: 24px; }
.hidden { display: none; }

/* ── Breadcrumb ──────────────────────────────────────────────────────────── */
.breadcrumb { display: flex; align-items: center; gap: 2px; flex-wrap: wrap; }
.bc-link { font-size: 12px; color: #515f74; text-decoration: none; transition: color 0.15s; }
.bc-link:hover { color: #E5004C; }
.bc-sep { font-size: 16px; color: #c7cdd4; }
.bc-current { font-size: 12px; color: #E5004C; font-weight: 600; }

/* ── Header ──────────────────────────────────────────────────────────────── */
.header-section { margin-bottom: 20px; }
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px;
    background: #f2f4f6; color: #515f74; transition: all 0.15s; text-decoration: none; flex-shrink: 0;
}
.icon-back:hover { background: #fff0f4; color: #E5004C; }

.header-icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; border-radius: 10px;
    background: linear-gradient(135deg, #E5004C 0%, #ff4d80 100%);
    color: #fff; flex-shrink: 0;
}
.header-icon-wrap .material-symbols-outlined { font-size: 22px; }
.page-title { font-size: 22px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #515f74; margin-top: 2px; }

/* ── Alert ───────────────────────────────────────────────────────────────── */
.alert-danger {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 18px; background: #ffdad6; border: 1px solid #f5b8b2;
    border-radius: 10px; color: #ba1a1a; font-size: 14px;
    margin-bottom: 16px;
}
.alert-danger strong { font-weight: 700; }

/* ── Stats ───────────────────────────────────────────────────────────────── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
.stat-card {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 18px 20px; background: #fff; border-radius: 12px;
    border: 1px solid #e0e3e5; transition: all 0.15s;
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); transform: translateY(-1px); }
.stat-icon {
    display: flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
}
.stat-budget .stat-icon  { background: #fff0f4; color: #E5004C; }
.stat-spent .stat-icon   { background: #fff3cd; color: #b45309; }
.stat-remaining .stat-icon { background: #dbeafe; color: #1d4ed8; }
.stat-over .stat-icon    { background: #ffdad6; color: #ba1a1a; }
.stat-progress-card      { flex-direction: column; align-items: stretch; }

.stat-content { flex: 1; min-width: 0; }
.stat-label {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: #515f74; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.stat-value { font-size: 19px; font-weight: 700; color: #191c1e; margin-top: 4px; line-height: 1.2; }
.stat-over .stat-value { color: #ba1a1a; }
.stat-sub { font-size: 12px; color: #515f74; margin-top: 4px; }
.stat-percent { font-size: 16px; font-weight: 700; color: #191c1e; }
.percent-danger  { color: #ba1a1a; }
.percent-warning { color: #b45309; }

/* ── Progress ────────────────────────────────────────────────────────────── */
.progress-track { height: 8px; background: #f2f4f6; border-radius: 99px; overflow: hidden; margin-top: 8px; }
.progress-fill  { height: 100%; transition: width 0.5s ease; border-radius: 99px; }
.bar-success { background: linear-gradient(90deg, #1F3A4D, #3a6b8a); }
.bar-warning { background: linear-gradient(90deg, #d97706, #f59e0b); }
.bar-danger  { background: linear-gradient(90deg, #ba1a1a, #E5004C); }

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.toolbar {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0; flex-wrap: wrap;
}
.search-wrap {
    flex: 1; min-width: 220px;
    display: flex; align-items: center; gap: 8px;
    padding: 9px 12px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 8px;
    transition: all 0.15s;
}
.search-wrap:focus-within { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.07); }
.search-ico  { font-size: 18px; color: #adb5bd; flex-shrink: 0; }
.search-input { flex: 1; border: none; outline: none; font-size: 14px; color: #191c1e; background: transparent; }
.search-input::placeholder { color: #adb5bd; }
.search-clear {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    background: #e0e3e5; color: #515f74; border: none; cursor: pointer;
    flex-shrink: 0; transition: all 0.15s;
}
.search-clear:hover { background: #ffdad6; color: #ba1a1a; }

.sort-wrap {
    display: flex; align-items: center; gap: 6px;
    padding: 9px 12px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 8px;
}
.sort-select { border: none; outline: none; font-size: 13px; color: #191c1e; background: transparent; cursor: pointer; }

/* ── Card ────────────────────────────────────────────────────────────────── */
.card { background: #fff; border-radius: 12px; border: 1px solid #e0e3e5; overflow: hidden; }
.card-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid #e0e3e5; background: #fafbfc;
}
.count-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 6px;
    border-radius: 99px; background: #e0e3e5; color: #515f74;
    font-size: 11px; font-weight: 700;
}
.filter-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; background: #fff0f4; color: #E5004C;
    border-radius: 99px; font-size: 12px; font-weight: 500;
}
.filter-tag-close { display: inline-flex; align-items: center; background: none; border: none; color: #E5004C; cursor: pointer; padding: 0; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty-state { padding: 56px 20px; text-align: center; }
.empty-illus { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 20px; background: #f2f4f6; margin-bottom: 4px; }
.empty-icon { font-size: 48px; color: #c7cdd4; }
.empty-state h3 { font-size: 17px; font-weight: 600; color: #191c1e; margin-top: 12px; }
.empty-state p  { color: #515f74; margin-top: 4px; font-size: 14px; }

/* ── Expense list ────────────────────────────────────────────────────────── */
.expenses-list { display: flex; flex-direction: column; }
.expense-row {
    display: flex; align-items: center; gap: 8px;
    padding: 13px 20px; border-bottom: 1px solid #f2f4f6;
    transition: background 0.12s;
}
.expense-row:last-child { border-bottom: none; }
.expense-row:hover { background: #fafbfc; }
.expense-row:hover .expense-actions { opacity: 1; }

.expense-main {
    display: flex; align-items: center; gap: 14px; flex: 1;
    min-width: 0; cursor: pointer;
}
.expense-icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px;
    background: #fff0f4; color: #E5004C; flex-shrink: 0;
    transition: all 0.15s;
}
.expense-row:hover .expense-icon-wrap { background: #E5004C; color: #fff; }

.expense-info { flex: 1; min-width: 0; }
.expense-title-row { display: flex; align-items: center; gap: 8px; }
.expense-title { font-weight: 600; color: #191c1e; font-size: 14px; }

.badge-new {
    display: inline-flex; align-items: center;
    padding: 1px 7px; border-radius: 99px;
    background: #d5e3fd; color: #1d4ed8;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
    flex-shrink: 0;
}

.expense-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 3px; font-size: 12px; color: #515f74; }
.meta-item   { display: inline-flex; align-items: center; gap: 3px; }
.meta-sep    { color: #c7cdd4; font-size: 10px; }
.meta-files  { color: #E5004C; font-weight: 600; }

.expense-amount { font-weight: 700; font-size: 15px; color: #1F3A4D; flex-shrink: 0; white-space: nowrap; padding-right: 8px; }

.expense-actions {
    display: flex; gap: 4px; flex-shrink: 0;
    opacity: 0; transition: opacity 0.15s;
}
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: transparent; color: #515f74;
    transition: all 0.15s; cursor: pointer; border: none;
}
.action-btn:hover { background: #f2f4f6; color: #E5004C; }
.action-danger:hover { background: #ffdad6; color: #ba1a1a; }

/* ── Modal transitions ───────────────────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s, transform 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-container, .modal-leave-to .modal-container { transform: scale(0.96) translateY(8px); }
.modal-container { transition: transform 0.2s, opacity 0.2s; }

/* ── Modal base ──────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(25, 28, 30, 0.55);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
    backdrop-filter: blur(2px);
}
.modal-container {
    background: #fff; border-radius: 16px; width: 100%;
    max-width: 680px; max-height: 92vh; overflow-y: auto;
    box-shadow: 0 32px 64px -12px rgba(0,0,0,0.22);
}
.modal-detail  { max-width: 580px; }
.modal-confirm { max-width: 440px; }

.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; border-bottom: 1px solid #e0e3e5;
    position: sticky; top: 0; background: #fff; z-index: 1;
    border-radius: 16px 16px 0 0;
}
.modal-header h2 { font-size: 17px; font-weight: 700; color: #191c1e; }
.modal-subtitle  { font-size: 12px; color: #515f74; margin-top: 2px; }

.modal-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 8px;
    background: #fff0f4; color: #E5004C; flex-shrink: 0;
}

.modal-close {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: #f2f4f6; color: #515f74;
    cursor: pointer; transition: all 0.15s; border: none;
}
.modal-close:hover { background: #e0e3e5; color: #191c1e; }
.modal-body   { padding: 20px 24px; }
.modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 14px 24px; border-top: 1px solid #e0e3e5;
    background: #fafbfc; border-radius: 0 0 16px 16px;
}

/* ── Form ────────────────────────────────────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field     { display: flex; flex-direction: column; gap: 5px; }
.col-span-2 { grid-column: span 2; }
.label { font-size: 12px; font-weight: 600; color: #515f74; letter-spacing: 0.02em; }
.required { color: #E5004C; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; transition: all 0.15s; outline: none;
    background: #fafafa; color: #191c1e; font-family: inherit;
}
.input:focus   { border-color: #E5004C; background: #fff; box-shadow: 0 0 0 3px rgba(229,0,76,0.07); }
.input-error   { border-color: #ba1a1a !important; }
textarea.input { resize: vertical; }

.input-icon-wrap { position: relative; display: flex; align-items: center; }
.input-prefix {
    position: absolute; left: 12px;
    font-size: 11px; font-weight: 700; color: #515f74;
    pointer-events: none; user-select: none;
    background: transparent;
}
.input-with-prefix { padding-left: 48px; }

.error-msg { display: flex; align-items: center; gap: 3px; color: #ba1a1a; font-size: 12px; }
.warn-msg  { display: flex; align-items: center; gap: 3px; color: #b45309; font-size: 12px; background: #fff3cd; padding: 5px 8px; border-radius: 6px; }

/* ── Upload ──────────────────────────────────────────────────────────────── */
.upload-zone {
    border: 2px dashed #c7cdd4; border-radius: 10px;
    padding: 20px; text-align: center;
    transition: all 0.2s; background: #fafbfc; cursor: pointer;
}
.upload-zone:hover { border-color: #9aaabb; background: #f5f7f9; }
.upload-zone.dragging { border-color: #E5004C; background: #fff0f4; transform: scale(1.01); }
.upload-label { display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; }
.upload-icon  { font-size: 34px; color: #9aaabb; transition: all 0.2s; }
.upload-icon-active { color: #E5004C; transform: translateY(-3px); }
.upload-text  { font-size: 13px; color: #515f74; }
.upload-link  { color: #E5004C; font-weight: 600; }
.upload-hint  { font-size: 11px; color: #adb5bd; }

/* ── Files preview ───────────────────────────────────────────────────────── */
.files-preview { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
.file-chip {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 12px; background: #fafbfc;
    border: 1px solid #e0e3e5; border-radius: 8px;
    transition: all 0.15s;
}
.file-chip:hover { border-color: #adb5bd; }
.file-chip-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.file-chip-name { font-size: 12px; color: #191c1e; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-chip-size { font-size: 11px; color: #adb5bd; }

.existing-files { display: flex; flex-direction: column; gap: 5px; }
.existing-file {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 12px; background: #fff8f9;
    border: 1px solid #ffdad6; border-radius: 8px;
}
.file-name    { flex: 1; font-size: 12px; color: #191c1e; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-size-sm { font-size: 11px; color: #adb5bd; flex-shrink: 0; }

.file-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 6px;
    color: #515f74; cursor: pointer; transition: all 0.15s;
    border: none; background: transparent; flex-shrink: 0;
}
.file-remove:hover { background: #ffdad6; color: #ba1a1a; }

.file-icon    { font-size: 20px; flex-shrink: 0; }
.icon-pdf     { color: #ba1a1a; }
.icon-image   { color: #E5004C; }
.icon-doc     { color: #1F3A4D; }
.icon-xls     { color: #15803d; }
.icon-default { color: #515f74; }

/* ── Detail modal ────────────────────────────────────────────────────────── */
.detail-hero {
    text-align: center; padding: 20px 0 22px;
    border-bottom: 1px solid #e0e3e5; margin-bottom: 20px;
    background: linear-gradient(135deg, #fafbfc, #fff0f4);
    border-radius: 10px; margin: 0 -4px 20px;
}
.detail-amount { font-size: 30px; font-weight: 800; color: #1F3A4D; }

.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 16px; }
.detail-item { display: flex; flex-direction: column; padding: 12px; background: #fafbfc; border-radius: 8px; border: 1px solid #e0e3e5; }
.detail-label { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #515f74; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.detail-value { font-size: 14px; color: #191c1e; font-weight: 600; margin-top: 4px; }

.detail-description { padding: 14px; border-radius: 8px; background: #fafbfc; border: 1px solid #e0e3e5; margin-top: 12px; }
.detail-desc-text   { font-size: 14px; color: #515f74; margin-top: 6px; line-height: 1.65; }

.detail-attachments { margin-top: 16px; }
.attachments-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin-top: 8px; }
.attachment-card {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: #fafbfc;
    border: 1px solid #e0e3e5; border-radius: 8px;
    transition: all 0.15s; text-decoration: none; color: inherit;
}
.attachment-card:hover { background: #fff0f4; border-color: #E5004C; transform: translateY(-1px); }
.attachment-icon { font-size: 22px; flex-shrink: 0; }
.attachment-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.attachment-name { font-size: 12px; color: #191c1e; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.attachment-size { font-size: 11px; color: #adb5bd; }
.open-icon { font-size: 16px; color: #adb5bd; flex-shrink: 0; }

/* ── Confirm modal ───────────────────────────────────────────────────────── */
.confirm-icon { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: #fff3cd; color: #b45309; flex-shrink: 0; }
.confirm-message { font-size: 14px; color: #515f74; line-height: 1.6; }

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-radius: 10px;
    font-size: 14px; font-weight: 500;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    min-width: 240px; max-width: 360px;
}
.toast-success { background: #1F3A4D; color: #fff; }
.toast-error   { background: #ba1a1a; color: #fff; }

.toast-enter-active { transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from   { opacity: 0; transform: translateY(16px) scale(0.95); }
.toast-leave-to     { opacity: 0; transform: translateY(8px); }

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; border: none; text-decoration: none;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(229,0,76,0.25); }
.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-lg { padding: 10px 22px; font-size: 14px; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: transparent; color: #515f74;
    border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; font-weight: 500; cursor: pointer;
    transition: all 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; border-color: #adb5bd; }

.btn-danger {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: #ba1a1a; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; border: none;
}
.btn-danger:hover { background: #9b1616; transform: translateY(-1px); }

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.spinner-sm {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .form-grid    { grid-template-columns: 1fr; }
    .col-span-2   { grid-column: span 1; }
    .expense-row  { padding: 12px 14px; }
    .expense-actions { opacity: 1; }
    .toolbar      { flex-direction: column; align-items: stretch; }
    .search-wrap  { min-width: unset; }
    .stats-grid   { grid-template-columns: 1fr 1fr; }
    .page-title   { font-size: 18px; }
}
@media (max-width: 480px) {
    .stats-grid   { grid-template-columns: 1fr; }
    .expense-amount { font-size: 13px; }
}
</style>
