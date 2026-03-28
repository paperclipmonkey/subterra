<template>
  <div ref="container" class="markdown-renderer">
    <vue-markdown :source="source" :plugins="[mermaidPlugin]" />
  </div>
</template>

<script>
let mermaidInstance = null

const initMermaid = async () => {
    if (mermaidInstance) return mermaidInstance
    const mermaid = (await import('mermaid')).default
    mermaid.initialize({
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
    })
    mermaidInstance = mermaid
    return mermaid
}

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
        const info = (token.info || '').trim().toLowerCase()
        if (info === 'mermaid' || info.startsWith('mermaid')) {
            // Using v-pre-like behavior to ensure mermaid gets the raw text
            return `<div class="mermaid">${md.utils.escapeHtml(token.content)}</div>`
        }
        return defaultFenceRenderer(tokens, idx, options, env, self)
    }
}
</script>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import VueMarkdown from 'vue-markdown-render'

const props = defineProps({
    source: {
        type: String,
        default: ''
    }
})

const container = ref(null)

const renderMermaidDiagrams = async () => {
    // Wait for ticks and a small delay to ensure vue-markdown-render has completed its DOM update
    await nextTick()
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 200))

    if (!container.value) {
        console.log('Mermaid: container not ready')
        return
    }

    const nodes = container.value.querySelectorAll('.mermaid')
    if (nodes.length === 0) return

    try {
        const mermaid = await initMermaid()
        if (!mermaid) return

        for (const node of nodes) {
            // Skip if already processed
            if (node.getAttribute('data-processed')) continue

            // Unique ID for each diagram
            const id = 'mermaid-svg-' + Math.random().toString(36).substring(2, 11)

            // Mermaid.render expects the raw text
            const text = node.textContent

            try {
                const { svg } = await mermaid.render(id, text)
                node.innerHTML = svg
                node.setAttribute('data-processed', 'true')
            } catch (renderError) {
                console.error('Mermaid individual render error:', renderError)
                node.innerHTML = `<div class="text-error">Mermaid error: ${renderError.message}</div>`
            }
        }
    } catch (e) {
        console.error('Mermaid rendering loop error:', e)
    }
}

onMounted(() => {
    renderMermaidDiagrams()
})

watch(() => props.source, () => {
    renderMermaidDiagrams()
}, { immediate: true })
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
