<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('custom/mmipayments/class/mmi_payments.class.php');
dol_include_once('custom/mmiworkflow/class/mmi_workflow.class.php');

class ActionsMMIPayments extends MMI_Actions_1_0
{
	const MOD_NAME = 'mmipayments';

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		$error = 0;
		$print = '';

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

		if ($this->in_context($parameters, 'propalcard')
			&& $object instanceof Propal
			&& $object->status == Propal::STATUS_VALIDATED
			&& $user->hasRight("propal", "creer")) {
			print dolGetButtonAction('', $langs->trans('AddLastPaymentLine'), 'default', $_SERVER["PHP_SELF"].'?action=addlinepaiement&token='.newToken().'&id='.$object->id);
		}

		// Facture
		if ($this->in_context($parameters, 'invoicecard') && $object->statut>0) {
			if (!empty($conf->global->MMIPAYMENTS_INVOICE_PAYMENT_ASSIGN)) {
				if (in_array($object->type, [0, 3])) {
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

		$error = 0;
		$print = '';

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

		$error = 0;
		$print = '';

		if ($this->in_context($parameters, 'propalcard')
			&& $object instanceof Propal
			&& $object->status == Propal::STATUS_VALIDATED
			&& $user->hasRight("propal", "creer")
			&& $action=='addlinepaiement') {
			mmi_payments::propal_addlinepayment($object);

		}

		if ($this->in_context($parameters, 'invoicecard') && $action=='payment_assign') {
			mmi_payments::invoice_autoassign_payments($object, true);
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

		$error = 0;
		$print = '';

		if ($this->in_context($parameters, ['propalcard', 'ordercard']) && $action=='payment_add') {
			$form = new Form($this->db);
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
				$notify = new Notify($this->db);
				$formquestion = array_merge($formquestion, array(
					array('type' => 'onecolumn', 'value' => $notify->confirmMessage('PROPAL_CLOSE_SIGNED', $object->socid, $object)),
				));
			}
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('EnterPaymentReceivedFromCustomer'), '', 'confirm_payment_add', $formquestion, 'ducon', 0, 400);
			// Auto check confirm
			$formconfirm .= '<script>$(document).ready(function(){ $("#confirm").val("yes"); });</script>';

			if (false && $conf->global->MMIPAYMENTS_AUTOASSIGN_INVOICE) {
				mmi_payments::invoice_autoassign_payments($object);
			}

			$print = $formconfirm;
		}

		if (! $error) {
			$this->resprints = $print;
			return 0;
		}
		else {
			return -1;
		}
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

	/* Payment means */

	/**
	 * Check Object OK
	 * @todo vérifier si utilisé et utile, idem dans mbietransactions
	 */
	function doCheckStatus($parameters, &$object, &$action, $hookmanager)
	{
		$this->doValidatePayment($parameters, $object, $action, $hookmanager);
		$objecttype = get_class($object);

		if (in_array($objecttype, ['Propal'])) {
			// Vérif devis ok, pas relié commande, etc.
		}

		return 0;
	}

	/**
	 * Check Object OK
	 */
	function addOnlinePaymentMeans($parameters, &$object, &$action, $hookmanager)
	{
		$objecttype = get_class($object);

		if (in_array($objecttype, ['Propal', 'Commande', 'Facture'])) {
			$hookmanager->results['useonlinepayment'] = true;
		}

		return 0;
	}
	
	// Boutons moyens de paiement
	function doaddButton($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $langs, $conf, $mysoc;

		// var_dump($object);
		// die();
		$time = time();

		$objecttype = get_class($object);
		$deja = mmi_payments::total_regle($objecttype, $object->id);
		//var_dump($deja);
		$reste = ($deja>0 ?max(0, round($object->total_ttc-$deja, 2)) :$object->total_ttc);
		//var_dump($object->fin_validite, $time, empty($object->fin_validite) || $object->fin_validite < $time);

		if($conf->global->MMIPAYMENTS_CGV_VOILE) {
			print '<div id="cgi_voile">';
			print '<div>';
			if ($objecttype=='Propal') {
				$fin_validite = $object->fin_validite ?$object->fin_validite+86400 :0;
				$ok = $fin_validite && $fin_validite > $time;
				// if (!empty($object->ref_client))
				// 	echo '<p><b>Référence du projet :</b> '.$object->ref_client.'</p>';
				if (empty($deja) && $fin_validite && $fin_validite < $time)
					echo '<p'.(!$ok ?' style="color: red;"' :'').'>Date de fin de validité de votre Devis : '.date('d/m/Y', $fin_validite).'</p>';
				if (!$ok) {
					$nok_message = '<b style="color: red;">Votre devis est échu, merci de contacter votre conseiller !</b>';
				}
			}
			else {
				$ok = true;
			}
			if ($ok) {
				echo '<p><input type="checkbox" id="cgv" name="cgv" value="1" /> '."<label for=\"cgv\">J'ai lu les <a href=\"".$conf->global->MMIPAYMENTS_CGV_URL."\" target=\"_blank\">conditions générales de vente</a> et j'y adhère sans réserve.</label>".'</p>';
			}
			elseif (!empty($nok_message)) {
				echo '<p>'.$nok_message.'</p>';
			}
			echo '</div>';
			//var_dump($object); die();
			echo '<div id="voile" class="voile"></div>';
			echo '</div>';
		}

		// Virement
		if ($conf->global->MMIPAYMENTS_TRANSFER_ENABLED) {
			if($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT) {
				$account = new account($db);
				$account->fetch($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT);

				echo '<div class="button buttonpayment" id="div_dopayment_transfer" data-pos="99">
				<input class="" type="submit" id="dopayment_transfer" name="dopayment_transfer" value="'.$langs->trans('MMIPaymentsDoPaymentTransfer').'" />';
				echo '<div class="pay_infos">';
				echo '<p>Il vous faudra transférer le montant de la facture sur notre compte bancaire.</p>'
				//.'<p>Vous recevrez votre confirmation de commande par e-mail, comprenant nos coordonnées bancaires et le numéro de commande.</p>'
				.'<p>Nous traiterons votre commande dès la réception du paiement.</p>'
				.'<p class="small">Cliquer pour plus d\'informations</p>';
				echo '</div>';
				echo '</div>';
			}
		}

		// Chèque
		if ($conf->global->MMIPAYMENTS_CHEQUE_ENABLED) {
			//var_dump($mysoc);
			echo '<div class="button buttonpayment" id="div_dopayment_cheque" data-pos="99">
			<input class="" type="submit" id="dopayment_cheque" name="dopayment_cheque" value="'.$langs->trans('MMIPaymentsDoPaymentCheque').'" />';
			echo '<div class="pay_infos">'
			.'<p>A l\'ordre de : <b>'.$mysoc->name.'</b><br />'.$mysoc->address.'<br />'.$mysoc->zip.' '.$mysoc->town.'</p>'
			.'<p>Nous traiterons votre commande dès la réception du paiement.</p>'
			.'<p class="small">Cliquer pour plus d\'informations</p>';
			echo '</div>';
			echo '</div>';
		}

		print '<script>
			$( document ).ready(function() {
				// Voile
				$("#cgi_voile").detach().insertAfter("#tablepublicpayment");
				$("#cgv").click(function(e){
					$("#voile").toggle();
				});

				// Reorder
				var newpos = $("#tablepublicpayment").parent();
				$("#div_dopayment_transfer").detach().appendTo(newpos);
				$("#div_dopayment_cheque").detach().appendTo(newpos);

				// Clic
				$("#div_dopayment_transfer input").click(function(e){
					if (confirm("Je confirme ma commande avec obligation de paiement.")) {
						$(this).css( \'cursor\', \'wait\' );
						$(\'input\', this).submit();
						return true;
					}
					else {
						return false;
					}
				});
				$("#div_dopayment_transfer p").click(function(e){
					$("#div_dopayment_transfer input").click();
				});
				$("#div_dopayment_cheque input").click(function(e){
					if (confirm("Je confirme ma commande avec obligation de paiement.")) {
						$(this).css( \'cursor\', \'wait\' );
						return true;
					}
					else {
						return false;
					}
				});
				$("#div_dopayment_cheque p").click(function(e){
					$("#div_dopayment_cheque input").click();
				});
			});
			</script>';

		return 0;
	}

	// Payment means
	function doValidatePayment($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		//var_dump($parameters); var_dump(get_class($object)); var_dump($action);
		$parameters['validpaymentmethod']['cheque'] = $conf->global->MMIPAYMENTS_CHEQUE_ENABLED;
		$parameters['validpaymentmethod']['transfer'] = $conf->global->MMIPAYMENTS_TRANSFER_ENABLED;

		return 0;
	}

	// Payment means
	function getValidPayment($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		//var_dump($parameters); var_dump(get_class($object)); var_dump($action);
		$this->results['validpaymentmethod']['cheque'] = $conf->global->MMIPAYMENTS_CHEQUE_ENABLED;
		$this->results['validpaymentmethod']['transfer'] = $conf->global->MMIPAYMENTS_TRANSFER_ENABLED;

		return 0;
	}

	// This hook is used to show the embedded form to make payments with external payment modules (ie Payzen, ...)
	function doPayment($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $conf, $mysoc, $user;
		//var_dump($mysoc); die();
		//echo $parameters['paymentmethod'];

		// If we are in a validpaymentmethod context, we only return the valid payment methods
		if (isset($parameters['validpaymentmethod'])) {
			$parameters['validpaymentmethod']['cheque'] = $conf->global->MMIPAYMENTS_CHEQUE_ENABLED;
			$parameters['validpaymentmethod']['transfer'] = $conf->global->MMIPAYMENTS_TRANSFER_ENABLED;
			return 0;
		}
		
		if(($client=$object->thirdparty) && $client->email) {
			$mail_notif_to = [];
			
			if (!empty($conf->global->MMIPAYMENTS_NOTIFICATION_EMAIL))
				$mail_notif_to[] = $conf->global->MMIPAYMENTS_NOTIFICATION_EMAIL;
			$contacts = $object->liste_contact(-1, 'internal');
			$contacts_ok = false;
			foreach($contacts as $contact) {
				$contacts_ok = true;
				if (!empty($contact->email) && !in_array($contact->email, $mail_notif_to))
					$mail_notif_to[] = $contact->email;
			}
			$contacts = $client->getSalesRepresentatives($user);
			foreach($contacts as $contact) {
				$contacts_ok = true;
				if (!empty($contact['email']) && !in_array($contact['email'], $mail_notif_to))
					$mail_notif_to[] = $contact['email'];
			}
			$to_email = $client->nom.' <'.$client->email.'>';
			$from_email = $mysoc->name.' <'.$mysoc->email.'>';
			$notif_email = (!empty($mail_notif_to) ?'Bcc: '.implode(',', $mail_notif_to)."\r\n" :'');
			// @todo : $mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid, '', $sendcontext);
		}

		$object_class = get_class($object);
		if ($object_class=='Propal')
			$otype = 'Devis';
		else
			$otype = $object_class;

		//var_dump($parameters);
		if ($parameters['paymentmethod']=='transfer') {
			if ($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT) {
				mmi_etransactions::object_mode_reglement_set($object, 'VIR');
				$account = new account($db);
				$account->fetch($conf->global->PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT);
				//var_dump($account);
				$title = '<h2 class="title" style="margin-top: 0;">Vous avez choisi de payer par virement</h2>';
				$info = '<p>Votre demande a bien été prise en considération.</p>
				<p>Merci de nous envoyer votre paiement par virement bancaire,</p>
				<p>Montant du règlement : '.$parameters['amount'].'&nbsp;&euro;</p>
				<p>Code Banque : '.$account->code_banque.'<br />
				Code Guichet :  '.$account->code_guichet.'<br />
				Numéro de compte : '.$account->number.'<br />
				Clé RIB : '.$account->cle_rib.'<br />
				IBAN : <b>'.$account->iban.'</b><br />
				Code BIC / SWIFT : <b>'.$account->bic.'</b></p>
				<p>Adresse de la banque / Domiciliation du compte :</p>
				<p>'.str_replace("\r\n", '<br />', $account->domiciliation).'</p>
				<p>N\'oubliez pas la référence de votre '.$otype.' dans la description du virement :<br /><b>'.$object->ref.'</b></p>'
				.($object->thirdparty && $object->thirdparty->email ?'<p>Un e-mail contenant ces informations a été envoyé sur votre adresse :<br /><b>'.$object->thirdparty->email.'</b></p>' :'')
				.'<p><b>Votre commande sera traitée dès réception de votre virement.</b></p>'
				.($conf->global->MMIPAYMENTS_WEBSITE_CONTACT_URL ?'<p>Pour toute question ou information complémentaire,<br />
				merci de contacter notre <a href="'.$conf->global->MMIPAYMENTS_WEBSITE_CONTACT_URL.'">support client</a>.</p>' :'');

				$this->resprints = '<table align="center" width="600">'
				.'<tr><td style="text-align: center;">'.$title.'</td></tr>'
				.'<tr><td><div style="width: 559px;border: 1px solid #aaa;padding: 20px;">'
				.$info
				.'</div></td></tr></table>';
				if($object->thirdparty && $object->thirdparty->email) {
					mail($to_email,
						'=?utf-8?B?'.base64_encode('Votre '.$otype.' '.$object->ref.' en attente de réglement par virement bancaire').'?=',
						$info,
						"Content-Type: text/html; charset=\"UTF-8\";\r\nFrom: ".$from_email."\r\n".$notif_email);
				}
			}
			else {
				$info = '<h2 class="title" style="margin-top: 0;">Vous avez choisi de payer par virement</h3>'
				.'<p>Toutefois, ce moyen de paiement est temporairement désactivé.</p>'
				.'<p>Merci de nous contacter pour plus de détails.</p>';
				$this->resprints = $info;

			}
		}
		elseif ($parameters['paymentmethod']=='cheque') {
			mmi_etransactions::object_mode_reglement_set($object, 'CHQ');

			$title = '<h2 class="title" style="margin-top: 0;">Vous avez choisi de payer par chèque</h2>';
			$info = '<p>Votre demande a bien été prise en considération.</p>
			<p>Merci de nous envoyer votre paiement par chèque,</p>
			<p>- Montant du règlement : '.$parameters['amount'].'&nbsp;&euro;</p>
			<p>- Payable à l\'ordre de : <b>'.$mysoc->name.'</b>,</p>
			<p>- Envoyer à l\'adresse suivante :</p>
			<p style="margin-left: 40px;"><b>'.$mysoc->address.'<br />'.$mysoc->zip.' '.$mysoc->town.'</b></p>
			<p>- N\'oubliez pas la référence de votre '.$otype.' : <b>'.$object->ref.'</b></p>'
			.($object->thirdparty && $object->thirdparty->email ?'<p>Un e-mail contenant ces informations a été envoyé sur votre adresse : '.$object->thirdparty->email.'</p>' :'')
			.'<p><b>Votre commande sera traitée dès réception de votre chèque.</b></p>'
			.($conf->global->MMIPAYMENTS_WEBSITE_CONTACT_URL ?'<p>Pour toute question ou information complémentaire,<br />
			merci de contacter notre <a href="'.$conf->global->MMIPAYMENTS_WEBSITE_CONTACT_URL.'">support client</a>.</p>' :'');

			$this->resprints = '<table align="center" width="600"><tr><td align="center">'.$title.'</td></tr><tr><td><div style="width: 559px;border: 1px solid #aaa;padding: 20px;">'
			.$info
			.'</div></td></tr></table>';
			if($object->thirdparty && $object->thirdparty->email) {
				mail($to_email,
					'=?utf-8?B?'.base64_encode('Votre '.$otype.' '.$object->ref.' en attente de réglement par chèque').'?=',
					$info, 
					"Content-Type: text/html; charset=\"UTF-8\";\r\nFrom: ".$from_email."\r\n".$notif_email);
			}
		}
		//die('YO');

		return 1;
	}
}

ActionsMMIPayments::__init();
