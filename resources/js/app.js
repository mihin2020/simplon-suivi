import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { chatStore } from './stores/chatStore';

// Effacer le chat si l'utilisateur arrive sur la page de connexion
// (déconnexion manuelle ou expiration de session)
router.on('navigate', (event) => {
    const component = event.detail.page.component;
    if (component === 'Auth/Login') {
        chatStore.messages.splice(0, chatStore.messages.length);
        chatStore.isOpen = false;
    }
});

createInertiaApp({
    title: (title) => `${title} — Simplon BF`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#E5004C',
    },
});
