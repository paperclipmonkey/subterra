import { describe, it, expect } from 'vitest';
import { useFormErrors } from '@/composables/useFormErrors';

describe('useFormErrors', () => {
    it('initializes with empty errors', () => {
        const { errors } = useFormErrors();
        expect(errors.value).toEqual({});
    });

    it('sets errors from a 422 API error', () => {
        const { setErrors, errors, errorMessages } = useFormErrors();
        const apiError = {
            response: {
                status: 422,
                data: {
                    errors: {
                        name: ['The name field is required.'],
                        email: ['The email must be a valid email address.']
                    }
                }
            }
        };

        setErrors(apiError);

        expect(errors.value).toEqual({
            name: ['The name field is required.'],
            email: ['The email must be a valid email address.']
        });

        expect(errorMessages('name')).toEqual(['The name field is required.']);
        expect(errorMessages('email')).toEqual(['The email must be a valid email address.']);
        expect(errorMessages('phone')).toEqual([]);
    });

    it('clears errors', () => {
        const { setErrors, clearErrors, errors } = useFormErrors();
        const apiError = {
            response: {
                status: 422,
                data: { errors: { name: ['Error'] } }
            }
        };

        setErrors(apiError);
        expect(errors.value).not.toEqual({});

        clearErrors();
        expect(errors.value).toEqual({});
    });

    it('handles non-422 errors by clearing errors', () => {
        const { setErrors, errors } = useFormErrors();
        const apiError = {
            response: {
                status: 500,
                data: { message: 'Server Error' }
            }
        };

        setErrors(apiError);
        expect(errors.value).toEqual({});
    });
});
