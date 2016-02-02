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
		/*$error = 0; // Error counter
		$myvalue = 'test'; // A result value

		print_r($parameters);
		echo "action: " . $action;
		print_r($object);

		if (in_array('somecontext', explode(':', $parameters['context'])))
		{
		  // do something only for the context 'somecontext'
		}

		if (! $error)
		{
			$this->results = array('myreturn' => $myvalue);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
			return -1;
		}*/
	}

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;
		
		//Context : frm creation propal
		if ($parameters['currentcontext'] === 'propalcard' && $action === 'create') 
		{
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
	
	function beforePDFCreation(&$parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$user,$langs,$db;
		
		if ($parameters['currentcontext'] === 'pdfgeneration' && !empty($conf->global->GRAPEFRUIT_SUPPLIER_CONTACT_SHIP_ADDRESS)) 
		{
			$base_object = $parameters['object'];
			if(isset($base_object) && $base_object->element == 'order_supplier')
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
				if(isset($base_object->linkedObjects['commande']))
				{
					// On récupère la donnée de la commande initiale
					// C'est un tableau basé sur des ID donc on boucle pour sortir le premier item
					$commande = reset($base_object->linkedObjects['commande']);
					$date_affiche = date("Y-m-d", $commande->date);
					$ref = $commande->ref;
					$usecommande=true;
				}
				if($usecontact)
				{
					//Recipient name
					// On peut utiliser le nom de la societe du contact
					$thirdparty=$base_object->thirdparty;
					if (!empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) $thirdparty = $base_object->contact;
					
					$carac_client_name= pdfBuildThirdpartyName($thirdparty, $parameters['outputlangs']);
					
					$thecontact = $base_object->contact;
					// SI un élément manquant ou qu'on veuille envoyé à la société du contact alors on change
					if((empty($thecontact->address) || empty($thecontact->zip) || empty($thecontact->town)) || (!empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)))
					{
						$contactSociete = new Societe($db);
						$contactSociete->fetch($thecontact->socid);
						$thecontact->address = $contactSociete->address;
						$thecontact->zip = $contactSociete->zip; 
						$thecontact->town = $contactSociete->town;
					}
					$carac_client=pdf_build_address($parameters['outputlangs'],$object->emetteur,$base_object->client,($usecontact?$base_object->contact:''),$usecontact,'target');
					
					$newcontent = $parameters['outputlangs']->trans('Delivery').' :'."\n".'<strong>'.$carac_client_name.'</strong>'."\n".$carac_client;
					if($usecommande)
					{
						$newcontent .= "\n".'Ref '.$parameters['outputlangs']->trans('Order').' : '.$ref;
						$newcontent .= "\n".$parameters['outputlangs']->trans('Date').' '.$parameters['outputlangs']->trans('Order').' : '.$date_affiche;
					}
					if(!empty($parameters['object']->note_public))
						$parameters['object']->note_public = dol_nl2br($newcontent."\n".$parameters['object']->note_public);
					else
						$parameters['object']->note_public = dol_nl2br($newcontent);
				}
			}
		}
	}
	
}