<?php

class mmi_payments
{
	public static function __init()
	{
	}
	
	public static function loadobject($objecttype, $id)
	{
		global $db;
	
		if ($objecttype=='Facture') {
			require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
			$object = new Facture($db);
		}
		elseif ($objecttype=='Commande') {
			require_once(DOL_DOCUMENT_ROOT . "/commande/class/commande.class.php");
			$object = new Commande($db);
		}
		elseif ($objecttype=='Propal') {
			require_once(DOL_DOCUMENT_ROOT . "/comm/propal/class/propal.class.php");
			$object = new Propal($db);
		}
		elseif ($objecttype=='Societe') {
			require_once(DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php");
			$object = new Societe($db);
		}
	
		if (!isset($object))
			return;
		
		$object->fetch($id);
		$object->fetch_thirdparty();
		return $object;
	}

	public static function paiements($objecttype, $fk_object)
	{
		global $db;
	
		$sql_where = ["(po.`objecttype`='".$objecttype."' AND po.`fk_object`='".$fk_object."')"];
	
		// Recherche des objets liés
		
		$sql = "SELECT e.targettype, e.fk_target
			FROM ".MAIN_DB_PREFIX."element_element e
			WHERE e.`sourcetype` LIKE '".$objecttype."' AND e.`fk_source`='".$fk_object."'";
		//echo '<p>'.$sql.'</p>';
		$resql = $db->query($sql);
		while ($obj = $db->fetch_object($resql)) {
			//var_dump($obj);
			if ($obj->targettype=='facture')
				$sql_where[] = "(pf.`fk_facture`='".$obj->fk_target."')";
			else
				$sql_where[] = "(po.`objecttype` LIKE '".$obj->targettype."' AND po.`fk_object`='".$obj->fk_target."')";
		}
			
		$sql = "SELECT e.sourcetype, e.fk_source
			FROM ".MAIN_DB_PREFIX."element_element e
			WHERE e.`targettype` LIKE '".$objecttype."' AND e.`fk_target`='".$fk_object."'";
		//echo '<p>'.$sql.'</p>';
		$resql = $db->query($sql);
		while ($obj = $db->fetch_object($resql)) {
			//var_dump($obj);
			if ($obj->targettype=='facture')
				$sql_where[] = "(pf.`fk_facture`='".$obj->fk_source."')";
			else
				$sql_where[] = "(po.`objecttype` LIKE '".$obj->sourcetype."' AND po.`fk_object`='".$obj->fk_source."')";
		}
	
		$sql = "SELECT DISTINCT p2.*, p.*, if(pf.amount>0, pf.amount, p.amount) amount,
				mr.trans, mr.erreur, mr.auto, mh.hash, mh.amount cb_amount, mh.multiple cb_multiple, mh.info cb_info,
				b.rowid b_rowid, ba.rowid ba_rowid, ba.ref ba_ref, ba.label ba_label
			FROM ".MAIN_DB_PREFIX."paiement p
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_extrafields p2 ON p2.fk_object=p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_object po ON po.fk_paiement=p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON b.rowid = p.fk_bank
			LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON ba.rowid = b.fk_account
			LEFT JOIN ".MAIN_DB_PREFIX."mbi_etransactions_return mr ON mr.fk_paiement=p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."mbi_etransactions_hash mh ON mh.rowid=mr.fk_mbi_etransactions
			WHERE ".implode(' OR ', $sql_where);
		//echo '<p>'.$sql.'</p>';
		$resql = $db->query($sql);
		if (!$resql)
			return;
		$l = [];
		while ($obj = $db->fetch_object($resql)) {
			//var_dump($obj);
			$l[$obj->rowid] = $obj;
		}
		//var_dump($l);
	
		//LEFT JOIN ".MAIN_DB_PREFIX."mbi_etransactions_return mr ON 
		//LEFT JOIN ".MAIN_DB_PREFIX."mbi_etransactions_hash mh ON mh.rowid=mr.fk_mbi_etransactions
	
		return $l;
	}
	
	public static function total_regle($objecttype, $fk_object)
	{
		$regle = 0;
		$paiements = static::paiements($objecttype, $fk_object);
		foreach($paiements as $paiement) {
			$regle += round($paiement->amount, 2);
		}
		return $regle;
	}
	
	public static function payment_invoices($id)
	{
		if (!is_numeric($id))
			return false;
		
		global $db;
		
		$sql = 'SELECT f.rowid as facid, f.ref, f.type, f.total_ttc, f.paye, f.entity, f.fk_statut, pf.amount, s.nom as name, s.rowid as socid'
			.' FROM '.MAIN_DB_PREFIX.'paiement_facture as pf'
			.' INNER JOIN '.MAIN_DB_PREFIX.'facture as f'
				.' ON f.rowid = pf.fk_facture AND f.entity IN ('.getEntity('invoice').')'
			.' INNER JOIN '.MAIN_DB_PREFIX.'societe as s'
				.' ON s.rowid = f.fk_soc'
			.' WHERE pf.fk_paiement = '.$id;
		$resql = $db->query($sql);
		$p = [];
		if ($resql) {
			while ($objp = $db->fetch_object($resql))
				$p[] = $objp;
			return $p;
		}
	}
	
	public static function payment_to_invoice()
	{
	}
	
	public static function invoice_autoassign_payments_from_object($object)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

		$object->fetchObjectLinked();
		//var_dump($object->linkedObjectsIds);
		if(!empty($object->linkedObjectsIds) && !empty($object->linkedObjectsIds['facture']) && count($object->linkedObjectsIds['facture'])==1) {
			foreach($object->linkedObjectsIds['facture'] as $invoice_id) {
				$invoice = new Facture($db);
				$invoice->fetch($invoice_id);
				static::invoice_autoassign_payments($invoice);
				return 1;
			}
			return 1;
		}
		return 0;
	}

	public static function invoice_autoassign_payments($object)
	{
		global $db, $user;
		
		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		
		$linked_objects = [];

		$object->fetchObjectLinked();
		//var_dump($object->linkedObjectsIds);
		if(!empty($object->linkedObjectsIds)) {
			foreach($object->linkedObjectsIds as $type=>$ids)
				foreach($ids as $id)
					$linked_objects[] = [ucfirst($type), $id];
		}
		elseif (!empty($object->context) && in_array($object->context['link_origin'], ['commande', 'propal'])) {
			$linked_objects[] = [ucfirst($object->context['link_origin']), $object->context['link_origin_id']];
		}

		if (isset($linked_objects)) {
			$sql_linked_objects = [];
			foreach($linked_objects as $row)
				$sql_linked_objects[] = '(po.objecttype="'.$row[0].'" AND po.fk_object='.$row[1].')';
			// Paiements associés à la commande ou au devis
			// non imputé à une autre facture
			// d'un montant <= cette facture (en fait non on assigne tout)
			$sql = 'SELECT po.fk_paiement, po.amount'
				.' FROM '.MAIN_DB_PREFIX.'paiement_object po'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'paiement_facture pi'
					.' ON pi.fk_paiement=po.fk_paiement'
				.' WHERE ('.implode(' OR ', $sql_linked_objects).')'
					//.' AND po.amount <= '.$object->total_ttc
					.' AND pi.fk_paiement IS NULL';
			//echo '<p>'.$sql.'</p>';
			$resql = $db->query($sql);
			//var_dump($resql);
			if ($resql && $db->num_rows($resql)) {
				//$object->fetch_thirdparty(); // inutile déjà fait
				$client = $object->thirdparty;
				
				// Valider la facture
				$object->validate($user, '', $object->fk_warehouse);
				
				while($objp = $resql->fetch_object()) {
					
					// Imputer le paiement à la facture
					$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'paiement_facture (fk_facture, fk_paiement, amount, multicurrency_amount)'
						.' VALUES ('.$object->id.', '.$objp->fk_paiement.', \''.$objp->amount.'\', \''.$objp->amount.'\')';
					//echo '<p>'.$sql.'</p>';
					$db->query($sql);
					
					// Add to bank
					$paiement = new Paiement($db);
					$paiement->fetch($objp->fk_paiement);
					// Pas créés automatiquement... pas joli joli tout ça
					$paiement->amounts = $paiement->getAmountsArray();
					$paiement->multicurrency_amounts = $paiement->amounts;
					$paiement->paiementid = ($paiement->type_code ?$paiement->type_code :'OTHER');
					//var_dump($paiement);
					
					$label = ($objp->amount>0 ?'(CustomerInvoicePayment)' :'(CustomerInvoicePaymentBack)');

					$sql = "SELECT * FROM `".MAIN_DB_PREFIX."paiement_extrafields`
						WHERE fk_object=".$paiement->id;
					//echo $sql;
					$q = $db->query($sql);
					if ($q && ($row = $q->fetch_object())) {
						//var_dump($row);
						$account_id = $row->fk_bank_account;
						$chqemetteur = $row->chqemetteur;
						$chqbank = $row->chqbank;
					}
					
					if (empty($account_id))
						$account_id = 1;
					if (empty($chqemetteur))
						$chqemetteur = $client->nom;
					if (empty($chqbank))
						$chqbank = '';

					$result = $paiement->addPaymentToBank($user, 'payment', $label, $account_id, $chqemetteur, $chqbank);
					//var_dump($result);
				}

				// Check if we set invoice payed
				$sql = 'SELECT SUM(ip.amount) paid
					FROM '.MAIN_DB_PREFIX.'paiement_facture ip
					WHERE ip.fk_facture='.$object->id;
				$q2 = $db->query($sql);
				if ($q2 && ($row = $q2->fetch_object())) {
					//var_dump($row->paid, $object->total_ttc);
					// @todo : voir côté doli standard en prenant en compte les avoirs, etc.
					//   une méthode existe déjà voir dans paiement::create
					if (round($row->paid-$object->total_ttc, 2) == 0) {
						//var_dump($object);
						$object->setPaid($user);
					}
				}
			}
		}
	}

	public static function add($objecttype, $id, $infos)
	{
		global $db, $user, $hookmanager;

		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		// Creation of payment line
		$paiement = new Paiement($db);

		if (empty($infos['date'])) {
			$infos['date'] = dol_now();
		}
		if (is_numeric(strpos($infos['date'], '/'))) {
			$date_e = explode('/', $infos['date']);
			$date = implode('-', array_reverse($date_e));
		}
		else {
			$date = $infos['date'];
		}
		if (!is_numeric(strpos($date, ' ')))
			$date .= ' 00:00:00';

		$paiement->datepaye = $date;
		$paiement->amounts = [];
		$paiement->multicurrency_amounts = [];
		$paiement->amount = $infos['amount'];
		$paiement->multicurrency_amount = $infos['amount'];
		$paiement->paiementid = $infos['mode'];
		$paiement->num_payment = $infos['num'];
		$paiement->note_private = $infos['note'];

		if (!($paiement_id = $paiement->create($user))) {
			// @todo Notify
			var_dump($paiement_id);
			echo 'Erreur création paiement';
			return;
		}
		//var_dump($paiement_id);
		//var_dump($paiement);

		$object = static::loadobject($objecttype, $id);

		if (!isset($infos['accountid']))
			$infos['accountid'] = 1; // @todo vérif : Compte bancaire par défaut ?
		if (!isset($infos['chqemetteur']))
			$infos['chqemetteur'] = '';
		if (!isset($infos['chqbank']))
			$infos['chqbank'] = '';

		// @todo encode strings protection injection !
		$sql = "INSERT INTO `".MAIN_DB_PREFIX."paiement_extrafields`
			(`fk_object`, `fk_module_oid`, `fk_bank_account`, `chqemetteur`, `chqbank`)
			VALUES (".$paiement_id.", ".(!empty($infos['module_oid']) ?"'".$infos['module_oid']."'" :'NULL').", '".$infos['accountid']."', '".$infos['chqemetteur']."', '".$infos['chqbank']."')";
		//echo $sql;
		$db->query($sql);

		if(in_array($objecttype, ['Propal', 'Commande'])) {
			// Spécifique association devis/commande
			$sql = "INSERT INTO `".MAIN_DB_PREFIX."paiement_object`
				(`fk_paiement`, `objecttype`, `fk_object`, `amount`, `multicurrency_code`, `multicurrency_tx`, `multicurrency_amount`)
				VALUES (".$paiement_id.", '".$objecttype."', ".$id.", ".$paiement->amount.", NULL, 1, ".$paiement->amount.")";
			//echo $sql;
			$db->query($sql);

			// If propal validated or unsigned, set Signed
			if ($objecttype=='Propal' && in_array($object->status, [Propal::STATUS_VALIDATED, Propal::STATUS_NOTSIGNED])) {
				$object->closeProposal($user, propal::STATUS_SIGNED, 'Payment done');
			}
	
			// Assign to invoice if only one
			$object->fetchObjectLinked();
			if(!empty($object->linkedObjectsIds) && !empty($object->linkedObjectsIds['facture']) && count($object->linkedObjectsIds['facture'])==1) {
				foreach($object->linkedObjectsIds['facture'] as $invoice_id) {
					$invoice = new Facture($db);
					$invoice->fetch($invoice_id);
					static::invoice_autoassign_payments($invoice);
				}
			}
		}
		elseif ($objecttype=='Facture') {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."paiement_facture (fk_facture, fk_paiement, amount, multicurrency_amount)";
			$sql .= " VALUES (".$object->id.", ".$paiement_id.", ".$paiement->amount.", ".$paiement->amount.")";
			//echo $sql;
			$db->query($sql);

			// Re-fetchg
			$paiement->fetch($paiement_id);
			$paiement->amounts = $paiement->getAmountsArray();
			$paiement->multicurrency_amounts = $paiement->amounts;
			$paiement->paiementid = ($paiement->type_code ?$paiement->type_code :'OTHER');
			//var_dump($paiement);
			
			$label = ($paiement->amount>0 ?'(CustomerInvoicePayment)' :'(CustomerInvoicePaymentBack)');
			$account_id = $infos['accountid'];
			$chqemetteur = $infos['chqemetteur'];
			$chqbank = $infos['chqbank'];
			
			if (empty($account_id))
				$account_id = 1;
			if (empty($chqemetteur))
				$chqemetteur = $client->nom;
			if (empty($chqbank))
				$chqbank = '';

			$result = $paiement->addPaymentToBank($user, 'payment', $label, $account_id, $chqemetteur, $chqbank);

			// DEJA REGLE
			$dejaregle = $object->getSommePaiement(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);
			// RESTE A PAYER
			$resteapayer = price2num($paiement->amount - $dejaregle);
			if (round($resteapayer, 2) == 0) {
				// Volontairement pas mis <= 0 pour que l'on traite manuellement les situations de trop perçu
				// FACTURE DECLAREE PAYEE
				$object->setPaid($user);
			}
		}

		//$label = '(CustomerInvoicePaymentBack)';
		//$paiement->addPaymentToBank($user, 'payment', $label, $infos['accountid'], $infos['chqemetteur'], $infos['chqbank']);

		return $paiement_id;
	}
}

mmi_payments::__init();
