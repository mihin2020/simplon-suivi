<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ContractsTabs from '@/Components/Contracts/ContractsTabs.vue'
import Can from '@/Components/Can.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import { projectDeletionMessage } from '@/Components/Contracts/projectDeletion'

defineOptions({ layout: AdminLayout })

interface Project {
    id: string
    name: string
    description: string | null
    target_label: string
    contracts_count: number
    created_at: string | null
}
interface Paginated {
    data: Project[]
    links: Array<{ url: string | null; label: string; active: boolean }>
}

defineProps<{
    projects: Paginated
    targets: Array<{ value: string; label: string }>
}>()

const dialogOpen = ref(false)
const form = useForm({ name: '', description: '', target: '' })

const openDialog = () => {
    form.reset()
    form.clearErrors()
    dialogOpen.value = true
}

const submit = () => form.post('/contracts/projects')

// ── Suppression (le projet part avec tous ses contrats) ───────────────────
const toDelete = ref<Project | null>(null)
const deleting = ref(false)
const destroy = () => {
    if (!toDelete.value) return
    router.delete(`/contracts/projects/${toDelete.value.id}`, {
        preserveScroll: true,
        onStart: () => { deleting.value = true },
        onFinish: () => { deleting.value = false; toDelete.value = null },
    })
}
</script>

<template>
    <Head title="Projets de contrats" />
    <div class="wrap">
        <ContractsTabs />

        <div class="head">
            <div>
                <h1 class="title">Projets de contrats</h1>
                <p class="subtitle">
                    Un projet regroupe les contrats d'une même opération : vous rédigez le contrat une fois,
                    puis vous le générez pour chaque personne concernée.
                </p>
            </div>
            <Can permission="contracts.create">
                <button type="button" class="btn-primary" @click="openDialog">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Nouveau projet
                </button>
            </Can>
        </div>

        <div v-if="projects.data.length === 0" class="empty">
            Aucun projet. Créez-en un pour préparer une série de contrats.
        </div>

        <div v-else class="cards">
            <div v-for="p in projects.data" :key="p.id" class="card-wrap">
                <Link :href="`/contracts/projects/${p.id}`" prefetch class="card">
                    <div class="card-top">
                        <span class="material-symbols-outlined card-ico">folder_open</span>
                        <span class="pill">{{ p.target_label }}</span>
                    </div>
                    <p class="card-name">{{ p.name }}</p>
                    <p class="card-desc">{{ p.description || 'Sans description' }}</p>
                    <p class="card-meta">{{ p.contracts_count }} contrat{{ p.contracts_count > 1 ? 's' : '' }}</p>
                </Link>
                <Can permission="contracts.delete">
                    <button type="button" class="card-delete" title="Supprimer le projet et ses contrats" @click="toDelete = p">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </Can>
            </div>
        </div>

        <ConfirmModal
            :show="toDelete !== null"
            title="Supprimer le projet et ses contrats"
            :message="toDelete ? projectDeletionMessage(toDelete.name, toDelete.contracts_count) : ''"
            :confirm-label="toDelete && toDelete.contracts_count > 0 ? 'Tout supprimer' : 'Supprimer le projet'"
            :loading="deleting"
            @confirm="destroy"
            @cancel="toDelete = null"
        />

        <div v-if="projects.links.length > 3" class="pagination">
            <template v-for="link in projects.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="page-link" :class="{ active: link.active }"><span v-html="link.label" /></Link>
            </template>
        </div>

        <div v-if="dialogOpen" class="overlay" @click.self="dialogOpen = false">
            <form class="dialog" @submit.prevent="submit">
                <h3 class="dialog-title">Nouveau projet de contrats</h3>

                <div class="field">
                    <label class="label">Nom du projet <span class="req">*</span></label>
                    <input v-model="form.name" type="text" class="input" maxlength="255" placeholder="Ex : Formateurs cohorte Dev Web 2026" />
                    <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
                </div>

                <div class="field">
                    <label class="label">Type de contrat <span class="req">*</span></label>
                    <select v-model="form.target" class="input">
                        <option value="" disabled>Choisir…</option>
                        <option v-for="t in targets" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <p class="hint">Tous les contrats du projet sont de ce type ; il ne peut plus être changé ensuite.</p>
                    <p v-if="form.errors.target" class="error-msg">{{ form.errors.target }}</p>
                </div>

                <div class="field">
                    <label class="label">Description</label>
                    <textarea v-model="form.description" rows="3" class="input" maxlength="2000" placeholder="À quoi servent ces contrats, pour qui, sur quelle période…" />
                    <p v-if="form.errors.description" class="error-msg">{{ form.errors.description }}</p>
                </div>

                <div class="dialog-actions">
                    <button type="button" class="btn-secondary" :disabled="form.processing" @click="dialogOpen = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !form.name.trim() || !form.target">
                        {{ form.processing ? 'Création…' : 'Créer le projet' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.wrap { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 14px; color: #515f74; margin-top: 2px; max-width: 620px; }
.empty { padding: 36px; text-align: center; font-size: 14px; color: #9aaabb; background: #fff; border: 1px dashed #d9e0e7; border-radius: 14px; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.card-wrap { position: relative; display: flex; }
.card-wrap .card { flex: 1; }
.card-delete { position: absolute; right: 12px; bottom: 12px; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent; background: transparent; color: #9aaabb; cursor: pointer; }
.card-delete:hover { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
.card-delete .material-symbols-outlined { font-size: 19px; }
.card { display: flex; flex-direction: column; gap: 6px; padding: 18px; background: #fff; border: 1px solid #e8edf2; border-radius: 14px; text-decoration: none; transition: border-color 0.15s, box-shadow 0.15s; }
.card:hover { border-color: #E5004C; box-shadow: 0 4px 14px rgba(229, 0, 76, 0.08); }
.card-top { display: flex; align-items: center; justify-content: space-between; }
.card-ico { font-size: 26px; color: #E5004C; }
.pill { padding: 3px 10px; border-radius: 99px; background: #eef1f4; color: #1F3A4D; font-size: 11px; font-weight: 700; }
.card-name { font-size: 15px; font-weight: 700; color: #191c1e; }
.card-desc { font-size: 13px; color: #515f74; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.card-meta { font-size: 12px; font-weight: 600; color: #868e96; margin-top: 4px; }
.pagination { display: flex; gap: 4px; justify-content: center; }
.page-link { padding: 6px 12px; border-radius: 8px; font-size: 13px; color: #515f74; text-decoration: none; border: 1px solid #e8edf2; background: #fff; }
.page-link.active { background: #E5004C; border-color: #E5004C; color: #fff; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 10px 18px; background: #fff; color: #515f74; border-radius: 10px; font-size: 14px; border: 1.5px solid #e0e3e5; cursor: pointer; }
.overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 50; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
.dialog { background: #fff; border-radius: 16px; padding: 24px; width: 100%; max-width: 520px; display: flex; flex-direction: column; gap: 16px; }
.dialog-title { font-size: 18px; font-weight: 700; color: #191c1e; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 700; color: #191c1e; letter-spacing: 0.04em; text-transform: uppercase; }
.req { color: #E5004C; }
.input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; font-size: 14px; background: #fafbfc; outline: none; font-family: inherit; }
.input:focus { border-color: #E5004C; background: #fff; }
.hint { font-size: 12px; color: #9aaabb; }
.error-msg { font-size: 13px; color: #ba1a1a; }
</style>
