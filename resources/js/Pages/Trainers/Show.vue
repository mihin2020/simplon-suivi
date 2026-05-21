<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Formation {
    id: string
    name: string
    status: string
    project: { id: string; name: string }
    pivot: { is_lead: boolean; assigned_at: string }
}

interface User {
    first_name: string
    last_name: string
    email: string
    is_active: boolean
    last_login_at: string | null
}

interface Profile {
    id: string
    name: string
}

interface Trainer {
    id: string
    phone: string | null
    phone2: string | null
    cv_path: string | null
    profile: Profile | null
    user: User
    formations: Formation[]
}

const props = defineProps<{
    trainer: Trainer
}>()

const form = useForm({})

const resendInvitation = () => {
    if (!confirm('Renvoyer l\'invitation à ce formateur ?')) return
    form.post(`/trainers/${props.trainer.id}/resend-invitation`)
}

const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''

const statusLabels: Record<string, string> = {
    active: 'Active',
    completed: 'Terminée',
    archived: 'Archivée',
}
</script>

<template>
    <div class="max-w-[1400px] mx-auto space-y-xl">

        <!-- En-tête -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-md">
                <Link href="/trainers" class="icon-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </Link>
                <div class="flex items-center gap-md">
                    <div class="avatar-lg">
                        {{ trainer.user.first_name.charAt(0) }}{{ trainer.user.last_name.charAt(0) }}
                    </div>
                    <div>
                        <h1 class="text-h1 font-bold text-on-surface">
                            {{ trainer.user.last_name }} {{ trainer.user.first_name }}
                        </h1>
                        <div class="flex items-center gap-sm mt-xs">
                            <span class="active-badge" :class="trainer.user.is_active ? 'badge-active' : 'badge-pending'">
                                {{ trainer.user.is_active ? 'Compte actif' : 'Invitation en attente' }}
                            </span>
                            <span v-if="trainer.profile" class="text-body-sm text-secondary">
                                · {{ trainer.profile.name }}
                            </span>
                            <button v-if="!trainer.user.is_active" @click="resendInvitation" class="btn-resend" :disabled="form.processing">
                                <span class="material-symbols-outlined" style="font-size:16px">send</span>
                                {{ form.processing ? 'Envoi...' : 'Renvoyer l\'invitation' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <Link :href="`/trainers/${trainer.id}/edit`" class="btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                Modifier
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-xl">

            <!-- Infos -->
            <div class="space-y-xl">
                <div class="card">
                    <h2 class="section-title">Informations</h2>
                    <dl class="info-list">
                        <div class="info-row">
                            <dt>Email</dt>
                            <dd>{{ trainer.user.email }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Téléphone 1</dt>
                            <dd>{{ trainer.phone ?? '' }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Téléphone 2</dt>
                            <dd>{{ trainer.phone2 ?? '' }}</dd>
                        </div>
                        <div class="info-row">
                            <dt>Profil</dt>
                            <dd>{{ trainer.profile?.name ?? '' }}</dd>
                        </div>
                        <div class="info-row" v-if="trainer.cv_path">
                            <dt>CV</dt>
                            <dd>
                                <a :href="`/storage/${trainer.cv_path}`" target="_blank" class="cv-link">
                                    <span class="material-symbols-outlined" style="font-size:16px">description</span>
                                    Voir le CV
                                </a>
                            </dd>
                        </div>
                        <div class="info-row">
                            <dt>Dernière connexion</dt>
                            <dd>{{ fmt(trainer.user.last_login_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Formations -->
            <div class="lg:col-span-2">
                <div class="bg-surface-container-lowest border border-surface-container-highest rounded-xl overflow-hidden shadow-sm">
                    <div class="px-lg py-md border-b border-surface-container-highest flex items-center justify-between">
                        <h2 class="text-h2 font-semibold text-on-surface">
                            Formations assignées
                            <span class="count-badge ml-sm">{{ trainer.formations.length }}</span>
                        </h2>
                    </div>
                    <div class="divide-y divide-surface-container-highest">
                        <div v-if="trainer.formations.length === 0" class="px-lg py-xl text-center text-secondary text-body-md">
                            Aucune formation assignée.
                        </div>
                        <div
                            v-for="formation in trainer.formations"
                            :key="formation.id"
                            class="px-lg py-md flex items-center justify-between gap-md hover:bg-surface-bright transition-colors"
                        >
                            <div>
                                <div class="flex items-center gap-sm">
                                    <Link
                                        :href="`/formations/${formation.id}`"
                                        class="font-semibold text-on-surface hover:text-primary transition-colors"
                                    >
                                        {{ formation.name }}
                                    </Link>
                                    <span v-if="formation.pivot.is_lead" class="lead-badge">Principal</span>
                                </div>
                                <div class="flex items-center gap-sm mt-xs text-body-sm text-secondary">
                                    <Link :href="`/projects/${formation.project.id}`" class="hover:text-primary">
                                        {{ formation.project.name }}
                                    </Link>
                                    <span>·</span>
                                    <span>Assigné le {{ fmt(formation.pivot.assigned_at) }}</span>
                                </div>
                            </div>
                            <span class="status-badge" :class="`status-${formation.status}`">
                                {{ statusLabels[formation.status] ?? formation.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
}
.icon-back:hover { background: #eceef0; color: #191c1e; }

.avatar-lg {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #1F3A4D;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
}

.active-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.badge-active  { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }

.card { background: #fff; border: 1px solid #e0e3e5; border-radius: 12px; padding: 24px; }

.section-title {
    font-size: 11px;
    font-weight: 700;
    color: #515f74;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 16px;
}

.info-list { display: flex; flex-direction: column; gap: 12px; }
.info-row { display: flex; flex-direction: column; gap: 2px; }
.info-row dt { font-size: 11px; font-weight: 600; color: #9aaabb; text-transform: uppercase; letter-spacing: 0.04em; }
.info-row dd { font-size: 14px; color: #191c1e; }

.cv-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #E5004C;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s;
}
.cv-link:hover { color: #c0003e; text-decoration: underline; }

.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    background: #f2f4f6;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    color: #515f74;
}

.lead-badge {
    display: inline-block;
    padding: 1px 6px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}
.status-active    { background: #d1fae5; color: #065f46; }
.status-completed { background: #dbeafe; color: #1e40af; }
.status-archived  { background: #f3f4f6; color: #6b7280; }

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    color: #515f74;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #e0e3e5;
    transition: background 0.15s;
    text-decoration: none;
}
.btn-secondary:hover { background: #f2f4f6; }

.btn-resend { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.btn-resend:hover { background: #bfdbfe; border-color: #60a5fa; }
.btn-resend:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
