<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const form = useForm({
    name:        '',
    description: '',
})

const submit = () => form.post('/referentiels')
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <div class="flex items-center gap-md mb-xl">
            <Link href="/referentiels" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Nouveau Référentiel</h1>
                <p class="text-body-md text-secondary mt-xs">Créez un référentiel de compétences global.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="card space-y-lg">
            <div class="field">
                <label class="label">Nom du référentiel <span class="required">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    :class="{ 'input-error': form.errors.name }"
                    placeholder="Ex : Référentiel Développement Web"
                    autofocus
                />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>

            <div class="field">
                <label class="label">Description</label>
                <textarea
                    v-model="form.description"
                    class="input"
                    rows="3"
                    placeholder="Description du référentiel (optionnel)"
                />
            </div>

            <div class="flex justify-end gap-sm pt-sm">
                <Link href="/referentiels" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    Créer le référentiel
                </button>
            </div>
        </form>

    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%; color: #515f74;
    transition: background 0.15s; flex-shrink: 0; text-decoration: none;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 32px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; letter-spacing: 0.02em; }
.required { color: #E5004C; }

.input {
    padding: 10px 14px; border: 1px solid #e0e3e5; border-radius: 8px;
    font-size: 14px; color: #191c1e; background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s; width: 100%; outline: none;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a; }
textarea.input { resize: vertical; }
.error-msg { font-size: 12px; color: #ba1a1a; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 24px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    transition: background 0.2s; border: none; cursor: pointer;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
    display: inline-flex; align-items: center; padding: 10px 20px;
    background: transparent; color: #515f74; border-radius: 8px;
    font-size: 14px; font-weight: 500; border: 1px solid #e0e3e5;
    transition: background 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
