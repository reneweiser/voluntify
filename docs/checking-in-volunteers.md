# Checking In Volunteers

This guide covers using the QR scanner and manual lookup to check in volunteers at your event entrance.

**Who can use the scanner**: Organizer and Entrance Staff only.

## Before the Event

### Open the Scanner

1. Click **Scanner** in the sidebar.
2. Select the event you're scanning for.

### Download Offline Data

When you open the scanner while connected to the internet, it automatically downloads:
- The full volunteer list for the event
- Shift assignments
- Cryptographic keys for QR validation

This data is stored locally on your device, so the scanner works even without internet.

### Install as a PWA (Recommended)

For the best experience on mobile, install Voluntify as a Progressive Web App:

- **Android (Chrome)**: Tap the browser menu > **Install app** or **Add to Home screen**.
- **iPhone (Safari)**: Tap the share icon > **Add to Home Screen**.

This gives you a fullscreen app experience without browser chrome, and ensures the scanner works offline.

## Using the QR Scanner

1. From the scanner page, the camera viewfinder fills most of the screen.
2. Point your device's camera at the volunteer's QR code.
3. The scanner automatically detects and validates the QR code. No button press needed.
4. A result panel slides up showing the scan result.

### Scan Result States

| Color | Meaning | What to Do |
|---|---|---|
| **Green** | Valid ticket, new arrival | Review the volunteer's name and shift info, then tap **Confirm Arrival** |
| **Yellow** | Already scanned | The volunteer was already checked in. Shows the time of their first scan. |
| **Red** | Invalid ticket | The QR code couldn't be validated. Try manual lookup instead. |

After confirming an arrival, the scanner returns to the viewfinder, ready for the next volunteer.

## Manual Name Lookup

If a volunteer can't present their QR code (forgot their phone, dead battery, lost ticket), use manual lookup:

1. From the scanner page, click **Manual Lookup** (or navigate to the lookup page directly).
2. Type the volunteer's name in the search bar.
3. Results appear as you type, showing matching volunteers with their job and shift info.
4. Select the correct volunteer.
5. Tap **Confirm Arrival** to record their check-in.

Manual lookup works offline using the downloaded volunteer data.

## Offline Mode

The scanner is designed to work without internet. Here's how offline mode works:

- **QR validation**: Happens entirely on-device using downloaded cryptographic keys. No server call needed.
- **Volunteer lookup**: Searches the locally cached volunteer list.
- **Arrival recording**: Arrivals are saved to a local queue on your device.
- **Automatic sync**: When your device reconnects to the internet, queued arrivals are automatically synced to the server.

A sync status indicator shows:
- **Online**: Arrivals sync immediately.
- **Offline -- N pending**: You're offline. N arrivals are queued and will sync when you're back online.

You don't need to do anything special to use offline mode. It happens automatically when you lose connectivity.

## Tips

- **Download data while online**: Always open the scanner page while connected before the event starts. This ensures you have the latest volunteer data cached.
- **Multiple staff**: Multiple Entrance Staff can scan at different entrances simultaneously. Each device maintains its own local data. Duplicate scans are handled automatically -- if volunteer A is scanned at entrance 1, and then again at entrance 2, the second scan will show "Already arrived."
- **Key rotation**: The scanner validates tickets using cryptographic keys that rotate at 4:00 AM. For events spanning past 4 AM, the scanner automatically accepts both the current and previous period's keys, so no tickets are rejected at the boundary.
- **Stale data**: If a volunteer signed up after you downloaded the data, their QR code will still validate (the JWT is self-contained), but you'll see a note that they're not in the cached list. You can still confirm their arrival, or tap **Refresh Data** to re-download the latest volunteer list.
