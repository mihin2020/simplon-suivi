<script setup lang="ts">
/**
 * Where the email sending of a contract stands, for the lists.
 */
const props = defineProps<{
    status: 'queued' | 'sent' | 'failed' | null
    emailedAt?: string | null
    emailedTo?: string | null
    count?: number
}>()

const date = (iso: string) => new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const title = () => props.emailedAt
    ? `Dernier envoi le ${new Date(props.emailedAt).toLocaleString('fr-FR')}${props.emailedTo ? ` à ${props.emailedTo}` : ''}`
    : ''
</script>

<template>
    <span v-if="status === 'queued'" class="badge queued" title="L'email part en arrière-plan">
        <span class="spinner" />Envoi en cours…
    </span>
    <span v-else-if="status === 'failed'" class="badge failed" title="L'envoi a échoué : vérifiez l'adresse puis renvoyez">
        <span class="material-symbols-outlined">error</span>Échec
    </span>
    <span v-else-if="status === 'sent' && emailedAt" class="badge sent" :title="title()">
        <span class="material-symbols-outlined">mark_email_read</span>
        Envoyé le {{ date(emailedAt) }}<template v-if="(count ?? 0) > 1"> · {{ count }}×</template>
    </span>
    <span v-else class="badge none">
        <span class="material-symbols-outlined">mail</span>Non envoyé
    </span>
</template>

<style scoped>
.badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.badge .material-symbols-outlined { font-size: 15px; }
.sent { background: #e7f8ef; color: #0a7b43; }
.queued { background: #e4edff; color: #1d4ed8; }
.failed { background: #ffeded; color: #ba1a1a; }
.none { background: #f1f3f5; color: #868e96; }
.spinner { width: 12px; height: 12px; border: 2px solid rgba(29, 78, 216, 0.25); border-top-color: #1d4ed8; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
