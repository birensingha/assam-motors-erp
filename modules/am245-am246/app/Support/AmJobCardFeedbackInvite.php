<?php
namespace App\Support;

/**
 * AM245 feedback invitation message builder. No database, sending or ledger access.
 * Call only after a server-side scoped Job Card/customer association and permitted
 * WhatsApp destination have been verified by the deployed ERP adapter.
 */
final class AmJobCardFeedbackInvite
{
    public static function compose(string $customerName, string $jobCardRef, string $vehiclePlate, string $feedbackUrl): string
    {
        $customerName = trim($customerName);
        $jobCardRef = trim($jobCardRef);
        $vehiclePlate = trim($vehiclePlate);
        if ($customerName === '' || $jobCardRef === '' || $vehiclePlate === '') {
            throw new \InvalidArgumentException('Verified Job Card context required');
        }
        if (mb_strlen($customerName) > 160 || mb_strlen($jobCardRef) > 64 || mb_strlen($vehiclePlate) > 32) {
            throw new \InvalidArgumentException('Job Card context exceeds bounds');
        }
        $url = filter_var($feedbackUrl, FILTER_VALIDATE_URL);
        $parts = $url ? parse_url($url) : false;
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || str_contains($feedbackUrl, "\r") || str_contains($feedbackUrl, "\n")) {
            throw new \InvalidArgumentException('Verified HTTPS feedback URL required');
        }
        foreach ([$customerName, $jobCardRef, $vehiclePlate] as $value) {
            if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new \InvalidArgumentException('Control characters not allowed');
            }
        }
        return "Namaskar {$customerName} Ji,\n\n"
            ."Thank you for choosing Assam Motors. Your vehicle {$vehiclePlate} "
            ."(Job Card {$jobCardRef}) has been serviced.\n\n"
            ."Please share your service feedback: {$feedbackUrl}\n\n"
            ."ASSAM MOTORS, Guwahati\nhttps://assammotors.com";
    }
}
