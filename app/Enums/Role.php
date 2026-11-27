<?php

namespace App\Enums;

enum Role: string
{
    case Clinician = 'clinician';
    case Coordinator = 'coordinator';
    case FacilityAdmin = 'facility_admin';
    case NetworkAnalyst = 'network_analyst';
    case PlatformAdmin = 'platform_admin';

    public function label(): string
    {
        return match ($this) {
            self::Clinician => 'Facility clinician',
            self::Coordinator => 'Facility coordinator',
            self::FacilityAdmin => 'Facility admin',
            self::NetworkAnalyst => 'Network analyst',
            self::PlatformAdmin => 'Platform admin',
        };
    }

    /** Roles that may handle referral content (subject to facility scoping). */
    public function isClinical(): bool
    {
        return $this === self::Clinician || $this === self::Coordinator;
    }

    public function needsFacility(): bool
    {
        return $this !== self::NetworkAnalyst && $this !== self::PlatformAdmin;
    }
}
