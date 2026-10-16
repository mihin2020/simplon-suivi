<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ContractsTabs from '@/Components/Contracts/ContractsTabs.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineOptions({ layout: AdminLayout })

interface Letterhead {
    id: string
    name: string
    header_url: string | null
    header_height_mm: number | null
    footer_url: string | null
    footer_height_mm: number | null
}
interface Paginated {
    data: Letterhead[]
    links: Array<{ url: string | null; label: string; active: boolean }>
}

defineProps<{
    letterheads: Paginated
    maxHeights: { header: number; footer: number }
}>()

// ── Création / modification (nom + import des deux images) ───────────────
const editing = ref<Letterhead | null>(null)
const dialogOpen = ref(false)
const form = useForm<{ name: string; header_image: File | null; footer_image: File | null }>({
    name: '',
    header_image: null,
    footer_image: null,
})
const previews = ref<{ header: string | null; footer: string | null }>({ header: null, footer: null })

const openDialog = (letterhead: Letterhead | null = null) => {
    editing.value = letterhead
    form.reset()
    form.clearErrors()
    form.name = letterhead?.name ?? ''
    previews.value = { header: letterhead?.header_url ?? null, footer: letterhead?.footer_url ?? null }
    dialogOpen.value = true
}

const closeDialog = () => { dialogOpen.value = false }

const dragging = ref<'header' | 'footer' | null>(null)

const setFile = (side: 'header' | 'footer', file: File | null) => {
    if (!file) return
    form[`${side}_image`] = file
    previews.value[side] = URL.createObjectURL(file)
}

const onFile = (side: 'header' | 'footer', event: Event) => {
    const input = event.target as HTMLInputElement
    setFile(side, input.files?.[0] ?? null)
    input.value = ''
}

const onDrop = (side: 'header' | 'footer', event: DragEvent) => {
    dragging.value = null
    setFile(side, event.dataTransfer?.files?.[0] ?? null)
}

const submit = () => {
    const options = { forceFormData: true, preserveScroll: true, onSuccess: closeDialog }

    if (editing.value) {
        // Files cannot travel in a real PUT: spoof the method over POST.
        form.transform(data => ({ ...data, _method: 'put' })).post(`/contracts/layouts/${editing.value.id}`, options)
    } else {
        form.transform(data => data).post('/contracts/layouts', options)
    }
}

const toDelete = ref<Letterhead | null>(null)
const deleting = ref(false)
const destroy = () => {
    if (!toDelete.value) return
    router.delete(`/contracts/layouts/${toDelete.value.id}`, {
        preserveScroll: true,
        onStart: () => { deleting.value = true },
        onFinish: () => { deleting.value = false; toDelete.value = null },
    })
}
</script>

<template>
    <Head title="En-têtes et pieds de page" />
    <div class="wrap">
        <ContractsTabs />

        <div class="head">
            <div>
                <h1 class="title">En-têtes & pieds de page</h1>
                <p class="subtitle">
                    Créez un habillage nommé (ex. « Contrat de prestation ») et importez-y votre en-tête et votre pied de page.
                    Vous le lierez ensuite à un contrat.
                </p>
            </div>
            <div class="head-actions">
                <button type="button" class="btn-primary" @click="openDialog()">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    Nouvel habillage
                </button>
            </div>
        </div>

        <div v-if="letterheads.data.length === 0" class="empty">
            Aucun habillage. Créez-en un et importez vos images (PNG, JPG ou WebP, 2 Mo max).
        </div>

        <div v-else class="cards">
            <article v-for="l in letterheads.data" :key="l.id" class="card">
                <div class="page">
                    <img v-if="l.header_url" :src="l.header_url" alt="En-tête" class="band" />
                    <div v-else class="band-empty">Pas d'en-tête</div>
                    <div class="page-body">Corps du contrat</div>
                    <img v-if="l.footer_url" :src="l.footer_url" alt="Pied de page" class="band" />
                    <div v-else class="band-empty">Pas de pied de page</div>
                </div>
                <div class="card-foot">
                    <p class="card-name">{{ l.name }}</p>
                    <div class="card-actions">
                        <button type="button" class="icon-btn" title="Modifier" @click="openDialog(l)">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                        <button type="button" class="icon-btn danger" title="Supprimer" @click="toDelete = l">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="letterheads.links.length > 3" class="pagination">
            <template v-for="link in letterheads.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="page-link" :class="{ active: link.active }"><span v-html="link.label" /></Link>
            </template>
        </div>

        <ConfirmModal
            :show="toDelete !== null"
            title="Supprimer l'habillage"
            :message="`Supprimer « ${toDelete?.name} » ? Les contrats déjà générés gardent leur en-tête et leur pied de page.`"
            confirm-label="Supprimer l'habillage"
            :loading="deleting"
            @confirm="destroy"
            @cancel="toDelete = null"
        />

        <div v-if="dialogOpen" class="overlay" @click.self="closeDialog">
            <form class="dialog" @submit.prevent="submit">
                <h3 class="dialog-title">{{ editing ? 'Modifier l\'habillage' : 'Nouvel habillage' }}</h3>

                <div class="field">
                    <label class="label">Nom <span class="req">*</span></label>
                    <input v-model="form.name" type="text" class="input" maxlength="255" placeholder="Ex : Entête pour contrat de prestation" />
                    <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
                </div>

                <div v-for="side in (['header', 'footer'] as const)" :key="side" class="field">
                    <label class="label">{{ side === 'header' ? 'En-tête' : 'Pied de page' }}</label>
                    <label
                        class="drop"
                        :class="{ 'is-over': dragging === side, 'has-image': previews[side] }"
                        @dragover.prevent="dragging = side"
                        @dragleave.prevent="dragging = null"
                        @drop.prevent="onDrop(side, $event)"
                    >
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onFile(side, $event)" />
                        <template v-if="previews[side]">
                            <img :src="previews[side]!" alt="" class="drop-img" />
                            <span class="drop-change">
                                <span class="material-symbols-outlined" style="font-size:18px">swap_horiz</span>
                                Cliquer ou déposer une image pour la remplacer
                            </span>
                        </template>
                        <template v-else>
                            <span class="material-symbols-outlined drop-ico">cloud_upload</span>
                            <span class="drop-title">Cliquez pour choisir une image</span>
                            <span class="drop-sub">ou glissez-déposez-la ici (PNG, JPG ou WebP, 2 Mo max)</span>
                        </template>
                    </label>
                    <p class="hint">
                        Mise à la largeur A4 (210 mm), hauteur maximale {{ maxHeights[side] }} mm.
                        <template v-if="editing && !form[`${side}_image`]">Sans nouveau fichier, l'image actuelle est conservée.</template>
                    </p>
                    <p v-if="form.errors[`${side}_image`]" class="error-msg">{{ form.errors[`${side}_image`] }}</p>
                </div>

                <div class="dialog-actions">
                    <button type="button" class="btn-secondary" :disabled="form.processing" @click="closeDialog">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !form.name.trim()">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.wrap { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.head-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.title { font-size: 24px; font-weight: 700; color: #191c1e; }
.subtitle { font-size: 14px; color: #515f74; margin-top: 2px; max-width: 620px; }
.empty { padding: 36px; text-align: center; font-size: 14px; color: #9aaabb; background: #fff; border: 1px dashed #d9e0e7; border-radius: 14px; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.card { background: #fff; border: 1px solid #e8edf2; border-radius: 14px; overflow: hidden; }
.page { background: #f3f5f7; padding: 16px; }
.page > * { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); }
.band { display: block; width: 100%; background: #fff; }
.band-empty { background: #fff; padding: 10px; text-align: center; font-size: 11px; color: #b4bcc6; }
.page-body { background: #fff; padding: 22px 0; text-align: center; font-size: 11px; color: #cdd5dd; border-top: 1px dashed #e8edf2; border-bottom: 1px dashed #e8edf2; }
.card-foot { display: flex; align-items: center; gap: 10px; padding: 12px 14px; }
.card-name { flex: 1; min-width: 0; font-size: 14px; font-weight: 700; color: #191c1e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-actions { display: flex; gap: 4px; }
.icon-btn { display: inline-flex; padding: 6px; border-radius: 8px; border: none; background: transparent; color: #515f74; cursor: pointer; }
.icon-btn:hover { background: #f2f4f6; color: #1F3A4D; }
.icon-btn.danger:hover { color: #ba1a1a; background: #ffeded; }
.icon-btn .material-symbols-outlined { font-size: 20px; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #E5004C; color: #fff; border-radius: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; color: #515f74; background: #fff; border-radius: 10px; font-size: 13px; font-weight: 500; border: 1.5px solid #e0e3e5; text-decoration: none; cursor: pointer; }
.btn-secondary:hover { background: #f2f4f6; }
.pagination { display: flex; gap: 4px; justify-content: center; }
.page-link { padding: 6px 12px; border-radius: 8px; font-size: 13px; color: #515f74; text-decoration: none; border: 1px solid #e8edf2; background: #fff; }
.page-link.active { background: #E5004C; border-color: #E5004C; color: #fff; }
.overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 50; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
.dialog { background: #fff; border-radius: 16px; padding: 24px; width: 100%; max-width: 540px; max-height: 92vh; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; }
.dialog-title { font-size: 18px; font-weight: 700; color: #191c1e; }
.drop { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 22px 16px; border: 2px dashed #c3ccd6; border-radius: 12px; background: #fafbfc; cursor: pointer; text-align: center; transition: border-color 0.15s, background 0.15s; }
.drop:hover, .drop.is-over { border-color: #E5004C; background: #fff5f8; }
.drop.has-image { padding: 12px; }
.drop-ico { font-size: 34px; color: #E5004C; }
.drop-title { font-size: 14px; font-weight: 700; color: #1F3A4D; }
.drop-sub { font-size: 12px; color: #868e96; }
.drop-img { max-width: 100%; max-height: 110px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15); }
.drop-change { display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; font-size: 12px; font-weight: 600; color: #E5004C; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 700; color: #191c1e; letter-spacing: 0.04em; text-transform: uppercase; }
.req { color: #E5004C; }
.input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e3e5; border-radius: 10px; font-size: 14px; background: #fafbfc; outline: none; }
.input:focus { border-color: #E5004C; background: #fff; }
.hint { font-size: 12px; color: #9aaabb; }
.error-msg { font-size: 13px; color: #ba1a1a; }
</style>
