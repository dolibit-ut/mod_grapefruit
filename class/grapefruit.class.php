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
	
	
}
