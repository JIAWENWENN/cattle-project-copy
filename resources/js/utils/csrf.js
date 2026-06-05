export function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length !== 2) {
        return '';
    }

    return decodeURIComponent(parts.pop().split(';').shift() || '');
}

export function getCsrfTokenFromMeta() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export function setCsrfToken(token) {
    if (!token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }

    if (window.axios?.defaults?.headers?.common) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
}

/**
 * Build CSRF headers for XHR requests.
 * Prefer the XSRF-TOKEN cookie (always current) over a stale meta tag value.
 */
export function getCsrfHeaders() {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };

    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken;
        return headers;
    }

    const metaToken = getCsrfTokenFromMeta();
    if (metaToken) {
        headers['X-CSRF-TOKEN'] = metaToken;
    }

    return headers;
}

export async function refreshCsrfCookie() {
    await window.axios.get('/sanctum/csrf-cookie', {
        withCredentials: true,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });
}

export function applyCsrfHeaders(config) {
    const csrfHeaders = getCsrfHeaders();

    config.headers = {
        ...(config.headers || {}),
        ...csrfHeaders,
    };

    // Let the browser set multipart boundaries; a manual Content-Type breaks uploads.
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
    }

    // Avoid sending a stale X-CSRF-TOKEN alongside a fresh X-XSRF-TOKEN cookie.
    if (csrfHeaders['X-XSRF-TOKEN']) {
        delete config.headers['X-CSRF-TOKEN'];
    }

    return config;
}
