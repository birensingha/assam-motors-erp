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
        // Unicode whitespace (NBSP, ideographic space), format controls (BOM,
// zero-width space), and ASCII controls can precede a formula trigger.
// Use UTF-8 Unicode properties; retain the original cell contents verbatim.
if (preg_match('/^[\\p{Z}\\p{C}\\x00-\\x20]*[=+\\-@]/u', $text)) {
            return "'".$text;
        }
        return $text;
    }
}
