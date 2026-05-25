// Plugins
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import Fonts from 'unplugin-fonts/vite'
import Layouts from 'vite-plugin-vue-layouts'
import Vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import VueRouter from 'unplugin-vue-router/vite'
import Vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

// Utilities
import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'node:url'

// Force restart
// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const isTest = mode === 'test'
  return {
    plugins: [
      VitePWA({
        registerType: 'autoUpdate',
        includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'mask-icon.svg'],
        devOptions: {
          enabled: false,
          type: 'module',
        },
        workbox: {
          globPatterns: mode === 'development' ? [] : ['**/*.{js,css,html,ico,png,jpg,jpeg,svg,gif,webp,woff2}'],
          maximumFileSizeToCacheInBytes: 5 * 1024 * 1024, // 5 MB
          navigateFallback: '/index.html',
          navigateFallbackDenylist: [/^\/api\//, /^\/storage\//, /^\/media\//],
          cleanupOutdatedCaches: true,
          runtimeCaching: [
            {
              // Belt-and-suspenders cache for app JS/CSS chunks.
              // If the precache ever misses (e.g. SW version race), serve stale
              // from this cache and revalidate in the background.
              urlPattern: ({ request }) =>
                request.destination === 'script' || request.destination === 'style',
              handler: 'StaleWhileRevalidate',
              options: {
                cacheName: 'app-chunks-runtime',
                expiration: { maxEntries: 120, maxAgeSeconds: 60 * 60 * 24 * 30 },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            {
              urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'google-fonts-cache',
                expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            {
              urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'gstatic-fonts-cache',
                expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            {
              urlPattern: /\/api\/users\/me$/,
              handler: 'NetworkFirst',
              options: {
                cacheName: 'user-api-cache',
                expiration: { maxEntries: 1, maxAgeSeconds: 60 * 60 * 24 * 7 },
                cacheableResponse: { statuses: [0, 200] },
                networkTimeoutSeconds: 3,
              },
            },
            {
              urlPattern: /\/api\/caves$/,
              handler: 'NetworkFirst',
              options: {
                cacheName: 'caves-list-cache',
                expiration: { maxEntries: 1, maxAgeSeconds: 60 * 60 * 24 },
                cacheableResponse: { statuses: [0, 200] },
                networkTimeoutSeconds: 5,
              },
            },
            {
              urlPattern: /\/api\/tags$/,
              handler: 'NetworkFirst',
              options: {
                cacheName: 'tags-cache',
                expiration: { maxEntries: 1, maxAgeSeconds: 60 * 60 * 24 },
                cacheableResponse: { statuses: [0, 200] },
                networkTimeoutSeconds: 3,
              },
            },
          ],
          skipWaiting: true,
          clientsClaim: true,
        },
        manifest: {
          name: 'Subterra.world',
          short_name: 'Subterra',
          description: 'Plan your next adventure with Subterra.world',
          theme_color: '#000000',
          background_color: '#ffffff',
          display: 'standalone',
          orientation: 'portrait-primary',
          scope: '/',
          start_url: '/',
          categories: ['sports', 'navigation', 'utilities'],
          icons: [
            {
              src: 'pwa-192x192.png',
              sizes: '192x192',
              type: 'image/png',
            },
            {
              src: 'pwa-512x512.png',
              sizes: '512x512',
              type: 'image/png',
            },
            {
              src: 'pwa-512x512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'maskable',
            },
          ],
        },
      }),
      VueRouter(),
      Layouts(),
      Vue({
        template: { transformAssetUrls }
      }),
      // https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin#readme
      !isTest && Vuetify({
        autoImport: true,
        styles: {
          configFile: 'src/styles/settings.scss',
        },
      }),
      Components(),
      Fonts({
        google: {
          families: [{
            name: 'Roboto',
            styles: 'wght@100;300;400;500;700;900',
          }],
        },
      }),
      AutoImport({
        imports: [
          'vue',
          'vue-router',
        ],
        eslintrc: {
          enabled: true,
        },
        vueTemplate: true,
      }),
    ],
    define: { 'process.env': {} },
    resolve: {
      alias: [
        { find: '@', replacement: fileURLToPath(new URL('./src', import.meta.url)) },
        ...(isTest ? [{ find: /^.*\.css$/, replacement: fileURLToPath(new URL('./tests/styleMock.js', import.meta.url)) }] : []),
      ],
      extensions: [
        '.js',
        '.json',
        '.jsx',
        '.mjs',
        '.ts',
        '.tsx',
        '.vue',
      ],
    },
    server: {
      port: 3000,
      proxy: {
        '/api': 'http://127.0.0.1',
        '/storage': 'http://127.0.0.1',
        '/media': 'http://127.0.0.1',
        'public': 'http://127.0.0.1',
      },
      host: '0.0.0.0',
    },
    test: {
      globals: true,
      environment: 'jsdom',
      setupFiles: ['./tests/setup.js'],
      css: {
        modules: {
          classNameStrategy: 'stable'
        }
      },
      deps: {
        inline: ['vuetify']
      }
    },
    build: {
      cssCodeSplit: true,
    }
  }
})
