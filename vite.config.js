import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    resolve: {
      alias: {
        '@indoorequal/vue-maplibre-gl': path.resolve(__dirname, 'node_modules/@indoorequal/vue-maplibre-gl/dist/vue-maplibre-gl.esm.js')
      }
    }
});
