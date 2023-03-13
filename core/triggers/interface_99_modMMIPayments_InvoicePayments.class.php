<?php
/**
 * Copyright (C) 2022       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for MyModule module
 */
class InterfaceInvoicePayments extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MMI Payment Invoice triggers";
		$this->version = 'development';
		$this->picto = 'logo@mmipayments';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->mmipayments->enabled)) return 0;

		global $db;
		$langs->loadLangs(array("mmipayments@mmipayments"));
		
		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		
		switch($action) {
			//var_dump($action); var_dump($object);
			case 'BILL_CREATE':
			case 'BILL_MODIFY':
			case 'BILL_VALIDATE':
				// @todo découper et mettre dans un service !
				// Si depuis commande
				if(!empty($object->linked_objects['commande'])) {
					// => Associer la commande
					$commande = new Commande($db);
					$commande->fetch($object->linked_objects['commande']);
					$commande->fetchObjectLinked();
					//var_dump($commande->linkedObjectsIds);
					// Si commande depuis un/des devis
					if (!empty($commande->linkedObjectsIds['propal'])) {
						// => Associer le(s) devis
						foreach($commande->linkedObjectsIds['propal'] as $id)
							$object->add_object_linked('propal', $id);
					}
				}
				//$object->fetchObjectLinked();
				//var_dump($object); $db->rollback(); die();
				break;
		}
		
		return 0;
	}
}
