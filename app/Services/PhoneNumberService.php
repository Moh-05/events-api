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
class PhoneNumberService
{
    private const COUNTRY_CODE = '963';

    public static function normalize(string $phone): string
    {
        // Strip everything but digits (drops '+', spaces, dashes if any slipped through).
        $digits = preg_replace('/\D/', '', $phone);

        // Drop a leading trunk 0 if present ("0933..." -> "933..."). A no-op
        // when the user already typed it without one ("933...").
        $local = preg_replace('/^0+/', '', $digits);

        return '+' . self::COUNTRY_CODE . $local;
    }
}
