<?php
require_once __DIR__.'/../app/Support/AmFeedbackInvitationToken.php';
use App\Support\AmFeedbackInvitationToken;

$a=AmFeedbackInvitationToken::issue();
$b=AmFeedbackInvitationToken::issue();
if (!preg_match('/\A[a-f0-9]{64}\z/D', $a['token'])) throw new RuntimeException('Invalid token format');
if (strlen($a['hash']) !== 64 || $a['hash'] === $a['token']) throw new RuntimeException('Raw token stored as hash');
if ($a['token'] === $b['token']) throw new RuntimeException('Token collision');
if (!AmFeedbackInvitationToken::matches($a['token'],$a['hash'])) throw new RuntimeException('Valid token rejected');
foreach ([$b['token'],'','not-a-token',strtoupper($a['token']),$a['token']."\n"] as $invalid) {
    if (AmFeedbackInvitationToken::matches($invalid,$a['hash'])) throw new RuntimeException('Invalid token accepted');
}
if (AmFeedbackInvitationToken::matches($a['token'],'invalid-hash')) throw new RuntimeException('Invalid hash accepted');
try {
    AmFeedbackInvitationToken::hash('short');
    throw new RuntimeException('Malformed token accepted');
} catch (InvalidArgumentException $expected) {}
echo "Feedback invitation token tests passed\n";
