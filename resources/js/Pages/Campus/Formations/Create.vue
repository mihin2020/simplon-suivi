<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Mode { value: string; label: string }

const props = defineProps<{ modes: Mode[] }>()

const form = useForm({
    name:            '',
    description:     '',
    duration_months: 3,
    mode:            'presentiel',
    total_cost:      0,
    is_active:       true,
})

const submit = () => {
    form.post('/campus/formations')
}
</script>

<template>
    <div class="max-w-[720px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center gap-md">
            <Link href="/campus/formations" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Nouvelle formation</h1>
                <p class="text-body-md text-secondary mt-xs">Ajoutez un parcours au catalogue Campus Workforce.</p>
            </div>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="card space-y-lg">

            <!-- Nom -->
            <div class="field">
                <label class="label">Nom de la formation <span class="required">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    :class="{ 'input-error': form.errors.name }"
                    placeholder="ex : Développement Web / Mobile"
                />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>

            <!-- Description -->
            <div class="field">
                <label class="label">Description <span class="optional">(optionnel)</span></label>
                <textarea
                    v-model="form.description"
                    class="input"
                    rows="3"
                    placeholder="Objectifs, public cible, prérequis..."
                />
                <p v-if="form.errors.description" class="error-msg">{{ form.errors.description }}</p>
            </div>

            <!-- Durée + Mode -->
            <div class="grid grid-cols-2 gap-lg">
                <div class="field">
                    <label class="label">Durée (mois) <span class="required">*</span></label>
                    <input
                        v-model.number="form.duration_months"
                        type="number"
                        min="1"
                        max="60"
                        class="input"
                        :class="{ 'input-error': form.errors.duration_months }"
                    />
                    <p v-if="form.errors.duration_months" class="error-msg">{{ form.errors.duration_months }}</p>
                </div>
                <div class="field">
                    <label class="label">Mode <span class="required">*</span></label>
                    <select v-model="form.mode" class="input" :class="{ 'input-error': form.errors.mode }">
                        <option v-for="m in modes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p v-if="form.errors.mode" class="error-msg">{{ form.errors.mode }}</p>
                </div>
            </div>

            <!-- Coût total -->
            <div class="field">
                <label class="label">Coût total (FCFA) <span class="required">*</span></label>
                <div class="input-with-suffix">
                    <input
                        v-model.number="form.total_cost"
                        type="number"
                        min="0"
                        class="input"
                        :class="{ 'input-error': form.errors.total_cost }"
                        placeholder="0"
                    />
                    <span class="suffix">FCFA</span>
                </div>
                <p v-if="form.errors.total_cost" class="error-msg">{{ form.errors.total_cost }}</p>
            </div>

            <!-- Statut actif -->
            <div class="field">
                <label class="checkbox-row">
                    <input v-model="form.is_active" type="checkbox" class="checkbox" />
                    <span class="checkbox-label">
                        Formation active
                        <span class="hint">Visible dans le sélecteur lors de la création de cohortes</span>
                    </span>
                </label>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <Link href="/campus/formations" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    <span v-else class="material-symbols-outlined" style="font-size:16px">save</span>
                    Créer la formation
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
.icon-back:hover { background: #1F3A4D; color: #fff; }

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
.optional { color: #9aaabb; font-weight: 400; }

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
textarea.input { resize: vertical; }

.input-with-suffix { position: relative; }
.input-with-suffix .input { padding-right: 60px; }
.suffix {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    font-weight: 600;
    color: #9aaabb;
    pointer-events: none;
}

.checkbox-row { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
.checkbox { width: 18px; height: 18px; margin-top: 2px; accent-color: #E5004C; cursor: pointer; flex-shrink: 0; }
.checkbox-label { font-size: 14px; color: #191c1e; display: flex; flex-direction: column; gap: 2px; }
.hint { font-size: 12px; color: #9aaabb; font-weight: 400; }

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
    text-decoration: none;
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
