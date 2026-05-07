<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Partner {
    id: string
    name: string
}

const props = defineProps<{
    statuses: Array<{ value: string; label: string }>
    partners: Partner[]
}>()

const form = useForm({
    name: '',
    description: '',
    started_at: '',
    ended_at: '',
    status: 'active',
    partner_ids: [] as string[],
})

// Partner selector
const partnerSearch = ref('')
const dropdownOpen = ref(false)

const selectedPartners = computed(() =>
    props.partners.filter(p => form.partner_ids.includes(p.id))
)

const filteredPartners = computed(() => {
    const q = partnerSearch.value.toLowerCase().trim()
    return props.partners.filter(p => !q || p.name.toLowerCase().includes(q))
})

const isSelected = (id: string) => form.partner_ids.includes(id)

const togglePartner = (partner: Partner) => {
    if (isSelected(partner.id)) {
        form.partner_ids = form.partner_ids.filter(id => id !== partner.id)
    } else {
        form.partner_ids = [...form.partner_ids, partner.id]
    }
}

const removePartner = (id: string) => {
    form.partner_ids = form.partner_ids.filter(pid => pid !== id)
}

const submit = () => form.post('/projects')
</script>

<template>
    <div class="max-w-2xl mx-auto">

        <!-- En-tête -->
        <div class="flex items-center gap-md mb-xl">
            <Link href="/projects" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="text-h1 font-bold text-on-surface">Nouveau Projet</h1>
                <p class="text-body-md text-secondary mt-xs">Renseignez les informations du projet.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="card space-y-lg">

            <!-- Nom -->
            <div class="field">
                <label class="label">Nom du projet <span class="required">*</span></label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    :class="{ 'input-error': form.errors.name }"
                    placeholder="Ex : Digital Skills Hub 2025"
                    autofocus
                />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>

            <!-- Description -->
            <div class="field">
                <label class="label">Description</label>
                <textarea
                    v-model="form.description"
                    class="input"
                    rows="3"
                    placeholder="Contexte et objectifs du projet..."
                />
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-md">
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
                    <label class="label">Date de fin prévue</label>
                    <input v-model="form.ended_at" type="date" class="input" />
                </div>
            </div>

            <!-- Statut -->
            <div class="field">
                <label class="label">Statut <span class="required">*</span></label>
                <select v-model="form.status" class="input">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <p v-if="form.errors.status" class="error-msg">{{ form.errors.status }}</p>
            </div>

            <!-- Partenaires -->
            <div class="field">
                <div class="flex items-center justify-between mb-xs">
                    <label class="label">Partenaires</label>
                    <span class="badge-count" v-if="selectedPartners.length">
                        {{ selectedPartners.length }} sélectionné{{ selectedPartners.length > 1 ? 's' : '' }}
                    </span>
                </div>

                <!-- Chips sélectionnés -->
                <div v-if="selectedPartners.length" class="flex flex-wrap gap-xs mb-sm">
                    <span
                        v-for="p in selectedPartners"
                        :key="p.id"
                        class="partner-chip"
                    >
                        <span class="material-symbols-outlined chip-icon">handshake</span>
                        {{ p.name }}
                        <button type="button" class="chip-remove" @click="removePartner(p.id)" title="Retirer">
                            <span class="material-symbols-outlined" style="font-size:14px">close</span>
                        </button>
                    </span>
                </div>

                <!-- Zone de sélection -->
                <div class="partner-selector" :class="{ open: dropdownOpen }">
                    <!-- Input recherche -->
                    <div class="partner-search-wrap">
                        <span class="material-symbols-outlined search-icon">search</span>
                        <input
                            v-model="partnerSearch"
                            type="text"
                            class="partner-search"
                            placeholder="Rechercher un partenaire..."
                            @focus="dropdownOpen = true"
                            @blur="setTimeout(() => dropdownOpen = false, 150)"
                        />
                    </div>

                    <!-- Liste déroulante -->
                    <div v-if="dropdownOpen" class="partner-dropdown">
                        <div v-if="partners.length === 0" class="partner-empty">
                            <span class="material-symbols-outlined" style="font-size:20px;color:#aaa">info</span>
                            Aucun partenaire configuré.
                            <Link href="/partners/create" class="text-primary underline ml-xs">En créer un.</Link>
                        </div>
                        <div v-else-if="filteredPartners.length === 0" class="partner-empty">
                            Aucun résultat pour « {{ partnerSearch }} ».
                        </div>
                        <button
                            v-for="p in filteredPartners"
                            :key="p.id"
                            type="button"
                            class="partner-option"
                            :class="{ selected: isSelected(p.id) }"
                            @mousedown.prevent="togglePartner(p)"
                        >
                            <div class="partner-option-left">
                                <div class="partner-option-avatar" :class="{ 'avatar-selected': isSelected(p.id) }">
                                    {{ p.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="partner-option-name">{{ p.name }}</div>
                                </div>
                            </div>
                            <span v-if="isSelected(p.id)" class="material-symbols-outlined check-icon">check_circle</span>
                        </button>
                    </div>
                </div>

                <p v-if="form.errors.partner_ids" class="error-msg">{{ form.errors.partner_ids }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-sm pt-md border-t border-surface-container-highest">
                <Link href="/projects" class="btn-secondary">Annuler</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner" />
                    Créer le projet
                </button>
            </div>

        </form>
    </div>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    color: #515f74; transition: background 0.15s; flex-shrink: 0;
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
select.input { appearance: none; cursor: pointer; }
.error-msg { font-size: 12px; color: #ba1a1a; margin-top: 2px; }

/* Badge count */
.badge-count {
    display: inline-flex; align-items: center;
    padding: 2px 8px; border-radius: 99px;
    font-size: 11px; font-weight: 600;
    background: rgba(229,0,76,0.08); color: #E5004C;
}

/* Chips */
.partner-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px 4px 8px; border-radius: 99px;
    background: #1F3A4D; color: #fff;
    font-size: 12px; font-weight: 500;
}
.chip-icon { font-size: 14px; opacity: 0.7; }
.chip-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; border-radius: 50%;
    background: rgba(255,255,255,0.15); border: none; cursor: pointer;
    color: #fff; transition: background 0.15s; padding: 0; margin-left: 2px;
}
.chip-remove:hover { background: rgba(255,255,255,0.3); }

/* Partner selector */
.partner-selector { position: relative; }
.partner-search-wrap {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 12px;
    border: 1px solid #e0e3e5; border-radius: 8px;
    background: #fff; transition: border-color 0.15s, box-shadow 0.15s;
}
.partner-selector.open .partner-search-wrap {
    border-color: #E5004C;
    box-shadow: 0 0 0 3px rgba(229,0,76,0.08);
    border-bottom-left-radius: 0; border-bottom-right-radius: 0;
}
.search-icon { font-size: 18px; color: #adb5bd; flex-shrink: 0; }
.partner-search {
    flex: 1; border: none; outline: none;
    font-size: 14px; color: #191c1e; background: transparent;
}
.partner-search::placeholder { color: #adb5bd; }

/* Dropdown */
.partner-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
    background: #fff; border: 1px solid #E5004C; border-top: none;
    border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    max-height: 240px; overflow-y: auto;
}
.partner-empty {
    display: flex; align-items: center; gap: 6px;
    padding: 16px; font-size: 13px; color: #adb5bd;
}
.partner-option {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 10px 14px; border: none;
    background: transparent; cursor: pointer; text-align: left;
    transition: background 0.1s; border-bottom: 1px solid #f2f4f6;
}
.partner-option:last-child { border-bottom: none; }
.partner-option:hover { background: #fdf0f4; }
.partner-option.selected { background: rgba(229,0,76,0.04); }

.partner-option-left { display: flex; align-items: center; gap: 10px; }
.partner-option-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: #e8edf2; color: #1F3A4D;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.partner-option-avatar.avatar-selected { background: #1F3A4D; color: #fff; }
.partner-option-name { font-size: 14px; font-weight: 500; color: #191c1e; }
.partner-option-type { font-size: 11px; color: #adb5bd; margin-top: 1px; }
.check-icon { font-size: 20px; color: #E5004C; }

/* Actions */
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
