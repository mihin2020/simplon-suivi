<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Profile { id: string; name: string }
interface EducationLevel { id: string; name: string }

const props = defineProps<{
    trainerProfiles: Profile[]
    educationLevels: EducationLevel[]
}>()

const createForm = useForm({ name: '' })
const editingId  = ref<string | null>(null)
const editForm   = useForm({ name: '' })

const startEdit = (p: Profile) => {
    editingId.value = p.id
    editForm.name   = p.name
}
const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
}
const submitCreate = () => {
    createForm.post('/trainer-profiles', { onSuccess: () => createForm.reset() })
}
const submitEdit = (id: string) => {
    editForm.put(`/trainer-profiles/${id}`, { onSuccess: () => cancelEdit() })
}
const destroy = (p: Profile) => {
    if (confirm(`Supprimer le profil « ${p.name} » ?`)) {
        router.delete(`/trainer-profiles/${p.id}`)
    }
}

// ── Education Levels ──
const eduCreateForm = useForm({ name: '' })
const eduEditingId = ref<string | null>(null)
const eduEditForm = useForm({ name: '' })
const showEduErrorModal = ref(false)
const eduErrorMessage = ref('')

const startEduEdit = (e: EducationLevel) => {
    eduEditingId.value = e.id
    eduEditForm.name = e.name
}
const cancelEduEdit = () => {
    eduEditingId.value = null
    eduEditForm.reset()
}
const submitEduCreate = () => {
    eduCreateForm.post('/education-levels', { onSuccess: () => eduCreateForm.reset() })
}
const submitEduEdit = (id: string) => {
    eduEditForm.put(`/education-levels/${id}`, { onSuccess: () => cancelEduEdit() })
}
const destroyEdu = (e: EducationLevel) => {
    if (confirm(`Supprimer le niveau d'études « ${e.name} » ?`)) {
        router.delete(`/education-levels/${e.id}`, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                if (errors.message) {
                    eduErrorMessage.value = errors.message
                    showEduErrorModal.value = true
                }
            }
        })
    }
}
const closeEduErrorModal = () => {
    showEduErrorModal.value = false
    eduErrorMessage.value = ''
}
</script>

<template>
    <div class="max-w-4xl mx-auto space-y-xl">

        <!-- En-tête -->
        <div>
            <h1 class="text-h1 font-bold text-on-surface">Configuration</h1>
            <p class="text-body-md text-secondary mt-xs">Paramètres globaux de l'application.</p>
        </div>

        <!-- ── Section : Profils Formateurs ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">person_badge</span>
                    <div>
                        <h2 class="config-section-title">Profils Formateurs</h2>
                        <p class="config-section-sub">Types de formateurs disponibles lors de l'invitation.</p>
                    </div>
                </div>
                <span class="count-pill">{{ trainerProfiles.length }}</span>
            </div>

            <div class="config-section-body">
                <!-- Formulaire ajout -->
                <form @submit.prevent="submitCreate" class="add-form">
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': createForm.errors.name }"
                        placeholder="Ex : Formateur principal, Vacataire..."
                        autofocus
                    />
                    <button type="submit" class="btn-add" :disabled="createForm.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter
                    </button>
                </form>
                <p v-if="createForm.errors.name" class="error-msg">{{ createForm.errors.name }}</p>

                <!-- Liste -->
                <div v-if="trainerProfiles.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#ddd">person_badge</span>
                    <p class="text-body-sm text-secondary mt-xs">Aucun profil. Ajoutez-en un ci-dessus.</p>
                </div>

                <ul v-else class="item-list">
                    <li v-for="p in trainerProfiles" :key="p.id" class="item-row">
                        <form v-if="editingId === p.id" @submit.prevent="submitEdit(p.id)" class="flex gap-sm flex-1">
                            <input v-model="editForm.name" type="text" class="input flex-1"
                                :class="{ 'input-error': editForm.errors.name }" />
                            <button type="submit" class="btn-icon-ok">
                                <span class="material-symbols-outlined" style="font-size:18px">check</span>
                            </button>
                            <button type="button" class="btn-icon-cancel" @click="cancelEdit">
                                <span class="material-symbols-outlined" style="font-size:18px">close</span>
                            </button>
                        </form>
                        <template v-else>
                            <div class="flex items-center gap-sm flex-1">
                                <span class="material-symbols-outlined" style="font-size:16px;color:#adb5bd">label</span>
                                <span class="item-name">{{ p.name }}</span>
                            </div>
                            <div class="flex gap-xs">
                                <button class="icon-btn" title="Modifier" @click="startEdit(p)">
                                    <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                </button>
                                <button class="icon-btn danger" title="Supprimer" @click="destroy(p)">
                                    <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                </button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Section : Niveaux d'études ── -->
        <div class="config-section">
            <div class="config-section-header">
                <div class="flex items-center gap-sm">
                    <span class="section-icon material-symbols-outlined">school</span>
                    <div>
                        <h2 class="config-section-title">Niveaux d'études</h2>
                        <p class="config-section-sub">Niveaux d'études disponibles pour les apprenants.</p>
                    </div>
                </div>
                <span class="count-pill">{{ educationLevels.length }}</span>
            </div>

            <div class="config-section-body">
                <!-- Formulaire ajout -->
                <form @submit.prevent="submitEduCreate" class="add-form">
                    <input
                        v-model="eduCreateForm.name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': eduCreateForm.errors.name }"
                        placeholder="Ex : Bac+2, Bac+3, Master..."
                    />
                    <button type="submit" class="btn-add" :disabled="eduCreateForm.processing">
                        <span class="material-symbols-outlined" style="font-size:18px">add</span>
                        Ajouter
                    </button>
                </form>
                <p v-if="eduCreateForm.errors.name" class="error-msg">{{ eduCreateForm.errors.name }}</p>

                <!-- Liste -->
                <div v-if="educationLevels.length === 0" class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#ddd">school</span>
                    <p class="text-body-sm text-secondary mt-xs">Aucun niveau d'études. Ajoutez-en un ci-dessus.</p>
                </div>

                <ul v-else class="item-list">
                    <li v-for="e in educationLevels" :key="e.id" class="item-row">
                        <form v-if="eduEditingId === e.id" @submit.prevent="submitEduEdit(e.id)" class="flex gap-sm flex-1">
                            <input v-model="eduEditForm.name" type="text" class="input flex-1"
                                :class="{ 'input-error': eduEditForm.errors.name }" />
                            <button type="submit" class="btn-icon-ok">
                                <span class="material-symbols-outlined" style="font-size:18px">check</span>
                            </button>
                            <button type="button" class="btn-icon-cancel" @click="cancelEduEdit">
                                <span class="material-symbols-outlined" style="font-size:18px">close</span>
                            </button>
                        </form>
                        <template v-else>
                            <div class="flex items-center gap-sm flex-1">
                                <span class="material-symbols-outlined" style="font-size:16px;color:#adb5bd">label</span>
                                <span class="item-name">{{ e.name }}</span>
                            </div>
                            <div class="flex gap-xs">
                                <button class="icon-btn" title="Modifier" @click="startEduEdit(e)">
                                    <span class="material-symbols-outlined" style="font-size:17px">edit</span>
                                </button>
                                <button class="icon-btn danger" title="Supprimer" @click="destroyEdu(e)">
                                    <span class="material-symbols-outlined" style="font-size:17px">delete</span>
                                </button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Modal d'erreur pour suppression impossible -->
        <div v-if="showEduErrorModal" class="modal-overlay" @click="closeEduErrorModal">
            <div class="modal-content" @click.stop>
                <div class="modal-header">
                    <span class="material-symbols-outlined modal-icon">error</span>
                    <h3 class="modal-title">Suppression impossible</h3>
                </div>
                <div class="modal-body">
                    <p class="modal-text">{{ eduErrorMessage }}</p>
                </div>
                <div class="modal-footer">
                    <button @click="closeEduErrorModal" class="btn-close">Fermer</button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Section card */
.config-section {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    overflow: hidden;
}

.config-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
    background: #fafbfc;
}

.section-icon {
    font-size: 22px;
    color: #1F3A4D;
}

.config-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #191c1e;
}

.config-section-sub {
    font-size: 12px;
    color: #9aaabb;
    margin-top: 2px;
}

.count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    background: #eceef0;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}

.config-section-body {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Add form */
.add-form {
    display: flex;
    gap: 8px;
}

.input {
    padding: 9px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    flex: 1;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 9px 16px;
    background: #E5004C;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
}
.btn-add:hover:not(:disabled) { background: #c40042; }
.btn-add:disabled { opacity: 0.6; cursor: not-allowed; }

/* List */
.item-list {
    display: flex;
    flex-direction: column;
    border: 1px solid #f0f1f3;
    border-radius: 8px;
    overflow: hidden;
}

.item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid #f0f1f3;
    transition: background 0.12s;
}
.item-row:last-child { border-bottom: none; }
.item-row:hover { background: #fafbfc; }

.item-name {
    font-size: 14px;
    color: #191c1e;
}

.btn-icon-ok {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 7px;
    background: #dcfce7; color: #166534; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-icon-ok:hover { background: #bbf7d0; }

.btn-icon-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 7px;
    background: #f3f4f6; color: #6b7280; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-icon-cancel:hover { background: #e5e7eb; }

.icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 5px; border-radius: 6px; background: none; border: none;
    color: #9aaabb; cursor: pointer; transition: color 0.15s, background 0.15s;
}
.icon-btn:hover { color: #E5004C; background: #fdf0f4; }
.icon-btn.danger:hover { color: #ba1a1a; background: #fff5f5; }

.empty-state {
    display: flex; flex-direction: column; align-items: center;
    padding: 24px; text-align: center;
}

.gap-xs { gap: 4px; }
.gap-sm { gap: 8px; }
.mt-xs  { margin-top: 4px; }

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px;
    border-bottom: 1px solid #f0f1f3;
}
.modal-icon {
    font-size: 24px;
    color: #dc2626;
}
.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #191c1e;
    margin: 0;
}
.modal-body {
    padding: 20px 24px;
}
.modal-text {
    font-size: 14px;
    color: #515f74;
    margin: 0;
    line-height: 1.5;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding: 12px 24px 20px;
}
.btn-close {
    padding: 8px 16px;
    background: #1F3A4D;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-close:hover { background: #17303f; }
</style>
