<?php
/**
 * Copyright (C) 2026  Mathieu Moulin / iProspective  <contact@iprospective.fr>
 *
 * Liste globale des règlements, et vue des tentatives de paiement.
 *
 * Deux natures de données, deux modes :
 *  - « Règlements » : tous les encaissements, tous tiers confondus (le socle) ;
 *  - « Tentatives » : les tentatives de paiement, filtrables par statut. Ces lignes
 *    viennent des modules de paiement — llx_paiement ne contient que les paiements
 *    aboutis, un refus n'y est par définition pas. Sans cette vue, un refus ne se
 *    voit qu'en ouvrant la fiche du document concerné.
 *
 * Le bouton « Paiements à recréer » n'est pas un troisième mode : c'est le mode
 * « Tentatives » préfiltré sur les transactions abouties dont le paiement Dolibarr
 * manque, la vue de travail des régularisations à faire.
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
$status   = GETPOST('search_status', 'aZ09');
if (!in_array($status, mmi_payments::attempt_statuses(), true)) {
	$status = mmi_payments::ATTEMPT_STATUS_DEFAULT;
}
$socid    = GETPOSTINT('search_socid');
$datefrom = dol_mktime(0, 0, 0, GETPOSTINT('datefrommonth'), GETPOSTINT('datefromday'), GETPOSTINT('datefromyear'));
$dateto   = dol_mktime(23, 59, 59, GETPOSTINT('datetomonth'), GETPOSTINT('datetoday'), GETPOSTINT('datetoyear'));

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page  = GETPOSTINT('page') > 0 ? GETPOSTINT('page') : 0;
$offset = $limit * $page;

$hookmanager->initHooks(array('mmipaymentslist'));

$filters = array(
	'mode' => $mode, 'status' => $status, 'socid' => $socid,
	'datefrom' => $datefrom, 'dateto' => $dateto,
	'limit' => $limit, 'offset' => $offset,
);
$res = mmi_payments::global_payment_rows($filters, $hookmanager);

// Filtrer depuis une page lointaine peut laisser au-delà de la fin du résultat : la
// page serait vide sans rien dire. On revient au début, comme les listes du coeur.
if (empty($res['rows']) && $page > 0 && !empty($res['total'])) {
	$page = 0;
	$offset = 0;
	$filters['offset'] = 0;
	$res = mmi_payments::global_payment_rows($filters, $hookmanager);
}

/*
 * View
 */

$title = $langs->trans("MMIPaymentsAllPayments");
llxHeader('', $title);

$form = new Form($db);

$num = count($res['rows']);

// Les filtres doivent survivre au changement de page : sans eux, page 2 d'une
// recherche filtrée renvoie la page 2 de TOUT, sans que rien ne le signale.
$filterparams = '';
if ($socid) {
	$filterparams .= '&search_socid='.$socid;
}
if ($datefrom) {
	$filterparams .= '&datefromday='.dol_print_date($datefrom, '%d').'&datefrommonth='.dol_print_date($datefrom, '%m').'&datefromyear='.dol_print_date($datefrom, '%Y');
}
if ($dateto) {
	$filterparams .= '&datetoday='.dol_print_date($dateto, '%d').'&datetomonth='.dol_print_date($dateto, '%m').'&datetoyear='.dol_print_date($dateto, '%Y');
}
$params = '&mode='.urlencode($mode).$filterparams;
if ($mode === 'attempts') {
	$params .= '&search_status='.urlencode($status);
}
// print_barre_liste n'affiche la navigation que si $num > $limit : la convention du
// coeur est de demander limit+1 lignes pour savoir s'il en reste une de plus. Nous
// connaissons le total exact, donc nous répondons directement à la question — sans
// cela, la liste s'arrête à la première page sans le moindre lien vers la suivante.
$hasnext = empty($res['truncated']) && !empty($res['total']) && ($res['total'] > $offset + $limit);
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $params, '', '', '',
	$hasnext ? $limit + 1 : $num, $res['total'], 'bill', 0, '', '', $limit);

// Bascule entre les natures de données. « Paiements à recréer » est le mode
// « Tentatives » préfiltré : un raccourci vers la vue de travail, pas un mode de plus.
$views = array(
	array('mode' => 'payments', 'status' => '',        'key' => 'MMIPaymentsModePayments'),
	array('mode' => 'attempts', 'status' => mmi_payments::ATTEMPT_STATUS_DEFAULT, 'key' => 'MMIPaymentsModeAttempts'),
	array('mode' => 'attempts', 'status' => 'missing', 'key' => 'MMIPaymentsModeToRecreate'),
);
print '<div class="marginbottomonly">';
foreach ($views as $v) {
	if ($v['mode'] === 'payments') {
		$active = ($mode === 'payments');
	} else {
		$active = ($mode === 'attempts' && (($status === 'missing') === ($v['status'] === 'missing')));
	}
	$href = $_SERVER['PHP_SELF'].'?mode='.$v['mode'].$filterparams;
	if ($v['status']) {
		$href .= '&search_status='.urlencode($v['status']);
	}
	print '<a class="'.($active ? 'butActionRefused' : 'butAction').'" href="'.$href.'">'.$langs->trans($v['key']).'</a>';
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
if ($mode !== 'attempts') {
	// Le statut n'a pas de sens sur les règlements, mais il doit survivre à un
	// aller-retour par ce mode : sinon revenir aux tentatives perd le filtre.
	print '<input type="hidden" name="search_status" value="'.dol_escape_htmltag($status).'">';
}

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$form->select_company($socid, 'search_socid', '', 1, 0, 0, array(), 0, 'minwidth200').'</td>';
print '<td colspan="'.($mode === 'attempts' ? 2 : 3).'">';
print $langs->trans("From").' '.$form->selectDate($datefrom, 'datefrom', 0, 0, 1, '', 1, 0);
print ' '.$langs->trans("to").' '.$form->selectDate($dateto, 'dateto', 0, 0, 1, '', 1, 0);
print '</td>';
if ($mode === 'attempts') {
	$statuslabels = array();
	foreach (mmi_payments::attempt_statuses() as $st) {
		$statuslabels[$st] = $langs->trans('MMIPaymentsAttemptStatus'.ucfirst($st));
	}
	print '<td>'.$form->selectarray('search_status', $statuslabels, $status, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150').'</td>';
}
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
