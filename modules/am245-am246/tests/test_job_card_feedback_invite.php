<?php
require_once __DIR__.'/../app/Support/AmJobCardFeedbackInvite.php';
use App\Support\AmJobCardFeedbackInvite;

function expectReject(callable $fn): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected invalid input rejection');
}
$link='https://staging.assammotors.com/feedback/example-token';
$message=AmJobCardFeedbackInvite::compose('Test Customer','JC-TEST-001','TEST1234',$link);
foreach (['Test Customer','JC-TEST-001','TEST1234',$link] as $needle) {
    if (!str_contains($message,$needle)) throw new RuntimeException('Missing verified context');
}
expectReject(fn()=>AmJobCardFeedbackInvite::compose('','JC-TEST-001','TEST1234',$link));
expectReject(fn()=>AmJobCardFeedbackInvite::compose('Test','JC-TEST-001','TEST1234','http://example.com/feedback'));
expectReject(fn()=>AmJobCardFeedbackInvite::compose('Test','JC-TEST-001','TEST1234','https://user:pass@example.com/'));
expectReject(fn()=>AmJobCardFeedbackInvite::compose("Test\nInjected",'JC-TEST-001','TEST1234',$link));
expectReject(fn()=>AmJobCardFeedbackInvite::compose('Test','JC-TEST-001','TEST1234',"https://example.com/\nInjected"));
echo "Job Card feedback invitation tests passed\n";
