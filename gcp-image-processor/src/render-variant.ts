import sharp, { OutputInfo } from 'sharp';

/**
 * Resize an image to fit `width` and encode it as WebP.
 *
 * Phones (iPhones especially) store pixels in sensor orientation and record
 * the intended rotation in the EXIF Orientation tag. sharp drops all metadata
 * on output, so without `.rotate()` — which bakes the EXIF orientation into
 * the pixels — portrait photos come out sideways.
 *
 * `limitInputPixels` makes a decompression bomb (small file, enormous
 * dimensions) throw instead of exhausting memory.
 */
export function renderVariant(
    input: Buffer,
    width: number,
    quality: number,
    limitInputPixels: number,
): Promise<{ data: Buffer; info: OutputInfo }> {
    return sharp(input, { limitInputPixels })
        .rotate()
        .resize(width, undefined, { withoutEnlargement: true, fit: 'inside' })
        .webp({ quality })
        .toBuffer({ resolveWithObject: true });
}
