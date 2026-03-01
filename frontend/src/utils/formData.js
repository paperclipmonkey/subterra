export function objectToFormData(obj, form, namespace) {
    const fd = form || new FormData()
    let formKey

    for (const property in obj) {
        if (obj.hasOwnProperty(property)) {
            if (namespace) {
                formKey = namespace + '[' + property + ']'
            } else {
                formKey = property
            }

            // If the property is an object, but not a File,
            // use recursivity.
            if (
                typeof obj[property] === 'object' &&
                obj[property] !== null &&
                !(obj[property] instanceof File) &&
                !(obj[property] instanceof Blob)
            ) {
                objectToFormData(obj[property], fd, formKey)
            } else if (obj[property] === null) {
                fd.append(formKey, '')
            } else if (obj[property] !== undefined) {
                // if it's a string, number, boolean, or File
                fd.append(formKey, obj[property])
            }
        }
    }

    return fd
}
