<?php

class TGrappeFruit {
	
	static function checkBudgetNotEmpty(&$object) {
		global $conf,$langs;
		
		if(empty($conf->global->GRAPEFRUIT_BUDGET_NEEDED)) return true;
		
		if(empty($object->budget_amount)) {
			setEventMessage($langs->trans('BudgetRequire'), 'errors');
			return false;
		}
		else{
			return true;
		}
		
	}

	static function checkDateEndNotEmpty(&$object) {
		global $conf,$langs;
                if(empty($conf->global->GRAPEFRUIT_DATEEND_NEEDED)) return true;
                if(empty($object->date_end)) {
                        setEventMessage($langs->trans('ProjectDateEndRequire'), 'errors');
                        return false;
                }
                else{
                        return true;
                }

	}

	static function createShippingFromOrderOnBillPayed(&$object) {
		global $conf,$langs, $db, $user;
        if(empty($conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID) || $object->element!='facture') return true;
		
		if(empty($object->linked_objects)) $object->fetchObjectLinked(null,null,$object->id,'facture');
		
		if(empty($object->linkedObjects['commande'])) return false;
		
		dol_include_once('/expedition/class/expedition.class.php');
		
		$TNotCopy=array('db','element','table_element','fk_element');
		
		foreach($object->linkedObjects['commande'] as &$commande) {
			
			$expedition = new Expedition($db); 
		
			foreach($commande as $k=>$v) {
				if(!in_array($k, $TNotCopy)) {
					$expedition->{$k} = $commande->{$k};	
				}
				
			} 
			$expedition->tracking_number='';
			
			$expedition->size=0;
			$expedition->weight=0;
			
			$expedition->sizeS = $expedition->sizeH = $expedition->sizeW = 0;	// TODO Should use this->trueDepth
			
			$expedition->width=0;
			$expedition->height=0;
			$expedition->weight_units=0;
			$expedition->size_units=0;
			
			if(empty($conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID_WAREHOUSE)) {
				setEventMessage($langs->trans('DefaultWarehouseRequired'));
				return false;
			}
			
			foreach($expedition->lines as &$line) {
				$line->entrepot_id = (int)$conf->global->GRAPEFRUIT_SHIPPING_CREATE_FROM_ORDER_WHERE_BILL_PAID_WAREHOUSE;
				$line->origin_line_id = $line->id;
				
			}
			
			$res = $expedition->create($user);
			if($res>0) {
				
				$expedition->add_object_linked($commande->element, $commande->id);
				
				setEventMessage($langs->trans('ShippingCreated'));	
			}	
			else{
				var_dump($expedition);exit;
			}		
		}
		
		return true;
	}

	static function createBillOnOrderValidate(&$object) {
		global $conf,$langs, $db, $user;
        if(empty($conf->global->GRAPEFRUIT_ORDER_CREATE_BILL_ON_VALIDATE)) return true;
			
		dol_include_once('/compta/facture/class/facture.class.php');
			
		$facture = new Facture($db);	
		$res = $facture->createFromOrder($object);
		if($res>0) {
			setEventMessage($langs->trans('BillCreated'));	
		}
	    
        return false;
	
	}

	static function sendBillByMail(&$object) {
		global $conf,$langs,$user,$db;
		
		if(!empty($conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE)) {
			
			if(empty($object->thirdparty)) $object->fetch_thirdparty();
			
			if(!empty($object->thirdparty->email)) {
				$sendto = $object->thirdparty->email;
				$sendtocc = '';
				
				$from = $user->email;
				$id = $object->id;
				
				$_POST['receiver'] = '-1';
				
				$_POST['frommail'] =  $_POST['replytomail'] = $from;
				$_POST['fromname'] =  $_POST['replytoname'] = $user->getFullName($langs);
				
				dol_include_once('/core/class/html.formmail.class.php');
				$formmail=new Formmail($db);
				$outputlangs = clone $langs;
				$id_template = (int)$conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE_MODEL;
				
				$formmail->fetchAllEMailTemplate('facture_send', $user, $outputlangs);
				
				foreach($formmail->lines_model as &$model) {
					
					if($model->id == $id_template) break;
					
				}
				
				if(empty($model)) setEventMessage($langs->trans('ModelRequire'),'errors');
				
				$_POST['message'] = $model->topic;
				$_POST['subject'] = $model->label;
				
				$action='send';
				$actiontypecode='AC_FAC';
				$trigger_name='BILL_SENTBYMAIL';
				$paramname='id';
				$mode='emailfrominvoice';
				require_once __DIR__.'/../tpl/actions_sendmails.inc.php';
				
				
			}			
			
			
			
		}
		
	}

	
	static function createTasks(&$object) {
		global $conf,$langs,$db,$user;
		
		if(!empty($conf->global->GRAPEFRUIT_PROJECT_AUTO_ADD_TASKS_ON_CREATE)) {
			
			$TLabel = explode("\n", $conf->global->GRAPEFRUIT_PROJECT_AUTO_ADD_TASKS_ON_CREATE);
			
			dol_include_once('/projet/class/task.class.php');
			
			foreach($TLabel as $label) {
				
				$label = trim($label);
				
				$t=new Task($db);
				
				$defaultref='';
				$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
				if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
				{
					require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
					$modTask = new $obj;
					$defaultref = $modTask->getNextValue($soc,$object);
				}
			
				if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';
				
				$t->ref = $defaultref;
				$t->label = $label;
				$t->fk_project = $object->id;
				$t->fk_task_parent = 0;
				
				$res = $t->create($user);
				
				if($res < 0) {
					setEventMessage($langs->trans('ImpossibleToAdd', $label));
				}
				
			}
			
			setEventMessage($langs->trans('autoTasksAdded'));
			
		}
		
	}
	
	static function checkContractFourn(&$object) {
		
		global $conf,$langs,$db;
		
		if(empty($conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN) || $conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN<0) return true;
		
		dol_include_once('/fourn/class/fournisseur.product.class.php');
		
		foreach($object->lines as &$line) {
			
			if(empty($line->fk_fournprice) && $line->fk_product>0) {
				
				$p_static=new ProductFournisseur($db);
				$TPrice = $p_static->list_product_fournisseur_price($line->fk_product);
				
				foreach($TPrice as &$price) {
					
					if($price->fourn_id == $conf->global->GRAPEFRUIT_CONTRACT_DEFAUL_FOURN) {
						//TODO AA updateline sur contrat, là j'ai la flemme, no comment
						$db->query("UPDATE ".MAIN_DB_PREFIX."contratdet
						SET fk_product_fournisseur_price=".$price->product_fourn_price_id.",buy_price_ht=".($price->fourn_price / $price->fourn_qty)."
						WHERE rowid=".$line->id);
						break;
					}
					
				}
				
			}
			
		}
		
		
	}
}
