<?php

namespace App\Services;

// One canonical phone format for the whole app, so the SAME real number always
// matches itself no matter how the person typed it. Login looks the account up
// by this value, so if two spellings of one number don't normalize identically,
// that person silently gets a second account instead of signing in.
//
// Stored form is always "+<country><subscriber>", e.g. "+963933123456".
//
// WHY THE + IS OPTIONAL ON INPUT: verified against the live UltraMsg instance —
// "+963949101231" and "963949101231" both deliver and are recorded by WhatsApp
// as the same "963949101231@c.us". So a person may or may not type it; we
// accept either and always store the "+" form.
//
// WHY A COUNTRY CODE IS REQUIRED FOR FOREIGN NUMBERS: also verified live.
// UltraMsg strips a leading trunk 0 and then hands the number to WhatsApp,
// which resolves a bare local number against the INSTANCE's own country
// (Syria). So "0949101231" reaches a Syrian phone, but a German "015123456789"
// came back with status "invalid" — after the 0 is stripped there is no "49"
// to say which country it belongs to. A foreign number therefore only works if
// it arrives carrying its own country code.
class PhoneNumberService
{
    private const SYRIA = '963';

    // A Syrian mobile subscriber number is 9 digits and starts with 9
    // (9XXXXXXXX). This is what lets us tell a BARE Syrian number apart from a
    // foreign number that already carries its own country code, in the absence
    // of a "+".
    private const SYRIAN_SUBSCRIBER = '/^9\d{8}$/';

    public static function normalize(string $phone): string
    {
        // Drop the "+", spaces, dashes, parentheses — compare digits only.
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return '';
        }

        // 1. Already Syrian with its country code ("963933123456", or our own
        //    canonical "+963933123456" once the + is stripped). Idempotent:
        //    normalizing an already-normalized value must not prepend 963 twice
        //    — an earlier version of this class had exactly that bug.
        if (str_starts_with($digits, self::SYRIA)) {
            $rest = substr($digits, strlen(self::SYRIA));

            // Guard against a foreign number that merely happens to start with
            // 963 — only treat it as Syrian if what follows is a real Syrian
            // subscriber number.
            if (preg_match(self::SYRIAN_SUBSCRIBER, $rest)) {
                return '+' . self::SYRIA . $rest;
            }
        }

        // 2. Bare Syrian local number, with or without the trunk 0:
        //    "0933123456" or "933123456" -> +963933123456.
        $withoutTrunk = preg_replace('/^0+/', '', $digits);

        if (preg_match(self::SYRIAN_SUBSCRIBER, $withoutTrunk)) {
            return '+' . self::SYRIA . $withoutTrunk;
        }

        // 3. Anything else is treated as a foreign number that already carries
        //    its own country code ("4915123456789", "971501234567"). Store it
        //    as-is — prepending 963 here is what used to corrupt every
        //    non-Syrian number into an unreachable one.
        //
        //    A leading 0 is still stripped: nobody's international number
        //    begins with 0, so it can only be a trunk prefix the person typed
        //    out of habit.
        return '+' . $withoutTrunk;
    }

    // The recipient string UltraMsg wants. It accepts the "+" fine (verified
    // live), so this is just the canonical value — kept as a named method so
    // the intent is obvious at the call site and one place can change if the
    // gateway ever gets fussier.
    public static function forWhatsApp(string $phone): string
    {
        return self::normalize($phone);
    }
}
