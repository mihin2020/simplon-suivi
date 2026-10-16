<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Can from '@/Components/Can.vue'
import ProgressModal from '@/Components/UI/ProgressModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'
import ContractEmailDialog, { type EmailableContract } from '@/Components/Contracts/ContractEmailDialog.vue'
import { useDownload } from '@/composables/useHttpProgress'

/**
 * Actions on the selected contracts, shown while at least one is selected:
 * download as one ZIP, send by email, delete.
 */
const props = defineProps<{ selected: EmailableContract[] }>()
const emit = defineEmits<{ clear: [] }>()

const ids = computed(() => props.selected.map(c => c.id))
const count = computed(() => props.selected.length)

const zip = useDownload()
const downloadZip = () => zip.download('/contracts/bulk/download', 'contrats.zip', { method: 'POST', body: { ids: ids.value } })
const zipLabel = computed(() => zip.phase.value === 'preparing'
    ? `Assemblage de ${count.value} PDF dans une archive…`
    : 'Téléchargement de l\'archive')

const emailOpen = ref(false)

const confirmDelete = ref(false)
const deleting = ref(false)
const destroy = () => {
    router.post('/contracts/bulk/delete', { ids: ids.value }, {
        preserveScroll: true,
        onStart: () => { deleting.value = true },
        onFinish: () => { deleting.value = false; confirmDelete.value = false },
        onSuccess: () => emit('clear'),
    })
}
</script>

<template>
    <div class="bulk">
        <button type="button" class="clear" title="Tout désélectionner" @click="emit('clear')">
            <span class="material-symbols-outlined">close</span>
        </button>
        <span class="count">{{ count }} sélectionné{{ count > 1 ? 's' : '' }}</span>

        <div class="actions">
            <button type="button" class="act" :disabled="zip.isBusy()" @click="downloadZip">
                <span class="material-symbols-outlined">folder_zip</span> Télécharger
            </button>
            <button type="button" class="act" @click="emailOpen = true">
                <span class="material-symbols-outlined">mail</span> Envoyer par email
            </button>
            <Can permission="contracts.delete">
                <button type="button" class="act danger" :disabled="deleting" @click="confirmDelete = true">
                    <span class="material-symbols-outlined">delete</span> Supprimer
                </button>
            </Can>
        </div>

        <p v-if="zip.error.value" class="error">{{ zip.error.value }}</p>
    </div>

    <ProgressModal
        :open="zip.isBusy()"
        title="Préparation du téléchargement"
        :label="zipLabel"
        :percent="zip.phase.value === 'downloading' ? zip.percent.value : null"
        icon="folder_zip"
    />
    <ConfirmModal
        :show="confirmDelete"
        :title="count > 1 ? `Supprimer ${count} contrats` : 'Supprimer le contrat'"
        :message="count > 1
            ? `Les ${count} contrats sélectionnés seront retirés de la liste.`
            : `Le contrat ${selected[0]?.reference} sera retiré de la liste.`"
        :confirm-label="count > 1 ? `Supprimer ${count} contrats` : 'Supprimer'"
        :loading="deleting"
        @confirm="destroy"
        @cancel="confirmDelete = false"
    />
    <ContractEmailDialog :open="emailOpen" :contracts="selected" @close="emailOpen = false" @sent="emit('clear')" />
</template>

<style scoped>
.bulk { position: sticky; top: 8px; z-index: 5; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 14px; border-radius: 12px; background: #1F3A4D; color: #fff; box-shadow: 0 6px 20px rgba(31, 58, 77, 0.25); }
.clear { display: inline-flex; padding: 4px; border: none; border-radius: 50%; background: transparent; color: #fff; cursor: pointer; }
.clear:hover { background: rgba(255, 255, 255, 0.15); }
.count { font-size: 14px; font-weight: 700; }
.actions { display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto; }
.act { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; background: transparent; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
.act:hover:not(:disabled) { background: rgba(255, 255, 255, 0.12); }
.act:disabled { opacity: 0.5; cursor: progress; }
.act .material-symbols-outlined { font-size: 18px; }
.act.danger:hover:not(:disabled) { background: #E5004C; border-color: #E5004C; }
.error { width: 100%; font-size: 13px; color: #ffd0dc; }
</style>
