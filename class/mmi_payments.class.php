<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';

// Payment
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
// Soc
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
// Documents
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

class mmi_payments
{
	// 3 centimes de marge
	const AMOUNT_DIFF_CTS = 3;

	/**
	 * Statut par défaut de la vue globale des tentatives (mode 'attempts').
	 *
	 * 'unsettled' = les tentatives qui ne se sont PAS soldées par un règlement
	 * lisible ailleurs : refus, et transactions abouties dont le paiement Dolibarr
	 * manque. C'est le défaut parce que les tentatives ABOUTIES sont, de loin, les
	 * plus nombreuses : les afficher par défaut noierait la vue et la ferait
	 * doublonner la liste des règlements, qui les montre déjà.
	 */
	const ATTEMPT_STATUS_DEFAULT = 'unsettled';

	/**
	 * Statuts acceptés par le filtre de la vue globale des tentatives.
	 *
	 * Le socle ne sait pas les évaluer — il ne connaît que les règlements aboutis.
	 * Il définit le vocabulaire, les modules de paiement le traduisent dans leurs
	 * propres tables (cf. README, § point d'extension).
	 *
	 *  - 'unsettled' : refus + abouties sans paiement Dolibarr (défaut)
	 *  - 'ko'        : refus uniquement
	 *  - 'missing'   : abouties sans paiement Dolibarr (les régularisations à faire)
	 *  - 'ok'        : abouties, paiement présent
	 *  - 'all'       : tout
	 *
	 * @return string[]
	 */
	public static function attempt_statuses()
	{
		return array('unsettled', 'ko', 'missing', 'ok', 'all');
	}

	/**
	 * @var DoliDB
	 */
	protected static $db;

	public static function __init()
	{
		global $langs, $db;
		static::$db = $db;
		$langs->load('payments');
	}

	public static function loadobject($objecttype, $id)
	{
		$objecttype = ucfirst($objecttype);

		if ($objecttype=='Facture') {
			$object = new Facture(static::$db);
		}
		elseif ($objecttype=='Commande') {
			$object = new Commande(static::$db);
		}
		elseif ($objecttype=='Propal') {
			$object = new Propal(static::$db);
		}
		elseif ($objecttype=='Societe') {
			$object = new Societe(static::$db);
		}

		if (!isset($object))
			return;

		$object->fetch($id);
		$object->fetch_thirdparty();
		return $object;
	}

	public static function object_url($objecttype, $id)
	{
		$objecttype = ucfirst($objecttype);

		$server_url = 'https://'.$_SERVER['HTTP_HOST'];

		if ($objecttype=='Facture')
			return $server_url.'/compta/facture/card.php?id='.$id;
		elseif ($objecttype=='Commande')
			return $server_url.'/commande/card.php?id='.$id;
		elseif ($objecttype=='Propal')
			return $server_url.'/comm/propal/card.php?id='.$id;
		elseif ($objecttype=='Societe')
			return $server_url.'/societe/societe.php?id='.$id;
	}

	public static function securekey($objecttype, $id)
	{
		global $conf;

		$object = static::loadobject($objecttype, $id);
		if (!isset($object))
			return;
                if ($objecttype=='Commande')
                        $objecttype = 'order';

		return dol_hash($conf->global->PAYMENT_SECURITY_TOKEN.strtolower($objecttype).$object->ref, 2);
	}

	public static function paiements($objecttype, $fk_object)
	{
		if ($objecttype=='Facture') {
			$sql_where[] = "(pf.`fk_facture`='".$fk_object."')";
		}
		else {
			$sql_where = ["(po.`objecttype`='".$objecttype."' AND po.`fk_object`='".$fk_object."')"];

			// Recherche des objets liés

			$sql = "SELECT e.targettype, e.fk_target
				FROM ".MAIN_DB_PREFIX."element_element e
				WHERE e.`sourcetype` LIKE '".$objecttype."' AND e.`fk_source`='".$fk_object."'";
			//echo '<p>'.$sql.'</p>';
			$resql = static::$db->query($sql);
			while ($obj = static::$db->fetch_object($resql)) {
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
			$resql = static::$db->query($sql);
			while ($obj = static::$db->fetch_object($resql)) {
				//var_dump($obj);
				if ($obj->targettype=='facture')
					$sql_where[] = "(pf.`fk_facture`='".$obj->fk_source."')";
				else
					$sql_where[] = "(po.`objecttype` LIKE '".$obj->sourcetype."' AND po.`fk_object`='".$obj->fk_source."')";
			}
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
		$resql = static::$db->query($sql);
		if (!$resql)
			return;
		$l = [];
		while ($obj = static::$db->fetch_object($resql)) {
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

	/**
	 * Liste des règlements (tous modes) reçus d'un tiers.
	 *
	 * Conservé pour les appelants historiques (bloc « Derniers règlements » de
	 * l'onglet Client) : simple raccourci sur object_payments().
	 *
	 * @param int $socid  Id du tiers
	 * @param int $limit  Nombre max de règlements retournés (0 = pas de limite)
	 * @return array      cf. object_payments()
	 */
	public static function client_payments($socid, $limit = 0)
	{
		return static::object_payments('Client', $socid, $limit);
	}

	/**
	 * Objets du CYCLE de vente auquel appartient un objet : devis, commande(s) et
	 * facture(s) qui se répondent.
	 *
	 * Le règlement d'une affaire ne se lit pas au niveau d'un seul document : un
	 * acompte est encaissé sur le devis, le solde sur la facture, et l'un comme
	 * l'autre concernent la même affaire. Regarder l'onglet Paiements d'un devis sans
	 * voir ce qui a été réglé sur la facture qui en découle donnerait une réponse
	 * fausse à « ce client a-t-il payé ? ».
	 *
	 * Les liens de llx_element_element se parcourent dans les DEUX sens : selon la
	 * façon dont les documents ont été créés, la facture est tantôt la cible du devis,
	 * tantôt sa source.
	 *
	 * @param string $objecttype 'Facture' | 'Commande' | 'Propal'
	 * @param int    $id         Id de l'objet
	 * @return array             Couples array('type' => …, 'id' => …), objet de départ inclus
	 */
	public static function cycle_objects($objecttype, $id)
	{
		$id = (int) $id;
		$map = array('propal' => 'Propal', 'commande' => 'Commande', 'facture' => 'Facture');
		if (!in_array($objecttype, $map)) {
			return array();
		}

		$cycle = array($objecttype.':'.$id => array('type' => $objecttype, 'id' => $id));
		$todo = array($objecttype.':'.$id => array('type' => $objecttype, 'id' => $id));

		// Parcours transitif borné : depuis une facture, on veut retrouver le devis
		// dont elle découle ET les factures sœurs issues du même devis. Un seul niveau
		// rendrait la vue asymétrique — le devis verrait tout, la facture presque rien.
		// Deux sauts suffisent à couvrir devis <-> commande <-> facture ; la borne
		// évite de dériver dans un graphe de liens qui peut être large.
		$MAX_DEPTH = 2;
		$MAX_OBJECTS = 50;

		for ($depth = 0; $depth < $MAX_DEPTH && $todo && count($cycle) < $MAX_OBJECTS; $depth++) {
			$conds = array();
			foreach ($todo as $o) {
				$lower = array_search($o['type'], $map);
				$conds[] = "(sourcetype = '".static::$db->escape($lower)."' AND fk_source = ".((int) $o['id']).")";
				$conds[] = "(targettype = '".static::$db->escape($lower)."' AND fk_target = ".((int) $o['id']).")";
			}
			$sql = "SELECT sourcetype AS s_type, fk_source AS s_id, targettype AS t_type, fk_target AS t_id
				FROM ".MAIN_DB_PREFIX."element_element
				WHERE ".implode(' OR ', $conds);

			$resql = static::$db->query($sql);
			if (!$resql) {
				break;
			}
			$todo = array();
			while ($obj = static::$db->fetch_object($resql)) {
				// element_element mêle expéditions, commandes fournisseur… : on ne garde
				// que les documents qui portent des règlements.
				foreach (array(array($obj->s_type, $obj->s_id), array($obj->t_type, $obj->t_id)) as $cand) {
					$t = strtolower($cand[0]);
					if (!isset($map[$t])) {
						continue;
					}
					$k = $map[$t].':'.((int) $cand[1]);
					if (isset($cycle[$k])) {
						continue;
					}
					$cycle[$k] = array('type' => $map[$t], 'id' => (int) $cand[1]);
					$todo[$k] = $cycle[$k];
					if (count($cycle) >= $MAX_OBJECTS) {
						return $cycle;
					}
				}
			}
		}

		return $cycle;
	}

	/**
	 * Liste des règlements (tous modes) rattachés à un objet métier.
	 *
	 * Agrège les paiements liés aux factures (paiement_facture) et aux
	 * commandes / devis / tiers (paiement_object). Un même règlement peut être
	 * lié à plusieurs objets (ex: acompte sur commande puis rattaché à la
	 * facture) : ces objets sont collectés dans $obj->objects, dédupliqués, et
	 * le règlement n'apparaît qu'une fois.
	 *
	 * Le filtre porte sur l'IDENTIFIANT DU RÈGLEMENT (sous-requête), pas sur les
	 * jointures : on récupère ainsi TOUS les objets rattachés à chaque règlement
	 * retenu, et pas seulement celui par lequel on l'a trouvé.
	 *
	 * @param string $objecttype 'Client' | 'Facture' | 'Commande' | 'Propal'
	 * @param int    $id         Id de l'objet
	 * @param int    $limit      Nombre max de règlements retournés (0 = pas de limite)
	 * @return array             Tableau d'objets paiement indexé par rowid, trié du + récent au + ancien.
	 *                           Chaque objet porte ->objects : array de array('type','id','ref').
	 */
	public static function object_payments($objecttype, $id, $limit = 0)
	{
		$id = (int) $id;
		if ($id <= 0)
			return array();

		$p = MAIN_DB_PREFIX;
		switch ($objecttype) {
			case 'Client':
			case 'Societe':
				// Règlements du tiers : via ses factures, ses commandes/devis,
				// ou rattachés directement au tiers.
				$filter = "SELECT pf2.fk_paiement FROM ".$p."paiement_facture pf2
						INNER JOIN ".$p."facture f2 ON f2.rowid = pf2.fk_facture
						WHERE f2.fk_soc = ".$id."
					UNION
					SELECT po2.fk_paiement FROM ".$p."paiement_object po2
						LEFT JOIN ".$p."commande c2 ON po2.objecttype = 'Commande' AND c2.rowid = po2.fk_object
						LEFT JOIN ".$p."propal pr2 ON po2.objecttype = 'Propal' AND pr2.rowid = po2.fk_object
						WHERE c2.fk_soc = ".$id."
							OR pr2.fk_soc = ".$id."
							OR (po2.objecttype = 'Client' AND po2.fk_object = ".$id.")";
				break;
			case 'Facture':
			case 'Commande':
			case 'Propal':
				// Tout le cycle devis / commande / facture, pas le seul document ouvert :
				// un acompte encaissé sur le devis et le solde réglé sur la facture
				// concernent la même affaire (cf. cycle_objects()).
				$parts = array();
				foreach (static::cycle_objects($objecttype, $id) as $o) {
					if ($o['type'] === 'Facture') {
						$parts[] = "SELECT pf2.fk_paiement FROM ".$p."paiement_facture pf2
							WHERE pf2.fk_facture = ".((int) $o['id']);
					} else {
						$parts[] = "SELECT po2.fk_paiement FROM ".$p."paiement_object po2
							WHERE po2.objecttype = '".static::$db->escape($o['type'])."' AND po2.fk_object = ".((int) $o['id']);
					}
				}
				$filter = implode(" UNION ", $parts);
				break;
			default:
				return array();
		}

		$sql = "SELECT p.rowid, p.ref, p.datep, p.datec, p.amount, p.num_paiement, p.note,
				p.fk_paiement AS fk_mode, cp.code AS mode_code, cp.libelle AS mode_label,
				pf.fk_facture, f.ref AS facture_ref,
				po.objecttype AS po_type, po.fk_object AS po_id,
				c.ref AS commande_ref, pr.ref AS propal_ref
			FROM ".MAIN_DB_PREFIX."paiement p
			LEFT JOIN ".MAIN_DB_PREFIX."c_paiement cp ON cp.id = p.fk_paiement
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement = p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = pf.fk_facture
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_object po ON po.fk_paiement = p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."commande c ON po.objecttype = 'Commande' AND c.rowid = po.fk_object
			LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON po.objecttype = 'Propal' AND pr.rowid = po.fk_object
			WHERE p.entity IN (".getEntity('facture').")
				AND p.rowid IN (".$filter.")
			ORDER BY p.datep DESC, p.rowid DESC";

		$resql = static::$db->query($sql);
		if (!$resql)
			return array();

		$l = array();
		while ($obj = static::$db->fetch_object($resql)) {
			if (!isset($l[$obj->rowid])) {
				$obj->objects = array();
				$l[$obj->rowid] = $obj;
			}
			// Collecte (dédupliquée) des objets métier rattachés à ce règlement
			if (!empty($obj->fk_facture)) {
				$l[$obj->rowid]->objects['Facture:'.$obj->fk_facture] =
					array('type' => 'Facture', 'id' => $obj->fk_facture, 'ref' => $obj->facture_ref);
			}
			if (!empty($obj->po_type) && !empty($obj->po_id)) {
				$ref = ($obj->po_type == 'Commande') ? $obj->commande_ref
					: (($obj->po_type == 'Propal') ? $obj->propal_ref : '');
				$l[$obj->rowid]->objects[$obj->po_type.':'.$obj->po_id] =
					array('type' => $obj->po_type, 'id' => $obj->po_id, 'ref' => $ref);
			}
		}

		if ($limit > 0 && count($l) > $limit)
			$l = array_slice($l, 0, $limit, true);

		return $l;
	}

	/**
	 * Lignes de l'onglet « Paiements » : règlements du socle + apports des modules
	 * de paiement, fusionnés et triés du plus récent au plus ancien.
	 *
	 * POINT D'EXTENSION — hook `addPaymentRows`, contexte `mmipaymentstab`.
	 *
	 * Un module de paiement remplit $this->results['payment_rows'] avec un tableau
	 * de lignes. Chaque ligne est un tableau associatif :
	 *
	 *   'date'       (int, requis)  timestamp, sert au tri chronologique
	 *   'amount'     (float)        montant
	 *   'ref'        (string)       HTML — référence, généralement un lien
	 *   'objects'    (string)       HTML — objets métier rattachés
	 *   'mode'       (string)       HTML — libellé du moyen de paiement
	 *   'status'     (string)       HTML — état (badge, motif de refus en infobulle…)
	 *   'actions'    (string)       HTML — actions proposées (formulaire POST)
	 *   'is_payment' (bool)         true = encaissement réel (compte dans le total),
	 *                               false = tentative sans suite (affichée en retrait)
	 *   'fk_paiement'(int, option.) ENRICHISSEMENT : si la ligne se rapporte à un
	 *                               règlement déjà listé par le socle, indiquer son id
	 *                               plutôt que d'ajouter une ligne. Les champs fournis
	 *                               COMPLÈTENT la ligne existante (typiquement 'mode',
	 *                               'status' et 'actions' précisés par le module) au
	 *                               lieu de la dupliquer.
	 *
	 * Sans ce mécanisme d'enrichissement, une transaction aboutie apparaîtrait deux
	 * fois : une fois comme règlement (vu du socle) et une fois comme tentative
	 * réussie (vu du module).
	 *
	 * L'ordre d'exécution entre modules se règle par la propriété `priority` de leur
	 * classe d'actions (défaut Dolibarr : 50 ; plus grand = plus tard).
	 *
	 * @param string      $objecttype  'Client' | 'Facture' | 'Commande' | 'Propal'
	 * @param int         $id          Id de l'objet porteur
	 * @param HookManager $hookmanager Gestionnaire de hooks de la page (contexte déjà initialisé)
	 * @return array                   Lignes prêtes à l'affichage, triées par date décroissante
	 */
	public static function payment_rows($objecttype, $id, $hookmanager = null)
	{
		global $langs, $conf;

		$rows = array();
		$index = array();	// fk_paiement -> clé dans $rows, pour l'enrichissement

		foreach (static::object_payments($objecttype, $id, 0) as $p) {
			$key = 'payment:'.$p->rowid;
			// Le règlement remonté par le cycle peut être rattaché à un AUTRE document
			// (typiquement : réglé sur la facture alors qu'on regarde le devis). Le
			// masquer serait faux, l'afficher sans le dire le serait tout autant.
			$attached = static::attachment_note($objecttype, $id, $p->objects);
			$rows[$key] = array(
				'date'       => static::$db->jdate($p->datep),
				'amount'     => (float) $p->amount,
				'ref'        => '<a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.$p->rowid.'">'
					.img_picto('', 'payment', 'class="paddingright"')
					.dol_escape_htmltag($p->ref ? $p->ref : '#'.$p->rowid).'</a>',
				'objects'    => static::render_linked_objects($p->objects).$attached,
				'mode'       => dol_escape_htmltag(static::mode_label($p))
					.(!empty($p->num_paiement) ? ' <span class="opacitymedium small">('.dol_escape_htmltag($p->num_paiement).')</span>' : ''),
				'status'     => '<span class="badge badge-status4 badge-status">'.$langs->trans("MMIPaymentsStatusReceived").'</span>',
				'actions'    => '',
				'is_payment' => true,
			);
			$index[(int) $p->rowid] = $key;
		}

		// Apports des modules de paiement (tentatives, échecs, échéanciers, actions).
		// On leur transmet le CYCLE déjà résolu : sans lui, ils filtreraient sur le seul
		// document ouvert et l'onglet deviendrait incohérent — les règlements de la
		// facture liée s'afficheraient, mais pas les tentatives CB de cette facture.
		if (is_object($hookmanager)) {
			$parameters = array(
				'objecttype' => $objecttype,
				'id'         => (int) $id,
				'cycle'      => static::cycle_objects($objecttype, $id),
			);
			$dummy = new stdClass();
			$action = '';
			$reshook = $hookmanager->executeHooks('addPaymentRows', $parameters, $dummy, $action);
			if ($reshook >= 0 && !empty($hookmanager->resArray['payment_rows'])) {
				foreach ($hookmanager->resArray['payment_rows'] as $i => $row) {
					$fk = !empty($row['fk_paiement']) ? (int) $row['fk_paiement'] : 0;
					if ($fk && isset($index[$fk])) {
						// Enrichissement d'un règlement déjà listé : pas de doublon
						foreach (array('mode', 'status', 'actions', 'objects', 'ref') as $f) {
							if (isset($row[$f]) && $row[$f] !== '') {
								$rows[$index[$fk]][$f] = $row[$f];
							}
						}
						continue;
					}
					$row += array('date' => 0, 'amount' => 0, 'ref' => '', 'objects' => '',
						'mode' => '', 'status' => '', 'actions' => '', 'is_payment' => false);
					$rows['hook:'.$i] = $row;
				}
			}
		}

		uasort($rows, function ($a, $b) {
			if ($a['date'] == $b['date']) {
				return 0;
			}
			return ($a['date'] > $b['date']) ? -1 : 1;
		});

		return $rows;
	}

	/**
	 * Mention à accoler à un règlement remonté par le cycle mais NON rattaché au
	 * document ouvert — « réglé sur FA2504-000545 » quand on regarde le devis.
	 *
	 * Silencieux quand le règlement porte bien sur le document courant : la mention
	 * ne doit attirer l'œil que là où elle apprend quelque chose.
	 *
	 * @param string $objecttype Type du document ouvert
	 * @param int    $id         Id du document ouvert
	 * @param array  $objects    Objets rattachés au règlement
	 */
	public static function attachment_note($objecttype, $id, $objects)
	{
		global $langs;

		if (empty($objects)) {
			return '';
		}
		if (isset($objects[$objecttype.':'.((int) $id)])) {
			return '';	// rattaché au document ouvert : rien à signaler
		}

		// Les documents porteurs sont déjà affichés dans la colonne : les répéter
		// ferait du bruit. Le marqueur ne dit que ce qu'elle ne dit pas — que ce
		// règlement ne porte pas sur le document ouvert.
		return ' <span class="badge badge-status1 badge-status" title="'
			.dol_escape_htmltag($langs->trans('MMIPaymentsAttachedElsewhereHelp')).'">'
			.dol_escape_htmltag($langs->trans('MMIPaymentsAttachedElsewhere')).'</span>';
	}

	/**
	 * Libellé traduit d'un mode de règlement (repli sur le libellé de c_paiement).
	 */
	public static function mode_label($p)
	{
		global $langs;
		if (empty($p->mode_code)) {
			return $p->mode_label ? $p->mode_label : '';
		}
		$key = 'PaymentType'.$p->mode_code;
		$label = $langs->trans($key);
		if ($label != $key) {
			return $label;
		}
		return $p->mode_label ? $p->mode_label : $p->mode_code;
	}

	/**
	 * Rendu HTML des objets métier (facture / commande / devis) liés à un règlement.
	 *
	 * @param array $objects  array de array('type','id','ref')
	 */
	public static function render_linked_objects($objects)
	{
		if (empty($objects)) {
			return '<span class="opacitymedium">-</span>';
		}
		$links = array();
		foreach ($objects as $o) {
			$url = '';
			if ($o['type'] == 'Facture') {
				$url = DOL_URL_ROOT.'/compta/facture/card.php?id='.$o['id'];
			} elseif ($o['type'] == 'Commande') {
				$url = DOL_URL_ROOT.'/commande/card.php?id='.$o['id'];
			} elseif ($o['type'] == 'Propal') {
				$url = DOL_URL_ROOT.'/comm/propal/card.php?id='.$o['id'];
			}
			$label = dol_escape_htmltag($o['ref'] ? $o['ref'] : $o['type'].' #'.$o['id']);
			$links[] = $url ? '<a href="'.$url.'">'.$label.'</a>' : $label;
		}
		return implode(', ', $links);
	}

	/**
	 * Vue GLOBALE : règlements de tous les tiers, et tentatives de paiement quand
	 * les modules en fournissent. Alimente la liste `list.php`.
	 *
	 * Deux modes, parce que les deux natures de données ne se paginent pas pareil :
	 *  - 'payments'  : les règlements, paginés en SQL (le socle sait faire) ;
	 *  - 'attempts'  : les tentatives, que seuls les modules de paiement connaissent.
	 *    Le socle n'a rien à y mettre : llx_paiement ne contient que des encaissements
	 *    aboutis. Il interroge le hook `addGlobalPaymentRows` (contexte
	 *    `mmipaymentslist`) en lui passant statut, limite et offset.
	 *
	 * Le mode 'attempts' répond aux questions qui n'avaient aucune réponse globale :
	 * quels paiements ont été refusés, et quels encaissements ont abouti côté banque
	 * sans que le paiement Dolibarr existe, sur l'ENSEMBLE du parc ?
	 *
	 * Pagination : un module qui honore limit/offset renvoie `payment_total` (le total
	 * AVANT pagination) — ses lignes sont alors la page demandée, telles quelles. Un
	 * module qui l'ignore renvoie tout : on trie et on plafonne ici, en l'annonçant.
	 *
	 * @param array       $filters     'mode', 'status', 'socid', 'datefrom', 'dateto', 'limit', 'offset'
	 * @param HookManager $hookmanager Gestionnaire de hooks (contexte mmipaymentslist initialisé)
	 * @return array                   array('rows' => …, 'total' => int|null, 'truncated' => bool)
	 */
	public static function global_payment_rows($filters = array(), $hookmanager = null)
	{
		global $langs;

		$filters += array('mode' => 'payments', 'status' => self::ATTEMPT_STATUS_DEFAULT,
			'socid' => 0, 'datefrom' => '', 'dateto' => '', 'limit' => 100, 'offset' => 0);
		$limit  = max(1, (int) $filters['limit']);
		$offset = max(0, (int) $filters['offset']);

		if ($filters['mode'] === 'attempts') {
			if (!in_array($filters['status'], self::attempt_statuses(), true)) {
				$filters['status'] = self::ATTEMPT_STATUS_DEFAULT;
			}
			// Compat : les modules écrits pour le contrat 1.4 ne lisent que ce drapeau.
			$filters['missing_payment'] = ($filters['status'] === 'missing') ? 1 : 0;

			$rows = array();
			$total = null;
			if (is_object($hookmanager)) {
				$parameters = array('filters' => $filters);
				$dummy = new stdClass();
				$action = '';
				$reshook = $hookmanager->executeHooks('addGlobalPaymentRows', $parameters, $dummy, $action);
				if ($reshook >= 0) {
					if (!empty($hookmanager->resArray['payment_rows'])) {
						$rows = $hookmanager->resArray['payment_rows'];
					}
					// Le total se lit même quand la page est vide : demander une page
					// au-delà de la fin ne veut pas dire qu'il n'y a rien à compter.
					if (isset($hookmanager->resArray['payment_total'])) {
						$total = (int) $hookmanager->resArray['payment_total'];
					}
				}
			}

			if ($total !== null) {
				// Le module a paginé lui-même : ses lignes SONT la page demandée et
				// son total est exact. Re-trancher ici les casserait.
				return array('rows' => $rows, 'total' => $total, 'truncated' => false);
			}

			// Repli : module qui ignore limit/offset. On trie et on plafonne, en le
			// disant — un plafond qui ne se voit pas se lit comme une liste complète.
			uasort($rows, function ($a, $b) {
				$da = isset($a['date']) ? $a['date'] : 0;
				$db = isset($b['date']) ? $b['date'] : 0;
				if ($da == $db) {
					return 0;
				}
				return ($da > $db) ? -1 : 1;
			});
			$total = count($rows);
			$truncated = false;
			if ($total > $limit) {
				$rows = array_slice($rows, 0, $limit, true);
				$truncated = true;	// jamais silencieux : la vue l'affiche
			}
			return array('rows' => $rows, 'total' => $total, 'truncated' => $truncated);
		}

		// Mode 'payments' : règlements, paginés en SQL
		$where = "p.entity IN (".getEntity('facture').")";
		if (!empty($filters['socid'])) {
			$where .= " AND (f.fk_soc = ".(int) $filters['socid']
				." OR c.fk_soc = ".(int) $filters['socid']
				." OR pr.fk_soc = ".(int) $filters['socid'].")";
		}
		if (!empty($filters['datefrom'])) {
			$where .= " AND p.datep >= '".static::$db->idate($filters['datefrom'])."'";
		}
		if (!empty($filters['dateto'])) {
			$where .= " AND p.datep <= '".static::$db->idate($filters['dateto'])."'";
		}

		$from = " FROM ".MAIN_DB_PREFIX."paiement p
			LEFT JOIN ".MAIN_DB_PREFIX."c_paiement cp ON cp.id = p.fk_paiement
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement = p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = pf.fk_facture
			LEFT JOIN ".MAIN_DB_PREFIX."paiement_object po ON po.fk_paiement = p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."commande c ON po.objecttype = 'Commande' AND c.rowid = po.fk_object
			LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON po.objecttype = 'Propal' AND pr.rowid = po.fk_object
			WHERE ".$where;

		$total = null;
		$resql = static::$db->query("SELECT COUNT(DISTINCT p.rowid) AS nb".$from);
		if ($resql && ($o = static::$db->fetch_object($resql))) {
			$total = (int) $o->nb;
		}

		$sql = "SELECT DISTINCT p.rowid, p.ref, p.datep, p.amount, p.num_paiement,
				p.fk_paiement AS fk_mode, cp.code AS mode_code, cp.libelle AS mode_label,
				COALESCE(f.fk_soc, c.fk_soc, pr.fk_soc) AS socid"
			.$from
			." ORDER BY p.datep DESC, p.rowid DESC"
			.static::$db->plimit($limit, $offset);

		$rows = array();
		$resql = static::$db->query($sql);
		if ($resql) {
			while ($obj = static::$db->fetch_object($resql)) {
				$obj->objects = array();
				$rows['payment:'.$obj->rowid] = $obj;
			}
		}

		// Objets métier rattachés, en une requête pour tout le lot (pas de N+1)
		if ($rows) {
			$ids = array();
			foreach ($rows as $r) {
				$ids[] = (int) $r->rowid;
			}
			$idlist = implode(',', $ids);
			$sql2 = "SELECT pf.fk_paiement, 'Facture' AS objecttype, f.rowid AS fk_object, f.ref
					FROM ".MAIN_DB_PREFIX."paiement_facture pf
					INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = pf.fk_facture
					WHERE pf.fk_paiement IN (".$idlist.")
				UNION ALL
				SELECT po.fk_paiement, po.objecttype, po.fk_object,
					COALESCE(c.ref, pr.ref) AS ref
					FROM ".MAIN_DB_PREFIX."paiement_object po
					LEFT JOIN ".MAIN_DB_PREFIX."commande c ON po.objecttype = 'Commande' AND c.rowid = po.fk_object
					LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON po.objecttype = 'Propal' AND pr.rowid = po.fk_object
					WHERE po.fk_paiement IN (".$idlist.")";
			$resql2 = static::$db->query($sql2);
			if ($resql2) {
				while ($o = static::$db->fetch_object($resql2)) {
					$k = 'payment:'.$o->fk_paiement;
					if (isset($rows[$k])) {
						$rows[$k]->objects[$o->objecttype.':'.$o->fk_object] =
							array('type' => $o->objecttype, 'id' => $o->fk_object, 'ref' => $o->ref);
					}
				}
			}
		}

		return array('rows' => $rows, 'total' => $total, 'truncated' => false);
	}

	public static function payment_invoices($id)
	{
		if (!is_numeric($id))
			return false;

		$sql = 'SELECT f.rowid as facid, f.ref, f.type, f.total_ttc, f.paye, f.entity, f.fk_statut, pf.amount, s.nom as name, s.rowid as socid'
			.' FROM '.MAIN_DB_PREFIX.'paiement_facture as pf'
			.' INNER JOIN '.MAIN_DB_PREFIX.'facture as f'
				.' ON f.rowid = pf.fk_facture AND f.entity IN ('.getEntity('invoice').')'
			.' INNER JOIN '.MAIN_DB_PREFIX.'societe as s'
				.' ON s.rowid = f.fk_soc'
			.' WHERE pf.fk_paiement = '.$id;
		$resql = static::$db->query($sql);
		$p = [];
		if ($resql) {
			while ($objp = static::$db->fetch_object($resql))
				$p[] = $objp;
			return $p;
		}
	}

	public static function payment_to_invoice()
	{
	}

	public static function invoice_autoassign_payments_from_object($object)
	{
		$object->fetchObjectLinked();
		//var_dump($object->linkedObjectsIds);
		if(!empty($object->linkedObjectsIds) && !empty($object->linkedObjectsIds['facture']) && count($object->linkedObjectsIds['facture'])==1) {
			foreach($object->linkedObjectsIds['facture'] as $invoice_id) {
				$invoice = new Facture(static::$db);
				$invoice->fetch($invoice_id);
				static::invoice_autoassign_payments($invoice);
				return 1;
			}
			return 1;
		}
		return 0;
	}

	public static function propal_addlinepayment(Propal $propal) {

		global $langs;
		$url = getOnlinePaymentUrl(0, 'propal', $propal->ref);

		$description = dol_html_entity_decode('<a href="'.$url.'">'.$langs->trans('TxtLinkPayment').'</a>', ENT_QUOTES, 'UTF-8', 1);

		//Hack to allow Dolibarr to add line
		$oldstatus = $propal->statut;
		$propal->statut=Propal::STATUS_DRAFT;
		$linemax = $propal->line_max();
		$rangtouse = $linemax + 1;

		$propal->addline($description, 0, 0, 0, 0, 0, 0, 0, "HT", 0, 0, 9, $rangtouse, 1790, 0, 0, 0, $langs->trans('TxtMileStoneLinkPayment'), '', '', 0, null, '', 0, null);

		$propal->statut=$oldstatus;
	}

	public static function invoice_autoassign_payments($object, $force=false)
	{
		global $user;

		/** @var Facture $object */

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
			// Paiements déjà associés à la facture
			$sql = 'SELECT SUM(pi.amount) AS amount'
				.' FROM '.MAIN_DB_PREFIX.'paiement_facture pi'
				.' WHERE pi.fk_facture = '.$object->id;
			$resql = static::$db->query($sql);
			//var_dump($resql);
			// d'un montant total <= cette facture
			if ($resql && static::$db->num_rows($resql)) {
				list($amount) = $resql->fetch_row();
			}
			else {
				$amount = 0;
			}
			//var_dump($amount); die();

			$sql_linked_objects = [];
			foreach($linked_objects as $row)
				$sql_linked_objects[] = '(po.objecttype="'.$row[0].'" AND po.fk_object='.$row[1].')';
			// Paiements associés à la commande ou au devis
			// non imputé à une autre facture
			// On commence par les plus gros comme ça on ne met pas les petits qui peuvent aller à une autre ;-)
			$sql = 'SELECT po.fk_paiement, po.amount, po.objecttype, po.fk_object'
				.' FROM '.MAIN_DB_PREFIX.'paiement_object po'
				.' INNER JOIN '.MAIN_DB_PREFIX.'paiement p ON p.rowid=po.fk_paiement'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'paiement_facture pi'
					.' ON pi.fk_paiement=po.fk_paiement'
				.' WHERE ('.implode(' OR ', $sql_linked_objects).')'
					//.' AND po.amount <= '.$object->total_ttc
					.' AND pi.fk_paiement IS NULL'
				.' ORDER BY po.amount DESC';
			//echo '<p>'.$sql.'</p>';
			$resql = static::$db->query($sql);
			//var_dump($resql);
			// d'un montant total <= cette facture
			if ($resql && static::$db->num_rows($resql)) {
				//$object->fetch_thirdparty(); // inutile déjà fait
				$client = $object->thirdparty;

				// Valider la facture
				$object->validate($user, '', $object->fk_warehouse);

				while($objp = $resql->fetch_object()) {
					$obj = NULL;
					$objtot = NULL;
					if ($objp->objecttype == 'Propal') {
						$obj = new Propal(static::$db);
						$obj->fetch($objp->fk_object);
						$objtot = $obj->total_ttc;
					}
					elseif($objp->objecttype == 'Commande') {
						$obj = new Commande(static::$db);
						$obj->fetch($objp->fk_object);
						$objtot = $obj->total_ttc;
					}
					// Si le montant dépasse mais que la propal ou commande est d'un montant inférieur, c'est qu'on a payé trop, donc on passe tout de même le paiement
					// Seul seul blocage c'est mettre qque chose qui dépasse de trop
					if (!$force && ($amount+$objp->amount > $object->total_ttc + static::AMOUNT_DIFF_CTS) && ($objtot > $object->total_ttc + static::AMOUNT_DIFF_CTS))
						break;
					$amount += $objp->amount;

					// Imputer le paiement à la facture
					$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'paiement_facture (fk_facture, fk_paiement, amount, multicurrency_amount)'
						.' VALUES ('.$object->id.', '.$objp->fk_paiement.', \''.(float)$objp->amount.'\', \''.(float)$objp->amount.'\')';
					//echo '<p>'.$sql.'</p>';
					$ret = static::$db->query($sql);

					// Add to bank
					$paiement = new Paiement(static::$db);
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
					$q = static::$db->query($sql);
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
				$q2 = static::$db->query($sql);
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

	/**
	 * Add a payment
	 * @param string $objecttype Propal|Commande|Facture
	 * @param int $id
	 * @param array $infos ['date', 'amount', 'mode', 'num', 'note', 'accountid', 'chqemetteur', 'chqbank']
	 */
	public static function add($objecttype, $id, $infos)
	{
		global $user, $conf, $hookmanager;
		$objecttype = ucfirst($objecttype);

		// Creation of payment line
		$paiement = new Paiement(static::$db);

		if (empty($infos['date'])) {
			$infos['date'] = dol_now();
		}
		// Paiement::datepaye attend un TIMESTAMP. Ne jamais lui passer une chaîne :
		// Dolibarr la transmet telle quelle à date() (create() -> getNextNumRef() ->
		// mod_payment_ant -> get_next_value()), ce qui est toléré en PHP 7 (conversion
		// implicite) mais lève une TypeError fatale à partir de PHP 8.
		$date = $infos['date'];
		if (is_numeric($date)) {
			// Timestamp déjà (dol_now(), ou valeur fournie telle quelle par l'appelant)
			$date = (int) $date;
		}
		else {
			// Chaîne : 'JJ/MM/AAAA' (saisie utilisateur) ou date/heure interprétable
			// ('AAAA-MM-JJ [HH:MM:SS]', ISO 8601 renvoyé par une API...)
			if (strpos($date, '/') !== false)
				$date = implode('-', array_reverse(explode('/', $date)));
			$timestamp = strtotime($date);
			// Date illisible : on ne crée pas un paiement daté de 1970
			$date = ($timestamp === false) ? dol_now() : $timestamp;
		}

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
		static::$db->query($sql);

		if(in_array($objecttype, ['Propal', 'Commande'])) {

			if (empty($object->mode_reglement_id)) {
				$object->mode_reglement_id=$paiement->paiementid;
				$object->update($user,1);
			}

			// Spécifique association devis/commande
			$sql = "INSERT INTO `".MAIN_DB_PREFIX."paiement_object`
				(`fk_paiement`, `objecttype`, `fk_object`, `amount`, `multicurrency_code`, `multicurrency_tx`, `multicurrency_amount`)
				VALUES (".$paiement_id.", '".$objecttype."', ".$id.", ".$paiement->amount.", NULL, 1, ".$paiement->amount.")";
			//echo $sql;
			static::$db->query($sql);

			// If propal validated or unsigned, set Signed
			if ($objecttype=='Propal' && in_array($object->status, [Propal::STATUS_VALIDATED, Propal::STATUS_NOTSIGNED])) {
				$object->closeProposal($user, propal::STATUS_SIGNED, 'Payment done');
			}

			// Assign to invoice if only one
			$object->fetchObjectLinked();
			if(!empty($object->linkedObjectsIds) && !empty($object->linkedObjectsIds['facture']) && count($object->linkedObjectsIds['facture'])==1) {
				foreach($object->linkedObjectsIds['facture'] as $invoice_id) {
					$invoice = new Facture(static::$db);
					$invoice->fetch($invoice_id);
					static::invoice_autoassign_payments($invoice);
				}
			}
		}
		elseif ($objecttype=='Facture') {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."paiement_facture (fk_facture, fk_paiement, amount, multicurrency_amount)";
			$sql .= " VALUES (".$object->id.", ".$paiement_id.", ".$paiement->amount.", ".$paiement->amount.")";
			//echo $sql;
			static::$db->query($sql);

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
			$resteapayer = price2num($object->total_ttc - $dejaregle - $object->getSumCreditNotesUsed() - (getDolGlobalInt('FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS') ?$object->getSumDepositsUsed() :0));
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
