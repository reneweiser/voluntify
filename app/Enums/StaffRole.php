<?php

namespace App\Enums;

/**
 * Staff roles used across organization and project scopes.
 *
 * - Organizer: Valid at both org level (organization_user) and project level (project_user).
 *   Org-level Organizers inherit full access to all projects in their organization.
 * - VolunteerAdmin: NOT valid as an org-level role after M9. Retained for M11 scanner
 *   assignees and historical VolunteerPromotion records.
 * - EntranceStaff: NOT valid as an org-level role after M9. Retained for M11 scanner
 *   assignees and historical VolunteerPromotion records.
 */
enum StaffRole: string
{
    case Organizer = 'organizer';
    case VolunteerAdmin = 'volunteer_admin';
    case EntranceStaff = 'entrance_staff';
}
