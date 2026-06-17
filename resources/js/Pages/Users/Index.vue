<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Permission {
    id: number
    name: string
    slug: string
}

interface Project {
    id: string
    name: string
}

interface Formation {
    id: string
    name: string
    period: string
    project: Project
}

interface TrainerData {
    id: string
    formations: Formation[]
}

interface UserItem {
    id: string
    first_name: string
    last_name: string
    email: string
    role: string
    role_label: string
    is_active: boolean
    permissions: Permission[]
    trainer: TrainerData | null
}

interface Paginated {
    data: UserItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
    from: number | null
    to: number | null
}

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    users: Paginated
    filters: { search?: string; role?: string }
    roles: RoleOption[]
    projects: Project[]
}>()

const search = ref(props.filters.search ?? '')
const selectedRole = ref(props.filters.role ?? '')

let timer: ReturnType<typeof setTimeout>
function reload() {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/users', { search: search.value, role: selectedRole.value }, { preserveState: true, replace: true })
    }, 400)
}
watch(search, reload)
watch(selectedRole, reload)

// Modal suppression
const deleteTarget = ref<UserItem | null>(null)
const confirmDelete = () => {
    if (!deleteTarget.value) return
    router.delete(`/users/${deleteTarget.value.id}`, {
        onFinish: () => { deleteTarget.value = null },
    })
}

// Modal désactivation / activation
const toggleTarget = ref<UserItem | null>(null)
const confirmToggle = () => {
    if (!toggleTarget.value) return
    router.patch(`/users/${toggleTarget.value.id}/toggle-active`, {}, {
        onFinish: () => { toggleTarget.value = null },
    })
}

const unassign = (user: UserItem, formation: Formation) => {
    if (!user.trainer) return
    if (confirm(`Retirer ${user.first_name} ${user.last_name} de la formation "${formation.name}" ?`)) {
        router.delete(`/trainers/${user.trainer.id}/unassign-formation/${formation.id}`)
    }
}

// Modal d'assignation à une formation (formateurs)
const showAssignModal = ref(false)
const selectedUser = ref<UserItem | null>(null)
const selectedProject = ref<string>('')
const availableFormations = ref<Formation[]>([])
const loadingFormations = ref(false)

const assignForm = useForm({
    formation_ids: [] as string[],
})

const openAssignModal = (user: UserItem) => {
    selectedUser.value = user
    selectedProject.value = ''
    availableFormations.value = []
    assignForm.reset()
    assignForm.clearErrors()
    showAssignModal.value = true
}

const closeAssignModal = () => {
    showAssignModal.value = false
    selectedUser.value = null
    selectedProject.value = ''
    availableFormations.value = []
    assignForm.reset()
}

const onProjectChange = async () => {
    if (!selectedProject.value) {
        availableFormations.value = []
        assignForm.formation_ids = []
        return
    }

    loadingFormations.value = true
    try {
        const response = await fetch(`/api/projects/${selectedProject.value}/formations`)
        if (response.ok) {
            availableFormations.value = await response.json()

            const assignedIds = selectedUser.value?.trainer?.formations.map(f => f.id) ?? []
            assignForm.formation_ids = availableFormations.value
                .filter(f => assignedIds.includes(f.id))
                .map(f => f.id)
        }
    } catch (error) {
        console.error('Erreur lors du chargement des formations:', error)
    } finally {
        loadingFormations.value = false
    }
}

const isAlreadyAssigned = (formationId: string) => {
    return selectedUser.value?.trainer?.formations.some(f => f.id === formationId) ?? false
}

const submitAssign = () => {
    if (!selectedUser.value?.trainer) return

    assignForm.post(`/trainers/${selectedUser.value.trainer.id}/assign-formation`, {
        preserveState: true,
        onSuccess: () => {
            closeAssignModal()
        },
    })
}

const canSubmitAssign = computed(() => {
    return assignForm.formation_ids.length > 0 && !assignForm.processing
})

const toggleFormation = (formationId: string) => {
    const index = assignForm.formation_ids.indexOf(formationId)
    if (index === -1) {
        assignForm.formation_ids.push(formationId)
    } else {
        assignForm.formation_ids.splice(index, 1)
    }
}

const isFormationSelected = (formationId: string) => {
    return assignForm.formation_ids.includes(formationId)
}

// Panneau latéral de détail (drawer)
const drawerUser = ref<UserItem | null>(null)
const openDrawer = (user: UserItem) => { drawerUser.value = user }
const closeDrawer = () => { drawerUser.value = null }

// Garde le drawer synchronisé avec les données fraîches après un reload Inertia
watch(() => props.users, () => {
    if (!drawerUser.value) return
    const fresh = props.users.data.find(u => u.id === drawerUser.value!.id)
    drawerUser.value = fresh ?? null
})
</script>

<template>
    <Head title="Utilisateurs" />
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined">group</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Utilisateurs</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        {{ users.total ?? 0 }} utilisateur(s) enregistré(s).
                    </p>
                </div>
            </div>
            <Link href="/users/create" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                Inviter un Utilisateur
            </Link>
        </div>

        <!-- Barre de recherche + filtres -->
        <div class="search-bar">
            <div class="search-input-wrapper">
                <span class="material-symbols-outlined search-icon">search</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Rechercher par nom, prénom, email..."
                    class="search-input"
                />
                <button
                    v-if="search"
                    @click="search = ''"
                    class="search-clear"
                    title="Effacer"
                >
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                </button>
            </div>
            <select v-model="selectedRole" class="filter-select">
                <option value="">Tous les rôles</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <span v-if="search || selectedRole" class="search-hint">
                {{ users.data.length }} résultat(s)
            </span>
        </div>

        <!-- Tableau -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Utilisateur</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Email</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Rôle</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="row-clickable hover:bg-surface-bright transition-colors group"
                            @click="openDrawer(user)"
                        >
                            <td class="px-md py-sm">
                                <div class="flex items-center gap-sm">
                                    <div class="avatar">
                                        {{ user.first_name.charAt(0) }}{{ user.last_name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-on-surface">
                                            {{ user.last_name }} {{ user.first_name }}
                                        </div>
                                        <div v-if="user.role === 'super_admin'" class="text-body-sm text-primary font-medium">
                                            Compte principal
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant">{{ user.email }}</td>
                            <td class="px-md py-sm">
                                <span class="role-badge" :class="'role-' + user.role">
                                    {{ user.role_label }}
                                </span>
                            </td>
                            <td class="px-md py-sm">
                                <span class="active-badge" :class="user.is_active ? 'badge-active' : 'badge-inactive'">
                                    {{ user.is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <span class="material-symbols-outlined row-chevron" style="font-size:20px">chevron_right</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-md py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ users.from }}–{{ users.to }} sur {{ users.total }} utilisateurs
                </span>
                <div class="flex items-center gap-xs">
                    <template v-for="link in users.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                        <span v-else class="page-btn page-disabled" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>

    </div>

    <!-- Drawer de détail utilisateur -->
    <Teleport to="body">
        <Transition name="drawer-fade">
            <div v-if="drawerUser" class="drawer-backdrop" @click.self="closeDrawer">
                <Transition name="drawer-slide">
                    <div v-if="drawerUser" class="drawer">
                        <div class="drawer-header">
                            <div class="flex items-center gap-sm">
                                <div class="avatar avatar-lg">
                                    {{ drawerUser.first_name.charAt(0) }}{{ drawerUser.last_name.charAt(0) }}
                                </div>
                                <div>
                                    <h3 class="drawer-name">{{ drawerUser.last_name }} {{ drawerUser.first_name }}</h3>
                                    <span class="role-badge" :class="'role-' + drawerUser.role">{{ drawerUser.role_label }}</span>
                                </div>
                            </div>
                            <button @click="closeDrawer" class="modal-close" title="Fermer">
                                <span class="material-symbols-outlined" style="font-size:20px">close</span>
                            </button>
                        </div>

                        <div class="drawer-body">
                            <!-- Informations -->
                            <div class="drawer-section">
                                <h4 class="drawer-section-title">Informations</h4>
                                <div class="drawer-info-row">
                                    <span class="drawer-info-label">Email</span>
                                    <span class="drawer-info-value">{{ drawerUser.email }}</span>
                                </div>
                                <div class="drawer-info-row">
                                    <span class="drawer-info-label">Statut</span>
                                    <span class="active-badge" :class="drawerUser.is_active ? 'badge-active' : 'badge-inactive'">
                                        {{ drawerUser.is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Permissions (admin / super admin) -->
                            <div v-if="drawerUser.role !== 'trainer'" class="drawer-section">
                                <h4 class="drawer-section-title">Permissions</h4>
                                <div v-if="drawerUser.role === 'super_admin'" class="text-body-sm text-on-surface-variant">
                                    Toutes les permissions.
                                </div>
                                <div v-else-if="drawerUser.permissions.length === 0" class="text-body-sm text-on-surface-variant">
                                    Aucune permission accordée.
                                </div>
                                <div v-else class="flex flex-wrap gap-xs">
                                    <span v-for="p in drawerUser.permissions" :key="p.id" class="perm-tag">{{ p.name }}</span>
                                </div>
                            </div>

                            <!-- Formations (formateur) -->
                            <div v-else class="drawer-section">
                                <div class="flex items-center justify-between mb-sm">
                                    <h4 class="drawer-section-title mb-0">Formations assignées</h4>
                                    <button v-if="drawerUser.trainer" @click="openAssignModal(drawerUser)" class="drawer-assign-btn">
                                        <span class="material-symbols-outlined" style="font-size:14px">add</span>
                                        Assigner
                                    </button>
                                </div>
                                <div v-if="!drawerUser.trainer || drawerUser.trainer.formations.length === 0" class="text-body-sm text-on-surface-variant">
                                    Aucune formation assignée.
                                </div>
                                <div v-else class="flex flex-col gap-xs">
                                    <div
                                        v-for="formation in drawerUser.trainer.formations"
                                        :key="formation.id"
                                        class="drawer-formation-row"
                                    >
                                        <div>
                                            <div class="formation-name">{{ formation.name }}</div>
                                            <div class="formation-project">{{ formation.project.name }}</div>
                                        </div>
                                        <button
                                            @click="unassign(drawerUser, formation)"
                                            class="formation-remove"
                                            title="Retirer de cette formation"
                                        >
                                            <span class="material-symbols-outlined" style="font-size:14px">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="drawer-footer">
                            <Link :href="`/users/${drawerUser.id}/edit`" class="drawer-action-btn">
                                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                                Modifier
                            </Link>
                            <Link v-if="drawerUser.role === 'trainer' && drawerUser.trainer" :href="`/trainers/${drawerUser.trainer.id}`" class="drawer-action-btn">
                                <span class="material-symbols-outlined" style="font-size:16px">visibility</span>
                                Voir le profil
                            </Link>
                            <button
                                v-if="drawerUser.role !== 'super_admin'"
                                @click="toggleTarget = drawerUser"
                                class="drawer-action-btn"
                                :class="drawerUser.is_active ? 'warn' : 'ok'"
                            >
                                <span class="material-symbols-outlined" style="font-size:16px">
                                    {{ drawerUser.is_active ? 'block' : 'check_circle' }}
                                </span>
                                {{ drawerUser.is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                            <button
                                v-if="drawerUser.role !== 'super_admin'"
                                @click="deleteTarget = drawerUser"
                                class="drawer-action-btn danger"
                            >
                                <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                                Supprimer
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Modal désactivation / activation -->
    <Teleport to="body">
        <div v-if="toggleTarget" class="modal-overlay" @click.self="toggleTarget = null">
            <div class="modal-box">
                <div class="modal-head">
                    <div class="modal-icon" :class="toggleTarget.is_active ? 'icon-warn' : 'icon-ok'">
                        <span class="material-symbols-outlined" style="font-size:20px">
                            {{ toggleTarget.is_active ? 'block' : 'check_circle' }}
                        </span>
                    </div>
                    <div>
                        <p class="modal-title">
                            {{ toggleTarget.is_active ? 'Désactiver' : 'Activer' }} l'utilisateur
                        </p>
                        <p class="modal-name">{{ toggleTarget.last_name }} {{ toggleTarget.first_name }}</p>
                    </div>
                </div>
                <p class="modal-msg" v-if="toggleTarget.is_active">
                    Cet utilisateur ne pourra plus se connecter. Vous pourrez le réactiver à tout moment.
                </p>
                <p class="modal-msg" v-else>
                    Cet utilisateur pourra à nouveau se connecter à la plateforme.
                </p>
                <div class="modal-foot">
                    <button class="btn-cancel" @click="toggleTarget = null">Annuler</button>
                    <button
                        class="btn-confirm"
                        :class="toggleTarget.is_active ? 'btn-confirm-warn' : 'btn-confirm-ok'"
                        @click="confirmToggle"
                    >
                        {{ toggleTarget.is_active ? 'Désactiver' : 'Activer' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal suppression -->
    <Teleport to="body">
        <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
            <div class="modal-box">
                <div class="modal-head">
                    <div class="modal-icon icon-del">
                        <span class="material-symbols-outlined" style="font-size:20px">delete</span>
                    </div>
                    <div>
                        <p class="modal-title">Supprimer l'utilisateur</p>
                        <p class="modal-name">{{ deleteTarget.last_name }} {{ deleteTarget.first_name }}</p>
                    </div>
                </div>
                <p class="modal-msg">
                    Cette action est irréversible. L'utilisateur sera définitivement supprimé.
                </p>
                <div class="modal-foot">
                    <button class="btn-cancel" @click="deleteTarget = null">Annuler</button>
                    <button class="btn-confirm btn-confirm-del" @click="confirmDelete">Supprimer</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal d'assignation à une formation -->
    <Teleport to="body">
        <div v-if="showAssignModal" class="modal-backdrop" @click.self="closeAssignModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title-assign">
                        <span class="material-symbols-outlined" style="font-size:20px; color:#E5004C">assignment_ind</span>
                        Assigner à une formation
                    </h3>
                    <p v-if="selectedUser" class="modal-subtitle">
                        {{ selectedUser.last_name }} {{ selectedUser.first_name }}
                    </p>
                    <button @click="closeAssignModal" class="modal-close" title="Fermer">
                        <span class="material-symbols-outlined" style="font-size:20px">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitAssign" class="modal-body">
                    <!-- Projet -->
                    <div class="field">
                        <label class="label">Projet <span class="required">*</span></label>
                        <select
                            v-model="selectedProject"
                            class="input"
                            @change="onProjectChange"
                            :disabled="loadingFormations"
                        >
                            <option value="">Sélectionner un projet</option>
                            <option v-for="p in props.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <!-- Formations (sélection multiple) -->
                    <div class="field">
                        <label class="label">Formations <span class="required">*</span></label>
                        <div v-if="!selectedProject" class="formation-placeholder">
                            Sélectionnez d'abord un projet
                        </div>
                        <div v-else-if="loadingFormations" class="formation-placeholder">
                            Chargement...
                        </div>
                        <div v-else-if="availableFormations.length === 0" class="formation-placeholder">
                            Aucune formation disponible
                        </div>
                        <div v-else class="formation-list">
                            <label
                                v-for="f in availableFormations"
                                :key="f.id"
                                class="formation-checkbox"
                                :class="{ 'formation-selected': isFormationSelected(f.id) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="f.id"
                                    :checked="isFormationSelected(f.id)"
                                    @change="toggleFormation(f.id)"
                                    class="sr-only"
                                />
                                <span class="formation-check">
                                    <span v-if="isFormationSelected(f.id)" class="material-symbols-outlined" style="font-size:14px">check</span>
                                </span>
                                <span class="formation-info">
                                    <span class="formation-info-name">{{ f.name }}</span>
                                    <span class="formation-info-period">{{ f.period }}</span>
                                </span>
                                <span v-if="isAlreadyAssigned(f.id)" class="already-assigned-badge">
                                    <span class="material-symbols-outlined" style="font-size:12px">check_circle</span>
                                    Déjà assignée
                                </span>
                            </label>
                        </div>
                        <p v-if="assignForm.errors.formation_ids" class="error-msg">{{ assignForm.errors.formation_ids }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="modal-actions">
                        <button type="button" @click="closeAssignModal" class="btn-secondary-assign">
                            Annuler
                        </button>
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="!canSubmitAssign"
                        >
                            <span v-if="assignForm.processing" class="spinner-small" />
                            <span v-else class="material-symbols-outlined" style="font-size:16px">add</span>
                            Assigner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

</template>

<style scoped>
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #1F3A4D;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    flex-shrink: 0;
    text-transform: uppercase;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.role-super_admin { background: #ede9fe; color: #5b21b6; }
.role-admin       { background: #dbeafe; color: #1e40af; }
.role-trainer     { background: #d1fae5; color: #065f46; }

.active-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.badge-active   { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #fee2e2; color: #991b1b; }

.perm-tag {
    display: inline-flex;
    padding: 2px 8px;
    background: #f3f4f6;
    border-radius: 4px;
    font-size: 11px;
    color: #374151;
}
.perm-more {
    background: #e0e3e5;
    color: #6b7280;
    font-weight: 600;
}

.icon-btn {
    padding: 4px;
    color: #515f74;
    border-radius: 4px;
    transition: color 0.15s;
    display: inline-flex;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }
.icon-btn.activate:hover { color: #059669; }

.search-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.search-input-wrapper {
    position: relative;
    flex: 1;
    max-width: 400px;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: #9aaabb;
    font-size: 20px;
    pointer-events: none;
}
.search-input {
    width: 100%;
    padding: 10px 40px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    transition: all 0.15s ease;
    outline: none;
}
.search-input:focus {
    background: #fff;
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.search-input::placeholder {
    color: #9aaabb;
}
.search-clear {
    position: absolute;
    right: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #e0e3e5;
    color: #515f74;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.search-clear:hover {
    background: #d1d5db;
    color: #191c1e;
}
.search-hint {
    font-size: 13px;
    color: #9aaabb;
    font-weight: 500;
}

.filter-select {
    padding: 10px 36px 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    cursor: pointer;
    outline: none;
}
.filter-select:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 6px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: #191c1e;
    transition: background 0.15s;
    cursor: pointer;
    text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }

/* ── Boutons icône tableau ── */
.icon-btn { padding: 4px; border-radius: 4px; display: inline-flex; background: none; border: none; cursor: pointer; color: #515f74; transition: color 0.15s; }
.btn-deactivate:hover { color: #b45309; }
.btn-activate:hover   { color: #059669; }
.btn-del:hover        { color: #ba1a1a; }

/* ── Modales ── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.modal-box {
    background: #fff; border-radius: 14px; padding: 28px 28px 22px;
    width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.18);
}
.modal-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.modal-icon {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.icon-warn { background: #e8edf2; color: #1F3A4D; }
.icon-ok   { background: #f0fdf4; color: #059669; }
.icon-del  { background: #fff0f4; color: #E5004C; }
.modal-title { font-size: 15px; font-weight: 700; color: #191c1e; }
.modal-name  { font-size: 12px; color: #9aaabb; margin-top: 2px; }
.modal-msg   { font-size: 13px; color: #515f74; line-height: 1.5; margin-bottom: 20px; }
.modal-foot  { display: flex; justify-content: flex-end; gap: 10px; }
.btn-cancel {
    padding: 8px 18px; background: #f2f4f6; color: #515f74;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-cancel:hover { background: #e0e3e5; }
.btn-confirm {
    padding: 8px 18px; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-confirm-warn { background: #1F3A4D; }
.btn-confirm-warn:hover { background: #2d5a7b; }
.btn-confirm-ok   { background: #059669; }
.btn-confirm-ok:hover   { background: #047857; }
.btn-confirm-del  { background: #E5004C; }
.btn-confirm-del:hover  { background: #c0003e; }

/* ── Badges formations (colonne Permissions/Formations) ── */
.formation-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 3px 6px 3px 10px; background: #f3f4f6; border-radius: 99px;
    font-size: 11px;
}
.formation-project { color: #9aaabb; font-weight: 500; }
.formation-name { color: #191c1e; font-weight: 600; }
.formation-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; border-radius: 50%;
    background: #e0e3e5; color: #515f74; border: none; cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.formation-remove:hover { background: #ba1a1a; color: #fff; }

.icon-btn.assign:hover { color: #1F3A4D; }

/* ── Modale d'assignation à une formation ── */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
    padding: 16px;
}
.modal-content {
    background: #fff; border-radius: 14px;
    width: 100%; max-width: 480px; max-height: 90vh;
    display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    overflow: hidden;
}
.modal-header {
    position: relative; padding: 20px 24px 14px; border-bottom: 1px solid #e0e3e5;
}
.modal-title-assign {
    display: flex; align-items: center; gap: 8px;
    font-size: 16px; font-weight: 700; color: #191c1e;
}
.modal-subtitle { font-size: 12px; color: #9aaabb; margin-top: 4px; }
.modal-close {
    position: absolute; top: 16px; right: 16px;
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    background: none; border: none; cursor: pointer; color: #9aaabb;
    transition: background 0.15s, color 0.15s;
}
.modal-close:hover { background: #f2f4f6; color: #191c1e; }
.modal-body { padding: 20px 24px; overflow-y: auto; }
.modal-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    margin-top: 20px; padding-top: 16px; border-top: 1px solid #e0e3e5;
}

.field { margin-bottom: 16px; }
.field:last-child { margin-bottom: 0; }
.label { display: block; font-size: 12px; font-weight: 600; color: #515f74; margin-bottom: 6px; }
.required { color: #E5004C; }
.input {
    width: 100%; padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; color: #191c1e; background: #fafafa; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.input:focus { background: #fff; border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.error-msg { font-size: 11px; color: #ba1a1a; margin-top: 4px; }

.formation-placeholder {
    padding: 18px; text-align: center; font-size: 12px; color: #9aaabb;
    background: #fafbfc; border: 1px dashed #e0e3e5; border-radius: 8px;
}
.formation-list {
    display: flex; flex-direction: column; gap: 6px;
    max-height: 240px; overflow-y: auto;
}
.formation-checkbox {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border: 1px solid #e0e3e5; border-radius: 8px;
    cursor: pointer; transition: border-color 0.15s, background 0.15s;
}
.formation-checkbox:hover { border-color: #E5004C; }
.formation-selected { border-color: #E5004C; background: #fff0f4; }
.formation-check {
    flex-shrink: 0; width: 18px; height: 18px; border-radius: 4px;
    border: 1.5px solid #d1d5db; background: #fff;
    display: flex; align-items: center; justify-content: center; color: #fff;
}
.formation-selected .formation-check { background: #E5004C; border-color: #E5004C; }
.formation-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.formation-info-name { font-size: 13px; font-weight: 600; color: #191c1e; }
.formation-info-period { font-size: 11px; color: #9aaabb; }
.already-assigned-badge {
    display: inline-flex; align-items: center; gap: 3px; flex-shrink: 0;
    padding: 2px 8px; background: #d1fae5; color: #065f46;
    border-radius: 99px; font-size: 10px; font-weight: 600; white-space: nowrap;
}

.btn-secondary-assign {
    padding: 9px 18px; background: #f2f4f6; color: #515f74;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
    transition: background 0.15s;
}
.btn-secondary-assign:hover { background: #e0e3e5; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px; background: #E5004C; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
    transition: background 0.15s;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner-small {
    width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

/* ── Ligne cliquable ── */
.row-clickable { cursor: pointer; }
.row-chevron { color: #c7ced6; transition: color 0.15s, transform 0.15s; }
.row-clickable:hover .row-chevron { color: #E5004C; transform: translateX(2px); }

/* ── Drawer de détail utilisateur ── */
.drawer-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    z-index: 9000; display: flex; justify-content: flex-end;
}
.drawer {
    width: 100%; max-width: 420px; height: 100%; background: #fff;
    box-shadow: -8px 0 30px rgba(0,0,0,0.12);
    display: flex; flex-direction: column;
}
.drawer-header {
    position: relative; display: flex; align-items: center; justify-content: space-between;
    padding: 20px 24px; border-bottom: 1px solid #e0e3e5; flex-shrink: 0;
}
.avatar-lg { width: 44px; height: 44px; font-size: 16px; }
.drawer-name { font-size: 16px; font-weight: 700; color: #191c1e; margin-bottom: 4px; }
.drawer-body { flex: 1; overflow-y: auto; padding: 20px 24px; }
.drawer-section { margin-bottom: 24px; }
.drawer-section:last-child { margin-bottom: 0; }
.drawer-section-title {
    font-size: 11px; font-weight: 700; color: #9aaabb; text-transform: uppercase;
    letter-spacing: 0.06em; margin-bottom: 12px;
}
.drawer-info-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px solid #f2f4f6;
}
.drawer-info-row:last-child { border-bottom: none; }
.drawer-info-label { font-size: 12px; color: #9aaabb; }
.drawer-info-value { font-size: 13px; color: #191c1e; font-weight: 500; }

.drawer-assign-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; background: #fff0f4; color: #E5004C;
    border: none; border-radius: 99px; font-size: 11px; font-weight: 600; cursor: pointer;
    transition: background 0.15s;
}
.drawer-assign-btn:hover { background: #ffd9e4; }

.drawer-formation-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 12px; background: #fafbfc; border: 1px solid #e0e3e5; border-radius: 8px;
}

.drawer-footer {
    display: flex; flex-wrap: wrap; gap: 8px;
    padding: 16px 24px; border-top: 1px solid #e0e3e5; flex-shrink: 0;
}
.drawer-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; background: #f2f4f6; color: #191c1e;
    border: none; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: background 0.15s;
}
.drawer-action-btn:hover { background: #e0e3e5; }
.drawer-action-btn.warn { background: #fef3c7; color: #92400e; }
.drawer-action-btn.warn:hover { background: #fde68a; }
.drawer-action-btn.ok { background: #d1fae5; color: #065f46; }
.drawer-action-btn.ok:hover { background: #a7f3d0; }
.drawer-action-btn.danger { background: #fee2e2; color: #991b1b; }
.drawer-action-btn.danger:hover { background: #fecaca; }

/* Transitions */
.drawer-fade-enter-active, .drawer-fade-leave-active { transition: opacity 0.2s ease; }
.drawer-fade-enter-from, .drawer-fade-leave-to { opacity: 0; }

.drawer-slide-enter-active, .drawer-slide-leave-active { transition: transform 0.25s ease; }
.drawer-slide-enter-from, .drawer-slide-leave-to { transform: translateX(100%); }
</style>
