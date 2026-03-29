<template>
  <Milkdown />
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { Crepe, CrepeFeature } from '@milkdown/crepe'
import { languages } from '@codemirror/language-data'
import { LanguageDescription } from '@codemirror/language'
import { listener, listenerCtx } from '@milkdown/kit/plugin/listener'
import { Milkdown, useEditor } from '@milkdown/vue'
import { replaceAll, getMarkdown } from '@milkdown/kit/utils'
import '@milkdown/crepe/theme/common/style.css'
import '@milkdown/crepe/theme/frame.css'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Start writing...'
    }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Track whether we're currently updating from user input
const isUserTyping = ref(false)
let userTypingTimeout = null

const { get, loading } = useEditor((root) => {
    const crepe = new Crepe({
        root,
        defaultValue: props.modelValue || '',
        placeholder: props.placeholder,
        featureConfigs: {
            [CrepeFeature.CodeMirror]: {
                languages: [
                    ...languages,
                    LanguageDescription.of({
                        name: 'Mermaid',
                        alias: ['mermaid'],
                        load: () => Promise.resolve()
                    })
                ]
            }
        }
    })

    // Configure the underlying editor
    crepe.editor
        .config((ctx) => {
            ctx.get(listenerCtx).markdownUpdated((ctx, markdown, prevMarkdown) => {
                // Mark that user is typing and emit the update
                isUserTyping.value = true

                // Clear any existing timeout
                if (userTypingTimeout) {
                    clearTimeout(userTypingTimeout)
                }

                // Reset typing flag after user stops typing
                userTypingTimeout = setTimeout(() => {
                    isUserTyping.value = false
                }, 500)

                emit('update:modelValue', markdown)
                emit('change', { markdown })
            })
        })
        .use(listener)

    return crepe
})

// Handle external value changes (e.g., loading existing trip data)
// Only update if NOT from user typing - this prevents the feedback loop
watch(() => props.modelValue, (newValue) => {
    // Skip if editor is still loading or user is actively typing
    if (loading.value || isUserTyping.value) {
        return
    }

    const editor = get()
    if (editor) {
        // Get current editor content to compare
        const currentContent = editor.action(getMarkdown())

        // Only replace if the content is actually different
        // This handles initial load and external data changes
        if (newValue !== currentContent) {
            editor.action(replaceAll(newValue || ''))
        }
    }
})
</script>
