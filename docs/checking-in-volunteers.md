# Checking In Volunteers

This guide covers using the QR scanner and manual lookup to check in volunteers at your event entrance.

**Who can use the scanner**: Organizer, Volunteer Admin, and Entrance Staff. The features available depend on your role -- see [Role-Based Scanner Behavior](#role-based-scanner-behavior) below.

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

![Scanner workflow: camera detects QR, validates, shows result by color](figures/scanner-workflow.svg)

### Scan Result States

| Color | Meaning | What to Do |
|---|---|---|
| **Green** | Valid ticket, new arrival | Review the volunteer's name and shift info, then tap **Confirm Arrival** |
| **Yellow** | Already scanned | The volunteer was already checked in. Shows the time of their first scan. |
| **Red** | Invalid ticket | The QR code couldn't be validated. Try manual lookup instead. |

After confirming an arrival, the scanner returns to the viewfinder, ready for the next volunteer.

### Shift Context on Scan Results

When a volunteer's QR code is scanned, the result panel shows their shift assignments with color-coded status indicators:

| Color | Status | Meaning |
|---|---|---|
| **Green** | Attended | Attendance was already recorded for this shift. |
| **Red** | Missed | The shift ended without an attendance record. |
| **Blue** | Active | The shift is currently in progress. |
| **Gray** | Upcoming | The shift hasn't started yet. |

This helps you see at a glance whether the volunteer has been accounted for across their shifts.

### Recording Shift Attendance from the Scanner

Organizers and Volunteer Admins can mark shift attendance directly from a scan result. Each active or upcoming shift shows a **Mark** button. Tapping it records the volunteer's attendance status (On Time or Late) based on the current time and the event's [attendance grace period](tracking-attendance.md#attendance-grace-period).

This is a faster alternative to switching to the Attendance tab -- you can handle both arrival and attendance in one workflow.

## Manual Name Lookup

If a volunteer can't present their QR code (forgot their phone, dead battery, lost ticket), use manual lookup:

1. From the scanner page, click **Manual Lookup** (or navigate to the lookup page directly).
2. Type the volunteer's name in the search bar.
3. Results appear as you type, showing matching volunteers with their job and shift info.
4. Select the correct volunteer.
5. Tap **Confirm Arrival** to record their check-in.
6. If you're an Organizer or Volunteer Admin, each of the volunteer's shifts shows a **Mark** button to record attendance directly from this page.

Manual lookup works offline using the downloaded volunteer data.

## Offline Mode

The scanner is designed to work without internet. Here's how offline mode works:

![Offline sync: device validates locally and syncs arrivals when back online](figures/offline-sync.svg)

- **QR validation**: Happens entirely on-device using downloaded cryptographic keys. No server call needed.
- **Volunteer lookup**: Searches the locally cached volunteer list.
- **Arrival recording**: Arrivals are saved to a local queue on your device.
- **Attendance recording**: Attendance records marked from the scanner are also queued locally when offline.
- **Automatic sync**: When your device reconnects to the internet, queued arrivals and attendance records are automatically synced to the server.

A sync status indicator shows:
- **Online**: Arrivals sync immediately.
- **Offline -- N pending**: You're offline. N arrivals are queued and will sync when you're back online.

You don't need to do anything special to use offline mode. It happens automatically when you lose connectivity.

## Tips

- **Download data while online**: Always open the scanner page while connected before the event starts. This ensures you have the latest volunteer data cached.
- **Multiple staff**: Multiple Entrance Staff can scan at different entrances simultaneously. Each device maintains its own local data. Duplicate scans are handled automatically -- if volunteer A is scanned at entrance 1, and then again at entrance 2, the second scan will show "Already arrived."
- **Key rotation**: The scanner validates tickets using cryptographic keys that rotate at 4:00 AM. For events spanning past 4 AM, the scanner automatically accepts both the current and previous period's keys, so no tickets are rejected at the boundary.
- **Stale data**: If a volunteer signed up after you downloaded the data, their QR code will still validate (the JWT is self-contained), but you'll see a note that they're not in the cached list. You can still confirm their arrival, or tap **Refresh Data** to re-download the latest volunteer list.

## Role-Based Scanner Behavior

Not all roles see the same features on the scanner page:

| Capability | Organizer | Volunteer Admin | Entrance Staff |
|---|:---:|:---:|:---:|
| Scan QR codes | Yes | Yes | Yes |
| Use manual lookup | Yes | Yes | Yes |
| Confirm arrival | Yes | -- | Yes |
| Mark shift attendance | Yes | Yes | -- |

- **Entrance Staff** focus on arrival confirmation only. They cannot mark shift attendance.
- **Volunteer Admins** focus on shift attendance tracking. They can use the scanner and manual lookup pages but cannot confirm arrivals via the QR sync endpoint.
- **Organizers** have full access to both arrival confirmation and attendance marking.
