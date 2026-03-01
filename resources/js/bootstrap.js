import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

const getMetaCsrfToken = () => document.head.querySelector('meta[name="csrf-token"]')?.content;

const setCsrfHeader = () => {
    const token = getMetaCsrfToken();

    if (!token) {
        return;
    }

    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
};

setCsrfHeader();

window.axios.interceptors.request.use((config) => {
    setCsrfHeader();

    return config;
});
