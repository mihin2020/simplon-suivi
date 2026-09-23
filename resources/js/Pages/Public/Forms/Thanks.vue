<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

defineProps<{
    title: string
    message: string
}>()
</script>

<template>
    <Head title="Candidature envoyée" />
    <div class="thanks-page">
        <div class="thanks-card">
            <span class="material-symbols-outlined icon">check_circle</span>
            <h1>Candidature envoyée</h1>
            <p class="title">{{ title }}</p>
            <p class="message">{{ message }}</p>
        </div>
    </div>
</template>

<style scoped>
.thanks-page {
    height: 100dvh;
    max-height: 100dvh;
    overflow-y: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    background: linear-gradient(160deg, #1F3A4D, #2a4f66 40%, #f3f4f6 40%);
    box-sizing: border-box;
}
.thanks-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 480px;
    width: 100%;
    text-align: center;
    box-shadow: 0 12px 32px rgba(31, 58, 77, 0.15);
}
.icon { font-size: 48px; color: #16a34a; }
h1 { font-size: 1.5rem; font-weight: 700; color: #1F3A4D; margin-top: 0.5rem; }
.title { color: #6b7280; margin-top: 0.35rem; }
.message { margin-top: 1rem; color: #374151; line-height: 1.5; white-space: pre-wrap; }
</style>
