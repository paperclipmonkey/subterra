/**
 * main.js
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Plugins
import { registerPlugins } from '@/plugins'

// Components
import App from './App.vue'

// Composables
import { createApp } from 'vue'

// Import calendar heatmap CSS
import 'vue3-calendar-heatmap/dist/style.css';

const app = createApp(App)

registerPlugins(app)

app.mount('#app')

// DEBUGGING: Trace page reloads
window.addEventListener('beforeunload', (event) => {
    console.log('[Debug] Page is unloading!', event);
    // debugger; // Uncomment to pause on unload
});

window.addEventListener('click', (event) => {
    const target = event.target.closest('a');
    if (target) {
        console.log('[Debug] Global click on link:', target.href, target);
        if (target.href && target.href.includes(window.location.origin) && !target.hasAttribute('download') && target.target !== '_blank') {
            console.warn('[Debug] Warning: Clicked an internal link that might be causing a reload if not handled by router:', target.href);
        }
    }
}, true);
