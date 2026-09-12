<?php
// Scratch probe: does the AGB rule actually produce an error? (checkout hardening (a))
// Mirrors validateCheckout(): rule key 'fields.termsAgreed' => ['sometimes','accepted'].
use Illuminate\Support\Facades\Validator;

$rules = ['fields.termsAgreed' => ['sometimes', 'accepted']];

$cases = [
    'key MISSING (fields has no termsAgreed)' => ['fields' => ['first_name' => 'A']],
    'null'                                    => ['fields' => ['termsAgreed' => null]],
    'false'                                   => ['fields' => ['termsAgreed' => false]],
    'string "0"'                              => ['fields' => ['termsAgreed' => '0']],
    'int 0'                                   => ['fields' => ['termsAgreed' => 0]],
    'empty string'                            => ['fields' => ['termsAgreed' => '']],
    'true (ticked)'                           => ['fields' => ['termsAgreed' => true]],
    'string "1" (ticked)'                     => ['fields' => ['termsAgreed' => '1']],
];

foreach ($cases as $label => $data) {
    $v = Validator::make($data, $rules);
    $fails = $v->fails();
    $msg = $v->errors()->first('fields.termsAgreed');
    printf("%-42s fails=%-5s msg=%s\n", $label, $fails ? 'YES' : 'no', $msg !== '' ? $msg : '(none)');
}
