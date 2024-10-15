import { useRouter } from 'vue-router';
const router = useRouter();

export async function fetchWithAuth(url, options = {}) {
    try {
        const response = await fetch(url, options);

        if (response.status === 401) { // Handle 401 Unauthorized error
            console.error('Unauthorized access');
            // Redirect to login page using vue router
            router.push({ name: '/' });
        }

        return response;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}