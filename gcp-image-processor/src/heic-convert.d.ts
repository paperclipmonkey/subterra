declare module 'heic-convert' {
    interface ConvertOptions {
        /** Raw HEIC/HEIF file bytes. */
        buffer: ArrayBufferLike | Uint8Array;
        /** Output format to decode the HEIC into. */
        format: 'JPEG' | 'PNG';
        /** JPEG quality between 0 and 1 (ignored for PNG). */
        quality?: number;
    }

    /** Decodes the primary image of a HEIC/HEIF buffer to the requested format. */
    function convert(options: ConvertOptions): Promise<ArrayBuffer>;

    export = convert;
}
