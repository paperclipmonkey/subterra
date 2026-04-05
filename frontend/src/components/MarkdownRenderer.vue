<template>
  <div ref="container" class="markdown-renderer">
    <vue-markdown :source="source" :plugins="[mermaidPlugin]" />
  </div>

  <v-dialog v-model="showDiagramModal" max-width="95vw">
    <v-card class="rounded-lg overflow-auto">
      <v-card-text class="pa-6 d-flex justify-center" v-html="diagramSvg" />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="showDiagramModal = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
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
const showDiagramModal = ref(false)
const diagramSvg = ref('')

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
                node.style.cursor = 'pointer'
                node.title = 'Click to enlarge'
                node.addEventListener('click', () => {
                    diagramSvg.value = node.innerHTML
                    showDiagramModal.value = true
                })
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

<style scoped>
.markdown-renderer {
  font-family: 'Roboto', sans-serif;
  line-height: 1.75;
  color: #374151;
  max-width: 100%;
}

.markdown-renderer :deep(h1),
.markdown-renderer :deep(h2),
.markdown-renderer :deep(h3),
.markdown-renderer :deep(h4),
.markdown-renderer :deep(h5),
.markdown-renderer :deep(h6) {
  color: #111827;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  line-height: 1.3;
}

.markdown-renderer :deep(h1) {
  font-size: 2.25rem;
  border-bottom: 3px solid #4285f4;
  padding-bottom: 0.5rem;
  margin-top: 0;
}

.markdown-renderer :deep(h2) {
  font-size: 1.875rem;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0.25rem;
}

.markdown-renderer :deep(h3) {
  font-size: 1.5rem;
}

.markdown-renderer :deep(h4) {
  font-size: 1.25rem;
}

.markdown-renderer :deep(h5) {
  font-size: 1.125rem;
}

.markdown-renderer :deep(h6) {
  font-size: 1rem;
}

.markdown-renderer :deep(p) {
  margin-bottom: 1.25rem;
}

.markdown-renderer :deep(ul),
.markdown-renderer :deep(ol) {
  margin-bottom: 1.25rem;
  padding-left: 1.5rem;
}

.markdown-renderer :deep(li) {
  margin-bottom: 0.5rem;
}

.markdown-renderer :deep(blockquote) {
  border-left: 4px solid #4285f4;
  background: #f9fafb;
  padding: 1rem 1.5rem;
  margin: 1.5rem 0;
  font-style: italic;
  color: #4b5563;
  border-radius: 0 0.5rem 0.5rem 0;
}

.markdown-renderer :deep(pre) {
  background: #1e293b;
  color: #f8fafc;
  padding: 1.25rem;
  border-radius: 0.75rem;
  overflow-x: auto;
  margin: 1.5rem 0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.markdown-renderer :deep(code) {
  font-family: 'Fira Code', 'Cascadia Code', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
}

.markdown-renderer :deep(:not(pre) > code) {
  background: #f1f5f9;
  color: #4285f4;
  padding: 0.2rem 0.4rem;
  border-radius: 0.375rem;
  font-weight: 500;
}

.markdown-renderer :deep(table) {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin: 1.5rem 0;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  overflow: hidden;
}

.markdown-renderer :deep(th) {
  background: #f8fafc;
  text-align: left;
  font-weight: 600;
  padding: 0.75rem 1rem;
  border-bottom: 2px solid #e5e7eb;
  color: #374151;
}

.markdown-renderer :deep(td) {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  color: #4b5563;
}

.markdown-renderer :deep(tr:last-child td) {
  border-bottom: none;
}

.markdown-renderer :deep(tr:nth-child(even)) {
  background: #f9fafb;
}

.markdown-renderer :deep(hr) {
  border: 0;
  border-top: 1px solid #e5e7eb;
  margin: 2.5rem 0;
}

.markdown-renderer :deep(.mermaid) {
  display: flex;
  justify-content: center;
  margin: 2rem 0;
  padding: 1.5rem;
  background: white;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
}

.markdown-renderer :deep(.mermaid svg) {
  max-width: 100%;
  height: auto;
}
</style>
