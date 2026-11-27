<?php
namespace App\Support;

/**
 * AM245 opaque public feedback invitation tokens.
 * The caller must persist only tokenHash, expiresAt and scoped Job Card ID.
 * This class does not create routes, save records or authorize submissions.
 */
final class AmFeedbackInvitationToken
{
    public static function issue(): array
    {
        $token = bin2hex(random_bytes(32));
        return ['token' => $token, 'hash' => self::hash($token)];
    }

    public static function hash(string $token): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            throw new \InvalidArgumentException('Malformed invitation token');
        }
        return hash('sha256', $token);
    }

    public static function matches(string $token, string $storedHash): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $storedHash)) {
            return false;
        }
        return hash_equals($storedHash, hash('sha256', $token));
    }
}
