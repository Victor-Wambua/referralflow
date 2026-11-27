<?php

return [
    'mode' => env('REFERRALFLOW_MODE', 'staging'),
    'production_ack' => env('REFERRALFLOW_PRODUCTION_ACK'),
    'ack_phrase' => 'I-ACKNOWLEDGE-THE-GO-LIVE-GATE-IS-UNMET',
    'blind_index_key' => env('BLIND_INDEX_KEY'),

    'session' => [
        'idle_minutes' => (int) env('SESSION_LIFETIME', 15),
        'absolute_minutes' => 480,
        'reauth_minutes' => 5,
        'warn_seconds' => 60,
    ],
    'reveal_minutes' => 5,
    'break_glass' => ['minutes' => 60, 'min_reason_length' => 20],
    'lockout' => ['max_attempts' => 5, 'minutes' => 15],

    'verification' => ['due_days' => 7, 'stale_days' => 30],

    // minutes until a Sent referral should be acknowledged (received) / hard overdue
    'sla' => [
        'emergency' => ['ack' => 15, 'overdue' => 120],
        'urgent' => ['ack' => 120, 'overdue' => 1440],
        'routine' => ['ack' => 1440, 'overdue' => 4320],
    ],

    'metrics' => ['min_cell' => 5],

    // PLACEHOLDERS ONLY: confirm with counsel. The Digital Health Act may require longer retention.
    'retention' => [
        'closed_referral_days' => 365,
        'message_days' => 365,
        'attachment_days' => 365,
        'draft_days' => 30,
        'audit_days' => null, // audit entries are never purged by the application
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024,
        'max_per_referral' => 5,
        'types' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
        'av_mode' => env('AV_MODE', 'optional'),
    ],

    'priorities' => ['emergency', 'urgent', 'routine'],
    'statuses' => ['created', 'sent', 'received', 'accepted', 'scheduled', 'attended', 'completed', 'declined', 'cancelled'],

    'reason_codes' => [
        'obstetric' => 'Obstetric or newborn',
        'trauma' => 'Injury or trauma',
        'medical' => 'Medical condition',
        'surgical' => 'Surgical review',
        'paediatric' => 'Child health',
        'diagnostic' => 'Diagnostic test or imaging',
        'specialist' => 'Specialist clinic',
        'mental_health' => 'Mental health',
        'other' => 'Other',
    ],
    'decline_reasons' => [
        'no_capacity' => 'No capacity or bed',
        'service_unavailable' => 'Service not available',
        'out_of_scope' => 'Outside our level of care',
        'incomplete_information' => 'Information incomplete',
        'other' => 'Other',
    ],
    'cancel_reasons' => [
        'patient_declined' => 'Patient declined',
        'patient_did_not_attend' => 'Patient did not attend',
        'no_longer_needed' => 'No longer needed',
        'referred_elsewhere' => 'Referred elsewhere',
        'consent_withdrawn' => 'Consent withdrawn',
        'created_in_error' => 'Created in error',
        'transport_failure' => 'Could not travel',
        'other' => 'Other',
    ],
    'outcomes' => [
        'treated_discharged' => 'Treated and discharged',
        'admitted' => 'Admitted',
        'further_referral' => 'Referred onward',
        'follow_up_needed' => 'Follow-up at referring facility',
        'deceased' => 'Patient died',
        'other' => 'Other',
    ],
    'consent_bases' => [
        'written' => 'Written consent',
        'verbal_witnessed' => 'Verbal consent, witnessed',
        'guardian' => 'Guardian or next of kin consent',
        'emergency_implied' => 'Emergency: patient unable to consent (counsel to confirm wording)',
    ],
    'identifier_types' => [
        'national_id' => 'National ID',
        'sha' => 'SHA number',
        'facility_record' => 'Facility record number',
        'birth_notification' => 'Birth notification number',
        'other' => 'Other',
    ],
    'reveal_reasons' => [
        'verify_identity' => 'Confirm this is the right patient',
        'prepare_visit' => 'Prepare for the visit',
        'clinical_care' => 'Direct clinical care',
        'other' => 'Other',
    ],
    'facility_types' => ['dispensary', 'health_centre', 'sub_county_hospital', 'county_hospital', 'referral_hospital', 'clinic', 'other'],

    'rate_limits' => [
        'login' => 5, 'mfa' => 6, 'reveal' => 20, 'search' => 60, 'sensitive' => 10, 'api' => 60,
    ],
];
