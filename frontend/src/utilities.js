const convertFileToBase64 = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.readAsDataURL(file)
        reader.onload = () => resolve({ data: reader.result, filename: file.name })
        reader.onerror = (error) => reject(error)
    })
}

/**
 * Recursively converts an object to FormData, handling nested objects, arrays and Files.
 * @param {Object} obj The object to convert
 * @param {FormData} formData The FormData object to append to (optional)
 * @param {String} parentKey The parent key name (optional)
 * @returns {FormData}
 */
const toFormData = (obj, formData = new FormData(), parentKey = '') => {
    for (const key in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, key)) {
            const value = obj[key]
            const formKey = parentKey ? `${parentKey}[${key}]` : key

            if (value === undefined) continue

            if (value === null) {
                formData.append(formKey, '')
            } else if (value instanceof File || value instanceof Blob) {
                formData.append(formKey, value)
            } else if (Array.isArray(value)) {
                value.forEach((item, index) => {
                    const arrayKey = `${formKey}[${index}]`
                    if (typeof item === 'object' && item !== null && !(item instanceof File)) {
                        toFormData(item, formData, arrayKey)
                    } else {
                        formData.append(arrayKey, item)
                    }
                })
            } else if (typeof value === 'object' && value !== null) {
                toFormData(value, formData, formKey)
            } else {
                formData.append(formKey, value)
            }
        }
    }
    return formData
}

export { convertFileToBase64, toFormData }