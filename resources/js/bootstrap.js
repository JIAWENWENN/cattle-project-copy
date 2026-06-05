import axios from 'axios';
import {
    applyCsrfHeaders,
    getCsrfTokenFromMeta,
    refreshCsrfCookie,
    setCsrfToken,
} from './utils/csrf';

window.axios = axios;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

setCsrfToken(getCsrfTokenFromMeta());

axios.interceptors.request.use((config) => applyCsrfHeaders(config));

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config;

        if (error.response?.status === 419 && config && !config.__csrfRetried) {
            config.__csrfRetried = true;

            try {
                await refreshCsrfCookie();
                applyCsrfHeaders(config);
                return axios(config);
            } catch (retryError) {
                return Promise.reject(retryError);
            }
        }

        return Promise.reject(error);
    },
);
