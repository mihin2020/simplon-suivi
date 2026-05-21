<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation { id: string; name: string }

const props = defineProps<{ formations: Formation[] }>()

const form = useForm({
    campus_formation_id: '',
    name:                '',
    started_at:          '',
    ended_at:            '',
})

const submit = () => {
    form.post('/campus/cohorts')
}
</script>

<template>
    <div class="max-w-[720px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center gap-md">
            <Link href="/campus/cohorts" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Nouvelle cohorte</h1>
                <p class="text-body-md text-secondary mt-xs">Créez un nouveau groupe d'apprenants pour une formation.</p>
            </div>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="card space-y-lg">

            <!-- Formation -->
            <div class="field">
                <label class="label">Formation <span class="required">*</span></label>
                <select
                    v-model="form.campus_formation_id"
                    class="input"
                    :class="{ 'input-error': form.errors.campus_formation_id }"
                >
                    <option value="">Sélectionner une formation</option>
                    <option v-for="f in formations" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="form.errors.campus_formation_id" class="error-msg">{{ form.errors.campus_formation_id }}</p>
            </div>

            <!-- Nom de la cohorte -->
            <div class="field">
                <label class="label">Nom de la cohorte <span class="required">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    :class="{ 'input-error': form.errors.name }"
                    placeholder="ex : Cohorte 2025 — Groupe A"
                />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-lg">
                <div class="field">
                    <label class="label">Date de début <span class="required">*</span></label>
                    <input
                        v-model="form.started_at"
                        type="date"
                        class="input"
                        :class="{ 'input-error': form.errors.started_at }"
                    />
                    <p v-if="form.errors.started_at" class="error-msg">{{ form.errors.started_at }}</p>
                </div>
                <div class="field">
                    <label class="label">Date de fin <span class="required">*</span></label>
                    <input
                        v-model="form.ended_at"
                        type="date"
                        class="input"
                        :class="{ 'input-error': form.errors.ended_at }"
                    />
                    <p v-if="form.errors.ended_at" class="error-msg">{{ form.errors.ended_at }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <Link href="/campus/cohorts" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    <span v-else class="material-symbols-outlined" style="font-size:16px">save</span>
                    Créer la cohorte
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: #515f74;
    transition: background 0.15s;
    flex-shrink: 0;
    text-decoration: none;
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.card {
    background: #fff;
    border: 1px solid #e0e3e5;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.field { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 12px; font-weight: 600; color: #191c1e; }
.required { color: #E5004C; }

.input {
    padding: 10px 14px;
    border: 1px solid #e0e3e5;
    border-radius: 8px;
    font-size: 14px;
    color: #191c1e;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
    outline: none;
    font-family: inherit;
}
.input:focus { border-color: #E5004C; box-shadow: 0 0 0 3px rgba(229,0,76,0.08); }
.input-error { border-color: #ba1a1a !important; }
.input::placeholder { color: #9aaabb; }
select.input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239aaabb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    padding-right: 36px;
}

.error-msg { font-size: 12px; color: #ba1a1a; }

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid #e0e3e5;
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
    transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #c0003e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid #e0e3e5;
    transition: background 0.15s;
    text-decoration: none;
    cursor: pointer;
}
.btn-secondary:hover { background: #f2f4f6; }
.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
