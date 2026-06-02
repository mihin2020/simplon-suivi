<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, watch } from 'vue'

defineOptions({ layout: AdminLayout })

interface WhatsAppMsg {
    id: number
    phone: string
    recipient_name: string | null
    message: string
    provider: string
    status: 'sent' | 'failed'
    external_id: string | null
    error: string | null
    formation_name: string | null
    project_name: string | null
    created_at: string
}

interface Paginator {
    data: WhatsAppMsg[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    from: number | null
    to: number | null
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    messages: Paginator
    filters: { search?: string; status?: string }
}>()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')

let debounce: ReturnType<typeof setTimeout>
watch([search, status], () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => {
        router.get('/communication/whatsapp/history', {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
})

function formatDate(d: string) {
    return new Date(d).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
}

const expandedId = ref<number | null>(null)
</script>

<template>
    <Head title="Historique WhatsApp" />

    <div class="hist-page">
        <!-- En-tête -->
        <div class="hist-header">
            <div class="hist-header-icon">
                <span class="material-symbols-outlined">history</span>
            </div>
            <div>
                <h1 class="hist-title">Historique WhatsApp</h1>
                <p class="hist-sub">{{ messages.total }} message(s) enregistré(s)</p>
            </div>
            <Link href="/communication/whatsapp" class="hist-back-btn">
                <span class="material-symbols-outlined" style="font-size:17px">arrow_back</span>
                Retour à l'envoi
            </Link>
        </div>

        <!-- Filtres -->
        <div class="hist-filters">
            <div class="hist-search-wrap">
                <span class="material-symbols-outlined hist-search-icon">search</span>
                <input
                    v-model="search"
                    type="text"
                    class="hist-search"
                    placeholder="Rechercher par numéro, nom, formation, projet..."
                />
            </div>
            <select v-model="status" class="hist-select">
                <option value="">Tous les statuts</option>
                <option value="sent">Envoyés</option>
                <option value="failed">Échoués</option>
            </select>
        </div>

        <!-- Tableau -->
        <div class="hist-table-wrap">
            <div v-if="messages.data.length === 0" class="hist-empty">
                <span class="material-symbols-outlined" style="font-size:40px;color:#c8cdd3">chat_bubble</span>
                <p>Aucun message trouvé.</p>
            </div>

            <table v-else class="hist-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Destinataire</th>
                        <th>Numéro</th>
                        <th>Formation / Projet</th>
                        <th>Provider</th>
                        <th>Statut</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="msg in messages.data" :key="msg.id">
                        <tr :class="msg.status === 'failed' ? 'row-failed' : 'row-sent'">
                            <td class="td-date">{{ formatDate(msg.created_at) }}</td>
                            <td class="td-name">{{ msg.recipient_name ?? '—' }}</td>
                            <td class="td-phone">{{ msg.phone }}</td>
                            <td class="td-context">
                                <span v-if="msg.formation_name" class="tag-formation">{{ msg.formation_name }}</span>
                                <span v-if="msg.project_name" class="tag-project">{{ msg.project_name }}</span>
                                <span v-if="!msg.formation_name && !msg.project_name" class="text-muted">—</span>
                            </td>
                            <td><span class="tag-provider">{{ msg.provider }}</span></td>
                            <td>
                                <span class="badge-status" :class="msg.status === 'sent' ? 'badge-sent' : 'badge-failed'">
                                    <span class="material-symbols-outlined" style="font-size:13px">
                                        {{ msg.status === 'sent' ? 'check_circle' : 'cancel' }}
                                    </span>
                                    {{ msg.status === 'sent' ? 'Envoyé' : 'Échec' }}
                                </span>
                            </td>
                            <td class="td-msg">
                                <button class="msg-expand-btn" @click="expandedId = expandedId === msg.id ? null : msg.id">
                                    <span class="material-symbols-outlined" style="font-size:16px">
                                        {{ expandedId === msg.id ? 'expand_less' : 'expand_more' }}
                                    </span>
                                    Voir
                                </button>
                            </td>
                        </tr>
                        <!-- Ligne dépliée : message + erreur -->
                        <tr v-if="expandedId === msg.id" class="row-expanded">
                            <td colspan="7">
                                <div class="expanded-body">
                                    <div class="expanded-message">
                                        <strong>Message :</strong>
                                        <pre class="msg-pre">{{ msg.message }}</pre>
                                    </div>
                                    <div v-if="msg.error" class="expanded-error">
                                        <strong>Erreur :</strong> {{ msg.error }}
                                    </div>
                                    <div v-if="msg.external_id" class="expanded-sid">
                                        <strong>ID externe :</strong> <code>{{ msg.external_id }}</code>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="messages.last_page > 1" class="hist-pagination">
            <template v-for="link in messages.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="pag-btn"
                    :class="{ 'pag-active': link.active }"
                    v-html="link.label"
                />
                <span v-else class="pag-btn pag-disabled" v-html="link.label" />
            </template>
        </div>
    </div>
</template>

<style scoped>
.hist-page { padding: 28px 32px; max-width: 1300px; margin: 0 auto; }

.hist-header {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.hist-header-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: #dcfce7; color: #16a34a;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.hist-title { font-size: 1.4rem; font-weight: 700; color: #111; margin: 0; }
.hist-sub { font-size: .85rem; color: #6b7280; margin: 2px 0 0; }
.hist-back-btn {
    margin-left: auto; display: flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; border: 1px solid #d1d5db;
    background: #fff; color: #374151; font-size: .85rem; font-weight: 500;
    text-decoration: none; transition: background .15s;
}
.hist-back-btn:hover { background: #f3f4f6; }

.hist-filters {
    display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap;
}
.hist-search-wrap {
    position: relative; flex: 1; min-width: 220px;
}
.hist-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    font-size: 18px; color: #9ca3af;
}
.hist-search {
    width: 100%; padding: 8px 12px 8px 36px;
    border: 1px solid #d1d5db; border-radius: 8px;
    font-size: .875rem; color: #111; background: #fff;
}
.hist-search:focus { outline: none; border-color: #6366f1; }
.hist-select {
    padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: .875rem; color: #374151; background: #fff; cursor: pointer;
}

.hist-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e5e7eb; }
.hist-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.hist-table thead tr { background: #f9fafb; }
.hist-table th {
    padding: 10px 14px; text-align: left; font-weight: 600;
    color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
}
.hist-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.row-failed td { background: #fff5f5; }
.row-sent td { background: #fff; }
.row-expanded td { background: #f9fafb; }

.td-date { white-space: nowrap; color: #6b7280; font-size: .8rem; }
.td-name { font-weight: 500; color: #111; }
.td-phone { font-family: monospace; color: #374151; }
.td-context { display: flex; flex-wrap: wrap; gap: 4px; }

.tag-formation {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    background: #ede9fe; color: #6d28d9; font-size: .75rem; font-weight: 500;
}
.tag-project {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    background: #dbeafe; color: #1d4ed8; font-size: .75rem; font-weight: 500;
}
.tag-provider {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    background: #f3f4f6; color: #374151; font-size: .75rem; text-transform: capitalize;
}
.text-muted { color: #9ca3af; }

.badge-status {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 9px; border-radius: 20px; font-size: .78rem; font-weight: 600;
}
.badge-sent { background: #dcfce7; color: #16a34a; }
.badge-failed { background: #fee2e2; color: #dc2626; }

.msg-expand-btn {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 4px 10px; border-radius: 6px; border: 1px solid #d1d5db;
    background: #fff; color: #374151; font-size: .8rem; cursor: pointer;
}
.msg-expand-btn:hover { background: #f3f4f6; }

.expanded-body {
    padding: 12px 16px; display: flex; flex-direction: column; gap: 8px;
}
.msg-pre {
    margin: 6px 0 0; padding: 10px 14px; background: #f9fafb;
    border: 1px solid #e5e7eb; border-radius: 8px;
    font-family: inherit; font-size: .875rem; white-space: pre-wrap; color: #111;
}
.expanded-error { color: #dc2626; font-size: .875rem; }
.expanded-sid { color: #6b7280; font-size: .82rem; }

.hist-empty {
    padding: 48px; text-align: center; color: #9ca3af;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
}

.hist-pagination {
    display: flex; gap: 6px; flex-wrap: wrap; margin-top: 20px; justify-content: center;
}
.pag-btn {
    padding: 6px 12px; border-radius: 6px; border: 1px solid #d1d5db;
    background: #fff; color: #374151; font-size: .85rem;
    text-decoration: none; cursor: pointer;
}
.pag-active { background: #6366f1; border-color: #6366f1; color: #fff; }
.pag-disabled { color: #9ca3af; cursor: default; }
</style>
