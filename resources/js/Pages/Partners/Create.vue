<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PartnerCategoryBadge from '@/Components/PartnerCategoryBadge.vue'

defineOptions({ layout: AdminLayout })

interface CategoryOption {
    value: string
    label: string
    color: string
}

defineProps<{ categories: CategoryOption[] }>()

const form = useForm({
    name: '',
    category: '' as string,
    logo: null as File | null,
    contact_first_name: '',
    contact_last_name: '',
    contact_email: '',
    contact_phone: '',
    contact_profile: '',
    contact_position: '',
})

const previewUrl = ref<string | null>(null)
const isDragging = ref(false)

const onLogoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    if (file) setLogo(file)
}

const onDrop = (e: DragEvent) => {
    isDragging.value = false
    const file = e.dataTransfer?.files?.[0] ?? null
    if (file && file.type.startsWith('image/')) setLogo(file)
}

const setLogo = (file: File) => {
    form.logo = file
    previewUrl.value = URL.createObjectURL(file)
}

const removeLogo = () => {
    form.logo = null
    previewUrl.value = null
}

const submit = () => form.post('/partners', { forceFormData: true })
</script>

<template>
    <Head title="Créer un partenaire" />
    <div class="page-wrapper">

        <!-- Titre -->
        <div class="page-title-row">
            <Link href="/partners" class="icon-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <div>
                <h1 class="page-title">Nouveau Partenaire</h1>
                <p class="page-subtitle">Configurez les informations de votre partenaire.</p>
            </div>
        </div>

        <!-- Formulaire deux colonnes -->
        <form @submit.prevent="submit" class="form-grid">

            <!-- ── Colonne gauche : Logo ── -->
            <div class="logo-panel">
                <div class="logo-panel-header">
                    <span class="material-symbols-outlined panel-icon">add_photo_alternate</span>
                    <div>
                        <p class="panel-title">Logo du partenaire</p>
                        <p class="panel-subtitle">Représentation visuelle</p>
                    </div>
                </div>

                <!-- Zone d'upload / Preview -->
                <div class="logo-drop-area">
                    <!-- Preview -->
                    <div v-if="previewUrl" class="logo-preview-container">
                        <div class="logo-preview-frame">
                            <img :src="previewUrl" alt="Logo" class="logo-img" />
                        </div>
                        <div class="logo-preview-actions">
                            <label class="logo-action-btn replace-btn">
                                <span class="material-symbols-outlined" style="font-size:16px">upload</span>
                                Remplacer
                                <input type="file" accept="image/*" class="sr-only" @change="onLogoChange" />
                            </label>
                            <button type="button" class="logo-action-btn remove-btn" @click="removeLogo">
                                <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                                Supprimer
                            </button>
                        </div>
                    </div>

                    <!-- Drop zone -->
                    <label
                        v-else
                        class="drop-zone"
                        :class="{ 'dragging': isDragging, 'has-error': form.errors.logo }"
                        @dragover.prevent="isDragging = true"
                        @dragleave="isDragging = false"
                        @drop.prevent="onDrop"
                    >
                        <div class="drop-zone-inner">
                            <div class="drop-icon-wrap">
                                <span class="material-symbols-outlined drop-icon">cloud_upload</span>
                            </div>
                            <p class="drop-title">Déposer le logo ici</p>
                            <p class="drop-or">ou</p>
                            <span class="drop-browse">Parcourir les fichiers</span>
                            <p class="drop-hint">PNG, JPG, WEBP, SVG · Max 2 Mo</p>
                        </div>
                        <input type="file" accept="image/*" class="sr-only" @change="onLogoChange" />
                    </label>
                </div>

                <p v-if="form.errors.logo" class="error-msg mt-sm">{{ form.errors.logo }}</p>
            </div>

            <!-- ── Colonne droite : Infos ── -->
            <div class="info-panel">
                <div class="info-panel-header">
                    <span class="material-symbols-outlined panel-icon">business</span>
                    <div>
                        <p class="panel-title">Informations générales</p>
                        <p class="panel-subtitle">Identité du partenaire</p>
                    </div>
                </div>

                <div class="fields-wrap">

                    <!-- Nom -->
                    <div class="field">
                        <label class="label">
                            Nom du partenaire
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrap" :class="{ 'input-focused': false }">
                            <input
                                v-model="form.name"
                                type="text"
                                class="input"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="Ex : ENABEL, AFD, Orange BF, GIZ..."
                                autofocus
                            />
                        </div>
                        <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
                    </div>

                    <!-- Catégorie -->
                    <div class="field">
                        <label class="label">
                            Catégorie
                            <span class="required">*</span>
                        </label>
                        <div class="category-options">
                            <label
                                v-for="cat in categories"
                                :key="cat.value"
                                class="category-option"
                                :class="{ selected: form.category === cat.value, 'has-error': form.errors.category }"
                            >
                                <input
                                    v-model="form.category"
                                    type="radio"
                                    :value="cat.value"
                                    class="sr-only"
                                />
                                <PartnerCategoryBadge :category="cat.value" />
                            </label>
                        </div>
                        <p v-if="form.errors.category" class="error-msg">{{ form.errors.category }}</p>
                    </div>

                    <!-- Contact Section -->
                    <div class="contact-section">
                        <div class="contact-header">
                            <span class="material-symbols-outlined contact-icon">person</span>
                            <div>
                                <p class="contact-title">Personne de contact</p>
                                <p class="contact-subtitle">Informations du contact source</p>
                            </div>
                        </div>

                        <div class="contact-grid">
                            <!-- Nom -->
                            <div class="field">
                                <label class="label">Nom</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_last_name"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_last_name }"
                                        placeholder="Nom du contact"
                                    />
                                </div>
                                <p v-if="form.errors.contact_last_name" class="error-msg">{{ form.errors.contact_last_name }}</p>
                            </div>

                            <!-- Prénom -->
                            <div class="field">
                                <label class="label">Prénom</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_first_name"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_first_name }"
                                        placeholder="Prénom du contact"
                                    />
                                </div>
                                <p v-if="form.errors.contact_first_name" class="error-msg">{{ form.errors.contact_first_name }}</p>
                            </div>

                            <!-- Email -->
                            <div class="field">
                                <label class="label">Email</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_email"
                                        type="email"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_email }"
                                        placeholder="email@exemple.com"
                                    />
                                </div>
                                <p v-if="form.errors.contact_email" class="error-msg">{{ form.errors.contact_email }}</p>
                            </div>

                            <!-- Téléphone -->
                            <div class="field">
                                <label class="label">Téléphone</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_phone"
                                        type="tel"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_phone }"
                                        placeholder="+XX XX XX XX XX"
                                    />
                                </div>
                                <p v-if="form.errors.contact_phone" class="error-msg">{{ form.errors.contact_phone }}</p>
                            </div>

                            <!-- Profil -->
                            <div class="field">
                                <label class="label">Profil</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_profile"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_profile }"
                                        placeholder="Ex : Manager, RH, DG..."
                                    />
                                </div>
                                <p v-if="form.errors.contact_profile" class="error-msg">{{ form.errors.contact_profile }}</p>
                            </div>

                            <!-- Poste -->
                            <div class="field">
                                <label class="label">Poste</label>
                                <div class="input-wrap">
                                    <input
                                        v-model="form.contact_position"
                                        type="text"
                                        class="input"
                                        :class="{ 'input-error': form.errors.contact_position }"
                                        placeholder="Titre du poste"
                                    />
                                </div>
                                <p v-if="form.errors.contact_position" class="error-msg">{{ form.errors.contact_position }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Aperçu de la carte -->
                    <div class="preview-card">
                        <p class="preview-label">Aperçu de la carte</p>
                        <div class="preview-inner">
                            <div class="preview-logo">
                                <img v-if="previewUrl" :src="previewUrl" alt="" class="preview-logo-img" />
                                <span v-else class="preview-logo-initial">
                                    {{ form.name ? form.name.charAt(0).toUpperCase() : '?' }}
                                </span>
                            </div>
                            <div>
                                <p class="preview-name">{{ form.name || 'Nom du partenaire' }}</p>
                                <PartnerCategoryBadge
                                    v-if="form.category"
                                    :category="form.category"
                                    size="sm"
                                    class="mt-xs"
                                />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <Link href="/partners" class="btn-secondary">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                        Annuler
                    </Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner" />
                        <span v-else class="material-symbols-outlined" style="font-size:18px">check</span>
                        Créer le partenaire
                    </button>
                </div>
            </div>

        </form>
    </div>
</template>

<style scoped>
/* ── Layout ── */
.page-wrapper {
    max-width: 960px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Titre page */
.page-title-row {
    display: flex;
    align-items: center;
    gap: 16px;
}
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
.page-title { font-size: 24px; font-weight: 700; color: #191c1e; line-height: 1.2; }
.page-subtitle { font-size: 14px; color: #515f74; margin-top: 2px; }

/* ── Grille deux colonnes ── */
.form-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
}

/* ── Panneaux ── */
.logo-panel,
.info-panel {
    background: #fff;
    border: 1px solid #e8edf2;
    border-radius: 16px;
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.logo-panel-header,
.info-panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f2f5;
}
.panel-icon {
    font-size: 22px;
    color: #E5004C;
    background: rgba(229,0,76,0.08);
    border-radius: 8px;
    padding: 6px;
}
.panel-title { font-size: 15px; font-weight: 700; color: #191c1e; }
.panel-subtitle { font-size: 12px; color: #adb5bd; margin-top: 1px; }

/* ── Drop zone ── */
.logo-drop-area { flex: 1; }

.drop-zone {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 240px;
    border: 2px dashed #d8dde3;
    border-radius: 12px;
    background: #fafbfc;
    cursor: pointer;
    transition: all 0.2s;
}
.drop-zone:hover,
.drop-zone.dragging {
    border-color: #E5004C;
    background: #fff5f8;
}
.drop-zone.has-error { border-color: #ba1a1a; }

.drop-zone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 32px 20px;
    pointer-events: none;
}
.drop-icon-wrap {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(229,0,76,0.07);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 4px;
}
.drop-icon { font-size: 32px; color: #E5004C; }
.drop-title { font-size: 15px; font-weight: 600; color: #191c1e; }
.drop-or { font-size: 12px; color: #adb5bd; }
.drop-browse {
    display: inline-block;
    padding: 6px 16px;
    background: #E5004C;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}
.drop-hint { font-size: 11px; color: #adb5bd; margin-top: 4px; }

/* Preview logo */
.logo-preview-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 24px 0;
}
.logo-preview-frame {
    width: 160px; height: 160px;
    border-radius: 16px;
    border: 1px solid #e8edf2;
    background: #fff;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    padding: 12px;
}
.logo-img { width: 100%; height: 100%; object-fit: contain; }
.logo-preview-actions { display: flex; gap: 8px; }
.logo-action-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 8px;
    font-size: 13px; font-weight: 500;
    cursor: pointer; border: 1px solid #e0e3e5;
    transition: all 0.15s; text-decoration: none;
}
.replace-btn { background: #fff; color: #515f74; }
.replace-btn:hover { background: #f2f4f6; border-color: #d0d3d5; }
.remove-btn { background: #fff5f5; color: #ba1a1a; border-color: #fbc4c4; }
.remove-btn:hover { background: #fee2e2; }

/* ── Champs ── */
.fields-wrap { display: flex; flex-direction: column; gap: 20px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.label {
    font-size: 12px; font-weight: 700; color: #191c1e;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.required { color: #E5004C; }
.field-hint { font-size: 12px; color: #adb5bd; }

.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.input {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #e0e3e5;
    border-radius: 10px;
    font-size: 14px;
    color: #191c1e;
    background: #fafbfc;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.input:focus {
    border-color: #E5004C;
    box-shadow: 0 0 0 4px rgba(229,0,76,0.08);
    background: #fff;
}
.input-error { border-color: #ba1a1a; }
.error-msg { font-size: 12px; color: #ba1a1a; }

/* ── Aperçu carte ── */
.preview-card {
    border: 1px solid #f0f2f5;
    border-radius: 12px;
    padding: 16px;
    background: #fafbfc;
}
.preview-label {
    font-size: 11px; font-weight: 700; color: #adb5bd;
    text-transform: uppercase; letter-spacing: 0.06em;
    margin-bottom: 12px;
}
.preview-inner {
    display: flex; align-items: center; gap: 12px;
}
.preview-logo {
    width: 48px; height: 48px; border-radius: 10px;
    border: 1px solid #e8edf2; background: #fff;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.preview-logo-img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
.preview-logo-initial {
    font-size: 20px; font-weight: 700;
    color: #fff; background: #1F3A4D;
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
}
.preview-name { font-size: 14px; font-weight: 600; color: #191c1e; }
.mt-xs { margin-top: 4px; }

.category-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.category-option {
    cursor: pointer;
    padding: 8px 12px;
    border: 1.5px solid #e0e3e5;
    border-radius: 10px;
    background: #fafbfc;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.category-option:hover { border-color: #c8cdd3; background: #fff; }
.category-option.selected {
    border-color: #E5004C;
    background: #fff5f8;
    box-shadow: 0 0 0 3px rgba(229, 0, 76, 0.08);
}
.category-option.has-error { border-color: #ba1a1a; }

.preview-type {
    display: inline-block; margin-top: 3px;
    padding: 2px 8px; border-radius: 99px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.06em;
    background: #eef2f8; color: #1F3A4D; text-transform: uppercase;
}

/* ── Actions ── */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #f0f2f5;
    margin-top: auto;
}
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 11px 24px; background: #E5004C; color: #fff;
    border-radius: 10px; font-size: 14px; font-weight: 600;
    transition: background 0.2s, box-shadow 0.2s;
    border: none; cursor: pointer;
}
.btn-primary:hover:not(:disabled) {
    background: #c0003e;
    box-shadow: 0 4px 12px rgba(229,0,76,0.3);
}
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 11px 20px; background: transparent; color: #515f74;
    border-radius: 10px; font-size: 14px; font-weight: 500;
    border: 1.5px solid #e0e3e5; transition: all 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; border-color: #d0d3d5; }

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Contact Section ── */
.contact-section {
    border: 1px solid #e8edf2;
    border-radius: 12px;
    padding: 20px;
    background: #fafbfc;
}
.contact-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e8edf2;
}
.contact-icon {
    font-size: 22px;
    color: #E5004C;
    background: rgba(229,0,76,0.08);
    border-radius: 8px;
    padding: 6px;
}
.contact-title { font-size: 14px; font-weight: 700; color: #191c1e; }
.contact-subtitle { font-size: 12px; color: #adb5bd; margin-top: 1px; }

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 640px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
}
</style>
