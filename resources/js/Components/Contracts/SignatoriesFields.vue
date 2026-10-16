<script setup lang="ts">
/**
 * The two columns of the signature block printed at the end of the contract.
 */
export interface Signatories {
    left_title: string
    left_name: string
    right_title: string
    right_name: string
}

const model = defineModel<Signatories>({ required: true })
</script>

<template>
    <fieldset class="signatories">
        <legend class="legend">Bloc de signatures</legend>
        <p class="hint">Imprimé à la fin du contrat, sur deux colonnes.</p>
        <div class="columns">
            <div class="column">
                <input v-model="model.left_title" type="text" class="input strong" maxlength="120" placeholder="Pour SIMPLON BURKINA FASO" />
                <input v-model="model.left_name" type="text" class="input" maxlength="120" placeholder="M. BAGRE Dominique" />
            </div>
            <div class="column">
                <input v-model="model.right_title" type="text" class="input strong" maxlength="120" placeholder="Pour le Prestataire" />
                <input v-model="model.right_name" type="text" class="input" maxlength="120" placeholder="Nom du signataire" />
            </div>
        </div>
    </fieldset>
</template>

<style scoped>
.signatories { margin: 0; padding: 14px 16px 16px; border: 1px solid #e8edf2; border-radius: 12px; background: #fafbfc; }
.legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: #191c1e; }
.hint { font-size: 12px; color: #868e96; margin-bottom: 10px; }
.columns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .columns { grid-template-columns: 1fr; } }
.column { display: flex; flex-direction: column; gap: 8px; }
.input { width: 100%; padding: 9px 12px; border: 1.5px solid #e0e3e5; border-radius: 10px; background: #fff; font-size: 14px; outline: none; }
.input:focus { border-color: #E5004C; }
.input.strong { font-weight: 700; }
</style>
