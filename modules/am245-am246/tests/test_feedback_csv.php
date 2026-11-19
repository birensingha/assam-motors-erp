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
    ["\u{00A0}=1+1","'\u{00A0}=1+1"], // non-breaking space
    ["\u{200B}=1+1","'\u{200B}=1+1"], // zero-width space
    ["\u{FEFF}=1+1","'\u{FEFF}=1+1"], // UTF-8 BOM
    ["\u{3000}@SUM(1)","'\u{3000}@SUM(1)"], // ideographic space
    ["\u{2028}+1","'\u{2028}+1"], // Unicode line separator
    ["\u{00A0}ordinary text","\u{00A0}ordinary text"],
    ['Excellent service','Excellent service'],
    ['Rating ★★★★','Rating ★★★★'],
    ['Customer + message','Customer + message'],
    [null,''],
];
foreach($cases as $index=>[$input,$expected]) {
    $got=AmFeedbackCsv::safeCell($input);
    if($got!==$expected) {
        fwrite(STDERR,"AM245 feedback CSV safety test failed at case ".($index+1)."\n");
        exit(1);
    }
}
echo "AM245 feedback CSV safety tests PASSED\n";
