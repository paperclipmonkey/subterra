import sharp from 'sharp';
import { renderVariant } from '../src/render-variant';

/**
 * Uses the real sharp (not the mock in index.test.ts) to check the pixels
 * that come out, since orientation bugs are invisible to a mocked pipeline.
 */

// A landscape 40x20 JPEG — left half red, right half blue — tagged with EXIF
// Orientation 6 ("rotate 90° clockwise to display"), as an iPhone writes a
// portrait shot.
async function sidewaysPortraitJpeg(): Promise<Buffer> {
    const half = (r: number, b: number) =>
        sharp({ create: { width: 20, height: 20, channels: 3, background: { r, g: 0, b } } })
            .png()
            .toBuffer();
    return sharp({ create: { width: 40, height: 20, channels: 3, background: { r: 0, g: 0, b: 0 } } })
        .composite([
            { input: await half(255, 0), left: 0, top: 0 },
            { input: await half(0, 255), left: 20, top: 0 },
        ])
        .jpeg({ quality: 100 })
        .withMetadata({ orientation: 6 })
        .toBuffer();
}

async function pixel(image: Buffer, x: number, y: number) {
    const { data, info } = await sharp(image).raw().toBuffer({ resolveWithObject: true });
    const i = (y * info.width + x) * info.channels;
    return { r: data[i], b: data[i + 2] };
}

describe('renderVariant', () => {
    it('applies the EXIF orientation so portrait photos are upright', async () => {
        const source = await sidewaysPortraitJpeg();
        expect((await sharp(source).metadata()).orientation).toBe(6);

        const { data, info } = await renderVariant(source, 1920, 90, 100_000_000);

        expect(info.format).toBe('webp');
        expect([info.width, info.height]).toEqual([20, 40]);
        // Rotated 90° clockwise: the left (red) half ends up on top.
        expect((await pixel(data, 10, 5)).r).toBeGreaterThan(200);
        expect((await pixel(data, 10, 35)).b).toBeGreaterThan(200);
        // The orientation is baked into the pixels, not left as a tag that
        // would rotate the image a second time.
        expect((await sharp(data).metadata()).orientation).toBeUndefined();
    });

    it('leaves an image with no orientation tag as it is', async () => {
        const source = await sharp({ create: { width: 40, height: 20, channels: 3, background: '#808080' } })
            .jpeg()
            .toBuffer();

        const { info } = await renderVariant(source, 1920, 90, 100_000_000);

        expect([info.width, info.height]).toEqual([40, 20]);
    });

    it('resizes to the preset width without enlarging', async () => {
        const source = await sharp({ create: { width: 1000, height: 500, channels: 3, background: '#808080' } })
            .jpeg()
            .toBuffer();

        expect((await renderVariant(source, 480, 60, 100_000_000)).info).toMatchObject({ width: 480, height: 240 });
        expect((await renderVariant(source, 1920, 70, 100_000_000)).info).toMatchObject({ width: 1000, height: 500 });
    });
});
