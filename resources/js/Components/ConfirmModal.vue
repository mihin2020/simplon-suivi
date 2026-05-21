<script setup lang="ts">
defineProps<{
    show: boolean
    title: string
    message: string
    confirmLabel?: string
    loading?: boolean
}>()

const emit = defineEmits<{
    confirm: []
    cancel: []
}>()
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="backdrop" @click.self="emit('cancel')">
                <div class="modal" role="dialog" aria-modal="true">
                    <!-- Icône -->
                    <div class="modal-icon">
                        <span class="material-symbols-outlined" style="font-size:28px;color:#dc2626">delete</span>
                    </div>

                    <!-- Contenu -->
                    <div class="modal-body">
                        <h3 class="modal-title">{{ title }}</h3>
                        <p class="modal-message">{{ message }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" @click="emit('cancel')">
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="btn-confirm"
                            :disabled="loading"
                            @click="emit('confirm')"
                        >
                            <span v-if="loading" class="spinner" />
                            {{ confirmLabel ?? 'Supprimer' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 400px;
    padding: 28px 24px 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    text-align: center;
}

.modal-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #fee2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.modal-body { display: flex; flex-direction: column; gap: 6px; }

.modal-title {
    font-size: 17px;
    font-weight: 700;
    color: #191c1e;
    margin: 0;
}

.modal-message {
    font-size: 14px;
    color: #515f74;
    margin: 0;
    line-height: 1.5;
}

.modal-actions {
    display: flex;
    gap: 10px;
    width: 100%;
    margin-top: 4px;
}

.btn-cancel {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    background: transparent;
    color: #515f74;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: #f2f4f6; }

.btn-confirm {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    background: #dc2626;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-confirm:hover:not(:disabled) { background: #b91c1c; }
.btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Transition */
.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-active .modal,
.modal-leave-active .modal { transition: transform 0.2s ease, opacity 0.2s ease; }

.modal-enter-from,
.modal-leave-to { opacity: 0; }
.modal-enter-from .modal,
.modal-leave-to .modal { transform: scale(0.95) translateY(8px); opacity: 0; }
</style>
