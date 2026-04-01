<?php

namespace App\Enums;

enum HintLocation: string
{
    case SignupEmail = 'signup_email';
    case SignupPhone = 'signup_phone';
    case SignupSummary = 'signup_summary';
    case PortalTopBanner = 'portal_top_banner';
    case PortalShiftsSection = 'portal_shifts_section';
    case ScannerWelcome = 'scanner_welcome';

    public function label(): string
    {
        return match ($this) {
            self::SignupEmail => 'Anmeldung: E-Mail-Feld',
            self::SignupPhone => 'Anmeldung: Telefon-Feld',
            self::SignupSummary => 'Anmeldung: Zusammenfassung',
            self::PortalTopBanner => 'Portal: Willkommensbanner',
            self::PortalShiftsSection => 'Portal: Schichten-Bereich',
            self::ScannerWelcome => 'Scanner: Willkommen',
        };
    }
}
