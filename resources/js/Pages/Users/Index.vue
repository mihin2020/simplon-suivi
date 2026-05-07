<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Permission {
    id: number
    name: string
    slug: string
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
}

interface Paginated {
    data: UserItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    meta: { from: number; to: number; total: number }
}

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    users: Paginated
    filters: { search?: string; role?: string }
    roles: RoleOption[]
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

const destroy = (u: UserItem) => {
    if (confirm(`Supprimer l'utilisateur « ${u.first_name} ${u.last_name} » ?`)) {
        router.delete(`/users/${u.id}`)
    }
}

const toggleActive = (u: UserItem) => {
    const action = u.is_active ? 'désactiver' : 'activer'
    if (confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} l'utilisateur « ${u.first_name} ${u.last_name} » ?`)) {
        router.patch(`/users/${u.id}/toggle-active`)
    }
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Utilisateurs</h1>
                <p class="text-body-md text-secondary mt-xs">
                    {{ users.meta?.total ?? 0 }} utilisateur(s) enregistré(s).
                </p>
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
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Permissions</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-surface-bright transition-colors group"
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
                                <div v-if="user.role === 'super_admin'" class="text-body-sm text-on-surface-variant">Toutes</div>
                                <div v-else-if="user.permissions.length === 0" class="text-body-sm text-on-surface-variant">—</div>
                                <div v-else class="flex flex-wrap gap-xs">
                                    <span
                                        v-for="p in user.permissions.slice(0, 3)"
                                        :key="p.id"
                                        class="perm-tag"
                                    >{{ p.name }}</span>
                                    <span v-if="user.permissions.length > 3" class="perm-tag perm-more">
                                        +{{ user.permissions.length - 3 }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-md py-sm">
                                <span class="active-badge" :class="user.is_active ? 'badge-active' : 'badge-inactive'">
                                    {{ user.is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/users/${user.id}/edit`" class="icon-btn" title="Modifier">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button
                                        v-if="user.role !== 'super_admin'"
                                        @click="toggleActive(user)"
                                        class="icon-btn"
                                        :class="user.is_active ? 'danger' : 'activate'"
                                        :title="user.is_active ? 'Désactiver' : 'Activer'"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:18px">
                                            {{ user.is_active ? 'block' : 'check_circle' }}
                                        </span>
                                    </button>
                                    <button
                                        v-if="user.role !== 'super_admin'"
                                        @click="destroy(user)"
                                        class="icon-btn danger"
                                        title="Supprimer"
                                    >
                                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-md py-sm border-t border-surface-container-highest bg-surface-bright flex items-center justify-between">
                <span class="text-body-sm text-on-surface-variant">
                    {{ users.meta?.from }}–{{ users.meta?.to }} sur {{ users.meta?.total }} utilisateurs
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
</template>

<style scoped>
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
</style>
