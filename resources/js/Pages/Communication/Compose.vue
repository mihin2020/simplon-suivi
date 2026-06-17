<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import RecipientSelector from '@/Components/RecipientSelector.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

defineOptions({ layout: AdminLayout })

defineProps<{ projects?: { id: string; name: string }[] }>()

const to = ref<{ email: string; name?: string }[]>([])
const cc = ref('')
const subject = ref('')
const body = ref('')
const files = ref<File[]>([])
const fileInput = ref<HTMLInputElement | null>(null)

const sending = ref(false)
const showCc = ref(false)

function fileSize(bytes: number) {
    if (bytes < 1024) return bytes + ' o'
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' Ko'
    return (bytes / 1024 / 1024).toFixed(1) + ' Mo'
}

function submit() {
    if (sending.value) return
    sending.value = true

    const formData = new FormData()
    to.value.forEach((r, i) => {
        formData.append(`to[${i}][email]`, r.email)
        if (r.name) formData.append(`to[${i}][name]`, r.name)
    })
    // Parse CC emails (comma-separated)
    if (cc.value.trim()) {
        const ccEmails = cc.value.split(',').map((e: string) => e.trim()).filter((e: string) => e)
        ccEmails.forEach((email: string, i: number) => {
            formData.append(`cc[${i}][email]`, email)
        })
    }
    formData.append('subject', subject.value)
    formData.append('body', body.value)
    files.value.forEach((f, i) => formData.append(`attachments[${i}]`, f))

    router.post('/communication/emails', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            to.value = []
            cc.value = ''
            subject.value = ''
            body.value = ''
            files.value = []
        },
        onError: (errors) => {
            console.error('Erreurs validation:', errors)
            alert('Erreur d\'envoi : ' + Object.values(errors).join(', '))
        },
        onFinish: () => {
            sending.value = false
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

    <div class="page-wrap">
        <!-- Header avec bouton retour -->
        <div class="page-header">
            <Link href="/communication/emails" class="back-btn" title="Retour">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div class="page-header-icon">
                <span class="material-symbols-outlined" style="font-size:24px">edit_square</span>
            </div>
            <div>
                <h1 class="page-title">Nouveau message</h1>
                <p class="page-subtitle">Composez et envoyez un email à un ou plusieurs destinataires</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="compose-form" @dragover.prevent @drop="onDrop">

            <!-- Destinataires -->
            <div class="form-section">
                <div class="form-label-row">
                    <label class="form-label">
                        <span class="material-symbols-outlined label-icon">group</span>
                        Destinataires
                    </label>
                    <button v-if="!showCc" type="button" class="cc-toggle" @click="showCc = true">
                        <span class="material-symbols-outlined" style="font-size:15px">add</span>
                        Ajouter Cc
                    </button>
                </div>
                <RecipientSelector v-model="to" :projects="projects ?? []" />
            </div>

            <!-- Copie (CC) — masqué par défaut -->
            <div v-if="showCc" class="form-section">
                <div class="form-label-row">
                    <label class="form-label">
                        <span class="material-symbols-outlined label-icon">content_copy</span>
                        Copie (Cc)
                    </label>
                    <button type="button" class="cc-toggle cc-toggle-remove" @click="showCc = false; cc = ''">
                        <span class="material-symbols-outlined" style="font-size:15px">close</span>
                        Retirer
                    </button>
                </div>
                <input v-model="cc" type="text" class="form-input" placeholder="Emails en copie, séparés par des virgules…" />
            </div>

            <!-- Objet -->
            <div class="form-section">
                <label class="form-label">
                    <span class="material-symbols-outlined label-icon">subject</span>
                    Objet
                </label>
                <input v-model="subject" type="text" class="form-input" placeholder="Sujet de votre message…" required />
            </div>

            <!-- Message -->
            <div class="form-section">
                <label class="form-label">
                    <span class="material-symbols-outlined label-icon">edit_note</span>
                    Message
                </label>
                <TiptapEditor v-model="body" />
            </div>

            <!-- Pièces jointes -->
            <div class="form-section">
                <label class="form-label">
                    <span class="material-symbols-outlined label-icon">attach_file</span>
                    Pièces jointes
                    <span v-if="files.length" class="attach-count">{{ files.length }}</span>
                </label>
                <div class="upload-zone" @click="fileInput?.click()">
                    <span class="material-symbols-outlined upload-icon">cloud_upload</span>
                    <p class="upload-text">Glissez-déposez des fichiers ici ou <strong>cliquez pour sélectionner</strong></p>
                    <p class="upload-hint">Taille max : 10 Mo par fichier</p>
                    <input ref="fileInput" type="file" multiple class="hidden" @change="e => files.push(...Array.from((e.target as HTMLInputElement).files || []))" />
                </div>
                <div v-if="files.length" class="file-list">
                    <div v-for="(f, i) in files" :key="i" class="file-item">
                        <span class="material-symbols-outlined file-icon">description</span>
                        <span class="file-name">{{ f.name }}</span>
                        <span class="file-size">{{ fileSize(f.size) }}</span>
                        <button type="button" @click="removeFile(i)" class="file-remove" title="Retirer">
                            <span class="material-symbols-outlined" style="font-size:16px">close</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <Link href="/communication/emails" class="btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                    Annuler
                </Link>
                <button type="submit" class="btn-primary" :disabled="sending || to.length === 0 || !subject || !body">
                    <span class="material-symbols-outlined" style="font-size:16px" :class="{ 'spin-send': sending }">{{ sending ? 'progress_activity' : 'send' }}</span>
                    {{ sending ? 'Envoi en cours…' : 'Envoyer' }}
                </button>
            </div>
        </form>
    </div>

    <!-- Popup loading envoi -->
    <Transition name="loading-fade">
        <div v-if="sending" class="loading-overlay">
            <div class="loading-card">
                <div class="spinner-ring">
                    <div></div><div></div><div></div><div></div>
                </div>
                <p class="loading-title">Envoi en cours...</p>
                <p class="loading-sub">Vos emails sont en cours d'envoi.<br>Vous serez redirigé automatiquement.</p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.page-wrap {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 16px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.page-header {
    display: flex;
    align-items: center;
    gap: 16px;
}
.back-btn {
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
.back-btn:hover {
    background: #1F3A4D;
    color: #fff;
}
.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
    line-height: 1.2;
}
.page-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin: 4px 0 0 0;
}

.compose-form {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.form-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.form-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.label-icon {
    font-size: 16px;
    color: #E5004C;
}
.attach-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 99px;
    background: #E5004C;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
}
.cc-toggle {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 4px 10px;
    border: 1px solid #e0e3e5;
    border-radius: 99px;
    background: #fff;
    color: #1F3A4D;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.cc-toggle:hover { background: #1F3A4D; color: #fff; border-color: #1F3A4D; }
.cc-toggle-remove { color: #9aaabb; }
.cc-toggle-remove:hover { background: #f2f4f6; color: #515f74; border-color: #e0e3e5; }
.form-input {
    padding: 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fafafa;
    outline: none;
    transition: all 0.15s;
    width: 100%;
}
.form-input:focus {
    border-color: #E5004C;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}

/* Upload */
.upload-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 28px 20px;
    border: 2px dashed #e0e3e5;
    border-radius: 12px;
    background: #fafbfc;
    cursor: pointer;
    transition: all 0.15s;
}
.upload-zone:hover {
    border-color: #E5004C;
    background: #fff5f8;
}
.upload-icon {
    font-size: 36px;
    color: #9aaabb;
    margin-bottom: 6px;
}
.upload-text {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    text-align: center;
}
.upload-text strong {
    color: #E5004C;
}
.upload-hint {
    font-size: 11px;
    color: #9aaabb;
    margin: 4px 0 0;
    text-align: center;
}
.hidden {
    display: none;
}
.file-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
}
.file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #fafafa;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
}
.file-icon {
    font-size: 18px;
    color: #6b7280;
}
.file-name {
    flex: 1;
    font-size: 13px;
    color: #191c1e;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.file-size {
    font-size: 11px;
    color: #9aaabb;
}
.file-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    background: transparent;
    color: #dc2626;
    cursor: pointer;
    border-radius: 4px;
}
.file-remove:hover {
    background: #fee2e2;
}

/* Actions */
.form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f0;
}
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
}
.btn-primary:hover:not(:disabled) {
    background: #c4003f;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(229, 0, 76, 0.2);
}
.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: #f2f4f6;
    color: #515f74;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover {
    background: #e0e3e5;
    color: #191c1e;
}

.spin-send { animation: spin-send 0.8s linear infinite; }
@keyframes spin-send { to { transform: rotate(360deg); } }

/* Loading overlay */
.loading-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.loading-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px 48px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.18);
    min-width: 280px;
    text-align: center;
}
.loading-title {
    font-size: 18px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
}
.loading-sub {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    line-height: 1.6;
}
/* Spinner CSS */
.spinner-ring {
    display: inline-block;
    position: relative;
    width: 56px;
    height: 56px;
}
.spinner-ring div {
    box-sizing: border-box;
    display: block;
    position: absolute;
    width: 44px;
    height: 44px;
    margin: 6px;
    border: 5px solid transparent;
    border-top-color: #E5004C;
    border-radius: 50%;
    animation: spinner-ring 1s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.spinner-ring div:nth-child(1) { animation-delay: -0.3s; }
.spinner-ring div:nth-child(2) { animation-delay: -0.2s; }
.spinner-ring div:nth-child(3) { animation-delay: -0.1s; }
@keyframes spinner-ring {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
/* Transition */
.loading-fade-enter-active,
.loading-fade-leave-active { transition: opacity 0.25s ease; }
.loading-fade-enter-from,
.loading-fade-leave-to { opacity: 0; }
</style>
