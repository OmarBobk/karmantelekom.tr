import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    
    // Build optimizations
    build: {
        // Optimize CSS
        cssCodeSplit: true,
        
        // Reduce bundle size
        chunkSizeWarningLimit: 1000,
    },
    
    // Development optimizations
    server: {
        hmr: {
            host: 'localhost',
        },
    },
    
    // Optimize dependencies
    optimizeDeps: {
        include: ['alpinejs'],
    },
    
    // CSS optimizations
    css: {
        devSourcemap: false,
    },
});
