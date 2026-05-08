<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    threadId: string
    emails: any[]
}>()

const replyBody = ref('')
const showReply = ref(false)
const showForward = ref(false)
const forwardTo = ref('')
const forwardBody = ref('')

function fmtDate(d: string | null) {
    if (!d) return ''
    const date = new Date(d)
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function reply(email: any) {
    router.post(`/communication/emails/${email.id}/reply`, { body: replyBody.value }, {
        preserveScroll: true,
        onSuccess: () => { replyBody.value = ''; showReply.value = false; },
    })
}

function forward(email: any) {
    const to = forwardTo.value.split(',').map(e => e.trim()).filter(Boolean).map(email => ({ email }))
    router.post(`/communication/emails/${email.id}/forward`, { to, body: forwardBody.value }, {
        preserveScroll: true,
        onSuccess: () => { forwardTo.value = ''; forwardBody.value = ''; showForward.value = false; },
    })
}

function archive(email: any) {
    router.patch(`/communication/emails/${email.id}/archive`, {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Fil de discussion" />

    <div class="max-w-4xl mx-auto space-y-xl">
        <div class="flex items-center justify-between">
            <Link href="/communication/emails" class="text-body-sm text-primary hover:underline flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
                Retour à la boîte de réception
            </Link>
        </div>

        <div class="space-y-lg">
            <div
                v-for="e in emails"
                :key="e.id"
                class="bg-surface-container-lowest border border-surface-container-highest rounded-xl p-lg shadow-sm"
            >
                <div class="flex items-start justify-between mb-md">
                    <div>
                        <div class="font-semibold text-on-surface text-body-md">{{ e.from_name || e.from_email }}</div>
                        <div class="text-body-sm text-secondary">{{ e.from_email }}</div>
                    </div>
                    <span class="text-body-sm text-secondary">{{ fmtDate(e.received_at || e.sent_at) }}</span>
                </div>

                <div class="text-h2 font-semibold text-on-surface mb-md">{{ e.subject }}</div>

                <div class="prose prose-sm max-w-none text-body-sm text-on-surface mb-md" v-html="e.body_html" />

                <div v-if="e.attachments?.length" class="flex flex-wrap gap-sm mb-md">
                    <a
                        v-for="a in e.attachments"
                        :key="a.id"
                        :href="`/storage/${a.path}`"
                        target="_blank"
                        class="inline-flex items-center gap-xs bg-surface-container-low px-sm py-xs rounded text-label-sm text-primary hover:underline"
                    >
                        <span class="material-symbols-outlined" style="font-size:16px">attach_file</span>
                        {{ a.filename }}
                    </a>
                </div>

                <div class="flex items-center gap-sm pt-md border-t border-surface-container-highest">
                    <button @click="showReply = !showReply; showForward = false" class="btn-secondary">
                        <span class="material-symbols-outlined" style="font-size:18px">reply</span>
                        Répondre
                    </button>
                    <button @click="showForward = !showForward; showReply = false" class="btn-secondary">
                        <span class="material-symbols-outlined" style="font-size:18px">forward</span>
                        Transférer
                    </button>
                    <button @click="archive(e)" class="btn-secondary">
                        <span class="material-symbols-outlined" style="font-size:18px">archive</span>
                        Archiver
                    </button>
                </div>

                <div v-if="showReply && (!showForward)" class="mt-md space-y-sm">
                    <TiptapEditor v-model="replyBody" />
                    <div class="flex justify-end gap-sm">
                        <button @click="showReply = false" class="btn-secondary">Annuler</button>
                        <button @click="reply(e)" class="btn-primary">Envoyer la réponse</button>
                    </div>
                </div>

                <div v-if="showForward && (!showReply)" class="mt-md space-y-sm">
                    <input v-model="forwardTo" type="text" placeholder="Emails séparés par des virgules" class="w-full border border-surface-container-highest rounded-lg px-sm py-xs text-body-sm text-on-surface bg-surface focus:outline-none focus:border-primary" />
                    <textarea v-model="forwardBody" rows="3" placeholder="Message optionnel..." class="w-full border border-surface-container-highest rounded-lg px-sm py-xs text-body-sm text-on-surface bg-surface focus:outline-none focus:border-primary"></textarea>
                    <div class="flex justify-end gap-sm">
                        <button @click="showForward = false" class="btn-secondary">Annuler</button>
                        <button @click="forward(e)" class="btn-primary">Transférer</button>
                    </div>
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
