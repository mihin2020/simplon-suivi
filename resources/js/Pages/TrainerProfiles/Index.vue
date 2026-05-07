<script setup lang="ts">
import { useForm, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }

defineProps<{ profiles: Profile[] }>()

const createForm = useForm({ name: '' })
const editingId = ref<string | null>(null)
const editForm = useForm({ name: '' })

const startEdit = (p: Profile) => {
    editingId.value = p.id
    editForm.name = p.name
}

const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
}

const submitCreate = () => {
    createForm.post('/trainer-profiles', {
        onSuccess: () => createForm.reset(),
    })
}

const submitEdit = (id: string) => {
    editForm.put(`/trainer-profiles/${id}`, {
        onSuccess: () => cancelEdit(),
    })
}

const destroy = (p: Profile) => {
    if (confirm(`Supprimer le profil « ${p.name} » ?`)) {
        router.delete(`/trainer-profiles/${p.id}`)
    }
}
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center gap-md">
            <Link href="/trainers" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Profils Formateurs</h1>
                <p class="text-body-md text-secondary mt-xs">Configurez les profils disponibles à l'invitation.</p>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <h2 class="section-title">Ajouter un profil</h2>
            <form @submit.prevent="submitCreate" class="flex gap-sm mt-md">
                <input
                    v-model="createForm.name"
                    type="text"
                    class="input flex-1"
                    :class="{ 'input-error': createForm.errors.name }"
                    placeholder="Ex : Formateur principal, Formateur vacataire..."
                    autofocus
                />
                <button type="submit" class="btn-primary" :disabled="createForm.processing">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Ajouter
                </button>
            </form>
            <p v-if="createForm.errors.name" class="error-msg mt-xs">{{ createForm.errors.name }}</p>
        </div>

        <!-- Liste -->
        <div class="card">
            <h2 class="section-title">{{ profiles.length }} profil(s) configuré(s)</h2>

            <div v-if="profiles.length === 0" class="empty-state">
                <span class="material-symbols-outlined" style="font-size:36px;color:#ddd">person_badge</span>
                <p class="text-body-md text-secondary mt-sm">Aucun profil. Ajoutez-en un ci-dessus.</p>
            </div>

            <ul v-else class="profile-list mt-md">
                <li v-for="p in profiles" :key="p.id" class="profile-item">

                    <!-- Mode édition -->
                    <form v-if="editingId === p.id" @submit.prevent="submitEdit(p.id)" class="flex gap-sm flex-1">
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="input flex-1"
                            :class="{ 'input-error': editForm.errors.name }"
                        />
                        <button type="submit" class="btn-save">
                            <span class="material-symbols-outlined" style="font-size:18px">check</span>
                        </button>
                        <button type="button" class="btn-cancel" @click="cancelEdit">
                            <span class="material-symbols-outlined" style="font-size:18px">close</span>
                        </button>
                    </form>

                    <!-- Mode lecture -->
                    <template v-else>
                        <div class="profile-name-wrap">
                            <span class="material-symbols-outlined profile-icon">person_badge</span>
                            <span class="profile-name">{{ p.name }}</span>
                        </div>
                        <div class="profile-actions">
                            <button class="icon-btn" title="Modifier" @click="startEdit(p)">
                                <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                            </button>
                            <button class="icon-btn danger" title="Supprimer" @click="destroy(p)">
                                <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                            </button>
                        </div>
                    </template>

                </li>
            </ul>
        </div>

    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    color: #515f74; transition: background 0.15s; flex-shrink: 0;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 28px; }
.section-title { font-size: 14px; font-weight: 700; color: #191c1e; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s; outline: none;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; transition: background 0.2s; white-space: nowrap;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-save {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 8px;
    background: #d1fae5; color: #065f46; border: none; cursor: pointer; transition: background 0.15s;
}
.btn-save:hover { background: #a7f3d0; }

.btn-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 8px;
    background: #f3f4f6; color: #6b7280; border: none; cursor: pointer; transition: background 0.15s;
}
.btn-cancel:hover { background: #e5e7eb; }

.profile-list { display: flex; flex-direction: column; gap: 2px; }
.profile-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px; border-radius: 8px; transition: background 0.12s;
}
.profile-item:hover { background: #fafbfc; }

.profile-name-wrap { display: flex; align-items: center; gap: 10px; }
.profile-icon { font-size: 18px; color: #adb5bd; }
.profile-name { font-size: 14px; font-weight: 500; color: #191c1e; }

.profile-actions { display: flex; gap: 4px; }
.icon-btn {
    padding: 5px; color: #515f74; border-radius: 6px;
    transition: color 0.15s, background 0.15s;
    display: inline-flex; background: none; border: none; cursor: pointer;
}
.icon-btn:hover { color: #E5004C; background: #fdf0f4; }
.icon-btn.danger:hover { color: #ba1a1a; background: #fff5f5; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 32px; text-align: center; }
</style>
