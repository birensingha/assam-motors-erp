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
