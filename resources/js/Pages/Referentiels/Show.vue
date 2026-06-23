<script setup lang="ts">
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Can from '@/Components/Can.vue'

defineOptions({ layout: AdminLayout })

interface Competence {
    id: string
    name: string
    order: number
}

interface Block {
    id: string
    name: string
    order: number
    competences: Competence[]
}

interface Formation {
    id: string
    name: string
    project: { id: string; name: string }
}

interface Referentiel {
    id: string
    name: string
    description: string | null
    blocks: Block[]
    formations: Formation[]
}

const props = defineProps<{
    referentiel: Referentiel
}>()

// ─── Edit state ──────────────────────────────────────────────────────────────
const editing = ref(false)

interface EditBlock { id?: string; name: string; competences: EditComp[] }
interface EditComp  { id?: string; name: string }

const buildState = () => ({
    name:        props.referentiel.name,
    description: props.referentiel.description ?? '',
    blocks: props.referentiel.blocks.map(b => ({
        id: b.id, name: b.name,
        competences: b.competences.map(c => ({ id: c.id, name: c.name })),
    })),
})

const editState = reactive(buildState())

const addBlock = () => editState.blocks.push({ name: '', competences: [] })
const removeBlock = (i: number) => editState.blocks.splice(i, 1)
const addComp = (block: EditBlock) => block.competences.push({ name: '' })
const removeComp = (block: EditBlock, i: number) => block.competences.splice(i, 1)

const saveForm = useForm({})
const save = () => {
    saveForm
        .transform(() => ({
            name:        editState.name,
            description: editState.description,
            blocks:      editState.blocks,
        }))
        .put(`/referentiels/${props.referentiel.id}`, {
            onSuccess: () => { editing.value = false },
        })
}

const showDeleteModal = ref(false)

const confirmDelete = () => {
    router.delete(`/referentiels/${props.referentiel.id}`, {
        onFinish: () => { showDeleteModal.value = false },
    })
}
</script>

<template>
    <Head :title="referentiel.name" />
    <div class="max-w-[1200px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <Link href="/referentiels" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">{{ referentiel.name }}</h1>
                    <p v-if="referentiel.description" class="text-body-md text-secondary mt-xs">{{ referentiel.description }}</p>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <Can permission="referentiels.update">
                    <button v-if="!editing" @click="editing = true" class="btn-navy">
                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                        Modifier
                    </button>
                </Can>
                <Can permission="referentiels.delete">
                    <button v-if="!editing" @click="showDeleteModal = true" class="btn-danger">
                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                        Supprimer
                    </button>
                </Can>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-xl">

            <!-- Formations qui utilisent ce référentiel -->
            <div class="lg:col-span-1">
                <div class="card">
                    <h2 class="section-title">Formations liées</h2>
                    <div v-if="referentiel.formations.length === 0" class="text-body-sm text-secondary py-sm">
                        Aucune formation n'utilise ce référentiel.
                    </div>
                    <ul v-else class="formation-list">
                        <li v-for="f in referentiel.formations" :key="f.id">
                            <Link :href="`/formations/${f.id}`" class="formation-link">
                                <span class="font-medium text-on-surface">{{ f.name }}</span>
                                <span class="text-body-sm text-secondary">{{ f.project.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Blocs et compétences -->
            <div class="lg:col-span-3">

                <!-- Mode lecture -->
                <template v-if="!editing">
                    <div v-if="referentiel.blocks.length === 0" class="empty-blocks">
                        <span class="material-symbols-outlined" style="font-size:40px; color:#e0e3e5">checklist</span>
                        <p class="text-secondary text-body-md mt-md">Aucun bloc de compétences défini.</p>
                        <Can permission="referentiels.update">
                            <button @click="editing = true" class="btn-secondary mt-md">
                                <span class="material-symbols-outlined" style="font-size:16px">add</span>
                                Ajouter des blocs
                            </button>
                        </Can>
                    </div>

                    <div v-else class="space-y-md">
                        <div v-for="(block, bi) in referentiel.blocks" :key="block.id" class="block-card">
                            <div class="block-header">
                                <div class="block-number">{{ String(bi + 1).padStart(2, '0') }}</div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-on-surface">{{ block.name }}</h3>
                                </div>
                                <span class="count-badge">{{ block.competences.length }} compétence(s)</span>
                            </div>
                            <ul v-if="block.competences.length > 0" class="competence-list">
                                <li v-for="(comp, ci) in block.competences" :key="comp.id" class="competence-item">
                                    <span class="comp-number">{{ bi + 1 }}.{{ ci + 1 }}</span>
                                    <div>
                                        <p class="font-medium text-on-surface">{{ comp.name }}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>

                <!-- Mode édition -->
                <template v-else>
                    <div class="space-y-lg">
                        <div class="card space-y-md">
                            <div class="field">
                                <label class="label">Nom du référentiel <span class="required">*</span></label>
                                <input v-model="editState.name" type="text" class="input" />
                            </div>
                            <div class="field">
                                <label class="label">Description</label>
                                <textarea v-model="editState.description" class="input" rows="2" />
                            </div>
                        </div>

                        <div v-for="(block, bi) in editState.blocks" :key="bi" class="block-edit-card">
                            <div class="flex items-start justify-between gap-md mb-md">
                                <div class="flex-1 space-y-sm">
                                    <div class="flex items-center gap-sm">
                                        <span class="block-number-edit">Bloc {{ bi + 1 }}</span>
                                        <input v-model="block.name" type="text" class="input flex-1" placeholder="Nom du bloc" />
                                    </div>
                                </div>
                                <button type="button" @click="removeBlock(bi)" class="icon-btn danger">
                                    <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                </button>
                            </div>

                            <div class="competences-edit">
                                <div v-for="(comp, ci) in block.competences" :key="ci" class="comp-edit-row">
                                    <span class="comp-idx">{{ bi + 1 }}.{{ ci + 1 }}</span>
                                    <input v-model="comp.name" type="text" class="input flex-1" placeholder="Nom de la compétence" />
                                    <button type="button" @click="removeComp(block, ci)" class="icon-btn danger">
                                        <span class="material-symbols-outlined" style="font-size:16px">close</span>
                                    </button>
                                </div>
                                <button type="button" @click="addComp(block)" class="add-comp-btn">
                                    <span class="material-symbols-outlined" style="font-size:16px">add</span>
                                    Ajouter une compétence
                                </button>
                            </div>
                        </div>

                        <button type="button" @click="addBlock" class="add-block-btn">
                            <span class="material-symbols-outlined" style="font-size:20px">add_circle</span>
                            Ajouter un bloc de compétences
                        </button>

                        <div class="flex justify-end gap-sm">
                            <button type="button" @click="editing = false" class="btn-secondary">Annuler</button>
                            <button type="button" @click="save" class="btn-primary" :disabled="saveForm.processing">
                                <span v-if="saveForm.processing" class="spinner" />
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </div>

    </div>

    <!-- Modal de confirmation de suppression -->
    <Teleport to="body">
        <div v-if="showDeleteModal" class="modal-backdrop" @click.self="showDeleteModal = false">
            <div class="modal-box">
                <div class="modal-icon">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#E5004C">delete</span>
                </div>
                <h3 class="modal-title">Supprimer le référentiel</h3>
                <p class="modal-body">
                    Voulez-vous vraiment supprimer <strong>« {{ referentiel.name }} »</strong> ?
                    Cette action est irréversible.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" @click="showDeleteModal = false">Annuler</button>
                    <button class="btn-confirm-danger" @click="confirmDelete">Supprimer</button>
                </div>
            </div>
        </div>
    </Teleport>

</template>

<style scoped>
.icon-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1.5px solid #1F3A4D;
    color: #1F3A4D;
    background: transparent;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
    text-decoration: none;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }

.section-title {
    font-size: 11px; font-weight: 700; color: #515f74;
    text-transform: uppercase; letter-spacing: 0.06em;
    margin-bottom: 14px;
}

.formation-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.formation-link {
    display: flex; flex-direction: column; padding: 8px 10px;
    border-radius: 8px; text-decoration: none;
    transition: background 0.15s;
}
.formation-link:hover { background: #f2f4f6; }

.empty-blocks {
    display: flex; flex-direction: column; align-items: center;
    padding: 48px 24px; background: #fff;
    border: 1px solid #e0e3e5; border-radius: 12px; text-align: center;
}

.block-card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; overflow: hidden; }
.block-header { display: flex; align-items: flex-start; gap: 16px; padding: 20px 24px; border-bottom: 1px solid #f2f4f6; }
.block-number {
    width: 36px; height: 36px; border-radius: 8px;
    background: #E5004C; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.count-badge {
    padding: 3px 10px; background: #f2f4f6; border-radius: 99px;
    font-size: 11px; font-weight: 600; color: #515f74; white-space: nowrap;
}

.competence-list { list-style: none; padding: 0; margin: 0; }
.competence-item {
    display: flex; align-items: flex-start; gap: 16px;
    padding: 12px 24px; border-bottom: 1px solid #f9fafb;
}
.competence-item:last-child { border-bottom: none; }
.comp-number { font-size: 12px; font-weight: 700; color: #E5004C; min-width: 28px; padding-top: 2px; }

.block-edit-card { background: #fff; border: 1.5px solid #e0e3e5; border-radius: 12px; padding: 20px; }
.block-number-edit {
    font-size: 11px; font-weight: 700; color: #E5004C;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; padding-top: 11px;
}
.competences-edit { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f2f4f6; display: flex; flex-direction: column; gap: 8px; }
.comp-edit-row { display: flex; align-items: center; gap: 8px; }
.comp-idx { font-size: 11px; font-weight: 700; color: #9aaabb; min-width: 24px; }

.add-comp-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 12px; border: 1px dashed #e0e3e5; border-radius: 6px;
    font-size: 12px; color: #515f74; cursor: pointer; background: transparent;
    transition: all 0.15s; width: fit-content;
}
.add-comp-btn:hover { border-color: #E5004C; color: #E5004C; }

.add-block-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 16px; border: 2px dashed #e0e3e5; border-radius: 12px;
    font-size: 14px; font-weight: 600; color: #515f74;
    cursor: pointer; background: transparent; transition: all 0.15s;
}
.add-block-btn:hover { border-color: #E5004C; color: #E5004C; background: #fff0f4; }

.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
.required { color: #E5004C; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s; outline: none;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
textarea.input { resize: vertical; width: 100%; }

.icon-btn { padding: 4px; color: #515f74; border-radius: 4px; transition: color 0.15s; display: inline-flex; background: transparent; border: none; cursor: pointer; }
.icon-btn.danger:hover { color: #ba1a1a; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 24px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    transition: background 0.2s; border: none; cursor: pointer;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-navy {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #1F3A4D;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none; cursor: pointer;
}
.btn-navy:hover { background: #1F3A4D; color: #fff; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #1F3A4D;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none; cursor: pointer;
}
.btn-secondary:hover { background: #1F3A4D; color: #fff; }

.btn-danger {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #ba1a1a;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #fca5a5; transition: background 0.15s, color 0.15s; cursor: pointer;
}
.btn-danger:hover { background: #ba1a1a; color: #fff; border-color: #ba1a1a; }

/* Modal */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    background: #fff; border-radius: 16px; padding: 32px 28px;
    width: 100%; max-width: 400px; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-icon { margin-bottom: 12px; }
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; margin-bottom: 8px; }
.modal-body { font-size: 14px; color: #515f74; line-height: 1.6; margin-bottom: 24px; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.btn-cancel {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    border: 1.5px solid #d0d3d5; color: #515f74; background: #fff; cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: #f4f5f6; }
.btn-confirm-danger {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    background: #E5004C; color: #fff; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-confirm-danger:hover { background: #c0003e; }

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
