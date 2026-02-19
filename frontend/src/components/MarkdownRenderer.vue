<template>
  <div ref="container" class="markdown-renderer">
    <vue-markdown :source="source" :plugins="[mermaidPlugin]" />
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import VueMarkdown from 'vue-markdown-render'
import mermaid from 'mermaid'

const props = defineProps({
    source: {
        type: String,
        default: ''
    }
})

const container = ref(null)

/**
 * Custom markdown-it plugin that converts ```mermaid code fences
 * into <div class="mermaid"> blocks so the mermaid library can render them.
 */
function mermaidPlugin(md) {
    const defaultFenceRenderer = md.renderer.rules.fence ||
        function (tokens, idx, options, env, self) {
            return self.renderToken(tokens, idx, options)
        }

    md.renderer.rules.fence = (tokens, idx, options, env, self) => {
        const token = tokens[idx]
        if (token.info.trim() === 'mermaid') {
            return `<div class="mermaid">${md.utils.escapeHtml(token.content)}</div>`
        }
        return defaultFenceRenderer(tokens, idx, options, env, self)
    }
}

const renderMermaidDiagrams = async () => {
    await nextTick()
    if (!container.value) return
    const nodes = container.value.querySelectorAll('.mermaid')
    if (nodes.length === 0) return
    // Reset any previously-rendered diagrams so mermaid re-processes the raw text
    nodes.forEach(node => {
        node.removeAttribute('data-processed')
    })
    try {
        await mermaid.run({ nodes })
    } catch (e) {
        console.warn('Mermaid rendering error:', e)
    }
}

onMounted(() => {
    mermaid.initialize({
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
    })
    renderMermaidDiagrams()
})

watch(() => props.source, () => {
    renderMermaidDiagrams()
})
</script>

<style>
.markdown-renderer .mermaid {
    display: flex;
    justify-content: center;
    margin: 1rem 0;
}

.markdown-renderer .mermaid svg {
    max-width: 100%;
}
</style>
