<?php

use Luracast\Restler\RestException;

dol_include_once('custom/mmicommon/class/mmi_prestasyncapi.class.php');

class MMIPaymentApi extends MMI_PrestasyncApi_1_0
{
	/**
	 * Assoc payment to invoice
	 *
	 * @param int   $id             Id of commande whom invoice has a new payment to be associated
	 * @param array $request_data   Datas
	 * @return int
	 *
	 * @url     payment_invoice_assoc/{id}
	 */
	function commande_payment_invoice_assoc($id, $request_data=[])
	{
		global $user;

		static::_getsynchrouser();
		
		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		
		$commande = new Commande($this->db);
		$commande->fetch($id);

		if ($commande->id) {
			mmi_payments::invoice_autoassign_payments_from_object($commande);
			return 1;
		}
		else {
			return 0;
		}
	}
}

