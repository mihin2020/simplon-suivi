<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<template>
    <div class="auth-wrapper">
        <div class="auth-inner">
            <!-- Logo -->
            <div class="auth-logo">
                <img src="/images/logo.jpeg" alt="Simplon Burkina Faso" />
            </div>

            <!-- Card -->
            <div class="auth-card">
                <slot />
            </div>

            <!-- Footer -->
            <p class="auth-footer">© {{ year }} Simplon Burkina Faso</p>
        </div>
    </div>
</template>

<style scoped>
.auth-wrapper {
    min-height: 100vh;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.auth-inner {
    width: 100%;
    max-width: 440px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.auth-logo {
    margin-bottom: 2rem;
}

.auth-logo img {
    height: 60px;
    width: auto;
    object-fit: contain;
}

.auth-card {
    width: 100%;
    background-color: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 2.5rem;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
}

.auth-footer {
    margin-top: 1.5rem;
    font-size: 0.75rem;
    color: #9ca3af;
    text-align: center;
}
</style>
