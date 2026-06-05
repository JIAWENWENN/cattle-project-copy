import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { refreshCsrfCookie, setCsrfToken } from './utils/csrf';

const appName = 'Cattle';

const syncCsrfFromPage = (page) => {
    const token = page?.props?.csrf_token;
    if (token) {
        setCsrfToken(token);
    }
};

router.on('navigate', (event) => {
    syncCsrfFromPage(event.detail.page);
});

// Retry once on CSRF mismatch before forcing a full page reload.
router.on('invalid', async (event) => {
    if (event.detail.response?.status !== 419) {
        return;
    }

    event.preventDefault();

    if (event.detail.visit?.__csrfRetried) {
        window.location.reload();
        return;
    }

    try {
        await refreshCsrfCookie();
        const visit = event.detail.visit;
        visit.__csrfRetried = true;
        router.reload({ preserveScroll: true });
    } catch {
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Temporary safeguard: remove stale "View Schedule (40/60 Split)" button if an old chunk is cached.
if (typeof window !== 'undefined') {
    const stripLegacyFeedingButton = () => {
        if (!window.location.pathname.startsWith('/feeding')) return;
        document.querySelectorAll('button').forEach((btn) => {
            const text = btn.textContent || '';
            if (text.includes('View Schedule') && text.includes('40/60')) {
                btn.remove();
            }
        });
    };

    window.addEventListener('load', stripLegacyFeedingButton);
    router.on('navigate', () => {
        stripLegacyFeedingButton();
        // Run once more after Inertia finishes rendering.
        setTimeout(stripLegacyFeedingButton, 100);
    });
}
