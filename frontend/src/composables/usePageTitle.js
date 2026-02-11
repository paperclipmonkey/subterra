import { watchEffect, ref, nextTick, unref, watch } from 'vue';
import { useRoute } from 'vue-router';

export function usePageTitle(title) {
    const pageTitle = ref(title);
    const route = useRoute();

    const updateTitle = () => {
        if (pageTitle.value) {
            document.title = `${pageTitle.value} - subterra.world`;
        } else {
            document.title = 'subterra.world';
        }
    }

    watchEffect(() => {
        updateTitle();
    });

    if (route) {
        watch(() => route.fullPath, async () => {
            await nextTick();
            updateTitle();
        });
    }

    return {
        pageTitle
    };
}
