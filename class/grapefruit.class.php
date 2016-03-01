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
