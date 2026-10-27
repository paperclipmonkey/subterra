import { setWorkerUrl } from 'maplibre-gl/dist/maplibre-gl.mjs'
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url'

// MapLibre 6 resolves its worker as a sibling of the bundled chunk, which Vite
// never emits. Point it at the emitted asset instead.
setWorkerUrl(workerUrl)

export * from 'maplibre-gl/dist/maplibre-gl.mjs'
