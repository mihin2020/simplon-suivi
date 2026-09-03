<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Sortable from 'sortablejs'
import Can from '@/Components/Can.vue'
import { usePermissions } from '@/composables/usePermissions'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import ProgressBar from '@/Components/Planning/ProgressBar.vue'
import TagInput from '@/Components/Planning/TagInput.vue'
import UserMentionInput, { type MentionUser } from '@/Components/Planning/UserMentionInput.vue'

/* ── Types ─────────────────────────────────────────────── */

interface Assignee { id: string; first_name: string; last_name: string; full_name?: string; email: string }

type PriorityValue = 'low' | 'medium' | 'high' | 'urgent' | null

interface Creator { id: string; first_name: string; last_name: string }

interface TaskItem {
    id: string; title: string; description: string | null
    started_at: string; ended_at: string
    status: 'todo' | 'in_progress' | 'done'
    priority: PriorityValue
    assignees: Assignee[]; creator?: Creator | null
}
interface ActivityItem {
    id: string; title: string; description: string | null
    started_at: string; ended_at: string
    resources: string[] | null
    priority: PriorityValue
    assignees: Assignee[]; tasks: TaskItem[]; progress_percentage: number; creator?: Creator | null
}
interface PhaseItem {
    id: string; name: string; description: string | null
    started_at: string; ended_at: string
    priority: PriorityValue
    assignees: Assignee[]; activities: ActivityItem[]; progress_percentage: number; creator?: Creator | null
}
interface ProjectItem {
    id: string; started_at: string; ended_at: string | null
    phases: PhaseItem[]; progress_percentage?: number
}

const props = defineProps<{ project: ProjectItem; assignableUsers: MentionUser[] }>()

/* ── Permissions ───────────────────────────────────────── */

const { can, authUser } = usePermissions()
const currentUserId = computed(() => authUser.value?.id)
const canManage = computed(() => can('projects.update'))

/* ── Priority helpers ──────────────────────────────────── */

const priorityConfig: Record<string, { label: string; color: string; bg: string }> = {
    low:    { label: 'Basse',   color: '#6b7280', bg: '#f3f4f6' },
    medium: { label: 'Moyenne', color: '#2563eb', bg: '#dbeafe' },
    high:   { label: 'Haute',   color: '#d97706', bg: '#fef3c7' },
    urgent: { label: 'Urgente', color: '#dc2626', bg: '#fee2e2' },
}

const priorities: Array<{ value: string | null; label: string }> = [
    { value: null, label: 'Aucune' },
    { value: 'low', label: 'Basse' },
    { value: 'medium', label: 'Moyenne' },
    { value: 'high', label: 'Haute' },
    { value: 'urgent', label: 'Urgente' },
]

/* ── Date helpers ──────────────────────────────────────── */

const toDateInput = (v?: string | null) => v ? v.slice(0, 10) : ''
const fmt = (v: string | null) => {
    if (!v) return '—'
    const [y, m, d] = v.slice(0, 10).split('-')
    return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

const today = new Date().toISOString().slice(0, 10)
const isOverdue = (endDate: string | null, isDone?: boolean) => {
    if (!endDate || isDone) return false
    return endDate.slice(0, 10) < today
}
const isApproaching = (endDate: string | null, isDone?: boolean) => {
    if (!endDate || isDone) return false
    const end = endDate.slice(0, 10)
    if (end < today) return false
    const diff = (new Date(end).getTime() - new Date(today).getTime()) / (1000 * 60 * 60 * 24)
    return diff <= 3
}

/* ── Display helpers ───────────────────────────────────── */

const displayName = (u: Assignee) => u.full_name || `${u.first_name} ${u.last_name}`.trim()
const initials = (u: Assignee) => `${u.first_name.charAt(0)}${u.last_name.charAt(0)}`.toUpperCase()
const creatorName = (c?: Creator | null) => c ? `${c.first_name} ${c.last_name}`.trim() : null
const taskStatusLabel: Record<TaskItem['status'], string> = { todo: 'À faire', in_progress: 'En cours', done: 'Terminée' }

/* ── Expand / Collapse ─────────────────────────────────── */

const phases = ref<PhaseItem[]>(props.project.phases)
watch(() => props.project.phases, (value) => {
    phases.value = value
    value.forEach((phase) => {
        if (!expandedPhases.value.includes(phase.id)) expandedPhases.value.push(phase.id)
        phase.activities.forEach((activity) => {
            if (!expandedActivities.value.includes(activity.id)) expandedActivities.value.push(activity.id)
        })
    })
}, { deep: true })

const expandedPhases = ref<string[]>(props.project.phases.map(p => p.id))
const expandedActivities = ref<string[]>(props.project.phases.flatMap(p => p.activities.map(a => a.id)))
const togglePhase = (id: string) => { expandedPhases.value = expandedPhases.value.includes(id) ? expandedPhases.value.filter(i => i !== id) : [...expandedPhases.value, id] }
const toggleActivity = (id: string) => { expandedActivities.value = expandedActivities.value.includes(id) ? expandedActivities.value.filter(i => i !== id) : [...expandedActivities.value, id] }

const inertiaOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['project'],
}

const recalcProgress = () => {
    phases.value.forEach((phase) => {
        phase.activities.forEach((activity) => {
            activity.progress_percentage = activity.tasks.length
                ? Math.round((activity.tasks.filter(task => task.status === 'done').length / activity.tasks.length) * 100)
                : 0
        })
        phase.progress_percentage = phase.activities.length
            ? Math.round(phase.activities.reduce((sum, activity) => sum + activity.progress_percentage, 0) / phase.activities.length)
            : 0
    })
}

/* ── Drag & drop ───────────────────────────────────────── */

const sortableInstances: Sortable[] = []
onBeforeUnmount(() => sortableInstances.forEach(s => s.destroy()))

const bindSortable = (el: HTMLElement | null, type: 'phases' | 'activities' | 'tasks') => {
    if (!canManage.value) return
    if (!el) return
    if ((el as HTMLElement & { __sortable?: Sortable }).__sortable) return

    const instance = Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'drag-ghost',
        chosenClass: 'drag-chosen',
        dragClass: 'drag-active',
        onEnd() {
            const orderedIds = Array.from(el.children)
                .map(item => (item as HTMLElement).dataset.id)
                .filter((id): id is string => Boolean(id))
            if (orderedIds.length === 0) return

            fetch(`/planning/reorder/${type}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ordered_ids: orderedIds }),
            })
        },
    })
    ;(el as HTMLElement & { __sortable?: Sortable }).__sortable = instance
    sortableInstances.push(instance)
}

/* ── Forms ─────────────────────────────────────────────── */

type FormKind = 'phase' | 'activity' | 'task' | null
const formKind = ref<FormKind>(null)
const editingPhase = ref<PhaseItem | null>(null)
const editingActivity = ref<ActivityItem | null>(null)
const editingTask = ref<TaskItem | null>(null)
const parentPhase = ref<PhaseItem | null>(null)
const parentActivity = ref<ActivityItem | null>(null)

const phaseForm = useForm({ name: '', description: '', started_at: '', ended_at: '', priority: null as string | null, assignee_ids: [] as string[] })
const activityForm = useForm({ title: '', description: '', started_at: '', ended_at: '', resources: [] as string[], priority: null as string | null, assignee_ids: [] as string[] })
const taskForm = useForm({ title: '', description: '', started_at: '', ended_at: '', status: 'todo' as TaskItem['status'], priority: null as string | null, assignee_ids: [] as string[] })

const defaultAssignees = () => currentUserId.value ? [currentUserId.value] : []

const openCreatePhase = () => { editingPhase.value = null; phaseForm.clearErrors(); phaseForm.defaults({ name: '', description: '', started_at: toDateInput(props.project.started_at), ended_at: toDateInput(props.project.ended_at), priority: null, assignee_ids: defaultAssignees() }); phaseForm.reset(); formKind.value = 'phase' }
const openEditPhase = (p: PhaseItem) => { editingPhase.value = p; phaseForm.clearErrors(); phaseForm.defaults({ name: p.name, description: p.description ?? '', started_at: toDateInput(p.started_at), ended_at: toDateInput(p.ended_at), priority: p.priority, assignee_ids: p.assignees.map(u => u.id) }); phaseForm.reset(); formKind.value = 'phase' }
const openCreateActivity = (p: PhaseItem) => { editingActivity.value = null; parentPhase.value = p; activityForm.clearErrors(); activityForm.defaults({ title: '', description: '', started_at: toDateInput(p.started_at), ended_at: toDateInput(p.ended_at), resources: [], priority: null, assignee_ids: defaultAssignees() }); activityForm.reset(); formKind.value = 'activity' }
const openEditActivity = (p: PhaseItem, a: ActivityItem) => { editingActivity.value = a; parentPhase.value = p; activityForm.clearErrors(); activityForm.defaults({ title: a.title, description: a.description ?? '', started_at: toDateInput(a.started_at), ended_at: toDateInput(a.ended_at), resources: a.resources ?? [], priority: a.priority, assignee_ids: a.assignees.map(u => u.id) }); activityForm.reset(); formKind.value = 'activity' }
const openCreateTask = (a: ActivityItem) => { editingTask.value = null; parentActivity.value = a; taskForm.clearErrors(); taskForm.defaults({ title: '', description: '', started_at: toDateInput(a.started_at), ended_at: toDateInput(a.ended_at), status: 'todo', priority: null, assignee_ids: defaultAssignees() }); taskForm.reset(); formKind.value = 'task' }
const openEditTask = (a: ActivityItem, t: TaskItem) => { editingTask.value = t; parentActivity.value = a; taskForm.clearErrors(); taskForm.defaults({ title: t.title, description: t.description ?? '', started_at: toDateInput(t.started_at), ended_at: toDateInput(t.ended_at), status: t.status, priority: t.priority, assignee_ids: t.assignees.map(u => u.id) }); taskForm.reset(); formKind.value = 'task' }

const closeForm = () => { formKind.value = null }

const submitPhase = () => { const o = { ...inertiaOptions, onSuccess: closeForm }; editingPhase.value ? phaseForm.put(`/phases/${editingPhase.value.id}`, o) : phaseForm.post(`/projects/${props.project.id}/phases`, o) }
const submitActivity = () => { if (!parentPhase.value && !editingActivity.value) return; const o = { ...inertiaOptions, onSuccess: closeForm }; editingActivity.value ? activityForm.put(`/activities/${editingActivity.value.id}`, o) : activityForm.post(`/phases/${parentPhase.value!.id}/activities`, o) }
const submitTask = () => { if (!parentActivity.value && !editingTask.value) return; const o = { ...inertiaOptions, onSuccess: closeForm }; editingTask.value ? taskForm.put(`/tasks/${editingTask.value.id}`, o) : taskForm.post(`/activities/${parentActivity.value!.id}/tasks`, o) }

/* ── Task status ───────────────────────────────────────── */

const canUpdateTaskStatus = (task: TaskItem) =>
    canManage.value || Boolean(currentUserId.value && task.assignees.some(user => user.id === currentUserId.value))

const patchStatus = (task: TaskItem, status: TaskItem['status']) => {
    if (!canUpdateTaskStatus(task)) return
    const previous = task.status
    task.status = status
    recalcProgress()
    router.patch(`/tasks/${task.id}/status`, { status }, {
        ...inertiaOptions,
        onError: () => {
            task.status = previous
            recalcProgress()
        },
    })
}
const toggleTaskDone = (task: TaskItem) => patchStatus(task, task.status === 'done' ? 'todo' : 'done')
const cycleStatus = (task: TaskItem) => patchStatus(task, task.status === 'todo' ? 'in_progress' : task.status === 'in_progress' ? 'done' : 'todo')

/* ── Delete ────────────────────────────────────────────── */

type DeleteTarget = { type: 'phase'; phase: PhaseItem } | { type: 'activity'; activity: ActivityItem } | { type: 'task'; task: TaskItem } | null
const deleteTarget = ref<DeleteTarget>(null)
const deleting = ref(false)
const deleteTitle = computed(() => { if (!deleteTarget.value) return ''; return deleteTarget.value.type === 'phase' ? 'Supprimer cette phase ?' : deleteTarget.value.type === 'activity' ? 'Supprimer cette activité ?' : 'Supprimer cette tâche ?' })
const deleteMessage = computed(() => {
    const t = deleteTarget.value; if (!t) return ''
    if (t.type === 'phase') { const a = t.phase.activities.length; const tk = t.phase.activities.reduce((s, act) => s + act.tasks.length, 0); return `La phase « ${t.phase.name} » contient ${a} activité(s) et ${tk} tâche(s). Elles seront également supprimées.` }
    if (t.type === 'activity') return `L'activité « ${t.activity.title} » contient ${t.activity.tasks.length} tâche(s). Elles seront également supprimées.`
    return `La tâche « ${t.task.title} » sera supprimée.`
})
const confirmDelete = () => {
    const t = deleteTarget.value; if (!t) return; deleting.value = true
    const url = t.type === 'phase' ? `/phases/${t.phase.id}` : t.type === 'activity' ? `/activities/${t.activity.id}` : `/tasks/${t.task.id}`
    router.delete(url, { ...inertiaOptions, onFinish: () => { deleting.value = false; deleteTarget.value = null } })
}

const modalTitle = computed(() => {
    if (formKind.value === 'phase') return editingPhase.value ? 'Modifier la phase' : 'Ajouter une phase'
    if (formKind.value === 'activity') return editingActivity.value ? 'Modifier l\'activité' : 'Ajouter une activité'
    if (formKind.value === 'task') return editingTask.value ? 'Modifier la tâche' : 'Ajouter une tâche'
    return ''
})

const modalIcon = computed(() => {
    if (formKind.value === 'phase') return 'flag'
    if (formKind.value === 'activity') return 'assignment'
    return 'task_alt'
})
</script>

<template>
    <section class="planning">
        <!-- Header -->
        <div class="planning-head">
            <div>
                <h2 class="planning-title">
                    <span class="material-symbols-outlined title-icon">account_tree</span>
                    Planification
                    <span class="planning-count">({{ phases.length }} phase{{ phases.length > 1 ? 's' : '' }})</span>
                </h2>
                <p class="planning-hint">L'avancement se calcule automatiquement à partir des tâches. Glissez-déposez pour réorganiser.</p>
            </div>
            <Can permission="projects.update">
                <button type="button" class="btn-add-phase" @click="openCreatePhase">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Ajouter une phase
                </button>
            </Can>
        </div>

        <!-- Empty state -->
        <div v-if="phases.length === 0" class="empty">
            <span class="material-symbols-outlined empty-icon">flag</span>
            <p class="empty-title">Aucune phase pour ce projet</p>
            <p class="empty-sub">Créez des phases, activités et tâches pour suivre l'avancement.</p>
            <Can permission="projects.update">
                <button type="button" class="btn-add-phase" @click="openCreatePhase">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Ajouter une phase
                </button>
            </Can>
        </div>

        <!-- Phase list (sortable) -->
        <div v-else :ref="(el: any) => bindSortable(el, 'phases')" class="phase-list">
            <article v-for="phase in phases" :key="phase.id" :data-id="phase.id" class="phase-card" :class="{ 'overdue-card': isOverdue(phase.ended_at) }">
                <header class="phase-header" @click="togglePhase(phase.id)">
                    <span v-if="canManage" class="drag-handle material-symbols-outlined" title="Glisser pour réordonner" @click.stop>drag_indicator</span>
                    <span class="level-icon phase-icon">
                        <span class="material-symbols-outlined">flag</span>
                    </span>
                    <div class="item-main">
                        <div class="item-title-row">
                            <div class="title-with-badge">
                                <h3>{{ phase.name }}</h3>
                                <span v-if="phase.priority && priorityConfig[phase.priority]" class="priority-badge" :style="{ background: priorityConfig[phase.priority].bg, color: priorityConfig[phase.priority].color }">
                                    {{ priorityConfig[phase.priority].label }}
                                </span>
                            </div>
                            <span class="date-range" :class="{ 'date-overdue': isOverdue(phase.ended_at), 'date-approaching': isApproaching(phase.ended_at) }">
                                <span class="material-symbols-outlined" style="font-size:14px">calendar_today</span>
                                {{ fmt(phase.started_at) }} → {{ fmt(phase.ended_at) }}
                            </span>
                        </div>
                        <p v-if="phase.description" class="muted">{{ phase.description }}</p>
                        <div class="meta-row">
                            <div class="people">
                                <span v-for="u in phase.assignees" :key="u.id" class="person" :title="displayName(u)">
                                    <span class="avatar">{{ initials(u) }}</span>
                                    {{ displayName(u) }}
                                </span>
                                <span v-if="phase.assignees.length === 0" class="muted-inline">Aucun responsable</span>
                                <span v-if="creatorName(phase.creator)" class="created-by">par {{ creatorName(phase.creator) }}</span>
                            </div>
                            <ProgressBar :percentage="phase.progress_percentage" size="sm" />
                        </div>
                    </div>
                    <span class="chevron material-symbols-outlined">{{ expandedPhases.includes(phase.id) ? 'expand_more' : 'chevron_right' }}</span>
                    <Can permission="projects.update">
                        <div class="card-actions" @click.stop>
                            <button type="button" class="act-btn act-add" title="Ajouter une activité" @click="openCreateActivity(phase)">
                                <span class="material-symbols-outlined">playlist_add</span>
                            </button>
                            <button type="button" class="act-btn act-edit" title="Modifier la phase" @click="openEditPhase(phase)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <button type="button" class="act-btn act-delete" title="Supprimer la phase" @click="deleteTarget = { type: 'phase', phase }">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </div>
                    </Can>
                </header>

                <!-- Activities -->
                <div v-if="expandedPhases.includes(phase.id)" class="phase-body">
                    <div v-if="phase.activities.length === 0" class="nested-empty">
                        Aucune activité.
                        <Can permission="projects.update">
                            <button type="button" class="link-btn" @click="openCreateActivity(phase)">+ Ajouter une activité</button>
                        </Can>
                    </div>

                    <div :ref="(el: any) => bindSortable(el, 'activities')">
                        <div v-for="activity in phase.activities" :key="activity.id" :data-id="activity.id" class="activity-card" :class="{ 'overdue-card': isOverdue(activity.ended_at) }">
                            <header class="activity-header" @click="toggleActivity(activity.id)">
                                <span v-if="canManage" class="drag-handle material-symbols-outlined" title="Glisser pour réordonner" @click.stop>drag_indicator</span>
                                <span class="level-icon activity-icon">
                                    <span class="material-symbols-outlined">assignment</span>
                                </span>
                                <div class="item-main">
                                    <div class="item-title-row">
                                        <div class="title-with-badge">
                                            <h4>{{ activity.title }}</h4>
                                            <span v-if="activity.priority && priorityConfig[activity.priority]" class="priority-badge" :style="{ background: priorityConfig[activity.priority].bg, color: priorityConfig[activity.priority].color }">
                                                {{ priorityConfig[activity.priority].label }}
                                            </span>
                                        </div>
                                        <span class="date-range" :class="{ 'date-overdue': isOverdue(activity.ended_at), 'date-approaching': isApproaching(activity.ended_at) }">
                                            {{ fmt(activity.started_at) }} → {{ fmt(activity.ended_at) }}
                                        </span>
                                    </div>
                                    <p v-if="activity.description" class="muted">{{ activity.description }}</p>
                                    <div v-if="activity.resources?.length" class="resources">
                                        <span v-for="r in activity.resources" :key="r" class="resource-tag">{{ r }}</span>
                                    </div>
                                    <div class="meta-row">
                                    <div class="people">
                                        <span v-for="u in activity.assignees" :key="u.id" class="person">
                                            <span class="avatar">{{ initials(u) }}</span>
                                            {{ displayName(u) }}
                                        </span>
                                        <span v-if="creatorName(activity.creator)" class="created-by">par {{ creatorName(activity.creator) }}</span>
                                    </div>
                                        <ProgressBar :percentage="activity.progress_percentage" size="sm" />
                                    </div>
                                </div>
                                <span class="chevron material-symbols-outlined">{{ expandedActivities.includes(activity.id) ? 'expand_more' : 'chevron_right' }}</span>
                                <Can permission="projects.update">
                                    <div class="card-actions" @click.stop>
                                        <button type="button" class="act-btn act-add" title="Ajouter une tâche" @click="openCreateTask(activity)">
                                            <span class="material-symbols-outlined">add_task</span>
                                        </button>
                                        <button type="button" class="act-btn act-edit" title="Modifier l'activité" @click="openEditActivity(phase, activity)">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button type="button" class="act-btn act-delete" title="Supprimer l'activité" @click="deleteTarget = { type: 'activity', activity }">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                </Can>
                            </header>

                            <!-- Tasks -->
                            <div v-if="expandedActivities.includes(activity.id)" class="task-list">
                                <div v-if="activity.tasks.length === 0" class="nested-empty">
                                    Aucune tâche.
                                    <Can permission="projects.update"><button type="button" class="link-btn" @click="openCreateTask(activity)">+ Ajouter une tâche</button></Can>
                                </div>

                                <div :ref="(el: any) => bindSortable(el, 'tasks')">
                                    <div v-for="task in activity.tasks" :key="task.id" :data-id="task.id" class="task-row" :class="{ done: task.status === 'done', 'overdue-task': isOverdue(task.ended_at, task.status === 'done') }">
                                        <span v-if="canManage" class="drag-handle material-symbols-outlined task-drag" @click.stop>drag_indicator</span>
                                        <button type="button" class="check" :class="{ checked: task.status === 'done' }" :disabled="!canUpdateTaskStatus(task)" @click="toggleTaskDone(task)">
                                            <span class="material-symbols-outlined" style="font-size:20px">{{ task.status === 'done' ? 'check_circle' : 'radio_button_unchecked' }}</span>
                                        </button>
                                        <div class="task-main">
                                            <div class="task-title-line">
                                                <p class="task-title">{{ task.title }}</p>
                                                <span v-if="task.priority && priorityConfig[task.priority]" class="priority-badge sm" :style="{ background: priorityConfig[task.priority].bg, color: priorityConfig[task.priority].color }">
                                                    {{ priorityConfig[task.priority].label }}
                                                </span>
                                            </div>
                                            <p v-if="task.description" class="muted">{{ task.description }}</p>
                                            <div class="task-meta">
                                                <button type="button" class="status-pill" :class="`st-${task.status}`" @click="cycleStatus(task)" :disabled="!canUpdateTaskStatus(task)">
                                                    {{ taskStatusLabel[task.status] }}
                                                </button>
                                                <span class="date-range" :class="{ 'date-overdue': isOverdue(task.ended_at, task.status === 'done'), 'date-approaching': isApproaching(task.ended_at, task.status === 'done') }">
                                                    {{ fmt(task.started_at) }} → {{ fmt(task.ended_at) }}
                                                </span>
                                                <span v-for="u in task.assignees" :key="u.id" class="person">
                                                    <span class="avatar">{{ initials(u) }}</span>
                                                    @{{ displayName(u) }}
                                                </span>
                                                <span v-if="creatorName(task.creator)" class="created-by">par {{ creatorName(task.creator) }}</span>
                                            </div>
                                        </div>
                                        <Can permission="projects.update">
                                            <div class="card-actions">
                                                <button type="button" class="act-btn act-edit" title="Modifier" @click="openEditTask(activity, task)">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </button>
                                                <button type="button" class="act-btn act-delete" title="Supprimer" @click="deleteTarget = { type: 'task', task }">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            </div>
                                        </Can>
                                    </div>
                                </div>

                                <Can permission="projects.update">
                                    <button v-if="activity.tasks.length" type="button" class="add-inline" @click="openCreateTask(activity)">+ Ajouter une tâche</button>
                                </Can>
                            </div>
                        </div>
                    </div>

                    <Can permission="projects.update">
                        <button v-if="phase.activities.length" type="button" class="add-inline" @click="openCreateActivity(phase)">+ Ajouter une activité</button>
                    </Can>
                </div>
            </article>
        </div>
    </section>

    <!-- ── Modal formulaire ── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="formKind" class="modal-backdrop" @click.self="closeForm">
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-header-left">
                            <div class="modal-icon-wrap" :class="`modal-icon-${formKind}`">
                                <span class="material-symbols-outlined" style="font-size:22px">{{ modalIcon }}</span>
                            </div>
                            <div>
                                <h2 class="modal-title">{{ modalTitle }}</h2>
                                <p class="modal-subtitle">{{ phases.length }} phase(s) sur ce projet</p>
                            </div>
                        </div>
                        <button type="button" class="modal-close" @click="closeForm">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Phase form -->
                    <form v-if="formKind === 'phase'" class="modal-body" @submit.prevent="submitPhase">
                        <label class="field"><span>Nom de la phase *</span><input v-model="phaseForm.name" type="text" class="input" placeholder="Préparation et mobilisation" /><small v-if="phaseForm.errors.name" class="error">{{ phaseForm.errors.name }}</small></label>
                        <label class="field"><span>Description</span><textarea v-model="phaseForm.description" class="input" rows="3" /></label>
                        <div class="grid-2">
                            <label class="field"><span>Date de début *</span><input v-model="phaseForm.started_at" type="date" class="input" /><small v-if="phaseForm.errors.started_at" class="error">{{ phaseForm.errors.started_at }}</small></label>
                            <label class="field"><span>Date de fin *</span><input v-model="phaseForm.ended_at" type="date" class="input" /><small v-if="phaseForm.errors.ended_at" class="error">{{ phaseForm.errors.ended_at }}</small></label>
                        </div>
                        <label class="field"><span>Priorité</span>
                            <div class="priority-select">
                                <button v-for="p in priorities" :key="String(p.value)" type="button" class="priority-opt" :class="{ active: phaseForm.priority === p.value }" :style="phaseForm.priority === p.value && p.value && priorityConfig[p.value] ? { background: priorityConfig[p.value].bg, color: priorityConfig[p.value].color, borderColor: priorityConfig[p.value].color } : {}" @click="phaseForm.priority = p.value">
                                    {{ p.label }}
                                </button>
                            </div>
                        </label>
                        <label class="field"><span>Responsable(s)</span><UserMentionInput v-model="phaseForm.assignee_ids" :users="assignableUsers" :error="phaseForm.errors.assignee_ids" /></label>
                        <div class="modal-footer"><button type="button" class="btn-ghost" @click="closeForm">Annuler</button><button type="submit" class="btn-primary" :disabled="phaseForm.processing">Enregistrer</button></div>
                    </form>

                    <!-- Activity form -->
                    <form v-else-if="formKind === 'activity'" class="modal-body" @submit.prevent="submitActivity">
                        <label class="field"><span>Titre *</span><input v-model="activityForm.title" type="text" class="input" placeholder="Mobilisation des participants" /><small v-if="activityForm.errors.title" class="error">{{ activityForm.errors.title }}</small></label>
                        <label class="field"><span>Description</span><textarea v-model="activityForm.description" class="input" rows="3" /></label>
                        <div class="grid-2">
                            <label class="field"><span>Date de début *</span><input v-model="activityForm.started_at" type="date" class="input" /><small v-if="activityForm.errors.started_at" class="error">{{ activityForm.errors.started_at }}</small></label>
                            <label class="field"><span>Date de fin *</span><input v-model="activityForm.ended_at" type="date" class="input" /><small v-if="activityForm.errors.ended_at" class="error">{{ activityForm.errors.ended_at }}</small></label>
                        </div>
                        <label class="field"><span>Priorité</span>
                            <div class="priority-select">
                                <button v-for="p in priorities" :key="String(p.value)" type="button" class="priority-opt" :class="{ active: activityForm.priority === p.value }" :style="activityForm.priority === p.value && p.value && priorityConfig[p.value] ? { background: priorityConfig[p.value].bg, color: priorityConfig[p.value].color, borderColor: priorityConfig[p.value].color } : {}" @click="activityForm.priority = p.value">
                                    {{ p.label }}
                                </button>
                            </div>
                        </label>
                        <label class="field"><span>Ressources</span><TagInput v-model="activityForm.resources" :error="activityForm.errors.resources" /></label>
                        <label class="field"><span>Responsable(s)</span><UserMentionInput v-model="activityForm.assignee_ids" :users="assignableUsers" :error="activityForm.errors.assignee_ids" /></label>
                        <div class="modal-footer"><button type="button" class="btn-ghost" @click="closeForm">Annuler</button><button type="submit" class="btn-primary" :disabled="activityForm.processing">Enregistrer</button></div>
                    </form>

                    <!-- Task form -->
                    <form v-else class="modal-body" @submit.prevent="submitTask">
                        <label class="field"><span>Titre *</span><input v-model="taskForm.title" type="text" class="input" placeholder="Préparer les supports de formation" /><small v-if="taskForm.errors.title" class="error">{{ taskForm.errors.title }}</small></label>
                        <label class="field"><span>Description</span><textarea v-model="taskForm.description" class="input" rows="3" /></label>
                        <div class="grid-2">
                            <label class="field"><span>Date de début *</span><input v-model="taskForm.started_at" type="date" class="input" /><small v-if="taskForm.errors.started_at" class="error">{{ taskForm.errors.started_at }}</small></label>
                            <label class="field"><span>Date de fin *</span><input v-model="taskForm.ended_at" type="date" class="input" /><small v-if="taskForm.errors.ended_at" class="error">{{ taskForm.errors.ended_at }}</small></label>
                        </div>
                        <div class="grid-2">
                            <label v-if="editingTask" class="field"><span>Statut</span>
                                <select v-model="taskForm.status" class="input">
                                    <option value="todo">À faire</option><option value="in_progress">En cours</option><option value="done">Terminée</option>
                                </select>
                            </label>
                            <label class="field"><span>Priorité</span>
                                <div class="priority-select">
                                    <button v-for="p in priorities" :key="String(p.value)" type="button" class="priority-opt" :class="{ active: taskForm.priority === p.value }" :style="taskForm.priority === p.value && p.value && priorityConfig[p.value] ? { background: priorityConfig[p.value].bg, color: priorityConfig[p.value].color, borderColor: priorityConfig[p.value].color } : {}" @click="taskForm.priority = p.value">
                                        {{ p.label }}
                                    </button>
                                </div>
                            </label>
                        </div>
                        <label class="field"><span>Utilisateur assigné</span><UserMentionInput v-model="taskForm.assignee_ids" :users="assignableUsers" :error="taskForm.errors.assignee_ids" /></label>
                        <div class="modal-footer"><button type="button" class="btn-ghost" @click="closeForm">Annuler</button><button type="submit" class="btn-primary" :disabled="taskForm.processing">Enregistrer</button></div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <ConfirmModal :show="Boolean(deleteTarget)" :title="deleteTitle" :message="deleteMessage" :loading="deleting" @cancel="deleteTarget = null" @confirm="confirmDelete" />
</template>

<style scoped>
/* ── Layout ──────────────────────────────────────────── */
.planning { background: #fff; border: 1px solid #e8edf2; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(15,23,42,0.05); }
.planning-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid #eef1f4; }
.planning-title { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 700; color: #191c1e; }
.title-icon { font-size: 22px; color: #1F3A4D; }
.planning-count { font-size: 14px; font-weight: 500; color: #9aaabb; }
.planning-hint { margin-top: 4px; font-size: 12px; color: #9aaabb; }

/* ── Empty ───────────────────────────────────────────── */
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 52px 20px; text-align: center; }
.empty-icon { font-size: 44px; color: #d0d5db; }
.empty-title { font-size: 15px; font-weight: 700; color: #191c1e; margin: 0; }
.empty-sub { font-size: 13px; color: #9aaabb; margin: 0; }

/* ── Phase card (level 1 — Navy left border) ─────────── */
.phase-list { display: flex; flex-direction: column; gap: 12px; padding: 16px; }
.phase-card { border: 1px solid #e0e5eb; border-left: 4px solid #1F3A4D; border-radius: 12px; background: #fff; transition: box-shadow 0.15s; }
.phase-card:hover { box-shadow: 0 2px 8px rgba(31,58,77,0.08); }

/* ── Activity card (level 2 — Rose left border) ──────── */
.activity-card { border: 1px solid #eef1f4; border-left: 4px solid #E5004C; border-radius: 10px; background: #fafbfc; margin-bottom: 8px; transition: box-shadow 0.15s; }
.activity-card:hover { box-shadow: 0 2px 6px rgba(229,0,76,0.06); }

/* ── Task row (level 3 — subtle) ─────────────────────── */
.task-row { display: flex; align-items: flex-start; gap: 8px; padding: 10px 10px; border-radius: 10px; background: #fff; border: 1px solid #eef1f4; margin-bottom: 6px; transition: all 0.15s; }
.task-row:hover { border-color: #d5dee6; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.task-row.done { opacity: 0.65; }
.task-row.done .task-title { text-decoration: line-through; color: #9aaabb; }

/* ── Overdue coloring ────────────────────────────────── */
.overdue-card { border-left-color: #dc2626 !important; background: #fff5f5 !important; }
.overdue-task { border-color: #fecaca !important; background: #fff5f5 !important; }
.date-overdue { color: #dc2626 !important; font-weight: 700; }
.date-approaching { color: #d97706 !important; font-weight: 600; }

/* ── Level icons (distinct per type) ─────────────────── */
.level-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.level-icon .material-symbols-outlined { font-size: 18px; }
.phase-icon { background: rgba(31,58,77,0.1); color: #1F3A4D; }
.activity-icon { background: rgba(229,0,76,0.08); color: #E5004C; }

/* ── Headers ─────────────────────────────────────────── */
.phase-header, .activity-header { display: flex; align-items: flex-start; gap: 10px; padding: 14px 14px 12px; cursor: pointer; }
.item-main { flex: 1; min-width: 0; }
.item-title-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
.title-with-badge { display: flex; align-items: center; gap: 8px; min-width: 0; }
.item-title-row h3, .item-title-row h4 { margin: 0; font-weight: 700; color: #191c1e; }
.item-title-row h3 { font-size: 15px; }
.item-title-row h4 { font-size: 14px; }
.task-title-line { display: flex; align-items: center; gap: 6px; }
.task-title { margin: 0; font-weight: 600; font-size: 13px; color: #191c1e; }
.muted { margin: 4px 0 0; font-size: 12px; color: #6b7280; }
.muted-inline { font-size: 12px; color: #9aaabb; }
.created-by { font-size: 11px; color: #9aaabb; font-weight: 500; font-style: italic; }
.chevron { color: #9aaabb; flex-shrink: 0; margin-top: 2px; }

/* ── Meta row ────────────────────────────────────────── */
.meta-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(140px,220px); gap: 12px; align-items: center; margin-top: 10px; }
.people { display: flex; flex-wrap: wrap; gap: 6px; }
.person { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: #1F3A4D; font-weight: 600; }
.avatar { width: 20px; height: 20px; border-radius: 50%; background: #1F3A4D; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 8px; font-weight: 700; }
.date-range { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #6b7280; white-space: nowrap; }
.task-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 6px; }
.task-main { flex: 1; min-width: 0; }

/* ── Priority badge ──────────────────────────────────── */
.priority-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.priority-badge.sm { padding: 1px 5px; }

/* ── Priority selector (in modal) ────────────────────── */
.priority-select { display: flex; gap: 6px; flex-wrap: wrap; }
.priority-opt { display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border: 1.5px solid #e0e3e5; border-radius: 8px; background: #fff; font-size: 12px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all 0.12s; }
.priority-opt:hover { border-color: #adb5bd; }
.priority-opt.active { border-width: 2px; }

/* ── Action buttons (colored & distinct) ─────────────── */
.card-actions { display: flex; gap: 2px; flex-shrink: 0; }
.act-btn { width: 30px; height: 30px; border: none; background: transparent; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.12s; }
.act-btn .material-symbols-outlined { font-size: 18px; }
.act-add { color: #059669; }
.act-add:hover { background: #ecfdf5; color: #047857; }
.act-edit { color: #2563eb; }
.act-edit:hover { background: #eff6ff; color: #1d4ed8; }
.act-delete { color: #9ca3af; }
.act-delete:hover { background: #fef2f2; color: #dc2626; }

/* ── Drag & drop ─────────────────────────────────────── */
.drag-handle { color: #c9d1d9; cursor: grab; font-size: 20px; flex-shrink: 0; margin-top: 2px; transition: color 0.12s; user-select: none; }
.drag-handle:hover { color: #6b7280; }
.drag-handle:active { cursor: grabbing; }
.task-drag { font-size: 16px; margin-top: 3px; }
:deep(.drag-ghost) { opacity: 0.4; }
:deep(.drag-chosen) { box-shadow: 0 4px 16px rgba(31,58,77,0.15); }
:deep(.drag-active) { opacity: 0.9; }

/* ── Check / Status ──────────────────────────────────── */
.check { border: none; background: transparent; color: #c9d1d9; cursor: pointer; padding: 0; margin-top: 1px; transition: color 0.12s; }
.check:hover { color: #6b7280; }
.check.checked { color: #059669; }
.status-pill { border: none; border-radius: 99px; padding: 2px 10px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.12s; }
.status-pill:disabled { cursor: default; }
.st-todo { background: #f3f4f6; color: #4b5563; }
.st-in_progress { background: #fef3c7; color: #92400e; }
.st-done { background: #d1fae5; color: #065f46; }

/* ── Resources ───────────────────────────────────────── */
.resources { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.resource-tag { padding: 2px 8px; border-radius: 99px; background: #eef4f8; color: #1F3A4D; font-size: 11px; font-weight: 600; }

/* ── Nested / inline add ─────────────────────────────── */
.phase-body { padding: 0 14px 14px 52px; display: flex; flex-direction: column; gap: 10px; }
.task-list { padding: 0 12px 12px 48px; display: flex; flex-direction: column; gap: 2px; }
.nested-empty { font-size: 13px; color: #9aaabb; padding: 6px 0; }
.link-btn, .add-inline { border: none; background: transparent; color: #E5004C; font-size: 13px; font-weight: 600; cursor: pointer; padding: 0; }
.add-inline { align-self: flex-start; padding: 4px 0 2px; }

/* ── Buttons ─────────────────────────────────────────── */
.btn-add-phase { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #E5004C; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.btn-add-phase:hover { background: #c0003e; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #1F3A4D; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #162c3b; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost { padding: 9px 18px; border: 1px solid #e0e3e5; background: #fff; border-radius: 8px; color: #515f74; font-weight: 600; cursor: pointer; font-size: 13px; }

/* ── Modal ───────────────────────────────────────────── */
.modal-backdrop { position: fixed; inset: 0; z-index: 80; background: rgba(15,23,42,0.45); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { width: 100%; max-width: 580px; background: #fff; border-radius: 18px; max-height: 86vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 64px rgba(0,0,0,0.18); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 22px 24px 18px; border-bottom: 1px solid #f0f2f5; }
.modal-header-left { display: flex; align-items: center; gap: 14px; }
.modal-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.modal-icon-phase { background: rgba(31,58,77,0.1); color: #1F3A4D; }
.modal-icon-activity { background: rgba(229,0,76,0.08); color: #E5004C; }
.modal-icon-task { background: #d1fae5; color: #059669; }
.modal-title { margin: 0; font-size: 18px; font-weight: 700; color: #191c1e; }
.modal-subtitle { margin: 4px 0 0; font-size: 12px; color: #9aaabb; }
.modal-close { width: 36px; height: 36px; border: none; border-radius: 50%; background: #f2f4f6; cursor: pointer; color: #515f74; display: flex; align-items: center; justify-content: center; }
.modal-body { padding: 22px 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding-top: 8px; }
.field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #1F3A4D; }
.input { width: 100%; border: 1.5px solid #e0e3e5; border-radius: 8px; padding: 9px 10px; font-size: 13px; font-weight: 500; color: #191c1e; }
.input:focus { outline: none; border-color: #1F3A4D; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.error { color: #E5004C; font-weight: 500; }

/* ── Transitions ─────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 767px) {
    .planning-head, .meta-row, .grid-2 { display: flex; flex-direction: column; }
    .phase-body { padding-left: 16px; }
    .task-list { padding-left: 16px; }
    .priority-select { gap: 4px; }
}
</style>
