<?php
/**
 * Copyright (C) 2026  Mathieu Moulin / iProspective  <contact@iprospective.fr>
 *
 * Onglet « Paiements » : règlements et tentatives de paiement rattachés à un
 * objet métier (tiers, devis, commande, facture).
 *
 * Ce que la page apporte par rapport à la liste native des règlements : les
 * TENTATIVES. La table llx_paiement ne contient que les paiements aboutis ;
 * les échecs, les échéanciers et les motifs de refus vivent dans les tables des
 * modules de paiement (mbietransactions, mmiyounited…). Ce socle affiche donc
 * les règlements qu'il connaît, et ouvre un point d'extension par lequel chaque
 * module de paiement ajoute ce que lui seul sait — cf. mmi_payments::payment_rows().
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

dol_include_once('/mmipayments/class/mmi_payments.class.php');

$langs->loadLangs(array("mmipayments@mmipayments", "companies", "compta", "bills", "banks", "orders", "propal", "other"));

$objecttype = GETPOST('objecttype', 'aZ09');
$id         = GETPOSTINT('id');
if (empty($id)) {
	$id = GETPOSTINT('socid');	// l'onglet tiers passe historiquement socid
}
$action = GETPOST('action', 'aZ09');

/*
 * Chargement de l'objet porteur — un cas par type d'onglet.
 * Chaque entrée porte tout ce qui diffère d'un type à l'autre : classe, droit,
 * en-tête de fiche, onglet actif, libellé et lien retour.
 */
$types = array(
	'Client' => array(
		'class' => 'Societe', 'file' => '/societe/class/societe.class.php',
		'right' => array('societe', 'lire'), 'feature' => 'societe',
		'head' => 'societe_prepare_head', 'headlib' => '/core/lib/company.lib.php',
		'activetab' => 'mmipayments', 'picto' => 'company',
		'title' => 'ThirdParty', 'backlist' => '/societe/list.php',
	),
	'Propal' => array(
		'class' => 'Propal', 'file' => '/comm/propal/class/propal.class.php',
		'right' => array('propal', 'lire'), 'feature' => 'propal',
		'head' => 'propal_prepare_head', 'headlib' => '/core/lib/propal.lib.php',
		'activetab' => 'mmipayments', 'picto' => 'propal',
		'title' => 'Proposal', 'backlist' => '/comm/propal/list.php',
	),
	'Commande' => array(
		'class' => 'Commande', 'file' => '/commande/class/commande.class.php',
		'right' => array('commande', 'lire'), 'feature' => 'commande',
		'head' => 'commande_prepare_head', 'headlib' => '/core/lib/order.lib.php',
		'activetab' => 'mmipayments', 'picto' => 'order',
		'title' => 'Order', 'backlist' => '/commande/list.php',
	),
	'Facture' => array(
		'class' => 'Facture', 'file' => '/compta/facture/class/facture.class.php',
		'right' => array('facture', 'lire'), 'feature' => 'facture',
		'head' => 'facture_prepare_head', 'headlib' => '/core/lib/invoice.lib.php',
		'activetab' => 'mmipayments', 'picto' => 'bill',
		'title' => 'Bill', 'backlist' => '/compta/facture/list.php',
	),
);

if (!isset($types[$objecttype])) {
	accessforbidden('Type d\'objet non géré : '.dol_escape_htmltag($objecttype));
}
$conf_type = $types[$objecttype];

if (!call_user_func_array(array($user, 'hasRight'), $conf_type['right'])) {
	accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.$conf_type['file'];
require_once DOL_DOCUMENT_ROOT.$conf_type['headlib'];

$object = new $conf_type['class']($db);
if ($id > 0) {
	$object->fetch($id);
}
if (empty($object->id)) {
	accessforbidden($langs->trans("ErrorRecordNotFound"));
}
if ($objecttype == 'Client') {
	restrictedArea($user, 'societe', $object->id, '&societe');
} else {
	restrictedArea($user, $conf_type['feature'], $object->id);
}

/*
 * Actions — déléguées aux modules de paiement.
 *
 * Le socle ne porte aucune action : recréer un paiement, relancer une échéance…
 * sont des gestes propres à chaque moyen de paiement. Le module qui a fourni la
 * ligne traite son action, puis la page se recharge en GET pour éviter le re-POST.
 */
$hookmanager->initHooks(array('mmipaymentstab'));

if (!empty($action)) {
	$parameters = array('objecttype' => $objecttype, 'id' => $object->id);
	$reshook = $hookmanager->executeHooks('doPaymentTabAction', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	if ($reshook != 0) {
		header('Location: '.$_SERVER['PHP_SELF'].'?objecttype='.urlencode($objecttype).'&id='.$object->id);
		exit;
	}
}

/*
 * View
 */

$title = $langs->trans("MMIPaymentsTabTitle").' - '.($object->ref ? $object->ref : $object->name);
llxHeader('', $title);

$head = call_user_func($conf_type['head'], $object);
print dol_get_fiche_head($head, $conf_type['activetab'], $langs->trans($conf_type['title']), -1, $conf_type['picto']);

$linkback = '<a href="'.DOL_URL_ROOT.$conf_type['backlist'].'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
if ($objecttype == 'Client') {
	dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');
} else {
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref');
}

print '<div class="fichecenter"><div class="underbanner clearboth"></div></div>';
print dol_get_fiche_end();

print '<br>';

$rows = mmi_payments::payment_rows($objecttype, $object->id, $hookmanager);

print load_fiche_titre($langs->trans("MMIPaymentsTabTitle"), '', 'bill');

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th>';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("LinkedObject").'</th>';
print '<th>'.$langs->trans("PaymentMode").'</th>';
print '<th class="center">'.$langs->trans("Status").'</th>';
print '<th class="right">'.$langs->trans("Amount").'</th>';
print '<th></th>';
print '</tr></thead>';

print '<tbody>';
if (empty($rows)) {
	print '<tr><td colspan="7" class="opacitymedium center">'.$langs->trans("MMIPaymentsNoPaymentForObject").'</td></tr>';
}

$total_received = 0;
foreach ($rows as $row) {
	if (!empty($row['is_payment'])) {
		$total_received += round($row['amount'], 2);
	}

	// Une tentative sans suite est de l'information, pas un encaissement : on
	// l'affiche en retrait pour que la lecture comptable reste évidente.
	print '<tr class="oddeven'.(empty($row['is_payment']) ? ' opacitymedium' : '').'">';
	print '<td class="nowraponall">'.dol_print_date($row['date'], 'day').'</td>';
	print '<td class="nowraponall">'.$row['ref'].'</td>';
	print '<td>'.$row['objects'].'</td>';
	print '<td>'.$row['mode'].'</td>';
	print '<td class="center">'.$row['status'].'</td>';
	print '<td class="right nowraponall">'.price($row['amount'], 0, $langs, 1, -1, -1, $conf->currency).'</td>';
	print '<td class="right nowraponall">'.$row['actions'].'</td>';
	print '</tr>';
}
print '</tbody>';

if (!empty($rows)) {
	print '<tfoot><tr class="liste_total">';
	print '<td colspan="5">'.$langs->trans("MMIPaymentsTotalReceived").'</td>';
	print '<td class="right nowraponall">'.price($total_received, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
	print '<td></td>';
	print '</tr></tfoot>';
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
