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

// Register Alpine component.
// This module loads as a deferred script, so Alpine (@fluxScripts) has already
// started by the time it executes. We register directly on the global Alpine
// instance rather than using the alpine:init event (which has already fired).
if (window.Alpine) {
    window.Alpine.data('scannerApp', scannerApp as (...args: unknown[]) => object);
} else {
    // Fallback: if Alpine hasn't loaded yet (unlikely), use the event
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('scannerApp', scannerApp as (...args: unknown[]) => object);
    });
}

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // SW registration failed — app still works without it
        });
    });
}
