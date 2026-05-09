<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    email: {
        id: string
        subject: string
        to: { email: string; name?: string }[]
        cc?: { email: string; name?: string }[] | null
        body_html: string
        sent_at: string | null
        from_name: string
        from_email: string
        attachments?: { id: string; filename: string; path: string; size: number }[]
    }
}>()

function fmtDate(d: string | null) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

function fmtSize(bytes: number) {
    if (bytes < 1024) return bytes + ' o'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko'
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo'
}
</script>

<template>
    <Head :title="email.subject" />

    <div class="max-w-4xl mx-auto space-y-xl">

        <!-- Header -->
        <div class="flex items-center gap-md">
            <Link href="/communication/emails/sent" class="back-btn" title="Retour aux envoyés">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">{{ email.subject }}</h1>
                <p class="text-body-sm text-secondary mt-1">Email envoyé</p>
            </div>
        </div>

        <!-- Carte email -->
        <div class="bg-surface border border-surface-container-highest rounded-2xl shadow-sm overflow-hidden">

            <!-- En-tête de l'email -->
            <div class="px-xl pt-xl pb-md border-b border-surface-container-highest">
                <div class="flex items-start justify-between gap-md">
                    <div class="flex items-center gap-md">
                        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-on-primary font-bold text-body-md flex-shrink-0">
                            {{ email.from_name?.charAt(0) ?? 'S' }}
                        </div>
                        <div>
                            <p class="font-semibold text-on-surface text-body-md">{{ email.from_name }}</p>
                            <p class="text-body-sm text-secondary">{{ email.from_email }}</p>
                        </div>
                    </div>
                    <span class="text-body-sm text-secondary whitespace-nowrap flex-shrink-0">
                        {{ fmtDate(email.sent_at) }}
                    </span>
                </div>

                <!-- Destinataires -->
                <div class="mt-md space-y-xs pl-14">
                    <div class="flex gap-sm text-body-sm">
                        <span class="text-secondary font-medium w-6">À :</span>
                        <span class="text-on-surface">
                            {{ email.to.map(t => t.name ? `${t.name} <${t.email}>` : t.email).join(', ') }}
                        </span>
                    </div>
                    <div v-if="email.cc && email.cc.length" class="flex gap-sm text-body-sm">
                        <span class="text-secondary font-medium w-6">Cc :</span>
                        <span class="text-on-surface">
                            {{ email.cc.map(t => t.name ? `${t.name} <${t.email}>` : t.email).join(', ') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Corps de l'email -->
            <div class="px-xl py-lg">
                <div
                    class="email-body prose max-w-none text-on-surface"
                    v-html="email.body_html"
                />
            </div>

            <!-- Pièces jointes -->
            <div v-if="email.attachments && email.attachments.length" class="px-xl pb-xl pt-md border-t border-surface-container-highest">
                <p class="text-body-sm font-semibold text-secondary mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined" style="font-size:16px">attach_file</span>
                    {{ email.attachments.length }} pièce(s) jointe(s)
                </p>
                <div class="flex flex-wrap gap-sm">
                    <div
                        v-for="att in email.attachments"
                        :key="att.id"
                        class="flex items-center gap-sm px-md py-sm bg-surface-container-low border border-surface-container-highest rounded-lg"
                    >
                        <span class="material-symbols-outlined text-secondary" style="font-size:20px">description</span>
                        <div>
                            <p class="text-body-sm font-medium text-on-surface leading-none">{{ att.filename }}</p>
                            <p class="text-body-sm text-secondary mt-0.5">{{ fmtSize(att.size) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: #515f74;
    transition: all 0.15s;
    flex-shrink: 0;
    text-decoration: none;
}
.back-btn:hover {
    background: #f2f4f6;
    color: #191c1e;
}

/* Styles pour le corps de l'email rendu en HTML */
.email-body {
    font-size: 14px;
    line-height: 1.7;
    color: #191c1e;
}
.email-body :deep(p) { margin: 0 0 12px; }
.email-body :deep(h1),
.email-body :deep(h2),
.email-body :deep(h3) { font-weight: 700; margin: 16px 0 8px; }
.email-body :deep(ul),
.email-body :deep(ol) { padding-left: 20px; margin: 8px 0; }
.email-body :deep(a) { color: #E5004C; text-decoration: underline; }
.email-body :deep(strong) { font-weight: 700; }
.email-body :deep(em) { font-style: italic; }
.email-body :deep(blockquote) {
    border-left: 3px solid #e0e3e5;
    padding-left: 12px;
    color: #515f74;
    margin: 8px 0;
}
</style>
