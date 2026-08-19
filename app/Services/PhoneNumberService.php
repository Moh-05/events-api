<?php

namespace App\Services;

// One canonical phone format for the whole app, so the SAME real number always
// matches itself no matter how the customer typed it.
//
// The bug this fixes: the Flutter app only ever sends a LOCAL Syrian number —
// either with the trunk 0 ("0933123456") or without it ("933123456"). It never
// sends "+963" (that's just UI decoration the app shows, not part of what it
// submits). Before this service, phone was used raw everywhere (OTP cache key,
// the User/Vendor DB column, and the login lookup). Signing up as
// "0933123456" and logging back in as "933123456" were two different strings
// to the code, so the SECOND one silently became "new_user" / "new_vendor"
// instead of a login. UltraMsg itself tolerates both shapes fine — only OUR
// matching was broken.
//
// Fix: normalize to ONE canonical form as early as possible (right after
// validation, before it touches the cache, the DB write, or the DB lookup) and
// use that everywhere. Canonical form (always stored, regardless of which
// shape the user typed): "+963" + the 9-digit local number, e.g.
// "+963933123456".
//
// MUST be idempotent: normalize(normalize($x)) === normalize($x). The
// 2026-08-19 backfill (phones:normalize) re-normalizes every existing row on
// each run, and a value already in canonical form is a completely normal
// input (e.g. a fresh signup's own phone passed through twice by accident).
// An earlier version of this function was NOT idempotent — it only knew how
// to strip a leading trunk 0, so an already-canonical "+963900000001" (no
// leading 0 in the digit string) fell through untouched and got a SECOND
// "963" prepended, corrupting it to "+963963900000001". Caught by re-running
// the backfill locally before it ever touched production.
class PhoneNumberService
{
    private const COUNTRY_CODE = '963';

    public static function normalize(string $phone): string
    {
        // Strip everything but digits (drops '+', spaces, dashes).
        $digits = preg_replace('/\D/', '', $phone);

        // Already canonical (or otherwise carries the country code) — strip
        // it off first so it isn't double-prepended below. Checked BEFORE the
        // trunk-0 strip because a canonical number's digits never start with
        // a literal "0".
        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        } else {
            // Local shape: drop a leading trunk 0 if present ("0933..." ->
            // "933..."). No-op when already typed without one ("933...").
            $digits = preg_replace('/^0+/', '', $digits);
        }

        return '+' . self::COUNTRY_CODE . $digits;
    }
}
