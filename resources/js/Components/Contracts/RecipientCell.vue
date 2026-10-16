<script setup lang="ts">
/**
 * Recipient of a contract in a list: who it is, and whether it can be sent
 * by email (address known) or not. The sending itself is shown by EmailStatusBadge.
 */
defineProps<{
    name: string
    email: string | null
    nominative: boolean
}>()
</script>

<template>
    <div class="recipient">
        <span class="name">{{ nominative && name !== '-' ? name : (nominative ? 'Destinataire sans fiche' : 'Contrat général') }}</span>
        <span v-if="email" class="mail ok" :title="`Envoi possible à ${email}`">
            <span class="material-symbols-outlined">alternate_email</span>{{ email }}
        </span>
        <span v-else-if="nominative" class="mail missing" title="Aucune adresse : à compléter avant l'envoi">
            <span class="material-symbols-outlined">unsubscribe</span>Sans email
        </span>
        <span v-else class="mail general" title="Sans destinataire : à envoyer aux adresses de votre choix">
            <span class="material-symbols-outlined">group</span>Pour tous
        </span>
    </div>
</template>

<style scoped>
.recipient { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.name { font-size: 14px; color: #374151; }
.mail { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mail .material-symbols-outlined { font-size: 14px; }
.ok { color: #515f74; }
.missing { color: #b45309; font-weight: 600; }
.general { color: #868e96; }
</style>
