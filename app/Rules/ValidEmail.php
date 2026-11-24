<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmail implements ValidationRule
{
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'throwaway.email', 'yopmail.com', 'getairmail.com', 'trashmail.com',
        'sharklasers.com', 'maildrop.cc', 'temp-mail.org', 'fakeinbox.com',
        'mailnator.com', 'mailexpire.com', 'spamgourmet.com', 'dispostable.com',
        'tempemail.net', 'tempmail.net', 'emailondeck.com', 'mailcatch.com',
        'burnermail.io', 'mohmal.com', 'emailfake.com', 'tempinbox.com',
        'mailmetrash.com', 'filzmail.com', 'mytrashmail.com', 'trash2009.com',
        'mt2009.com', 'trashymail.com', 'tyldd.com', 'maileater.com',
        'sogetthis.com', 'pookmail.com', 'dodgeit.com', 'spamhereplease.com',
        'sendspamhere.com', 'mailshell.com', 'meltmail.com', 'wegwerfmail.de',
        'spamavert.com', 'thankyou2010.com', 'trash-me.com', 'trash2009.com',
        'mailinator2.com', 'fuckingtemporaryemail.com', 'tempemail.co',
        'tmpeml.com', '10minutemail.co', 'emaill.com', 'emailsy.com',
        'gufum.com', 'jingg.org', 'kavosb.com', 'mail4trash.com',
        'mailtrash.net', 'mytempemail.com', 'tempemail.biz', 'trashmail.at',
        'trashmail.me', 'trashmail.net', 'wegwerfmail.net', 'wegwerfmail.org',
        'wh4f.org', 'zippymail.in', 'mail-temp.com', 'disposableemailaddresses.com',
        'emailhour.com', 'mailmetrash.com', 'mintemail.com', 'nepwk.com',
        'nomail.xl.cx', 'nulladdress.com', 'oneoffemail.com', 'thankyou2010.com',
        'trash2009.com', 'veryrealemail.com', 'wegwerfmail.de', 'wegwerfmail.info',
    ];

    private const COMMON_TYPOS = [
        'gmail.com' => ['gmial.com', 'gmal.com', 'gmil.com', 'gmaill.com', 'gnail.com', 'gamil.com', 'gmai.com', 'gmail.co', 'gmail.cm', 'gmail.vom'],
        'yahoo.com' => ['yaho.com', 'yahooo.com', 'yahho.com', 'yhoo.com', 'yaoo.com', 'yahoo.co'],
        'outlook.com' => ['outlok.com', 'outllok.com', 'outook.com', 'outloo.com', 'outloook.com'],
        'hotmail.com' => ['hotmal.com', 'hotmil.com', 'hotmai.com', 'hotmaill.com', 'hotmail.co'],
        'live.com' => ['liv.com', 'lve.com', 'live.co'],
        'ymail.com' => ['ymial.com', 'ymal.com'],
        'aol.com' => ['aol.co', 'aol.cm'],
        'icloud.com' => ['icloud.co', 'iclud.com', 'icould.com'],
        'protonmail.com' => ['protonail.com', 'protonmal.com', 'protonmil.com'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        $domain = strtolower(explode('@', $value)[1]);

        foreach (self::COMMON_TYPOS as $correct => $typos) {
            if (in_array($domain, $typos, true)) {
                $fail("The email domain looks incorrect. Did you mean @{$correct}?");

                return;
            }
        }

        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            $fail('Temporary or disposable email addresses are not allowed. Please use a permanent email address.');

            return;
        }

        if (! $this->domainHasMxRecord($domain)) {
            $fail('The email is not existing or invalid. Please use a valid email address.');
        }
    }

    private function domainHasMxRecord(string $domain): bool
    {
        if (function_exists('checkdnsrr') && checkdnsrr($domain, 'MX')) {
            return true;
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_MX);

            if (is_array($records) && count($records) > 0) {
                return true;
            }
        }

        return false;
    }
}
