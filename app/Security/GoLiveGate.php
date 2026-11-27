<?php

namespace App\Security;

/**
 * Prerequisites before ANY real patient-data pilot. Legal and organisational items are always "unmet"
 * here: this software cannot satisfy them. Technical items are evaluated from the live configuration.
 */
final class GoLiveGate
{
    /** @return list<array{id:string,area:string,requirement:string,met:bool,evidence:string}> */
    public static function items(): array
    {
        $tech = [
            ['tech.debug', 'Security', 'APP_DEBUG is false', ! config('app.debug'), 'config app.debug'],
            ['tech.cipher', 'Security', 'Field encryption uses AES-256-GCM', strtoupper((string) config('app.cipher')) === 'AES-256-GCM', 'config app.cipher'],
            ['tech.hash', 'Security', 'Passwords hashed with Argon2id', config('hashing.driver') === 'argon2id', 'config hashing.driver'],
            ['tech.session', 'Security', 'Session cookie is Secure and session data encrypted', (bool) config('session.secure') && (bool) config('session.encrypt'), 'config session.secure / session.encrypt'],
            ['tech.av', 'Security', 'Antivirus scanner bound and AV_MODE=required', config('referralflow.uploads.av_mode') === 'required' && app()->bound(\App\Domain\VirusScanner::class) && ! (app(\App\Domain\VirusScanner::class) instanceof \App\Domain\NullScanner), 'config referralflow.uploads.av_mode and container binding'],
            ['tech.db', 'Operations', 'Production database is PostgreSQL with encrypted volumes', config('database.default') === 'pgsql', 'config database.default (volume encryption must be confirmed by the host)'],
        ];
        $org = [
            ['legal.counsel', 'Legal', 'Legal review of Data Protection Act 2019 and Digital Health Act 2023 obligations (incl. whether referral data must be held by or exchanged with the Digital Health Agency; retention period)'],
            ['legal.odpc', 'Legal', 'Position from the Office of the Data Protection Commissioner: registration as controller/processor and whether a DPIA must be filed before processing'],
            ['legal.dpia', 'Legal', 'Data Protection Impact Assessment completed and approved'],
            ['legal.consent', 'Legal', 'Consent and emergency-processing wording approved by counsel (English and Kiswahili)'],
            ['org.agreements', 'Organisation', 'Signed data-sharing and processing agreements with each participating facility'],
            ['org.moh', 'Organisation', 'Engagement with the Ministry of Health referral division (policy is only proposed; no alignment is claimed)'],
            ['org.partner', 'Organisation', 'Named healthcare pilot partner and a clinical lead'],
            ['sec.pentest', 'Security', 'Independent penetration test with all critical and high findings fixed'],
            ['sec.keys', 'Security', 'Keys held in a managed secret store; rotation and restore drill rehearsed'],
            ['sec.worm', 'Security', 'Audit chain head anchored outside the database (write-once storage) and reviewed on a schedule'],
            ['sec.ir', 'Security', 'Incident response and breach-notification procedure agreed and rehearsed'],
            ['clin.safety', 'Clinical', 'Clinical safety review of the hazard log in docs/PILOT.md by a qualified clinician'],
            ['clin.language', 'Clinical', 'Kiswahili copy reviewed by Kenyan clinicians and a certified translator'],
            ['clin.training', 'Clinical', 'Staff training and downtime procedure (paper fallback) in place at each facility'],
            ['ops.backup', 'Operations', 'Backup and restore drill completed with encrypted backups'],
        ];
        $out = [];
        foreach ($tech as [$id, $area, $req, $met, $ev]) {
            $out[] = ['id' => $id, 'area' => $area, 'requirement' => $req, 'met' => (bool) $met, 'evidence' => $ev];
        }
        foreach ($org as [$id, $area, $req]) {
            $out[] = ['id' => $id, 'area' => $area, 'requirement' => $req, 'met' => false, 'evidence' => 'Not satisfiable by software; needs external action'];
        }

        return $out;
    }

    public static function unmet(): array
    {
        return array_values(array_filter(self::items(), fn ($i) => ! $i['met']));
    }
}
