<?php
/**
 * Copyright (C) 2026  Mathieu Moulin / iProspective  <contact@iprospective.fr>
 *
 * Liste consolidée des règlements d'un tiers (tous modes : CB eTransactions,
 * virement, chèque, espèces…), rattachés aux factures et aux commandes / devis.
 * Atteinte depuis le bloc « Derniers règlements » de l'onglet Client.
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

dol_include_once('/mmipayments/class/mmi_payments.class.php');

$langs->loadLangs(array("mmipayments@mmipayments", "companies", "compta", "bills", "banks"));

$socid = GETPOSTINT('socid');

// Sécurité : accès limité aux utilisateurs autorisés sur les tiers
if (!$user->hasRight('societe', 'lire')) {
	accessforbidden();
}
$object = new Societe($db);
if ($socid > 0) {
	$object->fetch($socid);
}
if (empty($object->id)) {
	accessforbidden('Tiers introuvable');
}
restrictedArea($user, 'societe', $object->id, '&societe');

/*
 * Helpers
 */

/**
 * Libellé traduit d'un mode de règlement (fallback sur le libellé c_paiement).
 */
function mmipayments_mode_label($p)
{
	global $langs;
	if (empty($p->mode_code))
		return $p->mode_label ? $p->mode_label : '';
	$key = 'PaymentType'.$p->mode_code;
	$label = $langs->trans($key);
	if ($label != $key)
		return $label;
	return $p->mode_label ? $p->mode_label : $p->mode_code;
}

/**
 * Rendu HTML des objets métier (facture / commande / devis) liés à un règlement.
 */
function mmipayments_render_objects($p)
{
	if (empty($p->objects))
		return '<span class="opacitymedium">-</span>';
	$links = array();
	foreach ($p->objects as $o) {
		$url = '';
		if ($o['type'] == 'Facture')
			$url = DOL_URL_ROOT.'/compta/facture/card.php?id='.$o['id'];
		elseif ($o['type'] == 'Commande')
			$url = DOL_URL_ROOT.'/commande/card.php?id='.$o['id'];
		elseif ($o['type'] == 'Propal')
			$url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$o['id'];
		$label = dol_escape_htmltag($o['ref'] ? $o['ref'] : $o['type'].' #'.$o['id']);
		$links[] = $url ? '<a href="'.$url.'">'.$label.'</a>' : $label;
	}
	return implode(', ', $links);
}

/*
 * View
 */

$title = $langs->trans("MMIPaymentsAllPayments").' - '.$object->name;
llxHeader('', $title);

$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'customer', $langs->trans("ThirdParty"), -1, 'company');

$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '</div>';

print dol_get_fiche_end();

print '<br>';
print load_fiche_titre($langs->trans("MMIPaymentsAllPayments"), '', 'bill');

$payments = mmi_payments::client_payments($object->id, 0);

$total = 0;
foreach ($payments as $p) {
	$total += round($p->amount, 2);
}

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<thead>';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("LinkedObject").'</th>';
print '<th>'.$langs->trans("PaymentMode").'</th>';
print '<th class="right">'.$langs->trans("Amount").'</th>';
print '</tr>';
print '</thead>';

print '<tbody>';

if (empty($payments)) {
	print '<tr><td colspan="5" class="opacitymedium center">'.$langs->trans("MMIPaymentsNoPayment").'</td></tr>';
}

foreach ($payments as $p) {
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($p->datep), 'day').'</td>';
	print '<td class="nowraponall"><a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.$p->rowid.'">'.img_picto('', 'payment', 'class="paddingright"').dol_escape_htmltag($p->ref ? $p->ref : '#'.$p->rowid).'</a></td>';
	print '<td>'.mmipayments_render_objects($p).'</td>';
	print '<td>'.dol_escape_htmltag(mmipayments_mode_label($p)).(!empty($p->num_paiement) ? ' <span class="opacitymedium small">('.dol_escape_htmltag($p->num_paiement).')</span>' : '').'</td>';
	print '<td class="right nowraponall">'.price($p->amount, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
	print '</tr>';
}

print '</tbody>';

if (!empty($payments)) {
	print '<tfoot>';
	print '<tr class="liste_total">';
	print '<td colspan="4">'.$langs->trans("MMIPaymentsTotalReceived").' ('.count($payments).')</td>';
	print '<td class="right nowraponall">'.price($total, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
	print '</tr>';
	print '</tfoot>';
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
