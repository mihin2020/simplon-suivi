<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    status: number
}>()

const content = computed(() => {
    return {
        403: {
            title: 'Action non autorisée',
            description: "Vous n'avez pas la permission d'accéder à cette page ou d'effectuer cette action.",
            icon: 'block',
        },
        404: {
            title: 'Page introuvable',
            description: "La page que vous recherchez n'existe pas ou a été déplacée.",
            icon: 'search_off',
        },
        500: {
            title: 'Erreur serveur',
            description: "Une erreur inattendue est survenue. Veuillez réessayer dans quelques instants.",
            icon: 'error',
        },
        503: {
            title: 'Service indisponible',
            description: "L'application est en maintenance. Merci de revenir un peu plus tard.",
            icon: 'build',
        },
    }[props.status] ?? {
        title: 'Une erreur est survenue',
        description: "Quelque chose s'est mal passé.",
        icon: 'error',
    }
})
</script>

<template>
    <Head :title="content.title" />
    <div class="error-wrapper">
        <div class="error-inner">
            <div class="error-logo">
                <img src="/images/logo.jpeg" alt="Simplon Burkina Faso" />
            </div>

            <div class="error-card">
                <span class="material-symbols-outlined error-icon">{{ content.icon }}</span>
                <p class="error-status">{{ status }}</p>
                <h1 class="error-title">{{ content.title }}</h1>
                <p class="error-description">{{ content.description }}</p>

                <Link href="/" class="error-btn">
                    <span class="material-symbols-outlined" style="font-size:18px">home</span>
                    Retour à l'accueil
                </Link>
            </div>

            <p class="error-footer">© {{ new Date().getFullYear() }} Simplon Burkina Faso</p>
        </div>
    </div>
</template>

<style scoped>
.error-wrapper {
    min-height: 100vh;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.error-inner {
    width: 100%;
    max-width: 440px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.error-logo {
    margin-bottom: 2rem;
}
.error-logo img {
    height: 60px;
    width: auto;
    object-fit: contain;
}

.error-card {
    width: 100%;
    background-color: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 2.5rem;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.error-icon {
    font-size: 48px;
    color: #E5004C;
    background: #fff0f5;
    border-radius: 50%;
    padding: 16px;
}

.error-status {
    margin-top: 1rem;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #9aaabb;
}

.error-title {
    margin-top: 0.5rem;
    font-size: 22px;
    font-weight: 700;
    color: #1F3A4D;
}

.error-description {
    margin-top: 0.75rem;
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}

.error-btn {
    margin-top: 1.75rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 22px;
    background: #E5004C;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
}
.error-btn:hover { background: #c0003e; }

.error-footer {
    margin-top: 1.5rem;
    font-size: 0.75rem;
    color: #9ca3af;
    text-align: center;
}
</style>
