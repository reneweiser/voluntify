# Tracking Attendance

Attendance tracking records whether volunteers showed up to their assigned shifts and whether they were on time. This is separate from entrance scanning -- arrival confirms they're at the venue, attendance confirms they reported to their shift.

**Who can mark attendance**: Organizer and Volunteer Admin (via scanner).

## Mark Attendance for a Shift

### Via Volunteer Admin Scanner

The primary way to mark attendance on-site:

1. Scan the volunteer's QR code (or use manual search).
2. The scanner shows the volunteer's shifts with status.
3. Tap the attendance button for each shift.
4. Status is set based on current time and the event's grace period.

> Example: Shift starts at 10:00, grace period is 15 minutes. Scan at 10:12 → On Time. Scan at 10:16 → Late.

### Via Admin Interface (Organizer only)

1. Go to the event's **Volunteers** page.
2. Select a shift.
3. You'll see a roster of all volunteers for that shift.
4. For each volunteer, set status:
   - **On Time** -- Arrived at the shift on time
   - **Late** -- Arrived but after the shift started
   - **No Show** -- Did not show up

You can change a status at any time -- useful if someone marked as "No Show" arrives late.

## Understanding Attendance vs. Arrival

| Column | Meaning |
|---|---|
| **Ankunft** | Whether the volunteer was scanned at the venue entrance (Entry Staff Scanner). Informational only. |
| **Anwesenheit** | The shift-level status: On Time, Late, or No Show. Set by Volunteer Admin. |

A **conflict indicator** highlights cases where a volunteer arrived at the venue (has an arrival record) but was marked No Show for their shift -- they came to the event but didn't report to their station.

### Status Summary

At the top of the roster:
- On Time count
- Late count
- No Show count
- Unmarked (no status yet)

## Attendance Grace Period

An optional per-event setting: how many minutes after shift start a scan is still **On Time**.

- **With grace period:** Scan within the window = On Time. After = Late.
- **Without grace period:** Any scan after shift start = Late.

Configure in Event > Einstellungen > Anwesenheit > **Attendance Grace Period (Minuten)**.

> Example: Grace period of 15 minutes for a shift starting at 10:00. Volunteer scanned at 10:12 → On Time. Volunteer scanned at 10:16 → Late.

## Automatic No-Show Detection

Volunteers are automatically marked **No Show** if:
- Their shift ended more than 2 hours ago
- No attendance record exists

This runs hourly in the background. You can override an automatic No Show by changing the status manually.

## Shifts Without Times

For shifts configured without start/end times (e.g. custom display text "nach Bedarf"):
- Automatic On Time / Late classification is not possible
- Attendance must be marked manually
- Grace period does not apply