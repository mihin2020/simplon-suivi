<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Partner {
    id: string
    name: string
    logo_path: string | null
}

interface Paginated {
    data: Partner[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
    from: number
    to: number
}

defineProps<{ partners: Paginated }>()

const confirmTarget = ref<Partner | null>(null)

const destroy = () => {
    if (!confirmTarget.value) return
    router.delete(`/partners/${confirmTarget.value.id}`, {
        onFinish: () => { confirmTarget.value = null },
    })
}
</script>

<template>
    <div class="max-w-[900px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <div class="page-header-icon">
                    <span class="material-symbols-outlined">handshake</span>
                </div>
                <div>
                    <h1 class="text-h1 font-bold text-on-surface">Partenaires</h1>
                    <p class="text-body-md text-secondary mt-xs">
                        {{ partners.total ?? 0 }} partenaire(s) configuré(s).
                    </p>
                </div>
            </div>
            <Link href="/partners/create" class="btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px">add</span>
                Ajouter un partenaire
            </Link>
        </div>

        <!-- Grille de cartes -->
        <div v-if="partners.data.length === 0" class="empty-state">
            <span class="material-symbols-outlined" style="font-size:48px;color:#ddd">handshake</span>
            <p class="text-body-md text-secondary mt-sm">Aucun partenaire configuré.</p>
            <Link href="/partners/create" class="btn-primary mt-md">Ajouter le premier</Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-md">
            <div
                v-for="partner in partners.data"
                :key="partner.id"
                class="partner-card group"
            >
                <!-- Logo -->
                <div class="partner-logo-wrap">
                    <img
                        v-if="partner.logo_path"
                        :src="`/storage/${partner.logo_path}`"
                        :alt="partner.name"
                        class="partner-logo"
                    />
                    <div v-else class="partner-logo-placeholder">
                        {{ partner.name.charAt(0).toUpperCase() }}
                    </div>
                </div>

                <!-- Infos -->
                <div class="partner-info">
                    <p class="partner-name">{{ partner.name }}</p>
                </div>

                <!-- Actions -->
                <div class="partner-actions opacity-0 group-hover:opacity-100 transition-opacity">
                    <Link :href="`/partners/${partner.id}/edit`" class="icon-btn" title="Modifier">
                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    </Link>
                    <button @click="confirmTarget = partner" class="icon-btn danger" title="Supprimer">
                        <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="partners.total > 20" class="flex items-center justify-center gap-xs">
            <template v-for="link in partners.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="page-btn" :class="{ 'page-active': link.active }" v-html="link.label" />
                <span v-else class="page-btn page-disabled" v-html="link.label" />
            </template>
        </div>

    </div>

    <!-- Modal de confirmation de suppression -->
    <Teleport to="body">
        <div v-if="confirmTarget" class="modal-backdrop" @click.self="confirmTarget = null">
            <div class="modal-box">
                <div class="modal-icon">
                    <span class="material-symbols-outlined" style="font-size:32px;color:#E5004C">delete</span>
                </div>
                <h3 class="modal-title">Supprimer le partenaire</h3>
                <p class="modal-body">
                    Voulez-vous vraiment supprimer <strong>« {{ confirmTarget.name }} »</strong> ?
                    Cette action est irréversible.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" @click="confirmTarget = null">Annuler</button>
                    <button class="btn-danger" @click="destroy">Supprimer</button>
                </div>
            </div>
        </div>
    </Teleport>

</template>

<style scoped>
.page-header-icon {
    display: flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #1F3A4D 0%, #2d5a7b 100%);
    color: #fff;
}
.page-header-icon .material-symbols-outlined { font-size: 24px; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #E5004C; color: #fff;
    border-radius: 8px; font-size: 11px; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    transition: background 0.2s; text-decoration: none;
}
.btn-primary:hover { background: #c0003e; }

/* Cartes */
.partner-card {
    background: #fff; border: 1px solid #e0e3e5; border-radius: 12px;
    padding: 20px; display: flex; flex-direction: column; align-items: center;
    gap: 12px; text-align: center; position: relative;
    transition: box-shadow 0.2s, border-color 0.2s;
}
.partner-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); border-color: #d0d3d5; }

.partner-logo-wrap {
    width: 72px; height: 72px; border-radius: 12px;
    border: 1px solid #e8edf2; background: #f7f9fb;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.partner-logo { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
.partner-logo-placeholder {
    width: 72px; height: 72px; border-radius: 12px;
    background: #1F3A4D; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: 700;
}

.partner-info { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.partner-name { font-size: 15px; font-weight: 600; color: #191c1e; }

.type-badge {
    display: inline-flex; align-items: center;
    padding: 2px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 600; letter-spacing: 0.04em;
    background: #f0f4ff; color: #1F3A4D; text-transform: uppercase;
}

.partner-actions {
    display: flex; align-items: center; gap: 4px;
    position: absolute; top: 10px; right: 10px;
}

.icon-btn {
    padding: 4px; color: #515f74; border-radius: 4px;
    transition: color 0.15s; display: inline-flex; background: none; border: none; cursor: pointer;
}
.icon-btn:hover { color: #E5004C; }
.icon-btn.danger:hover { color: #ba1a1a; }

/* Empty state */
.empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 64px 20px; background: #fff; border: 1px solid #e0e3e5;
    border-radius: 12px; text-align: center;
}

.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px; padding: 0 6px; border-radius: 4px;
    font-size: 13px; font-weight: 500; color: #191c1e;
    transition: background 0.15s; cursor: pointer; text-decoration: none;
}
.page-btn:hover { background: #eceef0; }
.page-active { background: #E5004C !important; color: #fff; }
.page-disabled { opacity: 0.4; cursor: default; }

/* Modal */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
}
.modal-box {
    background: #fff; border-radius: 16px; padding: 32px 28px;
    width: 100%; max-width: 400px; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-icon { margin-bottom: 12px; }
.modal-title { font-size: 18px; font-weight: 700; color: #191c1e; margin-bottom: 8px; }
.modal-body { font-size: 14px; color: #515f74; line-height: 1.6; margin-bottom: 24px; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.btn-cancel {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    border: 1.5px solid #d0d3d5; color: #515f74; background: #fff; cursor: pointer;
    transition: background 0.15s;
}
.btn-cancel:hover { background: #f4f5f6; }
.btn-danger {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    background: #E5004C; color: #fff; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-danger:hover { background: #c0003e; }
</style>
