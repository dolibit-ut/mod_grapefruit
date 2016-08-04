<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file core/triggers/interface_99_modMyodule_GrapeFruittrigger.class.php
 * \ingroup grapefruit
 * \brief Sample trigger
 * \remarks You can create other triggers by copying this one
 * - File name should be either:
 * interface_99_modMymodule_Mytrigger.class.php
 * interface_99_all_Mytrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceGrapeFruittrigger
{
	private $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db) {
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Triggers of this module are empty functions." . "They have no effect." . "They are provided for tutorial purpose only.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'grapefruit@grapefruit';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc() {
		return $this->description;
	}

	/**
	 * Trigger version
	 *
	 * @return string Version of trigger file
	 */
	public function getVersion() {
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') {
			return $langs->trans("Development");
		} elseif ($this->version == 'experimental')

			return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else {
			return $langs->trans("Unknown");
		}
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string $action Event action code
	 * @param Object $object Object
	 * @param User $user Object user
	 * @param Translate $langs Object langs
	 * @param conf $conf Object conf
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, &$object, &$user, &$langs, &$conf) {
		dol_include_once('/grapefruit/class/grapefruit.class.php');
		$langs->load('grapefruit@grapefruit');

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users

		if ($action == 'ACTION_CREATE') {
			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);

			if (! empty($conf->global->GRAPEFRUIT_CAN_ASSOCIATE_TASK_TO_ACTIONCOMM)) {
				// il faut récup le fk_task et fait un lien avec "related"
				$fk_task = GETPOST('fk_task', 'int');

				if ($fk_task > 0) {
					$r = $object->add_object_linked('task', $fk_task);
				}
			}
		} elseif ($action === 'LINECONTRACT_INSERT') {
			TGrappeFruit::checkContractFourn($object);
		} elseif ($action === 'ORDER_VALIDATE') {
			TGrappeFruit::createBillOnOrderValidate($object);

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action === 'BILL_CREATE') {

			if (! empty($conf->global->GRAPEFRUIT_LINK_INVOICE_TO_SESSION_IF_PROPAL_IS) && $conf->agefodd->enabled) {
				if ($object->origin == 'propal' && $object->origin_id > 0) {
					$db = &$this->db;

					dol_include_once('/agefodd.git/class/agefodd_session_element.class.php');

					$res = $db->query("SELECT fk_session_agefodd FROM " . MAIN_DB_PREFIX . "agefodd_session_element WHERE fk_element=" . $object->origin_id . " AND element_type='propal'");
					$obj = $db->fetch_object($res);

					if ($obj->fk_session_agefodd > 0) {
						$agf = new Agefodd_session_element($db);
						$agf->fk_element = $object->id;
						$agf->fk_session_agefodd = $obj->fk_session_agefodd;
						$agf->fk_soc = empty($object->socid) ? $object->fk_soc : $object->socid;
						$agf->element_type = 'invoice';
						$result = $agf->create($user);
					}
				}
			}
		} elseif ($action === 'BILL_PAYED') {

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);

			TGrappeFruit::createShippingFromOrderOnBillPayed($object);

			if (! empty ( $conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_BILLED )) {
				if (empty($conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_ORDER)) {
					TGrappeFruit::sendBillByMail($object);
				} else {
					if (empty($object->linked_objects))
						$object->fetchObjectLinked(null, null, $object->id, 'facture');

					if (empty($object->linkedObjects['commande']))
						return false;

					foreach ( $object->linkedObjects['commande'] as &$commande ) {
						TGrappeFruit::sendOrderByMail($commande);
					}
				}
			}
		} elseif ($action === 'BILL_VALIDATE') {

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);

			if (! empty ( $conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE )) {
				if (empty($conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_ORDER)) {

					// Define output language
					if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
					{
						$outputlangs = $langs;

						$model=$object->modelpdf;
						$object->ref=$object->newref;
						$object->generateDocument($model, $outputlangs);
						//if ($result < 0) setEventMessages($object->error, $object->errors, 'errors');
					}


					TGrappeFruit::sendBillByMail($object);
				} else {
					if (empty($object->linked_objects))
						$object->fetchObjectLinked(null, null, $object->id, 'facture');

					if (empty($object->linkedObjects['commande']))
						return false;

					foreach ( $object->linkedObjects['commande'] as &$commande ) {
						TGrappeFruit::sendOrderByMail($commande);
					}
				}
			}

			if (! empty($conf->propal->enabled) && ! empty($conf->global->GRAPEFRUIT_INVOICE_CLASSIFY_BILLED_PROPAL))
			{
				$object->fetchObjectLinked('','propal',$object->id,$object->element);
				if (! empty($object->linkedObjects))
				{
					foreach($object->linkedObjects['propal'] as $element)
					{
						$ret=$element->classifyBilled($user);
					}
				}
			}
		} elseif ($action === 'PROJECT_CREATE') {
			if (! TGrappeFruit::checkNoDuplicateRef($object))
				return - 1;


			if (! TGrappeFruit::checkBudgetNotEmpty($object))
				return - 1;
			if (! TGrappeFruit::checkDateEndNotEmpty($object))
				return - 1;

			TGrappeFruit::createTasks($object);

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action === 'PROJECT_MODIFY') {
			if (! TGrappeFruit::checkBudgetNotEmpty($object))
				return - 1;
			if (! TGrappeFruit::checkDateEndNotEmpty($object))
				return - 1;

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		}elseif ($action === 'PROPAL_CREATE') {
			$db= $this->db;
			if (!empty($conf->global->GRAPEFRUIT_LINK_PROPAL_2_PROJECT)){
				dol_include_once('/projet/class/project.class.php');

				$projId = 0;
				$sql  = "SELECT p.rowid AS rowid";
		        $sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
		        $sql .= " WHERE p.fk_soc = ".$object->socid;
		        $sql .= " ORDER BY p.dateo DESC";
				$sql .= " LIMIT 1";

				$resql = $db->query($sql);

				if ($resql){
					while ($line = $db->fetch_object($resql)){
								$projId = $line->rowid;
					}
				}

				//On fetch le projet
				$projet = new Project($db);
				$projet->fetch($projId);

				//TODO Ajouter la propale au projet
				//var_dump($object->table_element, $object->id);exit;
				$projet->update_element($object->table_element, $object->id);

			}
		 	dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		 } elseif ($action == 'ORDER_SUPPLIER_DISPATCH') {
        	// classify supplier order delivery status
        	dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

        	if (! empty($conf->commande->enabled) && ! empty($conf->fournisseur->enabled) && ! empty($conf->global->GRAPEFRUIT_SUPPLIER_ORDER_CLASSIFY_RECEIPT_ORDER))
        	{
        		dol_include_once('/grapefruit/class/supplier.commande.dispatch.class.php');

        		$qtydelivered=array();
        		$qtywished=array();

        		$supplierorderdispatch = new CommandeFournisseurDispatchATM($this->db);
        		$filter=array('t.fk_commande'=>$object->id);
        		if (!empty($conf->global->SUPPLIER_ORDER_USE_DISPATCH_STATUS)) {
        			$filter['t.status']=1;
        		}
        		$ret=$supplierorderdispatch->fetchAll('','',0,0,$filter);
        		if ($ret<0) {
        			$this->error=$supplierorderdispatch->error; $this->errors=$supplierorderdispatch->errors;
        			return $ret;
        		} else {
					if (is_array($supplierorderdispatch->lines) && count($supplierorderdispatch->lines)>0) {
						//Build array with quantity deliverd by product
						foreach($supplierorderdispatch->lines as $line) {
							$qtydelivered[$line->fk_product]+=$line->qty;
						}
						foreach($object->lines as $line) {
							$qtywished[$line->fk_product]+=$line->qty;
						}
						//Compare array
						$diff_array=array_diff_assoc($qtydelivered,$qtywished);
						if (count($diff_array)==0) {
							//No diff => mean everythings is received
							$ret=$object->setStatus($user,5);
							if ($ret<0) {
								$this->error=$object->error; $this->errors=$object->errors;
								return $ret;
							}
						} else {
							//Diff => received partially
							$ret=$object->setStatus($user,4);
							if ($ret<0) {
								$this->error=$object->error; $this->errors=$object->errors;
								return $ret;
							}
						}
					}
        		}
        	}
        }

		/*
		 if ($action === 'USER_LOGIN') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }
		 else if($action ==='LINECONTRACT_INSERT') {

		 TGrappeFruit::checkContractFourn($object);


		 }
		 elseif ($action === 'USER_UPDATE_SESSION') {
		 // Warning: To increase performances, this action is triggered only if
		 // constant MAIN_ACTIVATE_UPDATESESSIONTRIGGER is set to 1.
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_CREATE_FROM_CONTACT') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_NEW_PASSWORD') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_ENABLEDISABLE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_LOGOUT') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_SETINGROUP') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'USER_REMOVEFROMGROUP') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Groups
		 elseif ($action === 'GROUP_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'GROUP_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'GROUP_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Companies
		 elseif ($action === 'COMPANY_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'COMPANY_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'COMPANY_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Contacts
		 elseif ($action === 'CONTACT_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTACT_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTACT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Products
		 elseif ($action === 'PRODUCT_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PRODUCT_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PRODUCT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Customer orders
		 elseif ($action === 'ORDER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_CLONE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_VALIDATE') {
		 TGrappeFruit::createBillOnOrderValidate($object);


		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_BUILDDOC') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_SENTBYMAIL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEORDER_INSERT') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEORDER_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Supplier orders
		 elseif ($action === 'ORDER_SUPPLIER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_SUPPLIER_VALIDATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'ORDER_SUPPLIER_SENTBYMAIL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SUPPLIER_ORDER_BUILDDOC') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Proposals
		 elseif ($action === 'PROPAL_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_CLONE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_VALIDATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_BUILDDOC') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_SENTBYMAIL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_CLOSE_SIGNED') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_CLOSE_REFUSED') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROPAL_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEPROPAL_INSERT') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEPROPAL_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEPROPAL_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Contracts
		 elseif ($action === 'CONTRACT_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTRACT_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTRACT_ACTIVATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTRACT_CANCEL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTRACT_CLOSE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CONTRACT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Bills
		 elseif ($action === 'BILL_CREATE') {

		 if(!empty($conf->global->GRAPEFRUIT_LINK_INVOICE_TO_SESSION_IF_PROPAL_IS) && $conf->agefodd->enabled) {
		 //        		var_dump($object->origin, $object->origin_id);
		 if($object->origin == 'propal' && $object->origin_id>0) {
		 $db = &$this->db;

		 dol_include_once('/agefodd.git/class/agefodd_session_element.class.php');

		 $res = $db->query("SELECT fk_session_agefodd FROM ".MAIN_DB_PREFIX."agefodd_session_element WHERE fk_element=".$object->origin_id." AND element_type='propal'");
		 $obj = $db->fetch_object($res);
		 //var_dump($obj->fk_session_agefodd);

		 if($obj->fk_session_agefodd>0) {
		 $agf = new Agefodd_session_element($db);
		 $agf->fk_element = $object->id;
		 $agf->fk_session_agefodd = $obj->fk_session_agefodd;
		 $agf->fk_soc = empty($object->socid) ? $object->fk_soc : $object->socid;
		 $agf->element_type = 'invoice';0
		 $result = $agf->create($user);
		 //var_dump($result);
		 }
		 //var_dump($db);
		 //				exit('!');

		 }
		 }


		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_CLONE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_PAYED') {

		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );

		 TGrappeFruit::createShippingFromOrderOnBillPayed($object);

		 if (empty($conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_ORDER)) {
		 TGrappeFruit::sendBillByMail($object);
		 } else {
		 if(empty($object->linked_objects)) $object->fetchObjectLinked(null,null,$object->id,'facture');

		 if(empty($object->linkedObjects['commande'])) return false;

		 foreach($object->linkedObjects['commande'] as &$commande) {
		 TGrappeFruit::sendOrderByMail($commande);
		 }
		 }



		 } elseif ($action === 'BILL_BUILDDOC') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_SENTBYMAIL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_CANCEL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'BILL_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEBILL_INSERT') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'LINEBILL_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Payments
		 elseif ($action === 'PAYMENT_CUSTOMER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PAYMENT_SUPPLIER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PAYMENT_ADD_TO_BANK') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PAYMENT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Interventions
		 elseif ($action === 'FICHEINTER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'FICHEINTER_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'FICHEINTER_VALIDATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'FICHEINTER_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Members
		 elseif ($action === 'MEMBER_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_VALIDATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_SUBSCRIPTION') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_NEW_PASSWORD') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_RESILIATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'MEMBER_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Categories
		 elseif ($action === 'CATEGORY_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CATEGORY_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'CATEGORY_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Projects
		 elseif ($action === 'PROJECT_CREATE') {
		 if(!TGrappeFruit::checkBudgetNotEmpty($object)) return -1;
		 if(!TGrappeFruit::checkDateEndNotEmpty($object)) return -1;

		 TGrappeFruit::createTasks($object);

		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROJECT_MODIFY') {
		 if(!TGrappeFruit::checkBudgetNotEmpty($object)) return -1;
		 if(!TGrappeFruit::checkDateEndNotEmpty($object)) return -1;

		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'PROJECT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Project tasks
		 elseif ($action === 'TASK_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'TASK_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'TASK_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Task time spent
		 elseif ($action === 'TASK_TIMESPENT_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'TASK_TIMESPENT_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'TASK_TIMESPENT_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // Shipping
		 elseif ($action === 'SHIPPING_CREATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SHIPPING_MODIFY') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SHIPPING_VALIDATE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SHIPPING_SENTBYMAIL') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SHIPPING_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'SHIPPING_BUILDDOC') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }

		 // File
		 elseif ($action === 'FILE_UPLOAD') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 } elseif ($action === 'FILE_DELETE') {
		 dol_syslog(
		 "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
		 );
		 }
		 */
		return 0;
	}
}
