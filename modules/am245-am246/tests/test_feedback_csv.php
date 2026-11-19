<?php
require_once __DIR__.'/../app/Support/AmFeedbackCsv.php';

use App\Support\AmFeedbackCsv;

$cases=[
    ['=2+3',"'=2+3"],
    ['+SUM(1,2)',"'+SUM(1,2)"],
    ['-4+2', "'-4+2"],
    ['@HYPERLINK(x)',"'@HYPERLINK(x)"],
    ["\t=2+3","'\t=2+3"],
    ["\n+2","'\n+2"],
    ["  =X","'  =X"],
    ["\u00a0=1+1","'\u00a0=1+1"], // non-breaking space
    ["\u200b=1+1","'\u200b=1+1"], // zero-width space
    ["\ufeff=1+1","'\ufeff=1+1"], // UTF-8 BOM
    ["\u3000@SUM(1)","'\u3000@SUM(1)"], // ideographic space
    ["\u2028+1","'\u2028+1"], // Unicode line separator
    ["\u00a0ordinary text","\u00a0ordinary text"],
    ['Excellent service','Excellent service'],
    ['Rating ★★★★','Rating ★★★★'],
    ['Customer + message','Customer + message'],
    [null,''],
];
foreach($cases as [$input,$expected]) {
    $got=AmFeedbackCsv::safeCell($input);
    if($got!==$expected) {
        fwrite(STDERR,"AM245 feedback CSV safety test failed\n");
        exit(1);
    }
}
echo "AM245 feedback CSV safety tests PASSED\n";
