import axios from 'axios';
import { useNotificationStore } from '@/stores/notifications';

const instance = axios.create({
    baseURL: '/',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

instance.interceptors.response.use(
    (response) => response,
    (error) => {
        // Check if the request explicitly suppressed error notifications
        if (error.config?.suppressErrorNotification) {
            return Promise.reject(error);
        }

        const notificationStore = useNotificationStore();

        if (error.response) {
            const { status, data } = error.response;

            if (status === 422) {
                // Laravel validation error
                // We let the component handle this, but we could also show a generic toast if needed
            } else if (status === 401) {
                notificationStore.showError('Session expired. Please log in again.');
                // Optional: redirect to login
                // window.location.href = '/login';
            } else if (status === 403) {
                notificationStore.showError('You do not have permission to perform this action.');
            } else if (status >= 500) {
                notificationStore.showError('A server error occurred. Please try again later.');
            } else {
                const message = data.message || error.message || 'An unexpected error occurred.';
                notificationStore.showError(message);
            }
        } else if (error.request) {
            notificationStore.showError('No response from server. Please check your connection.');
        } else {
            notificationStore.showError(error.message);
        }

        return Promise.reject(error);
    }
);

export default {
    install: (app) => {
        app.config.globalProperties.$api = instance;
    },
};

export { instance as api };
