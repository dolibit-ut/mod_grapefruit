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
		global $conf,$langs,$user;
		
		if(!empty($conf->global->GRAPEFRUIT_SEND_BILL_BY_MAIL_ON_VALIDATE)) {
			
			if(!empty($object->thirdparty->email)) {
				$sendto = $object->thirdparty->email;
				$from = $user->email;
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
				
				$formmail = new FormMail($db);

				$attachedfiles=$formmail->get_attached_files();
				$filepath = $attachedfiles['paths'];
				$filename = $attachedfiles['names'];
				$mimetype = $attachedfiles['mimes'];
						
				exit;
				setEventMessage($langs->trans('BillSendedByMailTo', $sendto));
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
