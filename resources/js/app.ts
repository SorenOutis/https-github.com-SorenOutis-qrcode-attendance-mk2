import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import ToastContainer from '@/components/ToastContainer.vue';

import { vTilt } from '@/directives/v-tilt';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('ToastContainer', ToastContainer)
            .directive('tilt', vTilt)
            .mount(el);

        // Mount the global toast container outside Inertia's root so it persists across page visits
        const toastEl = document.createElement('div');
        document.body.appendChild(toastEl);
        createApp(ToastContainer).mount(toastEl);
    },
    progress: {
        color: 'var(--foreground)',
        showSpinner: true,
    },
});

// This will set light / dark mode on page load...
initializeTheme();
