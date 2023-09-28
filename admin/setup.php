<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    mmipayments/admin/setup.php
 * \ingroup mmipayments
 * \brief   MMIPayments setup page.
 */

// Load Dolibarr environment
require_once "../env.inc.php";
require_once "../main_load.inc.php";

$arrayofparameters = array(
	'MMIPAYMENTS_INVOICE_PAYMENT_ASSIGN'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPAYMENTS_INVOICE_PAYMENT_CHANGE_AMOUNT'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPAYMENTS_CAISSE_COMPANY'=>array('type'=>'company', 'enabled'=>1),
	'MMIPAYMENTS_DEFAULT_MODE'=>array('type'=>'types_paiements', 'enabled'=>1),
	'MMIPAYMENTS_DEFAULT_ACCOUNT'=>array('type'=>'comptes', 'enabled'=>1),
	//'MMIPAYMENTS_FORM_CONFIRM_NOTIF'=>array('type'=>'yesno', 'enabled'=>1),
);

require_once('../../mmicommon/admin/mmisetup_1.inc.php');
