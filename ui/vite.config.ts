import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import dts from 'vite-plugin-dts'
import { fileURLToPath } from 'url'

export default defineConfig({
  plugins: [
    vue(),
    // Bundles declarations into dist/ so consumers get resolvable types.
    dts({ tsconfigPath: './tsconfig.build.json', entryRoot: 'src', rollupTypes: false }),
  ],
  build: {
    lib: {
      entry: fileURLToPath(new URL('./src/index.ts', import.meta.url)),
      formats: ['es'],
      fileName: 'index',
    },
    // Two SFCs carry scoped styles; one stylesheet beats per-chunk splitting
    // for a library. Consumers import '@modufolio/media/styles'.
    cssCodeSplit: false,
    rollupOptions: {
      // Everything a consumer must provide stays external — the library
      // bundles none of its peers.
      external: [
        'vue',
        '@modufolio/panel',
        '@inertiajs/vue3',
        '@vueuse/core',
        'blurhash',
        'plyr',
        'tus-js-client',
        /^lodash/,
      ],
    },
    sourcemap: true,
    emptyOutDir: true,
  },
})
