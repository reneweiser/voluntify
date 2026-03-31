import reservationTimer from './signup/reservation-timer.js';

/**
 * Register Alpine components.
 *
 * Alpine.js is loaded globally via Flux UI's @fluxScripts. This module loads
 * as a deferred script, so Alpine may or may not have started by the time it
 * executes. We try the global instance first, then fall back to the event.
 */
if (window.Alpine) {
    window.Alpine.data('reservationTimer', reservationTimer);
} else {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('reservationTimer', reservationTimer);
    });
}
