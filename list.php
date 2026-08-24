<?php
/**
 * Copyright (C) 2026  Mathieu Moulin / iProspective  <contact@iprospective.fr>
 *
 * Liste globale des règlements, et vue des tentatives de paiement.
 *
 * Deux modes :
 *  - « Règlements » : tous les encaissements, tous tiers confondus (le socle) ;
 *  - « Paiements à recréer » : les transactions abouties côté banque dont le
 *    paiement Dolibarr n'existe pas (ou plus). Ces lignes viennent des modules de
 *    paiement — llx_paiement ne contient que les paiements aboutis, une transaction
 *    sans paiement n'y est par définition pas. Sans cette vue, il faut ouvrir les
 *    fiches une par une pour retrouver les régularisations à faire.
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

dol_include_once('/mmipayments/class/mmi_payments.class.php');

$langs->loadLangs(array("mmipayments@mmipayments", "companies", "compta", "bills", "banks", "other"));

if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

$mode     = GETPOST('mode', 'aZ09');
if (!in_array($mode, array('payments', 'attempts'))) {
	$mode = 'payments';
}
$socid    = GETPOSTINT('search_socid');
$datefrom = dol_mktime(0, 0, 0, GETPOSTINT('datefrommonth'), GETPOSTINT('datefromday'), GETPOSTINT('datefromyear'));
$dateto   = dol_mktime(23, 59, 59, GETPOSTINT('datetomonth'), GETPOSTINT('datetoday'), GETPOSTINT('datetoyear'));

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page  = GETPOSTINT('page') > 0 ? GETPOSTINT('page') : 0;
$offset = $limit * $page;

$hookmanager->initHooks(array('mmipaymentslist'));

$filters = array(
	'mode' => $mode, 'socid' => $socid,
	'datefrom' => $datefrom, 'dateto' => $dateto,
	'limit' => $limit, 'offset' => $offset,
	'missing_payment' => ($mode === 'attempts') ? 1 : 0,
);
$res = mmi_payments::global_payment_rows($filters, $hookmanager);

/*
 * View
 */

$title = $langs->trans("MMIPaymentsAllPayments");
llxHeader('', $title);

$form = new Form($db);

$num = count($res['rows']);
$params = '&mode='.urlencode($mode);
if ($socid) {
	$params .= '&search_socid='.$socid;
}
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $params, '', '', '', $num, $res['total'], 'bill', 0, '', '', $limit);

// Bascule entre les deux natures de données
print '<div class="marginbottomonly">';
foreach (array('payments' => 'MMIPaymentsModePayments', 'attempts' => 'MMIPaymentsModeAttempts') as $m => $key) {
	$cls = ($mode === $m) ? 'butActionRefused' : 'butAction';
	print '<a class="'.$cls.'" href="'.$_SERVER['PHP_SELF'].'?mode='.$m.'">'.$langs->trans($key).'</a>';
}
print '</div>';

if ($mode === 'attempts' && empty($res['rows']) && empty($hookmanager->hooks['mmipaymentslist'])) {
	print info_admin($langs->trans("MMIPaymentsNoModuleProvidesAttempts"));
}
if (!empty($res['truncated'])) {
	// Un plafond qui ne se voit pas se lit comme une liste complète : on l'annonce.
	print info_admin($langs->trans("MMIPaymentsListTruncated", $limit, $res['total']));
}

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$form->select_company($socid, 'search_socid', '', 1, 0, 0, array(), 0, 'minwidth200').'</td>';
print '<td colspan="3">';
print $langs->trans("From").' '.$form->selectDate($datefrom, 'datefrom', 0, 0, 1, '', 1, 0);
print ' '.$langs->trans("to").' '.$form->selectDate($dateto, 'dateto', 0, 0, 1, '', 1, 0);
print '</td>';
print '<td class="right"><input type="submit" class="button small" value="'.$langs->trans("Search").'"></td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("ThirdParty").'</th>';
print '<th>'.$langs->trans("LinkedObject").'</th>';
print '<th>'.$langs->trans("PaymentMode").'</th>';
print '<th class="right">'.$langs->trans("Amount").'</th>';
print '</tr>';

if (empty($res['rows'])) {
	print '<tr><td colspan="6" class="opacitymedium center">'.$langs->trans("MMIPaymentsNoPaymentForObject").'</td></tr>';
}

$total = 0;
foreach ($res['rows'] as $key => $r) {
	if (is_object($r)) {
		// Règlement (socle)
		$total += round($r->amount, 2);
		$soc = '';
		if (!empty($r->socid)) {
			$s = new Societe($db);
			if ($s->fetch($r->socid) > 0) {
				$soc = $s->getNomUrl(1);
			}
		}
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_print_date($db->jdate($r->datep), 'day').'</td>';
		print '<td class="nowraponall"><a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.$r->rowid.'">'
			.img_picto('', 'payment', 'class="paddingright"').dol_escape_htmltag($r->ref ? $r->ref : '#'.$r->rowid).'</a></td>';
		print '<td>'.$soc.'</td>';
		print '<td>'.mmi_payments::render_linked_objects($r->objects).'</td>';
		print '<td>'.dol_escape_htmltag(mmi_payments::mode_label($r)).'</td>';
		print '<td class="right nowraponall">'.price($r->amount, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
		print '</tr>';
	} else {
		// Tentative (contribuée par un module de paiement)
		$r += array('date' => 0, 'amount' => 0, 'ref' => '', 'thirdparty' => '',
			'objects' => '', 'mode' => '', 'status' => '', 'actions' => '');
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_print_date($r['date'], 'day').'</td>';
		print '<td class="nowraponall">'.$r['ref'].'</td>';
		print '<td>'.$r['thirdparty'].'</td>';
		print '<td>'.$r['objects'].'</td>';
		print '<td>'.$r['mode'].' '.$r['status'].'</td>';
		print '<td class="right nowraponall">'.price($r['amount'], 0, $langs, 1, -1, -1, $conf->currency).'</td>';
		print '</tr>';
	}
}

if ($mode === 'payments' && !empty($res['rows'])) {
	print '<tr class="liste_total">';
	print '<td colspan="5">'.$langs->trans("MMIPaymentsTotalReceived").' ('.$num.')</td>';
	print '<td class="right nowraponall">'.price($total, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
