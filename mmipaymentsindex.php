<?php
/* Copyright (C) 2026 Mathieu Moulin / iProspective <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    mmipayments/mmipaymentsindex.php
 * \brief   Page d'accueil du module MMI Payments.
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

$langs->loadLangs(array('mmipayments@mmipayments'));
dol_include_once('/mmicommon/lib/mmi_home.lib.php');

$title = $langs->trans('MMIPaymentsArea');

$tabs = array(
	'home' => array('url' => dol_buildpath('/mmipayments/mmipaymentsindex.php', 1), 'label' => $langs->trans('MMIPaymentsHome')),
	'payments' => array('url' => dol_buildpath('/mmipayments/payments.php', 1), 'label' => $langs->trans('MMIPaymentsTabTitle')),
	'list' => array('url' => dol_buildpath('/mmipayments/list.php', 1), 'label' => $langs->trans('MMIPaymentsAllPayments')),
);

MMIHomeHeader($title, $tabs, 'home');

print '<div class="opacitymedium">'.$langs->trans('MMIPaymentsHomeIntro').'</div>';

$links = array(
	$langs->trans('MMIPaymentsTabTitle') => dol_buildpath('/mmipayments/payments.php', 1),
	$langs->trans('MMIPaymentsAllPayments') => dol_buildpath('/mmipayments/list.php', 1),
	$langs->trans('Settings') => dol_buildpath('/mmipayments/admin/setup.php', 1),
);
print MMIHomeBlock($langs->trans('MMIPaymentsHomeLinks'), MMIHomeLinks($links));

MMIHomeFooter();
