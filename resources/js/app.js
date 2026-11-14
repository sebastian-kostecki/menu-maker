import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { reportClientError } from './lib/reportClientError';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.config.errorHandler = (error, instance, info) => {
            console.error(error);
            reportClientError({
                message: error?.message ?? String(error),
                stack: error?.stack,
                url: window.location.pathname,
                source: 'vue',
            });
        };

        if (typeof window !== 'undefined') {
            window.addEventListener('unhandledrejection', (event) => {
                const reason = event.reason;
                reportClientError({
                    message: reason?.message ?? String(reason),
                    stack: reason?.stack,
                    url: window.location.pathname,
                    source: 'promise',
                });
            });

            window.addEventListener('error', (event) => {
                reportClientError({
                    message: event.message || event.error?.message,
                    stack: event.error?.stack,
                    url: window.location.pathname,
                    source: 'window',
                });
            });
        }

        return app
            .use(plugin)
            .use(ZiggyVue, (typeof Ziggy !== 'undefined' ? Ziggy : undefined))
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
