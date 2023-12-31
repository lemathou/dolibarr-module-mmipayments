<?php

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('custom/mmipayments/class/mmi_payments.class.php');

class ActionsMMIPayments extends MMI_Actions_1_0
{
	const MOD_NAME = 'mmipayments';

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		// Réglement
		if ($this->in_context($parameters, 'paymentcard')) {
			if (false) {
				echo '<div style="text-align: left">';
				$client = $object->thirdparty;
				var_dump($client);
				var_dump($object);
			}

			return 0;
		}

		// Facture
		if ($this->in_context($parameters, 'invoicecard') && $object->statut>0) {
			if (!empty($conf->global->MMIPAYMENTS_INVOICE_PAYMENT_ASSIGN)) {
				if ($object->type == 0) {
					$link = '?facid='.$object->id.'&action=payment_assign';
					echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIPaymentsAssign")."</a>";
				}
				else {
					echo '<a class="butActionRefused" title="'.$langs->trans('NotAllowed').'" href="javascript:;">'.$langs->trans("MMIPaymentsAssign")."</a>";
				}
			}

			if (!empty($conf->global->MMIPAYMENTS_INVOICE_PAYMENT_CHANGE_AMOUNT)) {
				echo '<script>
				$(document).ready(function(){
					var onlinepaymenturlval = $("#onlinepaymenturl").val();
					$("#onlinepaymenturl").data("orig", onlinepaymenturlval);
					$("#onlinepaymenturl").parent().append("<p>Spécifier un montant si besoin (sinon solde) : <input id=\"onlinepaymenturlamount\" size=\"8\" /> <input type=\"button\" value=\"Mettre à jour l\'url de paiement\" onclick=\"onlinepaymenturlupdate();\" /></p>");
				});

				function onlinepaymenturlupdate()
				{
					var onlinepaymenturlval = $("#onlinepaymenturl").data("orig");
					var amount = $("#onlinepaymenturlamount").val();
					var url = amount != "" ?onlinepaymenturlval+"&amount="+amount :onlinepaymenturlval;
					//alert(onlinepaymenturlval);
					//alert(url);
					$("#onlinepaymenturl").val(url);
					$("#onlinepaymenturl").parent().find("a").attr("href", url);
				}
				</script>';
			}

			return 0;
		}

		// Devis/Commande
		if ($this->in_context($parameters, ['propalcard', 'ordercard']) && $object->statut>0) {
			//echo '<div style="text-align: left;">'; var_dump($object); echo '</div>';
			// Rechercher si facture associée
			$fact_id = null;
			if (false)
				$link = '/compta/paiement.php?facid='.$fact_id.'&action=create&accountid=';
			else
				$link = '?id='.$object->id.'&action=payment_add';
			echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIPaymentsAdd")."</a>";

			return 0;
		}

		return 0;
	}
	
	function afterCreateAction($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		if ($this->in_context($parameters, 'invoicecard')) {
			
			//var_dump($object);
			if (in_array($object->type, [Facture::TYPE_STANDARD, Facture::TYPE_DEPOSIT])) {
				mmi_payments::invoice_autoassign_payments($object);
			}
			
			return 0;
		}

		return 0;
	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $conf;

		if ($this->in_context($parameters, 'invoicecard') && $action=='payment_assign') {
			mmi_payments::invoice_autoassign_payments($object);
		}

		if ($this->in_context($parameters, ['propalcard', 'ordercard']) && $action=='confirm_payment_add') {
			//var_dump($_POST);
			$infos = [
				'date' => GETPOST('datepayment', 'date'),
				'amount' => GETPOST('amount', 'alphanohtml'),
				'mode' => GETPOST('paiementcode', 'alphanohtml'),
				'num' => GETPOST('paymentnum', 'alphanohtml'),
				'note' => GETPOST('comment', 'alphanohtml'),
				'accountid' => GETPOST('accountid', 'alphanohtml'),
				'chqemetteur' => GETPOST('chqemetteur', 'alphanohtml'),
				'chqbank' => GETPOST('chqbank', 'alphanohtml'),
			];
			//var_dump($infos);
			$object_class = get_class($object);
			$paiement_id = mmi_payments::add($object_class, $object->id, $infos);
			if (!empty($paiement_id) && $object_class=='Commande') {
				// Auto create shipping on payment
				// @todo use trigger PAYMENT_CUSTOMER_CREATE on module MMI_WORKFLOW
				if (
					(!empty($conf->global->MMIPAYMENTS_CAISSE_USER) && $conf->global->MMIPAYMENTS_CAISSE_USER==$user->id)
					|| (!empty($conf->global->MMIPAYMENTS_CAISSE_COMPANY) && $conf->global->MMIPAYMENTS_CAISSE_COMPANY==$object->thirdparty->id)
				) {
					// Validation auto expé
					if (true) {
						dol_include_once('custom/sfycustom/class/mmi_workflow.class.php');
						mmi_workflow::order_1clic_shipping($user, $object);
					}
				}
			}
			header('Location: /'.($object_class=='Commande' ?'commande' :'comm/propal').'/card.php?id='.$object->id);
		}

		return 0;
	}

	function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		$form = new Form($this->db);

		if ($this->in_context($parameters, ['propalcard', 'ordercard']) && $action=='payment_add') {
			// Hack MOyens de paiement
			echo '<style type="text/css">.selectpaymenttypes { width: 150px; }</style>';
			ob_start();
			$form->select_types_paiements($conf->global->MMIPAYMENTS_DEFAULT_MODE, 'paiementcode', '', 0);
			$paiementcode = ob_get_contents();
			ob_end_clean();
			// Comptes
			$accounts = $form->select_comptes($conf->global->MMIPAYMENTS_DEFAULT_ACCOUNT, 'accountid', 0, '', 2, '', 0, '', 1);

			//var_dump($object->thirdparty);

			$formquestion = array(
				array('type' => 'date', 'name' => 'datepayment', 'label' => '<span class="fieldrequired">'.$langs->trans("Date").'</span>', 'value'=>date('Y-m-d')),
				array('type' => 'other', 'name' => 'paiementcode', 'label' => $langs->trans("PaymentMode"), 'value' => $paiementcode),
				array('type' => 'text', 'name' => 'amount', 'label' => $langs->trans("PaymentAmount"), 'value' => $object->total_ttc),
				array('type' => 'other', 'name' => 'accountid', 'label' => $langs->trans("AccountToCredit"), 'value' => $accounts),
				array('type' => 'text', 'name' => 'paymentnum', 'label' => $langs->trans("ChequeOrTransferNumber"), 'value' => ''),
				array('type' => 'text', 'name' => 'chqemetteur', 'label' => $langs->trans("CheckTransmitter"), 'value' => ''),
				array('type' => 'text', 'name' => 'chqbank', 'label' => $langs->trans("ChequeBank"), 'value' => ''),
				array('type' => 'other', 'name' => 'comment', 'label' => $langs->trans("Comment"), 'value' => '<textarea name="comment" style="width: 90%;height: 3em;margin-top: 0.5em;"></textarea>'),
			);
	
			if (!empty($conf->MMIPAYMENTS_FORM_CONFIRM_NOTIF) && !empty($conf->notification->enabled)) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
				$notify = new Notify($this->db);
				$formquestion = array_merge($formquestion, array(
					array('type' => 'onecolumn', 'value' => $notify->confirmMessage('PROPAL_CLOSE_SIGNED', $object->socid, $object)),
				));
			}
	
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('EnterPaymentReceivedFromCustomer'), '', 'confirm_payment_add', $formquestion, 'ducon', 0, 400);
			// Auto check confirm
			$formconfirm .= '<script>$(document).ready(function(){ $("#confirm").val("yes"); });</script>';
	
			$hookmanager->resPrint = $formconfirm;
			//mmi_payments::invoice_autoassign_payments($object);
		}

		return 0;
	}

	// New hook on propal and order
	function doDisplayMoreInfos($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		//echo '<h3>Paiements :</h3>';
		print '<table class="noborder margintable centpercent">';
		print '<thead>';
		print '<tr class="liste_titre">';
		print '<th class="liste_titre" width="50">Réglement</th>';
		print '<th class="liste_titre">Date</th>';
		print '<th class="liste_titre">Type</th>';
		print '<th class="liste_titre">Compte bancaire</th>';
		print '<th class="liste_titre">Détails</th>';
		print '<th align="right" class="liste_titre">Montant</th>';
		print '</tr>';
		print '</thead>';
		$l = mmi_payments::paiements(get_class($object), $object->id);
		//var_dump($l);
		print '<tbody>';
		
		$total = $object->total_ttc;
		$regle = 0;
		$regle_prevu = 0;
		$regle_prevu_trans_list = [];
		
		if (is_array($l)) foreach($l as $obj) {

			$resql2 = $this->db->query("SELECT CONCAT(code, ' - ', libelle)
				FROM " . MAIN_DB_PREFIX . "c_paiement
				WHERE id=".$obj->fk_paiement);
			if ($resql2) {
				list($paiement_mode) = $this->db->fetch_row($resql2);
			}
			else
				$paiement_mode = '';
			
			$regle += round($obj->amount, 2);
			if (!in_array($obj->trans, $regle_prevu_trans_list)) {
				$regle_prevu_trans_list[] = $obj->trans;
				$regle_prevu += round($obj->cb_amount ?$obj->cb_amount :$obj->amount, 2);
			}

			print '<tr>';
			print '<td><a href="/compta/paiement/card.php?id='.$obj->rowid.'">'.$obj->ref.'</a></td>';
			print '<td>'.dol_print_date($obj->datec, 'dayhour').'</td>';
			print '<td>'.$paiement_mode.(!empty($obj->cb_multiple) ?' '.$obj->cb_multiple.'X' :'').'</td>';
			print '<td><a href="/compta/bank/bankentries_list.php?id='.$obj->ba_rowid.'">'.$obj->ba_ref.'</a></td>';
			echo '<td><a href="javascript:;" onclick="$(\'#pay_'.$obj->rowid.'\').toggle();">Détais</a></td>';
			print '<td align="right">'.round($obj->amount, 2).'</td>';
			print '</tr>';
			
			print '<tr id="pay_'.$obj->rowid.'" style="display:none;">';
			print '<td>--></td>';
			print '<td colspan="4">';
			print 'Trans : '.$obj->trans.'<br />';
			print 'Hash : '.$obj->hash.'<br />';
			print 'Autorisation : '.$obj->auto.'<br />';
			print 'Erreur : '.($obj->erreur !== '00000' ?'Erreur '.$obj->erreur :'OK').'<br />';
			if (!empty($obj->cb_multiple)) {
				print '<b>Paiement multi-échéances !</b><br />';
				print 'Nb échéances : '.$obj->cb_multiple.'<br />';
				print 'Montant total : '.($obj->cb_amount).'<br />';
			}
			echo '</td>';
			print '</tr>';
		}
		if (!empty($regle)) {
			echo '<tr>';
			echo '<td>TOTAL</td>';
			echo '<td>déjà réglé</td>';
			echo '<td>&nbsp;</td>';
			if ($regle != $regle_prevu) {
				echo '<td align="right">(Prévu / multi : '.($regle_prevu).')</td>';
				echo '<td></td>';
			}
			else {
				echo '<td></td>';
				echo '<td></td>';
			}
			echo '<td align="right">'.($regle).'</td>';
			echo '</tr>';
		}
			$reste = round($total-$regle, 2);
			$reste_prevu = round($total-$regle_prevu, 2);
			echo '<tr>';
			echo '<td>Reste</td>';
			echo '<td>à payer</td>';
			echo '<td>&nbsp;</td>';
			if ($reste_prevu) {
				echo '<td align="right">(Prévu / multi : '.($reste_prevu).')</td>';
				echo '<td></td>';
			}
			else {
				echo '<td></td>';
				echo '<td></td>';
			}
			echo '<td align="right" style="font-weight: bold;'.($reste>0 ?'color: red;' :'').'">'.($reste).'</td>';
			echo '</tr>';
		print '</tbody>';
		print '</table>';
		print '<div class="underbanner clearboth"></div>';

		return 0;
	}
}

ActionsMMIPayments::__init();
