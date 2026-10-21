import { defineConfig } from 'vite';

// The 3D command-room bundle (three.js + 3d-force-graph), built on its own into
// public/vendor/war-room - the main Vite build and its manifest stay untouched.
export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'public/vendor/war-room',
        emptyOutDir: true,
        lib: {
            entry: 'resources/js/war-room/index.js',
            name: 'WarRoom',
            formats: ['iife'],
            fileName: () => 'war-room.js',
        },
        minify: true,
        chunkSizeWarningLimit: 4000,
    },
});
