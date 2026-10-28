<?php
/**
 * Tests unitaires de mmi_payments_label::resolve() — RM3581.
 * Sans dépendance : php test/unit/mmi_payments_label_test.php
 */
require_once __DIR__.'/../../class/mmi_payments_label.class.php';

$fail = 0; $n = 0;
function check($label, $cond)
{
	global $fail, $n;
	$n++;
	if ($cond) { echo "ok   $label\n"; } else { $fail++; echo "FAIL $label\n"; }
}
$trans_found = function ($key) { return $key === 'PaymentType3' ? 'Carte bancaire' : $key; };
$trans_none  = function ($key) { return $key; };

check('code vide + libellé → libellé', mmi_payments_label::resolve('', 'CB', $trans_none) === 'CB');
check('code vide + sans libellé → vide', mmi_payments_label::resolve(null, null, $trans_none) === '');
check('traduction trouvée → libellé traduit', mmi_payments_label::resolve('3', 'CB', $trans_found) === 'Carte bancaire');
check('traduction absente + libellé → libellé de secours', mmi_payments_label::resolve('3', 'CB', $trans_none) === 'CB');
check('traduction absente sans libellé → code', mmi_payments_label::resolve('3', null, $trans_none) === '3');
check('traduction absente libellé vide → code', mmi_payments_label::resolve('3', '', $trans_none) === '3');
check('code "0" traité comme vide (comportement existant)', mmi_payments_label::resolve('0', 'CB', $trans_none) === 'CB');

echo "\n$n test(s), $fail échec(s)\n";
exit($fail ? 1 : 0);
