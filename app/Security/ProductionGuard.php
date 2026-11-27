<?php

namespace App\Security;

final class ProductionGuard
{
    public static function flagged(): bool
    {
        return config('referralflow.mode') === 'production' || config('app.env') === 'production';
    }

    public static function acknowledged(): bool
    {
        $ack = (string) config('referralflow.production_ack');

        return $ack !== '' && hash_equals((string) config('referralflow.ack_phrase'), $ack);
    }

    /** Refuse to boot in a production-flagged mode without the explicit acknowledgement. */
    public static function assertBootable(): void
    {
        if (! self::flagged() || self::acknowledged()) {
            return;
        }
        $lines = array_map(fn ($i) => ' - ['.$i['area'].'] '.$i['requirement'], GoLiveGate::unmet());
        throw new \RuntimeException(
            "ReferralFlow refuses to start in production mode.\n"
            ."This software is built for SYNTHETIC data only and has not cleared its go-live gate.\n"
            ."Unmet prerequisites:\n".implode("\n", $lines)."\n"
            .'To start anyway (staging/testing only), set REFERRALFLOW_PRODUCTION_ACK to the phrase in docs/GO-LIVE-GATE.md.'
        );
    }
}
