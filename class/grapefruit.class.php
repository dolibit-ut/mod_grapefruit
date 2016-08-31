<?php
class TGrappeFruit
{
	
	static function checkNoDuplicateRef(&$object) {
		global $conf, $langs, $db;
		
		$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE ref = '".$object->ref."' AND rowid!=".(int)$object->id." LIMIT 1");
		if($obj = $db->fetch_object($res)) {
			setEventMessage($langs->trans('DuplicateProjectRef'), 'errors');
		
			return false;
			
		}
		
		return true;
		
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function checkBudgetNotEmpty(&$object) {
		global $conf, $langs;
		
		if (empty($conf->global->GRAPEFRUIT_BUDGET_NEEDED))
			return true;
		
		if (empty($object->budget_amount)) {
			setEventMessage($langs->trans('BudgetRequire'), 'errors');
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function checkDateEndNotEmpty(&$object) {
		global $conf, $langs;
		if (empty($conf->global->GRAPEFRUIT_DATEEND_NEEDED))
			return true;
		if (empty($object->date_end)) {
			setEventMessage($langs->trans('ProjectDateEndRequire'), 'errors');
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function createShippingFromOrderOnBillPayed(&$object) {
		global $conf, $langs, $db, $user;
		if (empty($conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID) || $object->element != 'facture')
			return true;
		
		if (empty($object->linked_objects))
			$object->fetchObjectLinked(null, null, $object->id, 'facture');
		
		if (empty($object->linkedObjects['commande']))
			return false;
		
		dol_include_once('/expedition/class/expedition.class.php');
		
		$TNotCopy = array (
				'db',
				'element',
				'table_element',
				'fk_element' 
		);
		
		foreach ( $object->linkedObjects['commande'] as &$commande ) {
			
			$expedition = new Expedition($db);
			
			foreach ( $commande as $k => $v ) {
				if (! in_array($k, $TNotCopy)) {
					$expedition->{$k} = $commande->{$k};
				}
			}
			$expedition->tracking_number = '';
			
			$expedition->size = 0;
			$expedition->weight = 0;
			
			$expedition->sizeS = $expedition->sizeH = $expedition->sizeW = 0; // TODO Should use this->trueDepth
			
			$expedition->width = 0;
			$expedition->height = 0;
			$expedition->weight_units = 0;
			$expedition->size_units = 0;
			
			if (empty($conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID_WAREHOUSE)) {
				setEventMessage($langs->trans('DefaultWarehouseRequired'));
				return false;
			}
			
			foreach ( $expedition->lines as &$line ) {
				$line->entrepot_id = ( int ) $conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID_WAREHOUSE;
				$line->origin_line_id = $line->id;
			}
			
			$res = $expedition->create($user);
			if ($res > 0) {
				
				$expedition->add_object_linked($commande->element, $commande->id);
				
				setEventMessage($langs->trans('ShippingCreated'));
			} else {
				var_dump($expedition);
				exit();
			}
		}
		
		return true;
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function createBillOnOrderValidate(&$object) {
		global $conf, $langs, $db, $user;
		if (empty($conf->global->GRAPEFRUIT_ORDER_CREATE_BILL_ON_VALIDATE))
			return true;
		
		dol_include_once('/compta/facture/class/facture.class.php');
		
		$facture = new Facture($db);
		$res = $facture->createFromOrder($object);
		if ($res > 0) {
			setEventMessage($langs->trans('BillCreated'));
		}
		
		// Transfert Contact from order to invoice
		
		return false;
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function sendBillByMail(&$object) {
		global $conf, $langs, $user, $db;
		
		if (empty($object->thirdparty))
			$object->fetch_thirdparty();
		
		$sendto = $object->thirdparty->email;
		$sendtocc = '';
		
		$from = (empty($user->email) ? $conf->global->MAIN_MAIL_EMAIL_FROM : $user->email);
		$id = $object->id;
		
		$_POST['receiver'] = '-1';
		
		$_POST['frommail'] = $_POST['replytomail'] = $from;
		$_POST['fromname'] = $_POST['replytoname'] = $user->getFullName($langs);
		
		dol_include_once('/core/class/html.formmail.class.php');
		$formmail = new Formmail($db);
		$outputlangs = clone $langs;
		$id_template = ( int ) $conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_MODEL;
		
		$formmail->fetchAllEMailTemplate('facture_send', $user, $outputlangs);
		
		foreach ( $formmail->lines_model as &$model ) {
			
			if ($model->id == $id_template)
				break;
		}
		
		if (empty($model))
			setEventMessage($langs->trans('ModelRequire'), 'errors');
			
			// Find Order to put it into ref
		if (empty($object->linked_objects))
			$object->fetchObjectLinked(null, null, $object->id, 'facture');
		foreach ( $object->linkedObjects['commande'] as $commande ) {
			$orderref .= $commande->ref;
		}
		
		// Make substitution
		$substit['__REF__'] = (empty($object->newref)?$object->ref:$object->newref);
		$substit['__ORDER_REF__'] = $orderref;
		$substit['__SIGNATURE__'] = $user->signature;
		$substit['__REFCLIENT__'] = $object->ref_client;
		$substit['__THIRDPARTY_NAME__'] = $object->thirdparty->name;
		$substit['__PROJECT_REF__'] = (is_object($object->projet) ? $object->projet->ref : '');
		$substit['__PROJECT_NAME__'] = (is_object($object->projet) ? $object->projet->title : '');
		$substit['__PERSONALIZED__'] = '';
		$substit['__CONTACTCIVNAME__'] = '';
		
		// Find the good contact adress
		$custcontact = '';
		$contactarr = array ();
		$contactarr = $object->liste_contact(- 1, 'external');
		
		if (is_array($contactarr) && count($contactarr) > 0) {
			foreach ( $contactarr as $contact ) {
				dol_syslog(get_class($this) . '::' . __METHOD__ . 'lib=' . $contact['libelle']);
				dol_syslog(get_class($this) . '::' . __METHOD__ . 'trans=' . $langs->trans('TypeContact_facture_external_BILLING'));
				
				if ($contact['libelle'] == $langs->trans('TypeContact_facture_external_BILLING')) {
					
					require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
					
					$contactstatic = new Contact($db);
					$contactstatic->fetch($contact['id']);
					$custcontact = $contactstatic->getFullName($langs, 1);
					
					dol_syslog(get_class($this) . '::' . __METHOD__ . ' email=' . $contactstatic->email);
				}
			}
			
			if (! empty($custcontact)) {
				$substit['__CONTACTCIVNAME__'] = $custcontact;
			}
			if (! empty($contactstatic->email)) {
				$sendto = $contactstatic->email;
			}
		}
		
		$topic = make_substitutions($model->topic, $substit);
		$message = make_substitutions($model->content, $substit);
		
		$_POST['message'] = $message;
		$_POST['subject'] = $topic;
		
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
		// Add attached files
		$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $object->ref, preg_quote($object->ref, '/') . '[^\-]+');
		if (is_array($fileparams) && array_key_exists('fullname', $fileparams) && ! empty($fileparams['fullname'])) {
			$_SESSION["listofpaths"] = $fileparams['fullname'];
			$_SESSION["listofnames"] = basename($fileparams['fullname']);
			$_SESSION["listofmimes"] = dol_mimetype($fileparams['fullname']);
		} else {
			// generate invoice
			$result = $object->generateDocument($object->modelpdf, $outputlangs, 0, 0, 0);
			if ($result <= 0) {
				$this->error = $object->error;
			}
			$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $object->ref, preg_quote($object->ref, '/') . '[^\-]+');
			if (is_array($fileparams) && array_key_exists('fullname', $fileparams) && ! empty($fileparams['fullname'])) {
				$_SESSION["listofpaths"] = $fileparams['fullname'];
				$_SESSION["listofnames"] = basename($fileparams['fullname']);
				$_SESSION["listofmimes"] = dol_mimetype($fileparams['fullname']);
			}
		}
		
		$action = 'send';
		$actiontypecode = 'AC_FAC';
		$trigger_name = 'BILL_SENTBYMAIL';
		$paramname = 'id';
		$mode = 'emailfrominvoice';
		
		if (! empty($sendto)) {
			require_once __DIR__ . '/../tpl/actions_sendmails.inc.php';
		}
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function sendOrderByMail(&$object) {
		global $conf, $langs, $user, $db;
		
		if (empty($object->thirdparty))
			$object->fetch_thirdparty();
		
		$sendto = $object->thirdparty->email;
		$sendtocc = '';
		
		$from = (empty($user->email) ? $conf->global->MAIN_MAIL_EMAIL_FROM : $user->email);
		$id = $object->id;
		
		$_POST['receiver'] = '-1';
		
		$_POST['frommail'] = $_POST['replytomail'] = $from;
		$_POST['fromname'] = $_POST['replytoname'] = $user->getFullName($langs);
		
		dol_include_once('/core/class/html.formmail.class.php');
		$formmail = new Formmail($db);
		$outputlangs = clone $langs;
		$id_template = ( int ) $conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_MODEL;
		
		$formmail->fetchAllEMailTemplate('facture_send', $user, $outputlangs);
		
		foreach ( $formmail->lines_model as &$model ) {
			
			if ($model->id == $id_template)
				break;
		}
		
		if (empty($model))
			setEventMessage($langs->trans('ModelRequire'), 'errors');
			
			// Make substitution
		$substit['__REF__'] = $object->ref;
		$substit['__SIGNATURE__'] = $user->signature;
		$substit['__REFCLIENT__'] = $object->ref_client;
		$substit['__THIRDPARTY_NAME__'] = $object->thirdparty->name;
		$substit['__PROJECT_REF__'] = (is_object($object->projet) ? $object->projet->ref : '');
		$substit['__PROJECT_NAME__'] = (is_object($object->projet) ? $object->projet->title : '');
		$substit['__PERSONALIZED__'] = '';
		$substit['__CONTACTCIVNAME__'] = '';
		
		// Find the good contact adress
		$custcontact = '';
		$contactarr = array ();
		$contactarr = $object->liste_contact(- 1, 'external');
		
		if (is_array($contactarr) && count($contactarr) > 0) {
			foreach ( $contactarr as $contact ) {
				dol_syslog(get_class($this) . '::' . __METHOD__ . ' lib=' . $contact['libelle']);
				dol_syslog(get_class($this) . '::' . __METHOD__ . ' trans=' . $langs->trans('TypeContact_commande_external_BILLING'));
				
				if ($contact['libelle'] == $langs->trans('TypeContact_commande_external_BILLING')) {
					
					require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
					
					$contactstatic = new Contact($db);
					$contactstatic->fetch($contact['id']);
					$custcontact = $contactstatic->getFullName($langs, 1);
					
					dol_syslog(get_class($this) . '::' . __METHOD__ . ' email=' . $contactstatic->email);
				}
			}
			
			if (! empty($custcontact)) {
				$substit['__CONTACTCIVNAME__'] = $custcontact;
			}
			if (! empty($contactstatic->email)) {
				$sendto = $contactstatic->email;
			}
		}
		
		$topic = make_substitutions($model->topic, $substit);
		$message = make_substitutions($model->content, $substit);
		
		$_POST['message'] = $message;
		$_POST['subject'] = $topic;
		
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
		// Add attached files
		$fileparams = dol_most_recent_file($conf->commande->dir_output . '/' . $object->ref, preg_quote($object->ref, '/') . '[^\-]+');
		if (is_array($fileparams) && array_key_exists('fullname', $fileparams) && ! empty($fileparams['fullname'])) {
			$_SESSION["listofpaths"] = $fileparams['fullname'];
			$_SESSION["listofnames"] = basename($fileparams['fullname']);
			$_SESSION["listofmimes"] = dol_mimetype($fileparams['fullname']);
		} else {
			// generate invoice
			$result = $object->generateDocument($object->modelpdf, $outputlangs, 0, 0, 0);
			if ($result <= 0) {
				$this->error = $object->error;
			}
			$fileparams = dol_most_recent_file($conf->commande->dir_output . '/' . $object->ref, preg_quote($object->ref, '/') . '[^\-]+');
			if (is_array($fileparams) && array_key_exists('fullname', $fileparams) && ! empty($fileparams['fullname'])) {
				$_SESSION["listofpaths"] = $fileparams['fullname'];
				$_SESSION["listofnames"] = basename($fileparams['fullname']);
				$_SESSION["listofmimes"] = dol_mimetype($fileparams['fullname']);
			}
		}
		
		$action = 'send';
		$actiontypecode = 'AC_FAC';
		$trigger_name = 'BILL_SENTBYMAIL';
		$paramname = 'id';
		$mode = 'emailfrominvoice';
		if (! empty($sendto)) {
			require_once __DIR__ . '/../tpl/actions_sendmails.inc.php';
		}
	}
	
	/**
	 *
	 * @param unknown $object
	 */
	static function createTasks(&$object) {
		global $conf, $langs, $db, $user;
		
		if (! empty($conf->global->GRAPEFRUIT_PROJECT_AUTO_ADD_TASKS_ON_CREATE)) {
			
			$TLabel = explode("\n", $conf->global->GRAPEFRUIT_PROJECT_AUTO_ADD_TASKS_ON_CREATE);
			
			dol_include_once('/projet/class/task.class.php');
			
			foreach ( $TLabel as $label ) {
				
				$label = trim($label);
				
				$t = new Task($db);
				
				$defaultref = '';
				$obj = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;
				if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
					require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
					$modTask = new $obj();
					$defaultref = $modTask->getNextValue($soc, $object);
				}
				
				if (is_numeric($defaultref) && $defaultref <= 0)
					$defaultref = '';
				
				$t->ref = $defaultref;
				$t->label = $label;
				$t->fk_project = $object->id;
				$t->fk_task_parent = 0;
				
				$res = $t->create($user);
				
				if ($res < 0) {
					setEventMessage($langs->trans('ImpossibleToAdd', $label));
				}
			}
			
			setEventMessage($langs->trans('autoTasksAdded'));
		}
	}
	
	/**
	 *
	 * @param unknown $object
	 * @return boolean
	 */
	static function checkContractFourn(&$object) {
		global $conf, $langs, $db;
		
		if (empty($conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN) || $conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN < 0)
			return true;
		
		dol_include_once('/fourn/class/fournisseur.product.class.php');
		
		foreach ( $object->lines as &$line ) {
			
			if (empty($line->fk_fournprice) && $line->fk_product > 0) {
				
				$p_static = new ProductFournisseur($db);
				$TPrice = $p_static->list_product_fournisseur_price($line->fk_product);
				
				foreach ( $TPrice as &$price ) {
					
					if ($price->fourn_id == $conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN) {
						// TODO AA updateline sur contrat, là j'ai la flemme, no comment
						$db->query("UPDATE " . MAIN_DB_PREFIX . "contratdet
						SET fk_product_fournisseur_price=" . $price->product_fourn_price_id . ",buy_price_ht=" . ($price->fourn_price / $price->fourn_qty) . "
						WHERE rowid=" . $line->id);
						break;
					}
				}
			}
		}
	}
	
	/**
	 * @param $object : object expédition
	 */
	static function setOrderShippedIfAllProductShipped(&$object) {
		
		// Récupération de la commande d'origine
		$object->fetchObjectLinked();
		$TOriginOrder = array_values($object->linkedObjects['commande']);
		$order = $TOriginOrder[0];
		if(empty($order)) return 0; 
		
		// On refait la fonction dans l'autre sens car la commande peut avoir été expédiée en plusieurs fois
		$order->fetchObjectLinked();
		$TShipping = array_values($order->linkedObjects['shipping']);
		
		// Rangement des quantités dans la commande par produit, uniquement les produits avec un fk_product
		$TOrderProductQty = array();
		if(!empty($order->lines)) {
			foreach($order->lines as $order_line) {
				if($order_line->product_type == 0) $TOrderProductQty[$order_line->fk_product] += $order_line->qty;
			}
		}
		
		// Rangement des quantités dans l'expédition par produit
		$TShippingProductQty = array();
		foreach($TShipping as $shipping) {
			if(!empty($shipping->lines)) {
				foreach($shipping->lines as $shipping_line) {
					$TShippingProductQty[$shipping_line->fk_product] += $shipping_line->qty;
				}
			}
		}
		
		if(count($TOrderProductQty) != count($TShippingProductQty)) return 0;
		
		foreach($TShippingProductQty as $fk_product=>$qty) {
			if($qty < $TOrderProductQty[$fk_product]) return 0;
		}

		// Si on a passé le test des quantités sans problèmes, on passe la commande au statut "Livrée"
		if($order->setStatut(3) > 0) setEventMessage('Commande '.$order->getNomUrl().' passée au statut "livrée"');
		
	}

	function orderSupplierOrder(&$object, $methode_id) {
		
		global $user;
		
		$object->commande($user, time(), $methode_id);
		
	}
	
}
