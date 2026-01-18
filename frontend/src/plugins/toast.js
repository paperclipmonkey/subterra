import Toast, { useToast } from "vue-toastification";
import "vue-toastification/dist/index.css";

const options = {
    // You can set your default options here
    timeout: 3000
};

export default {
    install: (app) => {
        app.use(Toast, options);
        // Polyfill for Options API
        app.config.globalProperties.$toast = useToast();
    }
}
