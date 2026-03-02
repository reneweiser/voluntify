/**
 * Scanner PWA entry point.
 * Registers the Alpine.js scannerApp component and the Service Worker.
 *
 * Note: Alpine.js is loaded globally via Flux UI's @fluxScripts.
 */
import { scannerApp } from './scanner/alpine-scanner';

declare global {
    interface Window {
        Alpine: {
            data: (name: string, callback: (...args: unknown[]) => object) => void;
        };
    }
}

// Register Alpine component when DOM is ready
document.addEventListener('alpine:init', () => {
    window.Alpine.data('scannerApp', scannerApp as (...args: unknown[]) => object);
});

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // SW registration failed — app still works without it
        });
    });
}
