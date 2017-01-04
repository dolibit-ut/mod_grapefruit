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
 * \file    class/actions_grapefruit.class.php
 * \ingroup grapefruit
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsGrapeFruit
 */
class ActionsGrapeFruit
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;
		
		dol_include_once('/grapefruit/class/grapefruit.class.php');
		
		$TContext = explode(':', $parameters['context']);
		
		$actionATM = GETPOST('actionATM');
		if ($parameters['currentcontext'] == 'ordercard' && $object->statut >= 1 && !empty($conf->global->GRAPEFRUIT_ALLOW_CREATE_BILL_EXPRESS))
		{
			if($actionATM === 'create_bill_express'
				&& !empty($conf->global->GRAPEFRUIT_ALLOW_CREATE_BILL_EXPRESS)
				&& $object->statut > Commande::STATUS_DRAFT
				&& !$object->billed
				&& !empty($conf->facture->enabled)
				&& $user->rights->facture->creer
				&& empty($conf->global->WORKFLOW_DISABLE_CREATE_INVOICE_FROM_ORDER)) {
				
				TGrappeFruit::createFactureFromObject($object);
				
			}
		}
		if ($parameters['currentcontext'] == 'ordersuppliercard')
		{
			if ($action == 'builddoc' && !empty($conf->global->GRAPEFRUIT_FORCE_VAR_HIDEREF_ON_SUPPLIER_ORDER))
			{
				global $hideref;
				$hideref = 0;
			}
		}
		
		// Bypass des confirmation
		if (in_array('globalcard', $TContext))
		{
			$actionList = explode(',', $conf->global->GRAPEFRUIT_BYPASS_CONFIRM_ACTIONS);
			if (!empty($action) && !empty($conf->global->GRAPEFRUIT_BYPASS_CONFIRM_ACTIONS) && in_array($action, $actionList))
			{
				global $confirm;
				$confirm = 'yes';
				$action = 'confirm_'.$action;
			}
		}
		
		return 0;
	}

	

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;
		//var_dump($action, $parameters);exit;
		//Context : frm creation propal
		
		$langs->load('bills');
		$langs->load('grapefruit@grapefruit');
		
		// Script pour gérer les champs obligatoires sur une fiche contact
		if($parameters['currentcontext'] === 'contactcard' && !empty($conf->global->GRAPEFRUIT_CONTACT_FORCE_FIELDS) && ($action == 'edit' || $action == 'create')) {
			$TChamps = explode(',',$conf->global->GRAPEFRUIT_CONTACT_FORCE_FIELDS);
			$first = true;
			$match1 = '';
			$match2 = '#lastname';
			foreach($TChamps as $champ) {
				if(!$first) {
					$match1 .=', ';
				}
				$match2 .=', ';
				$match1 .= 'td > label[for="'.$champ.'"]';
				$match2 .= '#'.$champ;
				$first=false;
			}
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('<?php echo $match1; ?>').addClass('fieldrequired');
					$('<?php echo $match2; ?>').attr('required','required');
				})
			</script>
			<?php
		}
		
		if ($parameters['currentcontext'] === 'propalcard') 
		{
			if($action === 'create') {
				
				if ($conf->grapefruit->enabled && $conf->global->GRAPEFRUIT_PROPAL_DEFAULT_BANK_ACOUNT > 0)
				{
					?>
					<script type="text/javascript">
						$(function() {
							$("select[name=fk_account] option[value=<?php echo $conf->global->GRAPEFRUIT_PROPAL_DEFAULT_BANK_ACOUNT; ?>]").attr('selected', true);
						});
					</script>
					<?php
				}
				
			} elseif(!empty($conf->global->GRAPEFRUIT_ALLOW_CREATE_ORDER_AND_BILL_ON_UNSIGNED_PROPAL) && $object->statut == 1) {
				
				?>
					<script type="text/javascript">
						$(document).ready(function() {
							
							var bt_cmd = $('<a class="butAction" href="<?php echo dol_buildpath('/commande/card.php', 2); ?>?action=create&amp;origin=<?php echo $object->element; ?>&amp;originid=<?php echo $object->id; ?>&amp;socid=<?php echo $object->socid; ?>"><?php echo $langs->trans('AddOrder'); ?></a>');
							var bt_bill = $('<a class="butAction" href="<?php echo dol_buildpath('/compta/facture.php', 2); ?>?action=create&amp;origin=<?php echo $object->element; ?>&amp;originid=<?php echo $object->id; ?>&amp;socid=<?php echo $object->socid; ?>"><?php echo $langs->trans('AddBill'); ?></a>');
							
							if ($('div.tabsAction a.butAction:contains("<?php print $langs->trans('SendByMail'); ?>")').length > 0) {
								$('div.tabsAction a.butAction:contains("<?php print $langs->trans('SendByMail'); ?>")').after(bt_bill);
								$('div.tabsAction a.butAction:contains("<?php print $langs->trans('SendByMail'); ?>")').after(bt_cmd);
							} else {
								$('div.tabsAction').append(bt_bill);
								$('div.tabsAction').append(bt_cmd);
							}
							
						});
					</script>
				<?php
				
			}
			
		}
		
		elseif ($parameters['currentcontext'] == 'thirdpartycard')
		{
			if (!empty($conf->global->GRAPEFRUIT_DISABLE_PROSPECTCUSTOMER_CHOICE) && ($action == 'create' || $action == 'edit'))
			{
				?>
				<script type="text/javascript">
					$(function() {
						$('#customerprospect option[value=3]').remove();
					});
				</script>
				<?php	
			}
		}
		
		if ($parameters['currentcontext'] == 'ordercard') {
			
			if(!empty($conf->global->GRAPEFRUIT_ALLOW_CREATE_BILL_EXPRESS)
				&& $object->statut > Commande::STATUS_DRAFT
				&& !$object->billed
				&& !empty($conf->facture->enabled)
				&& $user->rights->facture->creer
				&& empty($conf->global->WORKFLOW_DISABLE_CREATE_INVOICE_FROM_ORDER)) {
				
				?>
				
				<script type="text/javascript">
					var bt_create_fact_express = $('<a class="butAction" href="<?php echo dol_buildpath('/commande/card.php?actionATM=create_bill_express&id='.GETPOST('id'), 2); ?>"><?php echo $langs->trans('GrapefruitCreateBillExpress'); ?></a>');
					$(document).ready(function() {
						
						if ($('div.tabsAction a.butAction:contains("<?php print $langs->transnoentities('CreateBill'); ?>")').length > 0) {
							
							$('div.tabsAction a.butAction:contains("<?php print $langs->transnoentities('CreateBill'); ?>")').after(bt_create_fact_express);
						} else {
							$('div.tabsAction').append(bt_create_fact_express);
						}
						
						// Pour éviter le double clic
						bt_create_fact_express.click(function() {
							this.remove();
						});
						
					});
				</script>
				
				<?php	
				
			}
		
		}
		
		/*else if ($parameters['currentcontext'] === 'invoicecard' && $action === 'confirm_valid') { 
		
				?>
				<script type="text/javascript">
					$(document).ready(function() {
						
						$a = $('a.butAction[href*=presend]');
						
						document.location.href = $a.attr('href');
												
					});
				</script>
				<?php
		}
		else if ($parameters['currentcontext'] === 'invoicecard' && $action === 'presend') { 
		
				?>
				<script type="text/javascript">
					$(document).ready(function() {
						
						
						$('')
												
					});
				</script>
				<?php
		}*/
	}
	
	function createFrom($parameters, &$object, &$action, $hookmanager) {
			
		global $conf,$user,$langs;
		
		if ($parameters['currentcontext'] === 'invoicecard') 
		{
			dol_include_once('/grapefruit/class/grapefruit.class.php');
			$langs->load('grapefruit@grapefruit');
			
			TGrappeFruit::billCloneLink($object,$parameters['objFrom']);
			
			
		}
	}
	
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$user,$langs;
		
		if ($parameters['currentcontext'] === 'suppliercard' && !empty($conf->global->GRAPEFRUIT_SUPPLIER_FORCE_BT_ORDER_TO_INVOICE)) 
		{
			if ($user->rights->fournisseur->facture->creer)
			{
				?>
				<script type="text/javascript">
					$(function() {
						var bt = $('a.butAction[href*="orderstoinvoice.php?socid="]');
						if (bt.length == 0)
						{
							var btOrder = $('<div class="inline-block divButAction"><a class="butAction" href="<?php echo DOL_URL_ROOT; ?>/fourn/commande/orderstoinvoice.php?socid=<?php echo $object->id; ?>"><?php echo $langs->transnoentitiesnoconv("CreateInvoiceForThisCustomer"); ?></a></div>');
							$('div.tabsAction').append(btOrder);
						}
					});
				</script>
				<?php
			}
		}
	}
	
	
	function pdf_getLinkedObjects(&$parameters, &$object, &$action, $hookmanager) {
		
		global $conf,$user,$langs,$db,$mysoc,$outputlangs;
		
		
		
		if (in_array( 'pdfgeneration', explode(':',$parameters['context']) ) && !empty($conf->global->GRAPEFRUIT_ADD_PROJECT_TO_PDF)) 
		{
			if (empty($object->project->ref)) $object->fetch_projet();
		
			if (! empty($object->project->ref) && !empty($outputlangs))
			{
				$outputlangs->load('grapefruit@grapefruit');
				$outputlangs->load('projects');

				$linkedobjects = $parameters['linkedobjects'];
				
				$objecttype = 'projet';
				$linkedobjects[$objecttype]['ref_title'] = $outputlangs->transnoentities("Project");
				$linkedobjects[$objecttype]['ref_value'] = $outputlangs->transnoentities(empty($object->project->ref)?'':$object->projet->ref);
/*				$linkedobjects[$objecttype]['date_title'] = $outputlangs->transnoentities("ProjectDate");
				$linkedobjects[$objecttype]['date_value'] = dol_print_date($object->project->date_start,'day','',$outputlangs);
*/				
		
				$this->results = $linkedobjects;
				
			
				return 1;
			}
		
		}
		
	}
	
	
	function beforePDFCreation(&$parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$user,$langs,$db,$mysoc;
		
		// Sur version 5.0 le $parameters['currentcontext'] == ordersuppliercard et le "pdfgeneration" est dans $parameters['context']
		$TContext = explode(':', $parameters['context']);
		
		if ($parameters['currentcontext'] === 'pdfgeneration' || in_array('pdfgeneration', $TContext)) 
		{
			$base_object = $parameters['object'];
			
			if(isset($base_object) && in_array($base_object->element, array('order_supplier','commande')))
			{
				require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
				$parameters['outputlangs']->load('deliveries');
				$parameters['outputlangs']->load('orders');
				$usecommande=$usecontact=false;
				// Load des contacts livraison
				$arrayidcontact=$base_object->getIdContact('external','SHIPPING');
				
				if (count($arrayidcontact) > 0)
				{
					$usecontact=true;
					$base_object->fetch_contact($arrayidcontact[0]);
				}
				$base_object->fetchObjectLinked();
				$Qwrite = false;
				if(isset($base_object->linkedObjects['commande']) && !empty($conf->global->GRAPEFRUIT_SUPPLIER_CONTACT_SHIP_ADDRESS))
				{
					// On récupère la donnée de la commande initiale
					// C'est un tableau basé sur des ID donc on boucle pour sortir le premier item
					$commande = reset($base_object->linkedObjects['commande']);
					$date_affiche = date("Y-m-d", $commande->date);
					$ref = $commande->ref;
					$ref_client = $commande->ref_client;
					$usecommande=$Qwrite=true;
				}
				elseif($base_object->element === 'commande' && !empty($conf->global->GRAPEFRUIT_ORDER_CONTACT_SHIP_ADDRESS))
				{
					$date_affiche = date("Y-m-d", $base_object->date);
					$ref = $base_object->ref;
					$ref_client = $base_object->ref_client;
					$usecommande=$Qwrite=true;
				}
				if($usecontact && $Qwrite)
				{
					//Recipient name
					// On peut utiliser le nom de la societe du contact
					$thirdparty=$base_object->thirdparty;
					if (!empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) $thirdparty = $base_object->contact;
					
					$carac_client_name= pdfBuildThirdpartyName($thirdparty, $parameters['outputlangs']);
					
					$thecontact = $base_object->contact;
					
					// SI un élément manquant ou qu'on veuille envoyé à la société du contact alors on change
					if(empty($thecontact->address) || empty($thecontact->zip) || empty($thecontact->town))
					{
						$contactSociete = new Societe($db);
						$contactSociete->fetch($thecontact->socid);
						$thecontact->address = $contactSociete->address;
						$thecontact->zip = $contactSociete->zip; 
						$thecontact->town = $contactSociete->town;
					}
					
					$carac_client=pdf_build_address($parameters['outputlangs'],$object->emetteur,$base_object->client,$base_object->contact,$usecontact,'target');
					
					$newcontent = $parameters['outputlangs']->trans('DeliveryAddress').' :'."\n".'<strong>'.$carac_client_name.'</strong>'."\n".$carac_client;
					if($usecommande)
					{
						if(isset($ref_client))
							$newcontent .= "\n"."<strong>".$parameters['outputlangs']->trans('RefOrder').' client : </strong>'.$ref_client;
						$newcontent .= "\n"."<strong>".$parameters['outputlangs']->trans('RefOrder').' '.$mysoc->name.' : </strong>'.$ref;
						$newcontent .= "\n"."<strong>".$parameters['outputlangs']->trans('OrderDate').' : </strong>'.$date_affiche;
					}
					if(!empty($parameters['object']->note_public))
						$parameters['object']->note_public = dol_nl2br($newcontent."\n\n".$parameters['object']->note_public);
					else
						$parameters['object']->note_public = dol_nl2br($newcontent);
				}
			} // Fin order / order_supplier
		}
	}

	function addOptionCalendarEvents($parameters, &$object, &$action, $hookmanager)
	{
		global $db,$conf,$langs;
		
		if (!empty($conf->global->GRAPEFRUIT_CAN_ASSOCIATE_TASK_TO_ACTIONCOMM) && $parameters['currentcontext']=='fullcalendardao') 
		{
			dol_include_once('/core/class/html.form.class.php');
			
			$form = new Form($db);
			
			ob_start();
			print '<div id="contentTasks" style="display:inline-block">';
			print $form->selectarray('fk_task', array(), '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth100 maxwidth300', 1);
			print '<div>';
			?>
				<span rel="task"></span>
				<script type="text/javascript">
						$div = $('#pop-new-event');
			        	$div.find('select#fk_project').on("change", function(e) {
			        		var fk_project = $(this).val();
			        		
			        		$.ajax({
			        			url: "<?php echo dol_buildpath('/grapefruit/script/interface.php',1); ?>"
			        			,dataType:'json'
			        			,data: {
			        				get: 'fullcalandar_tasks'
			        				,projectid: fk_project
			        			}
			        		}).done(function(data) {
			        			/*$('#pop-new-event span[rel=tasks]').html(data.value);*/
			        			$('#pop-new-event div#contentTasks').text("").append(data.value);
			        			$div.find('select#fk_task').change();
			        		});
			        	});
	        	</script>
			<?php
			$langs->load('projects');
			$option = $langs->trans('Task').' : '.ob_get_clean();
			$this->resprints = json_encode(array('fk_task' => $option));
			
			return 1;
		}
		return 0;
	}
	
	function insertExtraFields($parameters, &$object, &$action, $hookmanager)
	{
		global $db,$mysoc,$langs,$conf;
		
		if ($action == 'update_extras')
		{
			$context = explode(':', $parameters['context']);
			
			if (in_array('propalcard', $context) || in_array('ordercard', $context) || in_array('invoicecard', $context))
			{
				$tva_tx = price2num(GETPOST('options_grapefruit_default_doc_tva'));
				if (!empty($tva_tx))
				{
					$langs->load('grapefruit@grapefruit');
					
					$object->fetch_thirdparty();
					
					$code_country="'".$mysoc->country_code."'";
					$code_country.=",'".$object->thirdparty->country_code."'";
					
					$form = new Form($db);
					$form->load_cache_vatrates($code_country);
					
					$found = false;
					$TTxAllowed = array();
					foreach ($form->cache_vatrates as $rate)
					{
						if ($rate['txtva'] == $tva_tx)
						{
							$found = true;
							break;
						}
						
						$TTxAllowed[] = $rate['txtva'];
					}
					
					if ($found)
					{
						$object->db->begin();
						$res = 1;
						foreach ($object->lines as &$l)
						{
							if (!empty($conf->subtotal->enabled) && (TSubtotal::isTitle($l) || TSubtotal::isSubtotal($l)) ) continue;
							
							switch ($object->element) {
								case 'propal':
									//$rowid, $pu, $qty, $remise_percent, $txtva, $txlocaltax1=0.0, $txlocaltax2=0.0, $desc='', $price_base_type='HT', $info_bits=0, $special_code=0, $fk_parent_line=0, $skip_update_total=0, $fk_fournprice=0, $pa_ht=0, $label='', $type=0, $date_start='', $date_end='', $array_options=0, $fk_unit=null
									$res = $object->updateline($l->id, $l->subprice, $l->qty, $l->remise_percent, $tva_tx, $l->localtax1_tx, $l->localtax1_tx, $l->desc, 'HT', $l->info_bits, $l->special_code, $l->fk_parent_line, 0, $l->fk_fournprice, $l->pa_ht, '', $l->product_type, $l->date_start, $l->date_end, $l->array_options, $l->fk_unit);
									break;
								case 'commande':
									//$rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1=0.0,$txlocaltax2=0.0, $price_base_type='HT', $info_bits=0, $date_start='', $date_end='', $type=0, $fk_parent_line=0, $skip_update_total=0, $fk_fournprice=null, $pa_ht=0, $label='', $special_code=0, $array_options=0, $fk_unit=null
									$res = $object->updateline($l->id, $l->desc, $l->subprice, $l->qty, $l->remise_percent, $tva_tx, $l->localtax1_tx, $l->localtax2_tx, 'HT', $l->info_bits, $l->date_start, $l->date_end, $l->product_type, $l->fk_parent_line, 0, $l->fk_fournprice, $l->pa_ht, '', $l->special_code, $l->array_options, $l->fk_unit);
									break;
								case 'facture':
									//$rowid, $desc, $pu, $qty, $remise_percent, $date_start, $date_end, $txtva, $txlocaltax1=0, $txlocaltax2=0, $price_base_type='HT', $info_bits=0, $type= self::TYPE_STANDARD, $fk_parent_line=0, $skip_update_total=0, $fk_fournprice=null, $pa_ht=0, $label='', $special_code=0, $array_options=0, $situation_percent=0, $fk_unit = null
									$res = $object->updateline($l->id, $l->desc, $l->subprice, $l->qty, $l->remise_percent, $l->date_start, $l->date_end, $tva_tx, $l->localtax1_tx, $l->localtax2_tx, 'HT', $l->info_bits, $l->product_type, $l->fk_parent_line, 0, $l->fk_fournprice, $l->pa_ht, '', $l->special_code, $l->array_options, $l->situation_percent, $l->fk_unit);
									break;
							}
							
							if ($res <= 0) break;
						}
						
						if ($res <= 0)
						{
							$object->db->rollback();
							setEventMessage($langs->trans('grapefruit_error_tva_updateline'), 'errors');
							return -2;
						}
						else
						{
							$object->db->commit();
							setEventMessage($langs->trans('grapefruit_success_tva_updateline'));
							return 0;
						}
					}
					else
					{
						setEventMessage($langs->trans('grapefruit_error_tva', '('.implode(', ', $TTxAllowed).')'), 'warnings');
						return -1;
					}
				}
			}
		}
		
	}
}
