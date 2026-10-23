<?php

namespace App\Services\Auth;

/**
 * Normalises phone numbers so the E.164 number coming from Firebase can
 * be compared with the number stored on the customer account.
 *
 * Firebase always returns the phone number in E.164 format
 * (`+85512345678`). The `users.phone` column however may still hold a
 * locally formatted number (`012345678`) depending on how the account
 * was created, so both sides are normalised before they are compared.
 */
class PhoneNumberNormalizer
{
    /**
     * Convert a phone number to the E.164 format, or null when the
     * number cannot be understood.
     */
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Keep the digits only (and remember whether a "+" was there).
        $trimmed = trim($phone);

        if ($trimmed === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            return $this->e164($digits);
        }

        // International format written as 00<country code><number>.
        if (str_starts_with($digits, '00')) {
            return $this->e164(substr($digits, 2));
        }

        // National format: 0<national number> (Cambodia: 012345678).
        if (str_starts_with($digits, '0')) {
            return $this->e164(
                $this->defaultCountryCode().ltrim($digits, '0')
            );
        }

        // Already contains the country code (e.g. 85512345678).
        return $this->e164($digits);
    }

    /**
     * Whether the phone number stored on the account belongs to the
     * phone number that Firebase just verified.
     */
    public function matches(?string $storedPhone, string $verifiedPhone): bool
    {
        $stored = $this->normalize($storedPhone);
        $verified = $this->normalize($verifiedPhone);

        if ($stored === null || $verified === null) {
            return false;
        }

        if ($stored === $verified) {
            return true;
        }

        // Tolerate accounts whose number was stored with another country
        // code prefix by comparing the national part as well.
        $countryCode = $this->defaultCountryCode();

        $storedNational = str_starts_with($stored, '+'.$countryCode)
            ? substr($stored, strlen($countryCode) + 1)
            : $stored;

        $verifiedNational = str_starts_with($verified, '+'.$countryCode)
            ? substr($verified, strlen($countryCode) + 1)
            : $verified;

        return $storedNational !== '' && $storedNational === $verifiedNational;
    }

    /**
     * The E.164 representation of a number, or null when it is invalid.
     */
    private function e164(string $digits): ?string
    {
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * The country code used for numbers stored in the local format.
     */
    private function defaultCountryCode(): string
    {
        $code = preg_replace(
            '/\D+/',
            '',
            (string) config('firebase.default_country_code', '855')
        ) ?? '';

        return $code !== '' ? $code : '855';
    }
}
