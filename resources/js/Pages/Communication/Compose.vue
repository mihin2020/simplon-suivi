<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import RecipientSelector from '@/Components/RecipientSelector.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

defineOptions({ layout: AdminLayout })

const to = ref<{ email: string; name?: string }[]>([])
const subject = ref('')
const body = ref('')
const files = ref<File[]>([])
const fileInput = ref<HTMLInputElement | null>(null)

function submit() {
    const formData = new FormData()
    formData.append('to', JSON.stringify(to.value))
    formData.append('subject', subject.value)
    formData.append('body', body.value)
    files.value.forEach((f, i) => formData.append(`attachments[${i}]`, f))

    router.post('/communication/emails', formData, {
        preserveScroll: true,
        onSuccess: () => {
            to.value = []
            subject.value = ''
            body.value = ''
            files.value = []
        },
    })
}

function onDrop(e: DragEvent) {
    e.preventDefault()
    if (e.dataTransfer?.files) {
        files.value.push(...Array.from(e.dataTransfer.files))
    }
}

function removeFile(index: number) {
    files.value.splice(index, 1)
}
</script>

<template>
    <Head title="Nouveau message" />

    <div class="max-w-3xl mx-auto space-y-xl">
        <h1 class="text-h1 font-bold text-on-surface">Nouveau message</h1>

        <form @submit.prevent="submit" class="space-y-lg bg-surface-container-lowest border border-surface-container-highest rounded-xl p-lg shadow-sm" @dragover.prevent @drop="onDrop">
            <div>
                <label class="block text-body-sm font-medium text-on-surface mb-xs">Destinataires</label>
                <RecipientSelector v-model="to" />
            </div>

            <div>
                <label class="block text-body-sm font-medium text-on-surface mb-xs">Objet</label>
                <input v-model="subject" type="text" class="w-full border border-surface-container-highest rounded-lg px-sm py-xs text-body-sm text-on-surface bg-surface focus:outline-none focus:border-primary" />
            </div>

            <div>
                <label class="block text-body-sm font-medium text-on-surface mb-xs">Message</label>
                <TiptapEditor v-model="body" />
            </div>

            <div>
                <label class="block text-body-sm font-medium text-on-surface mb-xs">Pièces jointes</label>
                <div
                    class="border border-dashed border-surface-container-highest rounded-lg p-lg text-center cursor-pointer hover:bg-surface-container-low transition-colors"
                    @click="fileInput?.click()"
                >
                    <span class="material-symbols-outlined text-on-surface-variant">upload_file</span>
                    <p class="text-body-sm text-on-surface-variant mt-xs">Glissez-déposez des fichiers ici ou cliquez pour sélectionner</p>
                    <input ref="fileInput" type="file" multiple class="hidden" @change="e => files.push(...Array.from((e.target as HTMLInputElement).files || []))" />
                </div>
                <div v-if="files.length" class="mt-sm space-y-xs">
                    <div v-for="(f, i) in files" :key="i" class="flex items-center justify-between bg-surface-container-low px-sm py-xs rounded text-body-sm text-on-surface">
                        <span class="truncate">{{ f.name }}</span>
                        <button type="button" @click="removeFile(i)" class="text-error">
                            <span class="material-symbols-outlined" style="font-size:18px">close</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-sm">
                <button type="button" @click="$router.visit('/communication/emails')" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary">Envoyer</button>
            </div>
        </form>
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
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-primary:hover { background: #c4003f; }
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #f2f4f6;
    color: #191c1e;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-secondary:hover { background: #e0e3e5; }
</style>
