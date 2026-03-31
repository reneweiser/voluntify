/**
 * Alpine.js component: Reservation countdown timer for the signup wizard.
 *
 * Displays a countdown from the reservation expiry time. When the timer reaches
 * zero, it calls $wire.handleReservationExpired() to reset the wizard to step 1.
 *
 * $wire is Livewire's Alpine bridge — calling $wire.methodName() triggers
 * a server round-trip to the Livewire component.
 *
 * @param {object} $wire - Livewire Alpine proxy (auto-injected by Alpine)
 */
export default function reservationTimer($wire) {
    return {
        remaining: 0,
        interval: null,
        srAnnouncement: '',
        _announcedMilestones: new Set(),

        init() {
            this.$watch(() => $wire.reservationExpiresAt, (val) => {
                if (val) {
                    this.startCountdown(val);
                } else {
                    this.stopCountdown();
                }
            });

            if ($wire.reservationExpiresAt) {
                this.startCountdown($wire.reservationExpiresAt);
            }
        },

        startCountdown(expiresAt) {
            this.stopCountdown();
            this._announcedMilestones = new Set();

            // D14: Add 0-15s random jitter to prevent thundering herd
            // when many users' reservations expire simultaneously.
            const jitter = Math.floor(Math.random() * 15);
            const expiry = new Date(expiresAt).getTime() + (jitter * 1000);

            this.updateRemaining(expiry);
            this.interval = setInterval(() => {
                this.updateRemaining(expiry);
                this.announceMilestone();
                if (this.remaining <= 0) {
                    this.stopCountdown();
                    $wire.handleReservationExpired();
                }
            }, 1000);
        },

        updateRemaining(expiry) {
            this.remaining = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
        },

        announceMilestone() {
            const milestones = [300, 120, 60, 30];
            for (const threshold of milestones) {
                if (this.remaining <= threshold && !this._announcedMilestones.has(threshold)) {
                    this._announcedMilestones.add(threshold);
                    const m = Math.floor(this.remaining / 60);
                    const s = this.remaining % 60;
                    const time = m > 0 ? `${m} minute${m > 1 ? 's' : ''}` : `${s} seconds`;
                    this.srAnnouncement = `Reservation expires in ${time}`;
                    break;
                }
            }
        },

        stopCountdown() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
            this._announcedMilestones = new Set();
        },

        get formattedTime() {
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return `${m}:${s.toString().padStart(2, '0')}`;
        },

        destroy() {
            this.stopCountdown();
        },
    };
}
