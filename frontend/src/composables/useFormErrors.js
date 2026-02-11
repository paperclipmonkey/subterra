import { ref, computed } from 'vue';

export function useFormErrors() {
    const errors = ref({});
    const generalError = ref(null);

    const setErrors = (apiError) => {
        generalError.value = null;
        if (apiError.response && apiError.response.status === 422) {
            const validationErrors = apiError.response.data.errors;
            const flatErrors = {};

            if (validationErrors) {
                for (const field in validationErrors) {
                    flatErrors[field] = validationErrors[field];
                }
            }

            errors.value = flatErrors;

            // Capture top-level message if no specific field errors or if it's a general 422
            if (apiError.response.data.message) {
                generalError.value = apiError.response.data.message;
            }
        } else {
            errors.value = {};
            generalError.value = apiError.response?.data?.message || apiError.message || null;
        }
    };

    const clearErrors = () => {
        errors.value = {};
        generalError.value = null;
    };

    const errorMessages = (field) => {
        return errors.value[field] || [];
    };

    const hasError = (field) => {
        return !!errors.value[field];
    };

    return {
        errors,
        generalError,
        setErrors,
        clearErrors,
        errorMessages,
        hasError,
    };
}
