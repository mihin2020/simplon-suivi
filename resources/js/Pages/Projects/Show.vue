<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Partner {
    id: string
    name: string
    logo_path: string | null
}

interface Formation {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    active_learners_count: number
}

interface Project {
    id: string
    name: string
    description: string | null
    started_at: string
    ended_at: string | null
    status: string
    formations: Formation[]
    partners: Partner[]
}

const props = defineProps<{
    project: Project
    allPartners: Partner[]
}>()

const showPartnersModal = ref(false)
const partnerSaved = ref(false)

// Association partenaires — réactif aux props Inertia
const partnerForm = useForm({
    partner_ids: props.project.partners.map(p => p.id),
})
watch(() => props.project.partners, (val) => {
    partnerForm.partner_ids = val.map(p => p.id)
})

const selectedPartners = computed(() =>
    props.allPartners.filter(p => partnerForm.partner_ids.includes(p.id))
)
const unselectedPartners = computed(() =>
    props.allPartners.filter(p => !partnerForm.partner_ids.includes(p.id))
)

const isSelected = (id: string) => partnerForm.partner_ids.includes(id)
const togglePartner = (id: string) => {
    partnerSaved.value = false
    if (isSelected(id)) {
        partnerForm.partner_ids = partnerForm.partner_ids.filter(pid => pid !== id)
    } else {
        partnerForm.partner_ids = [...partnerForm.partner_ids, id]
    }
}
const savePartners = () => {
    partnerForm.patch(`/projects/${props.project.id}/partners`, {
        preserveScroll: true,
        onSuccess: () => { partnerSaved.value = true },
    })
}

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

const statusLabels: Record<string, string> = {
    active: 'Actif',
    completed: 'Terminé',
    archived: 'Archivé',
}

const confirmFormation = ref<Formation | null>(null)

const destroyFormation = () => {
    if (!confirmFormation.value) return
    router.delete(`/formations/${confirmFormation.value.id}`, {
        onFinish: () => { confirmFormation.value = null },
    })
}
</script>

<template>
    <Head :title="project.name" />
    <div class="max-w-[1600px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg">
            <div class="flex items-start gap-md">
                <Link href="/projects" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div>
                    <div class="flex items-center gap-sm mb-xs">
                        <h1 class="text-h1 font-bold text-on-surface">{{ project.name }}</h1>
                        <span class="status-badge" :class="`status-${project.status}`">
                            {{ statusLabels[project.status] ?? project.status }}
                        </span>
                    </div>
                    <p v-if="project.description" class="text-body-md text-secondary">{{ project.description }}</p>
                    <div class="flex items-center gap-md mt-xs text-body-sm text-secondary">
                        <span class="flex items-center gap-xs">
                            <span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>
                            {{ fmt(project.started_at) }}
                        </span>
                        <span>→</span>
                        <span>{{ fmt(project.ended_at) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-sm flex-wrap">
                <!-- Onglet Partenaires -->
                <button class="tab-partners" @click="showPartnersModal = true">
                    <span class="material-symbols-outlined" style="font-size:18px">handshake</span>
                    Partenaires
                    <span class="tab-count">{{ project.partners?.length ?? 0 }}</span>
                </button>

                <Link :href="`/projects/${project.id}/edit`" class="btn-navy">
                    <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    Modifier
                </Link>
                <Link :href="`/projects/${project.id}/formations/create`" class="btn-primary">
                    <span class="material-symbols-outlined" style="font-size:18px">add_circle</span>
                    Ajouter une Formation
                </Link>
            </div>
        </div>

        <!-- Formations -->
        <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                <h2 class="text-h2 font-semibold text-on-surface">
                    Formations
                    <span class="ml-sm text-body-md font-normal text-secondary">({{ project.formations.length }})</span>
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface border-b border-surface-container-highest">
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Formation</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Début</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Fin</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-center">Apprenants</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide">Statut</th>
                            <th class="px-md py-sm text-label-caps text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-highest">
                        <tr v-if="project.formations.length === 0">
                            <td colspan="6" class="px-md py-xl text-center text-secondary text-body-md">
                                Aucune formation dans ce projet.
                                <Link :href="`/projects/${project.id}/formations/create`" class="text-primary font-semibold ml-xs">
                                    Créer la première formation →
                                </Link>
                            </td>
                        </tr>
                        <tr
                            v-for="formation in project.formations"
                            :key="formation.id"
                            class="hover:bg-surface-bright transition-colors group"
                        >
                            <td class="px-md py-sm">
                                <Link
                                    :href="`/formations/${formation.id}`"
                                    class="font-semibold text-on-surface hover:text-primary transition-colors"
                                >
                                    {{ formation.name }}
                                </Link>
                                <p v-if="formation.description" class="text-body-sm text-on-surface-variant mt-xs line-clamp-1">
                                    {{ formation.description }}
                                </p>
                            </td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(formation.started_at) }}</td>
                            <td class="px-md py-sm text-data-tabular text-on-surface-variant whitespace-nowrap">{{ fmt(formation.ended_at) }}</td>
                            <td class="px-md py-sm text-center">
                                <span class="learner-count">{{ formation.active_learners_count }}</span>
                            </td>
                            <td class="px-md py-sm">
                                <span class="status-badge" :class="`status-${formation.status}`">
                                    {{ statusLabels[formation.status] ?? formation.status }}
                                </span>
                            </td>
                            <td class="px-md py-sm text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="`/formations/${formation.id}/attendances`" class="icon-btn" title="Saisir les présences">
                                        <span class="material-symbols-outlined" style="font-size:18px">fact_check</span>
                                    </Link>
                                    <Link :href="`/formations/${formation.id}/edit`" class="icon-btn" title="Modifier">
                                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                    </Link>
                                    <button @click="confirmFormation = formation" class="icon-btn danger" title="Supprimer">
                                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ── Modal Partenaires ── -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="showPartnersModal" class="modal-backdrop" @click.self="showPartnersModal = false">
                <div class="modal">

                    <!-- Header -->
                    <div class="modal-header">
                        <div class="modal-header-left">
                            <div class="modal-icon">
                                <span class="material-symbols-outlined" style="font-size:22px">handshake</span>
                            </div>
                            <div>
                                <h2 class="modal-title">Partenaires du projet</h2>
                                <p class="modal-subtitle">{{ project.name }}</p>
                            </div>
                        </div>
                        <button class="modal-close" @click="showPartnersModal = false">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">

                        <!-- Aucun partenaire dans le système -->
                        <div v-if="allPartners.length === 0" class="modal-empty">
                            <div class="modal-empty-icon">
                                <span class="material-symbols-outlined" style="font-size:36px">handshake</span>
                            </div>
                            <p class="modal-empty-title">Aucun partenaire disponible</p>
                            <p class="modal-empty-hint">Créez d'abord des partenaires depuis la gestion.</p>
                            <div class="modal-empty-actions">
                                <Link href="/partners" class="btn-primary" @click="showPartnersModal = false">
                                    <span class="material-symbols-outlined" style="font-size:16px">add_circle</span>
                                    Créer un partenaire
                                </Link>
                            </div>
                        </div>

                        <template v-else>
                            <!-- Banner succès -->
                            <div v-if="partnerSaved" class="partner-saved-banner">
                                <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
                                Partenaires enregistrés avec succès.
                            </div>

                            <!-- Partenaires associés -->
                            <div class="partner-section-label">
                                Associés
                                <span class="partner-section-count">{{ selectedPartners.length }}</span>
                            </div>
                            <div v-if="selectedPartners.length === 0" class="partner-section-empty">
                                Aucun partenaire associé — cliquez sur un partenaire ci-dessous pour l'ajouter.
                            </div>
                            <div v-else class="partner-chips-row">
                                <div v-for="p in selectedPartners" :key="p.id" class="partner-chip-item">
                                    <div class="partner-chip-avatar">
                                        <img v-if="p.logo_path" :src="`/storage/${p.logo_path}`" :alt="p.name" class="partner-logo-img" />
                                        <span v-else>{{ p.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <span class="partner-chip-name">{{ p.name }}</span>
                                    <button class="partner-chip-remove" @click="togglePartner(p.id)" title="Retirer">
                                        <span class="material-symbols-outlined" style="font-size:14px">close</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Partenaires disponibles à ajouter -->
                            <template v-if="unselectedPartners.length > 0">
                                <div class="partner-section-label" style="margin-top:16px">
                                    Disponibles
                                    <span class="partner-section-count">{{ unselectedPartners.length }}</span>
                                </div>
                                <div class="partner-select-list">
                                    <div
                                        v-for="partner in unselectedPartners"
                                        :key="partner.id"
                                        class="partner-select-row"
                                        @click="togglePartner(partner.id)"
                                    >
                                        <div class="partner-select-avatar">
                                            <img v-if="partner.logo_path" :src="`/storage/${partner.logo_path}`" :alt="partner.name" class="partner-logo-img" />
                                            <span v-else>{{ partner.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <span class="partner-select-name">{{ partner.name }}</span>
                                        <span class="material-symbols-outlined" style="font-size:20px;color:#d0d5db">add_circle</span>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <span class="modal-footer-info">
                            <span class="material-symbols-outlined" style="font-size:16px">info</span>
                            {{ partnerForm.partner_ids.length }} partenaire(s) sélectionné(s)
                        </span>
                        <button v-if="allPartners.length > 0" class="btn-primary" @click="savePartners" :disabled="partnerForm.processing">
                            <span class="material-symbols-outlined" style="font-size:16px">save</span>
                            {{ partnerForm.processing ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Modale suppression formation -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="confirmFormation" class="del-overlay" @click.self="confirmFormation = null">
                <div class="del-box">
                    <div class="del-head">
                        <div class="del-warn-icon">
                            <span class="material-symbols-outlined" style="font-size:20px">warning</span>
                        </div>
                        <h3 class="del-title">Supprimer cette formation ?</h3>
                    </div>
                    <p class="del-msg">La formation <strong>« {{ confirmFormation.name }} »</strong> sera supprimée. Cette action est irréversible.</p>
                    <div class="del-foot">
                        <button class="del-cancel" @click="confirmFormation = null">Annuler</button>
                        <button class="del-delete" @click="destroyFormation">Supprimer</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.icon-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid #1F3A4D; color: #1F3A4D; background: transparent;
    text-decoration: none; flex-shrink: 0; margin-top: 4px;
    transition: background 0.15s, color 0.15s;
}
.icon-back:hover { background: #1F3A4D; color: #fff; }
.btn-navy {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #1F3A4D;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    border: 1.5px solid #1F3A4D; transition: background 0.15s, color 0.15s; text-decoration: none;
}
.btn-navy:hover { background: #1F3A4D; color: #fff; }

/* ── Onglet Partenaires ── */
.tab-partners {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 14px;
    background: #fff;
    border: 1.5px solid #e0e3e5;
    border-radius: 8px;
    font-size: 13px; font-weight: 600; color: #1F3A4D;
    cursor: pointer; transition: all 0.15s;
}
.tab-partners:hover {
    border-color: #1F3A4D;
    background: #f0f4f8;
    box-shadow: 0 2px 8px rgba(31,58,77,0.10);
}
.tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 99px;
    background: #1F3A4D; color: #fff;
    font-size: 11px; font-weight: 700;
}

/* ── Statuts ── */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 600;
}
.status-badge::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: currentColor; flex-shrink: 0;
}
.status-active    { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-archived  { background: #f3f4f6; color: #6b7280; }

.learner-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 28px; height: 28px; padding: 0 6px;
    background: #f2f4f6; border-radius: 99px;
    font-size: 13px; font-weight: 600; color: #191c1e;
}

/* ── Boutons ── */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    transition: background 0.2s; text-decoration: none; border: none; cursor: pointer;
}
.btn-primary:hover { background: #c0003e; }

.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: transparent; color: #515f74;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    border: 1px solid #e0e3e5; transition: background 0.15s; text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.btn-secondary-sm {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; background: transparent; color: #515f74;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    border: 1px solid #e0e3e5; transition: background 0.15s; text-decoration: none;
}
.btn-secondary-sm:hover { background: #f2f4f6; }

.icon-btn {
    padding: 4px; color: #515f74; border-radius: 4px;
    transition: color 0.15s; display: inline-flex; background: none; border: none; cursor: pointer;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }

/* ── Modal ── */
.modal-backdrop {
    position: fixed; inset: 0; z-index: 100;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.modal {
    background: #fff; border-radius: 20px;
    width: 100%; max-width: 560px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.18);
    display: flex; flex-direction: column;
    max-height: 80vh; overflow: hidden;
}

.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 28px 20px;
    border-bottom: 1px solid #f0f2f5;
}
.modal-header-left { display: flex; align-items: center; gap: 14px; }
.modal-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: rgba(31,58,77,0.08); color: #1F3A4D;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; }
.modal-subtitle { font-size: 13px; color: #adb5bd; margin-top: 2px; }
.modal-close {
    width: 36px; height: 36px; border-radius: 50%;
    background: #f2f4f6; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #515f74; transition: background 0.15s; flex-shrink: 0;
}
.modal-close:hover { background: #e0e3e5; color: #191c1e; }

.modal-body { flex: 1; overflow-y: auto; padding: 24px 28px; }

/* Vide */
.modal-empty {
    display: flex; flex-direction: column; align-items: center;
    padding: 32px 20px; text-align: center;
}
.modal-empty-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #f2f4f6; color: #d0d3d5;
    display: flex; align-items: center; justify-content: center; margin-bottom: 16px;
}
.modal-empty-title { font-size: 16px; font-weight: 600; color: #191c1e; }
.modal-empty-hint { font-size: 13px; color: #9aaabb; margin-top: 4px; }
.modal-empty-actions { display: flex; align-items: center; gap: 10px; margin-top: 20px; }

/* Grille partenaires */
/* liste sélection partenaires */
.partner-select-hint { font-size: 12px; color: #9aaabb; margin-bottom: 10px; }
.partner-select-list { display: flex; flex-direction: column; gap: 4px; }
.partner-select-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 8px; cursor: pointer;
    border: 1.5px solid transparent; transition: background 0.12s, border-color 0.12s;
}
.partner-select-row:hover { background: #f6f8fa; }
.partner-select-row.partner-selected { background: #fff0f4; border-color: #ffc0d0; }
.partner-select-avatar {
    width: 36px; height: 36px; border-radius: 8px;
    background: #e8edf2; color: #1F3A4D;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 15px; flex-shrink: 0; overflow: hidden;
}
.partner-select-avatar.avatar-on { background: #1F3A4D; color: #fff; }
.partner-select-name { flex: 1; font-size: 14px; font-weight: 500; color: #191c1e; }
.partner-select-check { font-size: 20px; color: #d0d5db; }
.partner-select-row.partner-selected .partner-select-check { color: #E5004C; }

.partners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 16px;
}
.partner-card {
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    padding: 20px 12px; border-radius: 14px;
    border: 1px solid #f0f2f5; background: #fafbfc;
    text-align: center; transition: box-shadow 0.15s, border-color 0.15s;
}
.partner-card:hover { border-color: #d8dde3; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

.partner-logo {
    width: 64px; height: 64px; border-radius: 12px;
    border: 1px solid #e8edf2; background: #fff;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.partner-logo-img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
.partner-logo-initial {
    font-size: 24px; font-weight: 700; color: #fff; background: #1F3A4D;
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center; border-radius: 12px;
}
.partner-name { font-size: 13px; font-weight: 600; color: #191c1e; line-height: 1.3; }

.modal-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 28px;
    border-top: 1px solid #f0f2f5;
    background: #fafbfc;
}
.modal-footer-info {
    display: flex; align-items: center; gap: 5px;
    font-size: 13px; color: #adb5bd;
}

/* ── Transition ── */
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform 0.25s ease, opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal { transform: scale(0.95) translateY(8px); opacity: 0; }
.modal-leave-to .modal { transform: scale(0.95) translateY(8px); opacity: 0; }

.del-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.del-box {
    background: #fff; border-radius: 14px; padding: 28px 28px 22px;
    width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.18);
}
.del-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.del-warn-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: #fff0f4; color: #E5004C;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.del-title { font-size: 15px; font-weight: 700; color: #191c1e; }
.del-msg { font-size: 13px; color: #515f74; margin-bottom: 20px; line-height: 1.5; }
.del-foot { display: flex; justify-content: flex-end; gap: 10px; }
.del-cancel {
    padding: 8px 18px; background: #f2f4f6; color: #515f74;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.del-cancel:hover { background: #e0e3e5; }
.del-delete {
    padding: 8px 18px; background: #E5004C; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.del-delete:hover { background: #c0003e; }

/* ── Partners modal redesign ── */
.partner-saved-banner {
    display: flex; align-items: center; gap: 6px;
    padding: 10px 14px; margin-bottom: 14px;
    background: #f0fdf4; border: 1px solid #bbf7d0;
    border-radius: 8px; font-size: 13px; font-weight: 600; color: #166534;
}
.partner-section-label {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700; color: #9aaabb;
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 8px;
}
.partner-section-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 5px;
    background: #e8edf2; color: #1F3A4D;
    border-radius: 9px; font-size: 11px; font-weight: 700;
}
.partner-section-empty {
    font-size: 12px; color: #adb5bd;
    padding: 10px 0; margin-bottom: 4px;
}
.partner-chips-row {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-bottom: 4px;
}
.partner-chip-item {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 8px 5px 6px;
    background: #fff0f4; border: 1px solid #ffc0d0;
    border-radius: 20px; font-size: 13px;
}
.partner-chip-avatar {
    width: 22px; height: 22px; border-radius: 50%;
    background: #E5004C; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; flex-shrink: 0; overflow: hidden;
}
.partner-chip-name { font-size: 13px; font-weight: 500; color: #1F3A4D; }
.partner-chip-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 50%;
    border: none; background: #ffc0d0; color: #ba1a1a;
    cursor: pointer; padding: 0; transition: background 0.12s;
}
.partner-chip-remove:hover { background: #E5004C; color: #fff; }
</style>
