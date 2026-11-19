<?php
namespace App\Support;

/**
 * Prevent spreadsheet formula execution when importing admin feedback CSV exports.
 * Treat text from customers and staff as untrusted, including cells with leading
 * whitespace, CR/LF and other controls before a spreadsheet formula starter.
 */
final class AmFeedbackCsv
{
    public static function safeCell($value): string
    {
        $text = (string) ($value ?? '');
        if (preg_match('/^[\\x00-\\x20]*[=+\\-@]/', $text)) {
            return "'".$text;
        }
        return $text;
    }
}
