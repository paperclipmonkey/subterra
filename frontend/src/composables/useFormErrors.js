import { ref } from 'vue';

export function useFormErrors() {
    const errors = ref({});

    const setErrors = (apiError) => {
        if (apiError.response && apiError.response.status === 422) {
            const validationErrors = apiError.response.data.errors;
            const flatErrors = {};

            for (const field in validationErrors) {
                flatErrors[field] = validationErrors[field];
            }

            errors.value = flatErrors;
        } else {
            errors.value = {};
        }
    };

    const clearErrors = () => {
        errors.value = {};
    };

    const errorMessages = (field) => {
        return errors.value[field] || [];
    };

    const hasError = (field) => {
        return !!errors.value[field];
    };

    return {
        errors,
        setErrors,
        clearErrors,
        errorMessages,
        hasError,
    };
}
