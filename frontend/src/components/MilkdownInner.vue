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
import { getMarkdown } from '@milkdown/kit/utils'
import { editorViewCtx, parserCtx } from '@milkdown/kit/core'
import { Slice } from '@milkdown/kit/prose/model'
// Import individual theme CSS files instead of the bundle to avoid pulling in
// latex.css (which imports all KaTeX fonts) since we have Latex feature disabled.
import '@milkdown/crepe/theme/common/prosemirror.css'
import '@milkdown/crepe/theme/common/reset.css'
import '@milkdown/crepe/theme/common/block-edit.css'
import '@milkdown/crepe/theme/common/code-mirror.css'
import '@milkdown/crepe/theme/common/cursor.css'
import '@milkdown/crepe/theme/common/image-block.css'
import '@milkdown/crepe/theme/common/link-tooltip.css'
import '@milkdown/crepe/theme/common/list-item.css'
import '@milkdown/crepe/theme/common/placeholder.css'
import '@milkdown/crepe/theme/common/toolbar.css'
import '@milkdown/crepe/theme/common/table.css'
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
        features: {
            [CrepeFeature.Latex]: false,
        },
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

// Like Milkdown's replaceAll, but marked addToHistory: false. The listener
// ignores such transactions, so loading content doesn't echo Milkdown's
// re-serialised markdown (different list markers, escaping, spacing) back
// as an edit. That echo made every form holding an editor look changed as
// soon as its data loaded. It also keeps the load out of undo history.
const loadMarkdown = (markdown) => (ctx) => {
    const view = ctx.get(editorViewCtx)
    const doc = ctx.get(parserCtx)(markdown)
    if (!doc) return
    const { state } = view
    view.dispatch(
        state.tr
            .replace(0, state.doc.content.size, new Slice(doc.content, 0, 0))
            .setMeta('addToHistory', false)
    )
}

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
            editor.action(loadMarkdown(newValue || ''))
        }
    }
})

// A value that arrived while the editor was still loading was skipped above
// and would never be shown (the editor keeps its initial defaultValue), so
// load it once the editor is ready.
watch(loading, (isLoading) => {
    if (isLoading) return
    const editor = get()
    if (editor && (props.modelValue || '') !== editor.action(getMarkdown()).trim()) {
        editor.action(loadMarkdown(props.modelValue || ''))
    }
})
</script>
