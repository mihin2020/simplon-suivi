<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

interface ContractTypeItem {
    id: string
    name: string
}

const props = defineProps<{
    visible: boolean
    context: 'internship' | 'employment'
    types: ContractTypeItem[]
    canManage: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const title = props.context === 'internship' ? 'Types de contrat de stage' : 'Types de contrat d\'emploi'

const createForm = useForm({ name: '', context: props.context })
const editingId = ref<string | null>(null)
const editForm = useForm({ name: '' })
const errorMessage = ref<string | null>(null)

const startEdit = (item: ContractTypeItem) => {
    editingId.value = item.id
    editForm.name = item.name
    errorMessage.value = null
}

const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
}

const submitCreate = () => {
    errorMessage.value = null
    createForm.context = props.context
    createForm.post('/contract-types', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset()
            createForm.context = props.context
        },
        onError: (errors) => {
            errorMessage.value = errors.name ?? errors.message ?? 'Impossible d\'ajouter ce type.'
        },
    })
}

const submitEdit = (id: string) => {
    errorMessage.value = null
    editForm.put(`/contract-types/${id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
        onError: (errors) => {
            errorMessage.value = errors.name ?? errors.message ?? 'Impossible de modifier ce type.'
        },
    })
}

const destroy = (item: ContractTypeItem) => {
    if (!confirm(`Supprimer « ${item.name} » ?`)) return
    errorMessage.value = null
    router.delete(`/contract-types/${item.id}`, {
        preserveScroll: true,
        onError: (errors) => {
            errorMessage.value = errors.message ?? 'Impossible de supprimer ce type.'
        },
    })
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="visible" class="modal-overlay" @click.self="emit('close')">
                <div class="modal-box">
                    <div class="modal-head">
                        <div>
                            <h3 class="modal-title">{{ title }}</h3>
                            <p class="modal-sub">Ajoutez ou retirez les types disponibles dans les formulaires.</p>
                        </div>
                        <button type="button" class="modal-close" @click="emit('close')">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <p v-if="errorMessage" class="modal-error">{{ errorMessage }}</p>

                    <form v-if="canManage" @submit.prevent="submitCreate" class="add-row">
                        <input
                            v-model="createForm.name"
                            type="text"
                            class="add-input"
                            :class="{ 'input-error': createForm.errors.name }"
                            placeholder="Nouveau type de contrat..."
                        />
                        <button type="submit" class="add-btn" :disabled="createForm.processing || !createForm.name.trim()">
                            Ajouter
                        </button>
                    </form>
                    <p v-else class="modal-hint">
                        Vous n'avez pas la permission de modifier les types.
                        <a href="/configuration" class="link">Configuration</a>
                    </p>

                    <ul class="type-list">
                        <li v-for="item in types" :key="item.id" class="type-item">
                            <form v-if="editingId === item.id && canManage" @submit.prevent="submitEdit(item.id)" class="edit-row">
                                <input v-model="editForm.name" type="text" class="add-input" autofocus />
                                <button type="submit" class="btn-ok"><span class="material-symbols-outlined" style="font-size:17px">check</span></button>
                                <button type="button" class="btn-cancel" @click="cancelEdit"><span class="material-symbols-outlined" style="font-size:17px">close</span></button>
                            </form>
                            <template v-else>
                                <span class="type-name">{{ item.name }}</span>
                                <div v-if="canManage" class="type-actions">
                                    <button type="button" class="ref-btn" title="Modifier" @click="startEdit(item)">
                                        <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                                    </button>
                                    <button type="button" class="ref-btn danger" title="Supprimer" @click="destroy(item)">
                                        <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                                    </button>
                                </div>
                            </template>
                        </li>
                    </ul>

                    <div v-if="types.length === 0" class="empty-inline">Aucun type configuré.</div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 1100; padding: 20px;
}
.modal-box {
    background: #fff; border-radius: 14px; width: 100%; max-width: 480px;
    padding: 24px; box-shadow: 0 24px 48px rgba(0,0,0,0.18);
    display: flex; flex-direction: column; gap: 14px; max-height: 80vh; overflow-y: auto;
}
.modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.modal-title { font-size: 17px; font-weight: 700; color: #191c1e; margin: 0; }
.modal-sub { font-size: 12px; color: #515f74; margin-top: 4px; }
.modal-close {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; background: #f2f4f6;
    border-radius: 8px; cursor: pointer; color: #515f74;
}
.modal-error { font-size: 12px; color: #ba1a1a; margin: 0; }
.modal-hint { font-size: 12px; color: #515f74; margin: 0; }
.link { color: #E5004C; font-weight: 600; text-decoration: underline; }

.add-row { display: flex; gap: 8px; }
.add-input {
    flex: 1; padding: 9px 12px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 13px; outline: none;
}
.add-input:focus { border-color: #E5004C; }
.input-error { border-color: #ba1a1a !important; }
.add-btn {
    padding: 9px 14px; background: #E5004C; color: #fff; border: none;
    border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.add-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.type-list { list-style: none; margin: 0; padding: 0; border: 1px solid #f0f2f5; border-radius: 8px; overflow: hidden; }
.type-item {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px;
    border-bottom: 1px solid #f0f2f5;
}
.type-item:last-child { border-bottom: none; }
.type-name { flex: 1; font-size: 13px; font-weight: 500; color: #191c1e; }
.type-actions { display: flex; gap: 4px; }
.edit-row { display: flex; align-items: center; gap: 6px; flex: 1; }

.ref-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none;
    background: transparent; color: #9aaabb; cursor: pointer;
}
.ref-btn:hover { background: #f2f4f6; color: #E5004C; }
.ref-btn.danger:hover { background: #ffdad6; color: #ba1a1a; }

.btn-ok, .btn-cancel {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 7px; border: none; cursor: pointer;
}
.btn-ok { background: #d1fae5; color: #065f46; }
.btn-cancel { background: #f2f4f6; color: #515f74; }

.empty-inline { font-size: 12px; color: #9aaabb; text-align: center; padding: 8px; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
