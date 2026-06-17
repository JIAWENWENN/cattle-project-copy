<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailDomain implements ValidationRule
{
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'throwaway.email', 'yopmail.com', 'sharklasers.com', 'temp-mail.org',
        'mailnator.com', 'getnada.com', 'trashmail.com', 'maildrop.cc',
        'dispostable.com', 'tempinbox.com', 'mailmetrash.com', 'emailfake.com',
        'emailsender.org', 'emailtmp.com', 'spambox.us', 'tempr.email',
        'mail-temp.com', 'fakeinbox.com', 'mailtemp.net', 'mailinator2.com',
        'discard.email', 'mt2009.com', 'spamherelots.com', 'thingforward.com',
        'nomail.xl.cx', 'frapmail.com', 'boun.cr', 'mailexpire.com',
        'guerrillamailblock.com', 'guerrillamail.org', 'guerrillamail.biz',
        'grr.la', 'guerrillamail.net', 'guerrillamail.de',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr($value, '@'), 1));

        if (!$domain) {
            $fail('The :attribute does not have a valid domain.');
            return;
        }

        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            $fail('Temporary / disposable email addresses are not allowed.');
            return;
        }

        if (str_contains($domain, '.')) {
            $parts = explode('.', $domain);
            $tld = end($parts);
            if (strlen($tld) < 2) {
                $fail('The :attribute must have a valid domain extension.');
                return;
            }
        }

        $hasMx = @dns_get_record($domain, DNS_MX);
        $hasA  = @dns_get_record($domain, DNS_A);
        if (empty($hasMx) && empty($hasA)) {
            $fail('The :attribute domain does not have a valid mail server.');
        }
    }
}
