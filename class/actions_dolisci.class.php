<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_dolisci.class.php
 * \ingroup dolisci
 * \brief   DoliSci hooks for real estate projects, property products, and rental services
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';

/**
 * Class ActionsDoliSci
 */
class ActionsDoliSci extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message when Property Product or linked Service deletion failed
	 */
	protected $deleteFailedReason = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		dolisciSyncEnvironment();
	}

	/**
	 * Check whether the current Project card is viewed/created/edited within DoliSCI simplified environment
	 *
	 * @param  CommonObject|null $object Current object
	 * @return bool                      True if in DoliSCI simplified environment
	 */
	protected function isDoliSciProjectContext($object = null): bool
	{
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		return dolisciIsSimplifiedEnvironment();
	}

	/**
	 * Check whether the current Product card is a Property Product (type = 0) viewed/created/edited within DoliSCI simplified environment
	 *
	 * @param  CommonObject|null $object Current object
	 * @return bool                      True if in DoliSCI Property Product context
	 */
	protected function isDoliSciPropertyProductContext($object = null): bool
	{
		$prodType = GETPOSTISSET('type') ? GETPOSTINT('type') : ($object instanceof Product ? (int) $object->type : -1);
		if ($prodType !== Product::TYPE_PRODUCT) {
			return false;
		}
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		return dolisciIsSimplifiedEnvironment();
	}

	/**
	 * Check whether the current Product card is a Rental Service (type = 1) viewed/created/edited within DoliSCI simplified environment
	 *
	 * @param  CommonObject|null $object Current object
	 * @return bool                      True if in DoliSCI Rental Service context
	 */
	protected function isDoliSciRentalServiceContext($object = null): bool
	{
		$prodType = GETPOSTISSET('type') ? GETPOSTINT('type') : ($object instanceof Product ? (int) $object->type : -1);
		if ($prodType !== Product::TYPE_SERVICE) {
			return false;
		}
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		return dolisciIsSimplifiedEnvironment();
	}

	/**
	 * Check whether the current Thirdparty card is viewed/created/edited within DoliSCI simplified environment
	 *
	 * @param  CommonObject|null $object Current object
	 * @return bool                      True if in DoliSCI Thirdparty context
	 */
	protected function isDoliSciThirdpartyContext($object = null): bool
	{
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		return dolisciIsSimplifiedEnvironment();
	}

	/**
	 * Check whether the current Contact card is viewed/created/edited within DoliSCI simplified environment
	 *
	 * @param  CommonObject|null $object Current object
	 * @return bool                      True if in DoliSCI Contact context
	 */
	protected function isDoliSciContactContext($object = null): bool
	{
		require_once __DIR__ . '/../lib/dolisci.lib.php';
		return dolisciIsSimplifiedEnvironment();
	}

	/**
	 * Hide all extrafields for a given table_element when in DoliSCI simplified form context
	 * (Preserve keys in 'label' array so third-party hooks do not trigger Undefined array key warnings)
	 *
	 * @param  string $tableElement Table element ('societe' or 'socpeople')
	 * @return void
	 */
	protected function hideAllExtrafieldsForElement(string $tableElement): void
	{
		global $extrafields;

		if (isset($extrafields->attributes[$tableElement]['label']) && is_array($extrafields->attributes[$tableElement]['label'])) {
			foreach (array_keys($extrafields->attributes[$tableElement]['label']) as $efKey) {
				$extrafields->attributes[$tableElement]['enabled'][$efKey] = '0';
				$extrafields->attributes[$tableElement]['list'][$efKey]    = '0';
			}
		}
	}

	/**
	 * Filter extrafields on the Property Product card when in DoliSCI quick creation/edition/view context:
	 * keep only 'dolisci_fk_project' (Real estate project) visible, make it required, and filter options on DoliSCI projects.
	 *
	 * @return void
	 */
	protected function filterProductExtrafieldsForDoliSci(): void
	{
		global $extrafields;

		if (isset($extrafields->attributes['product']['label']) && is_array($extrafields->attributes['product']['label'])) {
			foreach (array_keys($extrafields->attributes['product']['label']) as $efKey) {
				$isDoliSciProjectField = ((string) $efKey === 'dolisci_fk_project');
				$extrafields->attributes['product']['enabled'][$efKey] = $isDoliSciProjectField ? '1' : '0';
				$extrafields->attributes['product']['list'][$efKey]    = $isDoliSciProjectField ? '1' : '0';
			}
			$extrafields->attributes['product']['required']['dolisci_fk_project'] = 1;
			$tagProjectId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
			if ($tagProjectId > 0) {
				$filter = 'projet:ref|title:rowid::(rowid:IN:SELECT fk_project FROM ' . MAIN_DB_PREFIX . 'categorie_project WHERE fk_categorie = ' . $tagProjectId . ')';
				$extrafields->attributes['product']['type']['dolisci_fk_project']             = 'sellist';
				$extrafields->attributes['product']['param']['dolisci_fk_project']['options'] = [$filter => null];
			}
		}
	}

	/**
	 * Filter extrafields on the Rental Service card when in DoliSCI simplified environment:
	 * keep only 'dolisci_service_nature', 'dolisci_fk_property_product', and 'dolisci_service_fk_project'.
	 *
	 * @return void
	 */
	protected function filterServiceExtrafieldsForDoliSci(): void
	{
		global $extrafields;

		$allowedKeys = ['dolisci_service_nature', 'dolisci_fk_property_product', 'dolisci_service_fk_project'];
		if (isset($extrafields->attributes['product']['label']) && is_array($extrafields->attributes['product']['label'])) {
			foreach (array_keys($extrafields->attributes['product']['label']) as $efKey) {
				$keep = in_array((string) $efKey, $allowedKeys, true);
				$extrafields->attributes['product']['enabled'][$efKey] = $keep ? '1' : '0';
				$extrafields->attributes['product']['list'][$efKey]    = $keep ? '1' : '0';
			}
		}
	}

	/**
	 * Filter extrafields on the Project card when in DoliSCI context:
	 * keep only 'dolisci_fk_socpeople' (Property address) visible and hide all other extrafields.
	 * (Preserve keys in 'label' array so third-party hooks like ReedCRM do not trigger Undefined array key warnings)
	 *
	 * @return void
	 */
	protected function filterProjectExtrafieldsForDoliSci(): void
	{
		global $extrafields;

		if (isset($extrafields->attributes['projet']['label']) && is_array($extrafields->attributes['projet']['label'])) {
			foreach (array_keys($extrafields->attributes['projet']['label']) as $efKey) {
				$isDoliSciField = ((string) $efKey === 'dolisci_fk_socpeople');
				$extrafields->attributes['projet']['enabled'][$efKey] = $isDoliSciField ? '1' : '0';
				$extrafields->attributes['projet']['list'][$efKey]    = $isDoliSciField ? '1' : '0';
			}
			$tagContactAddressId = getDolGlobalInt('DOLISCI_TAG_CONTACT_ADDRESS');
			if ($tagContactAddressId > 0) {
				$filter = 'socpeople:lastname|address|zip|town:rowid::(rowid:IN:SELECT fk_socpeople FROM ' . MAIN_DB_PREFIX . 'categorie_contact WHERE fk_categorie = ' . $tagContactAddressId . ')';
				$extrafields->attributes['projet']['param']['dolisci_fk_socpeople']['options'] = [$filter => null];
			}
		}
	}

	/**
	 * Prefix all DoliSCI extrafield labels (dolisci_*) with the DoliSCI module favicon/picto
	 * following Saturne / DoliMeet convention (img_picto with class 'pictoModule').
	 *
	 * @param  array|null $arrayfields Optional list $arrayfields reference to also prefix list column headers
	 * @return void
	 */
	protected function prefixDoliSciExtrafieldLabels(&$arrayfields = null): void
	{
		global $extrafields, $langs;

		$langs->load('dolisci@dolisci');
		$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
		$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

		foreach (['projet', 'product'] as $tableElement) {
			if (!empty($extrafields->attributes[$tableElement]['label']) && is_array($extrafields->attributes[$tableElement]['label'])) {
				foreach ($extrafields->attributes[$tableElement]['label'] as $efKey => $efLabel) {
					if (strpos((string) $efKey, 'dolisci_') === 0 && strpos((string) $efLabel, 'dolisci_color.png') === false) {
						$extrafields->attributes[$tableElement]['label'][$efKey] = $picto . $langs->transnoentities((string) $efLabel);
					}
				}
			}
		}

		if (is_array($arrayfields)) {
			foreach ($arrayfields as $fieldKey => &$fieldDef) {
				if (strpos((string) $fieldKey, 'ef.dolisci_') === 0 && isset($fieldDef['label']) && strpos((string) $fieldDef['label'], 'dolisci_color.png') === false) {
					$fieldDef['label'] = $picto . $langs->transnoentities((string) $fieldDef['label']);
				}
			}
			unset($fieldDef);
		}
	}

	/**
	 * Overloading the formObjectOptions function: inject marker row to simplify the Project form in DoliSCI
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager): int
	{
		global $extrafields, $form, $formcompany, $langs, $mysoc;

		$this->prefixDoliSciExtrafieldLabels();

		$contexts = explode(':', $parameters['context'] ?? '');
		if (in_array('projectcard', $contexts, true)) {
			if ($this->isDoliSciProjectContext($object)) {
				$this->filterProjectExtrafieldsForDoliSci();

				$inlineAddressHtml = '';
				if ($action === 'create' || $action === 'edit') {
					if (!is_object($form)) {
						$form = new Form($this->db);
					}
					if (!is_object($formcompany)) {
						require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
						$formcompany = new FormCompany($this->db);
					}
					require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
					require_once __DIR__ . '/../lib/dolisci.lib.php';

					$langs->loadLangs(['companies', 'categories', 'dolisci@dolisci']);
					$tagContactAddressId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_CONTACT_ADDRESS');
					$cateArboContact     = $form->select_all_categories(Categorie::TYPE_CONTACT, '', 'parent', 64, 0, 3);
					$addrSelectedCats    = GETPOSTISARRAY('dolisci_addr_contcats') ? GETPOST('dolisci_addr_contcats', 'array:alpha') : ($tagContactAddressId > 0 ? [(string) $tagContactAddressId] : []);
					$addrTagsHtml        = img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('dolisci_addr_contcats', $cateArboContact, $addrSelectedCats, 0, 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);

					$addrLastname    = GETPOST('dolisci_addr_lastname', 'alphanohtml');
					$addrFirstname   = GETPOST('dolisci_addr_firstname', 'alphanohtml');
					$addrSocid       = GETPOSTINT('dolisci_addr_socid');
					$addrAddress     = GETPOST('dolisci_addr_address', 'alphanohtml');
					$addrZip         = GETPOST('dolisci_addr_zipcode', 'alphanohtml');
					$addrTown        = GETPOST('dolisci_addr_town', 'alphanohtml');
					$addrCountryId   = GETPOSTISSET('dolisci_addr_country_id') ? GETPOSTINT('dolisci_addr_country_id') : ((int) ($mysoc->country_id ?: 1));
					$addrPhonePro    = GETPOST('dolisci_addr_phone_pro', 'alphanohtml');
					$addrPhoneMobile = GETPOST('dolisci_addr_phone_mobile', 'alphanohtml');
					$addrEmail       = GETPOST('dolisci_addr_email', 'alphanohtml');

					$hasInlineValues = ($addrLastname !== '' || $addrFirstname !== '' || $addrAddress !== '' || $addrZip !== '' || $addrTown !== '');
					$displayStyle    = $hasInlineValues ? 'display:block; margin-top:10px;' : 'display:none; margin-top:10px;';

					$nameLabel = $langs->trans('Lastname') . ' | ' . $langs->trans('Firstname');
					$addrLabel = $langs->trans('Address') . ' | ' . $langs->trans('Zip') . ' | ' . $langs->trans('Country');

					$inlineAddressHtml  = '<div id="dolisci-project-inline-address-card" class="dolisci-form-card" style="border-left-color:#7c3aed; max-width:820px; ' . $displayStyle . '">';
					$inlineAddressHtml .= '<div class="dolisci-form-card-header"><span><span class="fas fa-map-marker-alt paddingright" style="color:#7c3aed"></span>' . $langs->trans('DoliSciNewPropertyAddressSection') . '</span><span class="opacitymedium small">' . $langs->trans('DoliSciOrCreateAddressBelow') . '</span></div>';
					$inlineAddressHtml .= '<table class="noborder centpercent">';

					$inlineAddressHtml .= '<tr><td class="titlefieldcreate"><label for="dolisci_addr_lastname">' . $nameLabel . '</label></td><td><div class="dolisci-inline-fields">';
					$inlineAddressHtml .= '<input type="text" name="dolisci_addr_lastname" id="dolisci_addr_lastname" maxlength="80" value="' . dolPrintHTMLForAttribute($addrLastname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Lastname')) . '" style="flex: 1; min-width: 160px;">';
					$inlineAddressHtml .= '<input type="text" name="dolisci_addr_firstname" id="dolisci_addr_firstname" maxlength="80" value="' . dolPrintHTMLForAttribute($addrFirstname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Firstname')) . '" style="flex: 1; min-width: 140px;">';
					$inlineAddressHtml .= '</div></td></tr>';

					$inlineAddressHtml .= '<tr><td><label for="dolisci_addr_socid">' . $langs->trans('ThirdParty') . '</label></td><td>';
					$inlineAddressHtml .= img_picto('', 'company', 'class="pictofixedwidth"') . $form->select_company($addrSocid, 'dolisci_addr_socid', '', 'SelectThirdParty', 0, 0, [], 0, 'minwidth250 maxwidth400');
					$inlineAddressHtml .= '</td></tr>';

					$inlineAddressHtml .= '<tr><td><label for="dolisci_addr_address">' . $addrLabel . '</label></td><td><div class="dolisci-inline-fields">';
					$inlineAddressHtml .= '<input type="text" name="dolisci_addr_address" id="dolisci_addr_address" value="' . dolPrintHTMLForAttribute($addrAddress) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Address')) . '" style="flex: 2; min-width: 200px;">';
					$inlineAddressHtml .= $formcompany->select_ziptown($addrZip, 'dolisci_addr_zipcode', ['dolisci_addr_town', 'selectdolisci_addr_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Zip')) . '"', 'maxwidth100');
					$inlineAddressHtml .= $formcompany->select_ziptown($addrTown, 'dolisci_addr_town', ['dolisci_addr_zipcode', 'selectdolisci_addr_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Town')) . '"', 'maxwidth150');
					$inlineAddressHtml .= '<span>' . img_picto('', 'country', 'class="pictofixedwidth"') . $form->select_country($addrCountryId, 'dolisci_addr_country_id', '', 0, 'minwidth130 maxwidth180') . '</span>';
					$inlineAddressHtml .= '</div></td></tr>';

					$inlineAddressHtml .= '<tr><td><label for="dolisci_addr_phone_pro">' . $langs->trans('PhonePro') . '</label></td><td>';
					$inlineAddressHtml .= img_picto('', 'object_phoning', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_addr_phone_pro" id="dolisci_addr_phone_pro" class="minwidth200" value="' . dolPrintHTMLForAttribute($addrPhonePro) . '">';
					$inlineAddressHtml .= '</td></tr>';

					$inlineAddressHtml .= '<tr><td><label for="dolisci_addr_phone_mobile">' . $langs->trans('PhoneMobile') . '</label></td><td>';
					$inlineAddressHtml .= img_picto('', 'object_phoning_mobile', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_addr_phone_mobile" id="dolisci_addr_phone_mobile" class="minwidth200" value="' . dolPrintHTMLForAttribute($addrPhoneMobile) . '">';
					$inlineAddressHtml .= '</td></tr>';

					$inlineAddressHtml .= '<tr><td><label for="dolisci_addr_email">' . $langs->trans('EMail') . '</label></td><td>';
					$inlineAddressHtml .= img_picto('', 'object_email', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_addr_email" id="dolisci_addr_email" class="minwidth250" value="' . dolPrintHTMLForAttribute($addrEmail) . '">';
					$inlineAddressHtml .= '</td></tr>';

					$inlineAddressHtml .= '<tr><td>' . $langs->trans('Categories') . '</td><td>' . $addrTagsHtml . '</td></tr>';

					$inlineAddressHtml .= '</table>';
					$inlineAddressHtml .= '</div>';
				}

				$this->resprints = '<tr class="dolisci-simplified-project-form" style="display:none"><td colspan="2">'
					. '<style>'
					. 'table:has(.dolisci-simplified-project-form) tr:has(input[name="usage_task"]),'
					. 'table:has(.dolisci-simplified-project-form) tr:has(input[name="usage_opportunity"]),'
					. 'table:has(.dolisci-simplified-project-form) tr:has(#socid),'
					. 'table:has(.dolisci-simplified-project-form) tr.classuseopportunity,'
					. 'table:has(.dolisci-simplified-project-form) tr.classuseorganizeevent,'
					. 'table:has(.dolisci-simplified-project-form) tr:has(input[name="budget_amount"]),'
					. 'table:has(.dolisci-simplified-project-form) tr:has(#projectstart),'
					. 'table:has(.dolisci-simplified-project-form) tr:has(#public),'
					. 'table:has(.dolisci-simplified-project-form) tr:has(#typeofcontact)'
					. '{ display: none !important; }'
					. '</style>'
					. $inlineAddressHtml
					. '</td></tr>';
			} elseif (isset($extrafields->attributes['projet']['enabled']['dolisci_fk_socpeople'])) {
				$extrafields->attributes['projet']['enabled']['dolisci_fk_socpeople'] = '0';
				$extrafields->attributes['projet']['list']['dolisci_fk_socpeople']    = '0';
			}
		}
		if (in_array('productcard', $contexts, true)) {
			if ($this->isDoliSciPropertyProductContext($object)) {
				$this->filterProductExtrafieldsForDoliSci();
				if ($action === 'create' || $action === 'edit') {
					$this->resprints = '<tr class="dolisci-simplified-product-form" style="display:none"><td colspan="2">'
						. '<style>'
						. 'table:has(.dolisci-simplified-product-form) > tbody > tr:not(:has(input[name="ref"])):not(:has(input[name="label"])):not(:has([name="desc"])):not(:has(input[name="url"])):not(:has([name="options_dolisci_fk_project"])):not(:has(#categories)),'
						. 'form[name="formprod"]:has(.dolisci-simplified-product-form) tr:has(input[name="price_min"]),'
						. 'form[name="formprod"]:has(.dolisci-simplified-product-form) table:has([name="accountancy_code_sell"])'
						. '{ display: none !important; }'
						. '</style>'
						. '</td></tr>';
				}
			} elseif ($this->isDoliSciRentalServiceContext($object)) {
				$this->filterServiceExtrafieldsForDoliSci();
				if ($action === 'create' || $action === 'edit') {
					$this->resprints = '<tr class="dolisci-simplified-service-form" style="display:none"><td colspan="2">'
						. '<style>'
						. 'table:has(.dolisci-simplified-service-form) > tbody > tr:not(:has(input[name="ref"])):not(:has(input[name="label"])):not(:has(select[name="statut"])):not(:has([name="desc"])):not(:has([name="options_dolisci_service_nature"])):not(:has([name="options_dolisci_fk_property_product"])):not(:has([name="options_dolisci_service_fk_project"])):not(:has(#categories)),'
						. 'form[name="formprod"]:has(.dolisci-simplified-service-form) tr:has(input[name="price_min"]),'
						. 'form[name="formprod"]:has(.dolisci-simplified-service-form) table:has([name="accountancy_code_sell"]) tr:not(:has([name="accountancy_code_sell"]))'
						. '{ display: none !important; }'
						. '</style>'
						. '</td></tr>';
				}
			}
		}
		if (in_array('thirdpartycard', $contexts, true) && $this->isDoliSciThirdpartyContext($object)) {
			$this->hideAllExtrafieldsForElement('societe');
			if ($action === 'create' || $action === 'edit') {
				$this->resprints = '<tr class="dolisci-simplified-thirdparty-form" style="display:none"><td colspan="4">'
					. '<style>'
					. '#selectthirdpartytype, #selectthirdpartytype + br,'
					. 'table:has(.dolisci-simplified-thirdparty-form) > tbody > tr:not(:has(#name)):not(:has(#address)):not(:has(#zipcode)):not(:has(#selectcountry_id)):not(:has(#idprof1)):not(:has(#custcats)),'
					. 'table:has(.dolisci-simplified-thirdparty-form) tr:has(#idprof1) > td:nth-child(n+3)'
					. '{ display: none !important; }'
					. '</style>'
					. '</td></tr>';
			}
		}
		if (in_array('contactcard', $contexts, true) && $this->isDoliSciContactContext($object)) {
			$this->hideAllExtrafieldsForElement('socpeople');
			if ($action === 'create' || $action === 'edit') {
				$this->resprints = '<tr class="dolisci-simplified-contact-form" style="display:none"><td colspan="4">'
					. '<style>'
					. 'table:has(.dolisci-simplified-contact-form) > tbody > tr:not(:has(#lastname)):not(:has(#firstname)):not(:has(#socid)):not(:has(#address)):not(:has(#zipcode)):not(:has(#selectcountry_id)):not(:has(#phone_pro)):not(:has(#phone_mobile)):not(:has(#email)):not(:has(#contcats)),'
					. 'table:has(.dolisci-simplified-contact-form) tr:has(#phone_pro) > td:nth-child(n+3),'
					. 'table:has(.dolisci-simplified-contact-form) tr:has(#phone_mobile) > td:nth-child(n+3),'
					. 'table:has(.dolisci-simplified-contact-form) tr:has(#email) > td:nth-child(n+3),'
					. 'form[name="formsoc"]:has(.dolisci-simplified-contact-form) hr,'
					. 'form[name="formsoc"]:has(.dolisci-simplified-contact-form) table:has(.comboperso),'
					. 'form[name="formsoc"]:has(.dolisci-simplified-contact-form) table:has(#birthday)'
					. '{ display: none !important; }'
					. '</style>'
					. '</td></tr>';
			}
		}
		return 0;
	}

	/**
	 * Overloading the tabContentCreateThirdparty function: render the visual 3-section creation form
	 * (Nouveau Tiers / Locataire + Contact / Locataire + Contact / Garant) in DoliSCI context
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process (Societe)
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0 or 1 to replace standard creation table
	 */
	public function tabContentCreateThirdparty($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $langs, $user, $mysoc, $form, $formcompany, $modCodeClient;

		$contexts = explode(':', $parameters['context'] ?? '');
		if (!in_array('thirdpartycard', $contexts, true) || !$this->isDoliSciThirdpartyContext($object)) {
			return 0;
		}

		if (!is_object($form)) {
			$form = new Form($this->db);
		}
		if (!is_object($formcompany)) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
			$formcompany = new FormCompany($this->db);
		}
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

		$langs->loadLangs(['companies', 'categories', 'dolisci@dolisci']);

		$tmpCode = $object->code_client ?? '';
		if (empty($tmpCode) && is_object($modCodeClient) && !empty($modCodeClient->code_auto)) {
			$tmpCode = $modCodeClient->getNextValue($object, 0);
		}

		$tpCountryId = GETPOSTISSET('country_id') ? GETPOSTINT('country_id') : ((int) ($object->country_id ?: $mysoc->country_id));
		$tpCountryCode = $object->country_code ?: $mysoc->country_code ?: 'FR';

		$tagCustomerTenantId   = getDolGlobalInt('DOLISCI_TAG_CUSTOMER_TENANT');
		$tagContactTenantId    = getDolGlobalInt('DOLISCI_TAG_CONTACT_TENANT');
		$tagContactGuarantorId = getDolGlobalInt('DOLISCI_TAG_CONTACT_GUARANTOR');

		$cateArboCustomer = $form->select_all_categories(Categorie::TYPE_CUSTOMER, '', 'parent', 64, 0, 3);
		$custSelectedCats = GETPOSTISARRAY('custcats') ? GETPOST('custcats', 'array:alpha') : ($tagCustomerTenantId > 0 ? [(string) $tagCustomerTenantId] : []);
		$custTagsHtml     = img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('custcats', $cateArboCustomer, $custSelectedCats, 0, 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);

		$cateArboContact       = $form->select_all_categories(Categorie::TYPE_CONTACT, '', 'parent', 64, 0, 3);
		$tenantSelectedCats    = GETPOSTISARRAY('dolisci_tenant_contcats') ? GETPOST('dolisci_tenant_contcats', 'array:alpha') : ($tagContactTenantId > 0 ? [(string) $tagContactTenantId] : []);
		$tenantTagsHtml        = img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('dolisci_tenant_contcats', $cateArboContact, $tenantSelectedCats, 0, 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
		$guarantorSelectedCats = GETPOSTISARRAY('dolisci_guarantor_contcats') ? GETPOST('dolisci_guarantor_contcats', 'array:alpha') : ($tagContactGuarantorId > 0 ? [(string) $tagContactGuarantorId] : []);
		$guarantorTagsHtml     = img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('dolisci_guarantor_contcats', $cateArboContact, $guarantorSelectedCats, 0, 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);

		$tenantLastname    = GETPOST('dolisci_tenant_lastname', 'alphanohtml');
		$tenantFirstname   = GETPOST('dolisci_tenant_firstname', 'alphanohtml');
		$tenantAddress     = GETPOST('dolisci_tenant_address', 'alphanohtml');
		$tenantZip         = GETPOST('dolisci_tenant_zipcode', 'alphanohtml');
		$tenantTown        = GETPOST('dolisci_tenant_town', 'alphanohtml');
		$tenantCountryId   = GETPOSTISSET('dolisci_tenant_country_id') ? GETPOSTINT('dolisci_tenant_country_id') : $tpCountryId;
		$tenantPhonePro    = GETPOST('dolisci_tenant_phone_pro', 'alphanohtml');
		$tenantPhoneMobile = GETPOST('dolisci_tenant_phone_mobile', 'alphanohtml');
		$tenantEmail       = GETPOST('dolisci_tenant_email', 'alphanohtml');

		$guarantorLastname    = GETPOST('dolisci_guarantor_lastname', 'alphanohtml');
		$guarantorFirstname   = GETPOST('dolisci_guarantor_firstname', 'alphanohtml');
		$guarantorAddress     = GETPOST('dolisci_guarantor_address', 'alphanohtml');
		$guarantorZip         = GETPOST('dolisci_guarantor_zipcode', 'alphanohtml');
		$guarantorTown        = GETPOST('dolisci_guarantor_town', 'alphanohtml');
		$guarantorCountryId   = GETPOSTISSET('dolisci_guarantor_country_id') ? GETPOSTINT('dolisci_guarantor_country_id') : $tpCountryId;
		$guarantorPhonePro    = GETPOST('dolisci_guarantor_phone_pro', 'alphanohtml');
		$guarantorPhoneMobile = GETPOST('dolisci_guarantor_phone_mobile', 'alphanohtml');
		$guarantorEmail       = GETPOST('dolisci_guarantor_email', 'alphanohtml');

		$addrLabel   = $langs->trans('Address') . ' | ' . $langs->trans('Zip') . ' | ' . $langs->trans('Country');
		$nameLabel   = $langs->trans('Lastname') . ' | ' . $langs->trans('Firstname');
		$profIdLabel = $langs->transcountry('ProfId1', (string) $tpCountryCode);

		$html  = '<input type="hidden" name="dolisci_thirdparty_create_form" value="1">';
		$html .= '<input type="hidden" name="status" value="1">';
		$html .= '<input type="hidden" name="customer" value="1">';
		$html .= '<input type="hidden" name="prospect" value="0">';
		$html .= '<input type="hidden" name="supplier" value="0">';
		$html .= '<input type="hidden" name="assujtva_value" value="1">';
		$html .= '<input type="hidden" name="customer_code" value="' . dolPrintHTMLForAttribute((string) $tmpCode) . '">';
		$html .= '<input type="hidden" name="commercial[]" value="' . ((int) $user->id) . '">';

		$html .= '<div class="dolisci-tp-create-wrapper">';

		// 1. Section: Nouveau Tiers / Locataire
		$html .= '<div class="dolisci-form-card">';
		$html .= '<div class="dolisci-form-card-header"><span><span class="fas fa-user paddingright" style="color:#2563eb"></span>' . $langs->trans('DoliSciNewThirdpartyTenantSection') . '</span></div>';
		$html .= '<table class="noborder centpercent">';
		$html .= '<tr><td class="titlefieldcreate fieldrequired"><label for="name">' . $langs->trans('ThirdPartyName') . '</label></td><td>';
		$html .= '<input type="text" class="minwidth300" maxlength="128" name="name" id="name" value="' . dolPrintHTMLForAttribute((string) $object->name) . '" autofocus="autofocus" spellcheck="false">';
		$html .= '</td></tr>';

		$html .= '<tr><td><label for="address">' . $addrLabel . '</label></td><td><div class="dolisci-inline-fields">';
		$html .= '<input type="text" name="address" id="address" value="' . dolPrintHTMLForAttribute((string) $object->address) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Address')) . '" style="flex: 2; min-width: 220px;">';
		$html .= $formcompany->select_ziptown((string) $object->zip, 'zipcode', ['town', 'selectcountry_id', 'state_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Zip')) . '"', 'maxwidth100');
		$html .= $formcompany->select_ziptown((string) $object->town, 'town', ['zipcode', 'selectcountry_id', 'state_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Town')) . '"', 'maxwidth150');
		$html .= '<span>' . img_picto('', 'country', 'class="pictofixedwidth"') . $form->select_country($tpCountryId, 'country_id', '', 0, 'minwidth150 maxwidth200') . '</span>';
		$html .= '</div></td></tr>';

		$html .= '<tr><td><label for="idprof1">' . $profIdLabel . '</label></td><td>';
		$html .= $formcompany->get_input_id_prof(1, 'idprof1', (string) $object->idprof1, (string) $tpCountryCode, 'minwidth200');
		$html .= '</td></tr>';

		$html .= '<tr><td>' . $langs->trans('Categories') . '</td><td>' . $custTagsHtml . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		// 2 & 3. Grid for Contact / Locataire and Contact / Garant
		$html .= '<div class="dolisci-tp-contacts-grid">';

		// 2. Section: Contact / Locataire
		$html .= '<div class="dolisci-form-card">';
		$html .= '<div class="dolisci-form-card-header"><span><span class="fas fa-user paddingright" style="color:#2563eb"></span>' . $langs->trans('DoliSciContactTenantSection') . '</span></div>';
		$html .= '<table class="noborder centpercent">';
		$html .= '<tr><td class="titlefieldcreate"><label for="dolisci_tenant_lastname">' . $nameLabel . '</label></td><td><div class="dolisci-inline-fields">';
		$html .= '<input type="text" name="dolisci_tenant_lastname" id="dolisci_tenant_lastname" maxlength="80" value="' . dolPrintHTMLForAttribute($tenantLastname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Lastname')) . '" style="flex: 1; min-width: 140px;">';
		$html .= '<input type="text" name="dolisci_tenant_firstname" id="dolisci_tenant_firstname" maxlength="80" value="' . dolPrintHTMLForAttribute($tenantFirstname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Firstname')) . '" style="flex: 1; min-width: 140px;">';
		$html .= '</div></td></tr>';

		$html .= '<tr><td><label for="dolisci_tenant_address">' . $addrLabel . '</label></td><td><div class="dolisci-inline-fields">';
		$html .= '<input type="text" name="dolisci_tenant_address" id="dolisci_tenant_address" value="' . dolPrintHTMLForAttribute($tenantAddress) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Address')) . '" style="flex: 2; min-width: 160px;">';
		$html .= $formcompany->select_ziptown($tenantZip, 'dolisci_tenant_zipcode', ['dolisci_tenant_town', 'selectdolisci_tenant_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Zip')) . '"', 'maxwidth100');
		$html .= $formcompany->select_ziptown($tenantTown, 'dolisci_tenant_town', ['dolisci_tenant_zipcode', 'selectdolisci_tenant_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Town')) . '"', 'maxwidth150');
		$html .= '<span>' . img_picto('', 'country', 'class="pictofixedwidth"') . $form->select_country($tenantCountryId, 'dolisci_tenant_country_id', '', 0, 'minwidth130 maxwidth180') . '</span>';
		$html .= '</div></td></tr>';

		$html .= '<tr><td><label for="dolisci_tenant_phone_pro">' . $langs->trans('PhonePro') . '</label></td><td>';
		$html .= img_picto('', 'object_phoning', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_tenant_phone_pro" id="dolisci_tenant_phone_pro" class="minwidth200" value="' . dolPrintHTMLForAttribute($tenantPhonePro) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td><label for="dolisci_tenant_phone_mobile">' . $langs->trans('PhoneMobile') . '</label></td><td>';
		$html .= img_picto('', 'object_phoning_mobile', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_tenant_phone_mobile" id="dolisci_tenant_phone_mobile" class="minwidth200" value="' . dolPrintHTMLForAttribute($tenantPhoneMobile) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td><label for="dolisci_tenant_email">' . $langs->trans('EMail') . '</label></td><td>';
		$html .= img_picto('', 'object_email', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_tenant_email" id="dolisci_tenant_email" class="minwidth250" value="' . dolPrintHTMLForAttribute($tenantEmail) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td>' . $langs->trans('Categories') . '</td><td>' . $tenantTagsHtml . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		// 3. Section: Contact / Garant
		$html .= '<div class="dolisci-form-card card-guarantor">';
		$html .= '<div class="dolisci-form-card-header"><span><span class="fas fa-user-shield paddingright" style="color:#0891b2"></span>' . $langs->trans('DoliSciContactGuarantorSection') . '</span><span class="opacitymedium small">' . $langs->trans('DoliSciOptionalSection') . '</span></div>';
		$html .= '<table class="noborder centpercent">';
		$html .= '<tr><td class="titlefieldcreate"><label for="dolisci_guarantor_lastname">' . $nameLabel . '</label></td><td><div class="dolisci-inline-fields">';
		$html .= '<input type="text" name="dolisci_guarantor_lastname" id="dolisci_guarantor_lastname" maxlength="80" value="' . dolPrintHTMLForAttribute($guarantorLastname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Lastname')) . '" style="flex: 1; min-width: 140px;">';
		$html .= '<input type="text" name="dolisci_guarantor_firstname" id="dolisci_guarantor_firstname" maxlength="80" value="' . dolPrintHTMLForAttribute($guarantorFirstname) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Firstname')) . '" style="flex: 1; min-width: 140px;">';
		$html .= '</div></td></tr>';

		$html .= '<tr><td><label for="dolisci_guarantor_address">' . $addrLabel . '</label></td><td><div class="dolisci-inline-fields">';
		$html .= '<input type="text" name="dolisci_guarantor_address" id="dolisci_guarantor_address" value="' . dolPrintHTMLForAttribute($guarantorAddress) . '" placeholder="' . dolPrintHTMLForAttribute($langs->trans('Address')) . '" style="flex: 2; min-width: 160px;">';
		$html .= $formcompany->select_ziptown($guarantorZip, 'dolisci_guarantor_zipcode', ['dolisci_guarantor_town', 'selectdolisci_guarantor_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Zip')) . '"', 'maxwidth100');
		$html .= $formcompany->select_ziptown($guarantorTown, 'dolisci_guarantor_town', ['dolisci_guarantor_zipcode', 'selectdolisci_guarantor_country_id'], 0, 0, 'placeholder="' . dolPrintHTMLForAttribute($langs->trans('Town')) . '"', 'maxwidth150');
		$html .= '<span>' . img_picto('', 'country', 'class="pictofixedwidth"') . $form->select_country($guarantorCountryId, 'dolisci_guarantor_country_id', '', 0, 'minwidth130 maxwidth180') . '</span>';
		$html .= '</div></td></tr>';

		$html .= '<tr><td><label for="dolisci_guarantor_phone_pro">' . $langs->trans('PhonePro') . '</label></td><td>';
		$html .= img_picto('', 'object_phoning', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_guarantor_phone_pro" id="dolisci_guarantor_phone_pro" class="minwidth200" value="' . dolPrintHTMLForAttribute($guarantorPhonePro) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td><label for="dolisci_guarantor_phone_mobile">' . $langs->trans('PhoneMobile') . '</label></td><td>';
		$html .= img_picto('', 'object_phoning_mobile', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_guarantor_phone_mobile" id="dolisci_guarantor_phone_mobile" class="minwidth200" value="' . dolPrintHTMLForAttribute($guarantorPhoneMobile) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td><label for="dolisci_guarantor_email">' . $langs->trans('EMail') . '</label></td><td>';
		$html .= img_picto('', 'object_email', 'class="pictofixedwidth"') . '<input type="text" name="dolisci_guarantor_email" id="dolisci_guarantor_email" class="minwidth250" value="' . dolPrintHTMLForAttribute($guarantorEmail) . '">';
		$html .= '</td></tr>';

		$html .= '<tr><td>' . $langs->trans('Categories') . '</td><td>' . $guarantorTagsHtml . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		$html .= '</div>'; // .dolisci-tp-contacts-grid
		$html .= '</div>'; // .dolisci-tp-create-wrapper

		print $html;
		return 1;
	}

	/**
	 * Overloading the showOptionals function: ensure Product::$type matches GETPOST('type') on creation form
	 * and prefill DoliSCI project extrafields
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function showOptionals($parameters, &$object, &$action, $hookmanager): int
	{
		global $extrafields;

		$this->prefixDoliSciExtrafieldLabels();

		$contexts = explode(':', $parameters['context'] ?? '');
		if (in_array('productcard', $contexts, true) && $object instanceof Product) {
			if (GETPOSTISSET('type')) {
				$object->type = GETPOSTINT('type');
			}
			if ($action === 'create') {
				if (GETPOSTISSET('options_dolisci_service_nature')) {
					$object->array_options['options_dolisci_service_nature'] = GETPOST('options_dolisci_service_nature', 'alphanohtml');
				}
				if (GETPOSTISSET('options_dolisci_fk_project')) {
					$object->array_options['options_dolisci_fk_project'] = GETPOSTINT('options_dolisci_fk_project');
				}
				if (GETPOSTISSET('options_dolisci_service_fk_project')) {
					$object->array_options['options_dolisci_service_fk_project'] = GETPOSTINT('options_dolisci_service_fk_project');
				}
			}
			if ($this->isDoliSciPropertyProductContext($object)) {
				$this->filterProductExtrafieldsForDoliSci();
			} elseif ($this->isDoliSciRentalServiceContext($object)) {
				$this->filterServiceExtrafieldsForDoliSci();
			}
		}
		if (in_array('projectcard', $contexts, true) && $object instanceof Project) {
			if ($this->isDoliSciProjectContext($object)) {
				$this->filterProjectExtrafieldsForDoliSci();
			} elseif (isset($extrafields->attributes['projet']['enabled']['dolisci_fk_socpeople'])) {
				$extrafields->attributes['projet']['enabled']['dolisci_fk_socpeople'] = '0';
				$extrafields->attributes['projet']['list']['dolisci_fk_socpeople']    = '0';
			}
		}
		if (in_array('thirdpartycard', $contexts, true) && $this->isDoliSciThirdpartyContext($object)) {
			$this->hideAllExtrafieldsForElement('societe');
		}
		if (in_array('contactcard', $contexts, true) && $this->isDoliSciContactContext($object)) {
			$this->hideAllExtrafieldsForElement('socpeople');
		}
		return 0;
	}

	/**
	 * Overloading the tabContentViewProduct function: render simplified Product (Property) and Service (Rent/Charges/Deposit)
	 * view cards when inside the DoliSCI simplified environment.
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process (Product)
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0 or 1 to replace standard view table
	 */
	public function tabContentViewProduct($parameters, &$object, &$action, $hookmanager): int
	{
		global $db, $extrafields, $form, $langs, $user;

		$contexts = explode(':', $parameters['context'] ?? '');
		if (!in_array('productcard', $contexts, true) || !($object instanceof Product)) {
			return 0;
		}
		if (!$this->isDoliSciPropertyProductContext($object) && !$this->isDoliSciRentalServiceContext($object)) {
			return 0;
		}

		if (!is_object($form)) {
			$form = new Form($this->db);
		}
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
		require_once __DIR__ . '/../lib/dolisci.lib.php';

		$langs->loadLangs(['products', 'projects', 'categories', 'dolisci@dolisci']);
		if (empty($object->array_options)) {
			$object->fetch_optionals();
		}

		if ((int) $object->type === Product::TYPE_PRODUCT) {
			$this->filterProductExtrafieldsForDoliSci();
			$_SESSION['pageforbacktolist']['product'] = dol_buildpath('/dolisci/product_list.php', 1);
		} else {
			$this->filterServiceExtrafieldsForDoliSci();
			$_SESSION['pageforbacktolist']['product'] = dol_buildpath('/dolisci/service_list.php', 1);
		}
		$this->prefixDoliSciExtrafieldLabels();

		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';

		// Extrafields (with native inline edit support via extrafields_view.tpl.php)
		$cols = 2;
		include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

		if ((int) $object->type === Product::TYPE_SERVICE) {
			// Accountancy sell code for rental service (706100 / 708800 / 165000)
			print '<tr><td class="titlefield">' . $langs->trans('ProductAccountancySellCode') . '</td><td>';
			print '<code>' . dolPrintHTML((string) $object->accountancy_code_sell) . '</code>';
			print '</td></tr>';
		}

		// Description
		print '<tr><td class="titlefield tdtop">' . $langs->trans('Description') . '</td>';
		print '<td class="wordbreakimp wrapimp"><div class="longmessagecut">' . (dol_textishtml($object->description) ? $object->description : dol_nl2br($object->description, 1, true)) . '</div></td></tr>';

		// Public URL (if property product and filled)
		if ((int) $object->type === Product::TYPE_PRODUCT && !empty($object->url)) {
			print '<tr><td>' . $langs->trans('PublicUrl') . '</td><td>' . dol_print_url($object->url, '_blank', 128) . '</td></tr>';
		}

		print '</table>';
		print '</div>';

		print '<div class="fichehalfright">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';

		// Selling Price HT & VAT Rate
		print '<tr><td class="titlefield">' . $langs->trans('SellingPriceHT') . '</td><td>' . price($object->price, 1, $langs, 1, -1, -1, 'auto') . '</td></tr>';
		print '<tr><td>' . $langs->trans('VATRate') . '</td><td>' . vatrate($object->tva_tx, true) . '</td></tr>';

		// Categories / Tags
		if (isModEnabled('category')) {
			print '<tr><td class="valignmiddle">' . $langs->trans('Categories') . '</td><td>';
			print $form->showCategories($object->id, Categorie::TYPE_PRODUCT, 1);
			print '</td></tr>';
		}

		print '</table>';
		print '</div>';
		print '</div>';
		print '<div class="clearboth"></div>';

		return 1;
	}

	/**
	 * Overloading the tabContentViewThirdparty function: render simplified Thirdparty / Tenant view card
	 * when inside the DoliSCI simplified environment.
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process (Societe)
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0 or 1 to replace standard view table
	 */
	public function tabContentViewThirdparty($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $db, $form, $langs, $mysoc, $user;

		$contexts = explode(':', $parameters['context'] ?? '');
		if (!in_array('thirdpartycard', $contexts, true) || !($object instanceof Societe) || !$this->isDoliSciThirdpartyContext($object)) {
			return 0;
		}

		if (!is_object($form)) {
			$form = new Form($this->db);
		}
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

		$langs->loadLangs(['companies', 'categories', 'dolisci@dolisci']);
		$_SESSION['pageforbacktolist']['societe'] = dol_buildpath('/dolisci/thirdparty_list.php', 1);

		$countryCode = $object->country_code ?: ($mysoc->country_code ?: 'FR');
		$profId1Label = $langs->transcountry('ProfId1', (string) $countryCode);

		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';

		// Nature of Thirdparty
		print '<tr><td class="titlefield">' . $langs->trans('NatureOfThirdParty') . '</td><td>' . $object->getTypeUrl(1) . '</td></tr>';

		// ProfId1 (SIREN)
		if ($profId1Label !== '-') {
			print '<tr><td>' . dolPrintHTML($profId1Label) . '</td><td>';
			print dol_print_profids($object->idprof1, 'ProfId1', $countryCode, 1);
			print '</td></tr>';
		}

		print '</table>';
		print '</div>';

		print '<div class="fichehalfright">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';

		// Categories / Tags
		if (isModEnabled('category') && $user->hasRight('categorie', 'lire')) {
			print '<tr><td class="titlefield">' . $langs->trans('CustomersCategoriesShort') . '</td><td>';
			print $form->showCategories($object->id, Categorie::TYPE_CUSTOMER, 1);
			print '</td></tr>';
		}

		// Sales representatives
		include DOL_DOCUMENT_ROOT . '/societe/tpl/linesalesrepresentative.tpl.php';

		print '</table>';
		print '</div>';
		print '</div>';
		print '<div class="clearboth"></div>';

		return 1;
	}

	/**
	 * Overloading the printFieldListWhere function: fallback filter on Product / Service lists when DoliSCI tags are not yet initialized
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager): int
	{
		$contexts = explode(':', $parameters['context'] ?? '');
		if (in_array('productservicelist', $contexts, true)) {
			$leftmenu = GETPOST('leftmenu', 'alphanohtml');
			if (strpos($leftmenu, 'dolisci_products') === 0 && getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY') <= 0) {
				$this->resprints = " AND (ef.dolisci_fk_project > 0 OR (ef.dolisci_property_nature IS NOT NULL AND ef.dolisci_property_nature <> ''))";
			} elseif (strpos($leftmenu, 'dolisci_services') === 0 && getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT') <= 0 && getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES') <= 0 && getDolGlobalInt('DOLISCI_TAG_SERVICE_DEPOSIT') <= 0) {
				$this->resprints = " AND (ef.dolisci_service_fk_project > 0 OR ef.dolisci_fk_property_product > 0 OR (ef.dolisci_service_nature IS NOT NULL AND ef.dolisci_service_nature <> ''))";
			}
		}
		return 0;
	}

	/**
	 * Overloading the completeTabsHead function:
	 * - Add the DoliSCI "Loyers & Charges" tab with service counter on Property Products (type == 0)
	 * - Hide Tasks and TimeSpent tabs on SCI real estate projects
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0 or 1 to replace $head
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager): int
	{
		global $langs;

		$mode      = $parameters['mode'] ?? '';
		$filter    = $parameters['filterorigmodule'] ?? ($parameters['filter'] ?? '');
		$targetObj = ($object instanceof CommonObject) ? $object : ($parameters['object'] ?? null);

		// 1. Add DoliSCI "Loyers, Charges & Caution" tab with counter on Property Products (type == 0) and DoliSCI Rental Services (type == 1)
		if ($mode === 'add' && ($filter === 'external' || $filter === '') && $targetObj instanceof Product && !empty($targetObj->id)) {
			require_once __DIR__ . '/../lib/dolisci.lib.php';
			$isProp = ((int) $targetObj->type === Product::TYPE_PRODUCT && dolisciIsPropertyProduct($this->db, $targetObj));
			$isServ = ((int) $targetObj->type === Product::TYPE_SERVICE && dolisciIsRentalService($this->db, $targetObj));
			if ($isProp || $isServ) {
				$head = is_array($parameters['head'] ?? null) ? $parameters['head'] : [];
				foreach ($head as $tab) {
					if (($tab[2] ?? '') === 'dolisci_services') {
						return 0;
					}
				}
				$langs->load('dolisci@dolisci');
				$propProduct = dolisciGetPropertyFromService($this->db, $targetObj);
				if ($isServ) {
					$childServices = dolisciGetServiceChildServices($this->db, $targetObj, $propProduct);
					$badgeCount    = count($childServices);
				} else {
					$rentalData = $propProduct ? dolisciGetPropertyRentalServices($this->db, $propProduct) : ['count' => 0, 'rentCount' => 0];
					$badgeCount = (int) ($rentalData['rentCount'] ?? 0);
				}
				$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
				$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');
				$tabUrl    = dol_buildpath('/dolisci/product_services.php', 1) . '?id=' . ((int) $targetObj->id);
				$tabLabel  = $picto . $langs->trans('DoliSciTabRentalServices') . '<span class="badge marginleftonlyshort">' . $badgeCount . '</span>';

				$this->results = [[$tabUrl, $tabLabel, 'dolisci_services']];
				return 0;
			}
		}

		// 2. Add DoliSCI "Biens & Locations [N]" tab with property counter on Real Estate Projects
		if ($mode === 'add' && ($filter === 'external' || $filter === '') && $targetObj instanceof Project && !empty($targetObj->id)) {
			require_once __DIR__ . '/../lib/dolisci.lib.php';
			if (dolisciIsRealEstateProject($this->db, (int) $targetObj->id)) {
				$head = is_array($parameters['head'] ?? null) ? $parameters['head'] : [];
				foreach ($head as $tab) {
					if (($tab[2] ?? '') === 'dolisci_project_properties') {
						return 0;
					}
				}
				$langs->load('dolisci@dolisci');
				$propCount = dolisciGetProjectPropertiesCount($this->db, $targetObj);
				$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
				$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');
				$tabUrl    = dol_buildpath('/dolisci/project_properties.php', 1) . '?id=' . ((int) $targetObj->id);
				$tabLabel  = $picto . $langs->trans('DoliSciTabProjectProperties') . '<span class="badge marginleftonlyshort">' . $propCount . '</span>';

				$this->results = [[$tabUrl, $tabLabel, 'dolisci_project_properties']];
				return 0;
			}
		}

		// 3. Hide Tasks and TimeSpent tabs on SCI real estate projects
		if (!getDolGlobalInt('DOLISCI_HIDE_TASKS_ON_SCI_PROJECTS', 1)) {
			return 0;
		}
		if ($mode !== 'remove' || !($targetObj instanceof Project) || empty($targetObj->id)) {
			return 0;
		}

		require_once __DIR__ . '/../lib/dolisci.lib.php';
		if (!dolisciIsRealEstateProject($this->db, (int) $targetObj->id)) {
			return 0;
		}

		if (!empty($parameters['head']) && is_array($parameters['head'])) {
			foreach ($parameters['head'] as $key => $tab) {
				if (isset($tab[2]) && in_array($tab[2], ['tasks', 'timespent', 'ticket'], true)) {
					unset($parameters['head'][$key]);
				}
			}
			$this->results = $parameters['head'];
			return 1;
		}

		return 0;
	}

	/**
	 * Overloading the formDolBanner function: inject CSS marker on Project, Thirdparty, and Contact cards in view mode
	 * when inside the DoliSCI simplified environment (hide ReedCRM opportunity/call widgets, Digirisk task rows, etc.)
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function formDolBanner($parameters, &$object, &$action, $hookmanager): int
	{
		global $langs;

		$contexts = explode(':', $parameters['context'] ?? '');
		require_once __DIR__ . '/../lib/dolisci.lib.php';

		// 1. Project card in view mode (within DoliSCI simplified environment or on Biens & Locations tab)
		if ((in_array('projectcard', $contexts, true) || in_array('dolisciprojectproperties', $contexts, true)) && $object instanceof Project && !empty($object->id)) {
			if ($this->isDoliSciProjectContext($object) || in_array('dolisciprojectproperties', $contexts, true)) {
				$addrScript = '';
				if ($action !== 'edit_extras') {
					if (empty($object->array_options)) {
						$object->fetch_optionals();
					}
					$addrId = (int) ($object->array_options['options_dolisci_fk_socpeople'] ?? 0);
					if ($addrId > 0) {
						require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
						$contact = new Contact($this->db);
						if ($contact->fetch($addrId) > 0) {
							$parts    = array_filter([
								trim($contact->getFullName($langs)),
								trim((string) $contact->address),
								trim(trim((string) $contact->zip) . ' ' . trim((string) $contact->town)),
							]);
							$addrHtml   = $contact->getNomUrl(1) . (!empty($parts) ? ' - ' . dolPrintHTML(implode(' ', $parts)) : '');
							$addrScript = '<script>document.addEventListener("DOMContentLoaded",function(){var cell=document.querySelector(".valuefield.project_extras_dolisci_fk_socpeople");if(cell){cell.innerHTML=' . json_encode($addrHtml) . ';}});</script>';
						}
					}
				}

				$this->resprints = '<div class="dolisci-simplified-project-view" style="display:none"></div>'
					. '<style>'
					. '.rcrm-co-org-row,'
					. '.reedcrm-header-company-wrapper,'
					. '.reedcrm-header-origin-master,'
					. '.reedcrm-header-salesrep-master,'
					. '.reedcrm-header-contact-master,'
					. '.reedcrm-header-relaunch-master,'
					. '.reedcrm-card-header-blocks,'
					. '.reedcrm-header-stats,'
					. '.reedcrm-add-to-call-list-wrapper,'
					. '#reedcrm-closure-widget,'
					. '.reedcrm-lifecycle-timeline,'
					. '#reedcrm-relaunch-row-hidden,'
					. '.arearef:has(.dolisci-simplified-project-view) ~ .fichecenter .fichehalfleft table.tableforfield tr:not(:has([class*="dolisci_fk_socpeople"])),'
					. '.arearef:has(.dolisci-simplified-project-view) ~ .fichecenter .fichehalfright table.tableforfield tr:nth-child(n+4)'
					. '{ display: none !important; }'
					. '</style>'
					. $addrScript;
			}
		}

		// 2. Thirdparty card in view mode (within DoliSCI simplified environment)
		if (in_array('thirdpartycard', $contexts, true) && $object instanceof Societe && $this->isDoliSciThirdpartyContext($object)) {
			$this->resprints = '<div class="dolisci-simplified-thirdparty-view" style="display:none"></div>'
				. '<style>'
				. '.reedcrm-header-relaunch-master,'
				. '.reedcrm-card-header-blocks,'
				. '#reedcrm-relaunch-row-hidden'
				. '{ display: none !important; }'
				. '</style>';
		}

		// 3. Contact card in view mode (within DoliSCI simplified environment)
		if (in_array('contactcard', $contexts, true) && $object instanceof Contact && $this->isDoliSciContactContext($object)) {
			$this->resprints = '<div class="dolisci-simplified-contact-view" style="display:none"></div>'
				. '<style>'
				. '.arearef:has(.dolisci-simplified-contact-view) ~ .fichecenter .fichehalfleft,'
				. '.arearef:has(.dolisci-simplified-contact-view) ~ .fichecenter .fichehalfright table.tableforfield tr:not(:first-child)'
				. '{ display: none !important; }'
				. '</style>';
		}

		return 0;
	}

	/**
	 * Overloading the doActions function: handle 1-click generation of Rent & Charges services from a Property Product
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $contextpage, $langs, $search_categ, $search_categ_cus, $search_category_array, $searchCategoryCustomerList, $searchCategoryProductList, $searchCategoryProductOperator, $status, $user;

		$contexts       = explode(':', $parameters['context'] ?? '');
		$leftmenu       = GETPOST('leftmenu', 'alphanohtml');
		$isRemoveFilter = GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha');

		require_once __DIR__ . '/../lib/dolisci.lib.php';

		if (isset($parameters['arrayfields']) && is_array($parameters['arrayfields'])) {
			$this->prefixDoliSciExtrafieldLabels($parameters['arrayfields']);
		} else {
			$this->prefixDoliSciExtrafieldLabels();
		}

		// 1. Isolate DoliSCI Project list (contextpage = dolisciprojectlist) vs general Project list (contextpage = projectlist)
		$isDoliSciProjectList = ($contextpage === 'dolisciprojectlist'
			|| GETPOST('contextpage', 'aZ') === 'dolisciprojectlist'
			|| strpos($leftmenu, 'dolisci_projects') === 0);

		if (in_array('projectlist', $contexts, true)) {
			if ($isDoliSciProjectList) {
				$contextpage = 'dolisciprojectlist';

				if (!$isRemoveFilter) {
					$tagProjectId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_PROJECT_PROPERTY');
					if ($tagProjectId > 0 && empty($search_category_array)) {
						$search_category_array = [$tagProjectId];
					}
				}

				// Default columns on DoliSCI project list (isolated from general projectlist): Ref, Label, Property Address
				$defaultCheckedFields = [
					'p.ref',
					'p.title',
					'ef.dolisci_fk_socpeople',
				];
				if (isset($parameters['arrayfields']) && is_array($parameters['arrayfields'])) {
					// Hide ReedCRM extrafields on DoliSCI real estate project list
					$reedcrmFields = [
						'ef.commrelaunch',
						'ef.reedcrm_gravityform',
						'ef.commtask',
						'ef.reedcrm_lastname',
						'ef.reedcrm_firstname',
						'ef.reedcrm_website',
						'ef.projectphone',
						'ef.reedcrm_email',
						'ef.opporigin',
						'ef.projectaddress',
						'ef.opprefusal',
					];
					foreach ($reedcrmFields as $rcField) {
						if (isset($parameters['arrayfields'][$rcField])) {
							$parameters['arrayfields'][$rcField]['enabled'] = 0;
							$parameters['arrayfields'][$rcField]['checked'] = 0;
						}
					}
					if (empty($user->conf->MAIN_SELECTEDFIELDS_dolisciprojectlist)) {
						foreach ($parameters['arrayfields'] as $fieldKey => &$fieldDef) {
							$fieldDef['checked'] = in_array((string) $fieldKey, $defaultCheckedFields, true) ? '1' : '0';
						}
						unset($fieldDef);
					}
				}
			} elseif (isset($parameters['arrayfields']['ef.dolisci_fk_socpeople'])) {
				// Hide DoliSCI Property Address extrafield on general Project list
				$parameters['arrayfields']['ef.dolisci_fk_socpeople']['enabled'] = 0;
				$parameters['arrayfields']['ef.dolisci_fk_socpeople']['checked'] = 0;
			}
		}

		// 2. Pre-filter Product / Service list by DoliSCI tags ("Bien" or "Loyer" / "Charge" / "Caution")
		if (in_array('productservicelist', $contexts, true) && !$isRemoveFilter) {
			if ($contextpage === 'dolisciproductlist' || strpos($leftmenu, 'dolisci_products') === 0) {
				$contextpage  = 'dolisciproductlist';
				$tagProductId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_PRODUCT_PROPERTY');
				if ($tagProductId > 0 && empty($searchCategoryProductList)) {
					$searchCategoryProductList = [$tagProductId];
				}
			} elseif ($contextpage === 'dolisciservicelist' || strpos($leftmenu, 'dolisci_services') === 0) {
				$contextpage  = 'dolisciservicelist';
				$tagRentId    = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_RENT');
				$tagChargesId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_CHARGES');
				$tagDepositId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_DEPOSIT');
				$serviceTags  = array_values(array_filter([$tagRentId, $tagChargesId, $tagDepositId]));
				if (!empty($serviceTags) && empty($searchCategoryProductList)) {
					$searchCategoryProductList     = $serviceTags;
					$searchCategoryProductOperator = 1;
				}
			}
		}

		// 2b. Pre-filter Thirdparty list by DoliSCI customer tenant tag ("Locataire")
		if (in_array('thirdpartylist', $contexts, true) && !$isRemoveFilter) {
			if ($contextpage === 'doliscithirdpartylist' || strpos($leftmenu, 'dolisci_thirdparties') === 0) {
				$contextpage         = 'doliscithirdpartylist';
				$tagCustomerTenantId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_CUSTOMER_TENANT');
				if ($tagCustomerTenantId > 0 && empty($searchCategoryCustomerList)) {
					$search_categ_cus          = $tagCustomerTenantId;
					$searchCategoryCustomerList = [$tagCustomerTenantId];
					$_GET['search_categ_cus']  = (string) $tagCustomerTenantId;
					$_POST['search_categ_cus'] = (string) $tagCustomerTenantId;
				}
			}
		}

		// 2c. Simplify Thirdparty creation in DoliSCI context:
		//     - Disable native radio header (contacts are created via the 3-section DoliSCI form)
		//     - Force customer = 1
		//     - Pre-select Customer Tenant tag ("Locataire")
		if (in_array('thirdpartycard', $contexts, true) && $this->isDoliSciThirdpartyContext($object)) {
			$conf->global->THIRDPARTY_SUGGEST_ALSO_ADDRESS_CREATION = 0;
			$conf->global->THIRDPARTY_DEFAULT_CREATE_CONTACT        = 0;

			if ($action === 'create' || $action === 'add') {
				$_GET['private']   = '0';
				$_POST['private']  = '0';
				$_GET['customer']  = '1';
				$_POST['customer'] = '1';
				$_GET['prospect']  = '0';
				$_POST['prospect'] = '0';

				$tagCustomerTenantId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_CUSTOMER_TENANT');
				if ($tagCustomerTenantId > 0 && !GETPOSTISSET('custcats')) {
					$_POST['custcats'] = [$tagCustomerTenantId];
					$_GET['custcats']  = [$tagCustomerTenantId];
				}
			}
		}

		// 2d. Pre-filter Contact list by DoliSCI contact tags according to leftmenu or dolisci_contact_tag parameter
		if (in_array('contactlist', $contexts, true) || in_array('doliscicontactlist', $contexts, true)) {
			if ($contextpage === 'doliscicontactlist' || GETPOST('mainmenu', 'aZ09') === 'dolisci' || strpos($leftmenu, 'dolisci_') === 0) {
				$contextpage = 'doliscicontactlist';
			}
			if (!$isRemoveFilter && empty($search_categ)) {
				$contactMenuTagMap = [
					'dolisci_contacts_address'   => 'DOLISCI_TAG_CONTACT_ADDRESS',
					'dolisci_contacts_tenant'    => 'DOLISCI_TAG_CONTACT_TENANT',
					'dolisci_contacts_guarantor' => 'DOLISCI_TAG_CONTACT_GUARANTOR',
					'dolisci_contacts_syndic'    => 'DOLISCI_TAG_CONTACT_SYNDIC',
					'dolisci_contacts_notary'    => 'DOLISCI_TAG_CONTACT_NOTARY',
				];
				$tagConst = $contactMenuTagMap[$leftmenu] ?? GETPOST('dolisci_contact_tag', 'alphanohtml');
				if (!empty($tagConst) && in_array($tagConst, $contactMenuTagMap, true)) {
					$tagContactId = dolisciEnsureTagId($this->db, $tagConst);
					if ($tagContactId > 0) {
						$search_categ          = (string) $tagContactId;
						$_GET['search_categ']  = (string) $tagContactId;
						$_POST['search_categ'] = (string) $tagContactId;
					}
				}
			}
		}

		// 3. Simplify Project card when in DoliSCI context: force Validated status, check usage_task by default, pre-select "Immob." tag
		if (in_array('projectcard', $contexts, true) && $this->isDoliSciProjectContext($object)) {
			$status = Project::STATUS_VALIDATED;
			$conf->global->PROJECT_CREATE_NO_DRAFT = 1;
			$this->filterProjectExtrafieldsForDoliSci();

			if (in_array($action, ['create', 'add', 'edit', 'update'], true)) {
				$_POST['usage_opportunity'] = '';
				$_POST['usage_task']        = 'on';
				$_POST['usage_bill_time']   = '';
			}
			if ($action === 'create') {
				$tagProjectId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_PROJECT_PROPERTY');
				if ($tagProjectId > 0 && !GETPOSTISARRAY('categories')) {
					$_POST['categories'] = [$tagProjectId];
				}
			}
		}

		// 4. Pre-select DoliSCI tag and enforce required Project on Product / Service creation form when in DoliSCI context
		if (in_array('productcard', $contexts, true)) {
			if (in_array($action, ['create', 'add', 'edit', 'update'], true) && $this->isDoliSciPropertyProductContext($object)) {
				$this->filterProductExtrafieldsForDoliSci();
			}
			if ($action === 'create' && !GETPOSTISARRAY('categories')) {
				if (GETPOST('mainmenu', 'aZ09') === 'dolisci' || ($_SESSION['mainmenu'] ?? '') === 'dolisci' || strpos($leftmenu, 'dolisci_') === 0) {
					$prodType = GETPOSTINT('type');
					if ($prodType === Product::TYPE_PRODUCT) {
						$tagProductId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_PRODUCT_PROPERTY');
						if ($tagProductId > 0) {
							$_POST['categories'] = [$tagProductId];
						}
					} elseif ($prodType === Product::TYPE_SERVICE) {
						$servNature = GETPOST('options_dolisci_service_nature', 'alphanohtml');
						if ($servNature === 'LOYER') {
							$tagRentId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_RENT');
							if ($tagRentId > 0) {
								$_POST['categories'] = [$tagRentId];
							}
						} elseif ($servNature === 'CHARGES') {
							$tagChargesId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_CHARGES');
							if ($tagChargesId > 0) {
								$_POST['categories'] = [$tagChargesId];
							}
						} elseif ($servNature === 'DEPOT_GARANTIE' || $servNature === 'CAUTION') {
							$tagDepositId = dolisciEnsureTagId($this->db, 'DOLISCI_TAG_SERVICE_DEPOSIT');
							if ($tagDepositId > 0) {
								$_POST['categories'] = [$tagDepositId];
							}
						}
					}
				}
			}
		}

		if (in_array('productcard', $contexts, true) && $action === 'add_dolisci_rental_services' && $object instanceof Product) {
			if (GETPOSTISSET('confirm') && GETPOST('confirm', 'alpha') !== 'yes') {
				$action = '';
				return 0;
			}
			if (!$user->hasRight('service', 'creer')) {
				accessforbidden();
			}

			// Explicit CSRF token check (in addition to main.inc.php check on 'add_*' actions)
			$token        = GETPOST('token', 'alpha');
			$sessionToken = $_SESSION['token'] ?? '';
			if (empty($token) || ($sessionToken !== '' && $token !== $sessionToken)) {
				setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
				$action = '';
				return 0;
			}

			$langs->load('dolisci@dolisci');
			$propProduct = dolisciGetPropertyFromService($this->db, $object) ?: $object;
			$propProduct->fetch_optionals();

			$projectId    = (int) ($propProduct->array_options['options_dolisci_fk_project'] ?? ($object->array_options['options_dolisci_service_fk_project'] ?? 0));
			$createdLinks = [];

			$isOnService   = ((int) $object->type === Product::TYPE_SERVICE);
			$targetGrpIdx  = $isOnService ? dolisciGetServiceGroupIndexFromRef((string) $object->ref) : 1;
			$rentRef       = trim(GETPOST('dolisci_rent_ref', 'alphanohtml')) ?: ('Loyer-' . $targetGrpIdx . '_' . $propProduct->ref);
			$rentAmount    = (float) price2num(GETPOST('dolisci_rent_amount', 'alphanohtml'));
			$rentVatRaw    = (string) GETPOST('dolisci_rent_tva', 'alphanohtml');
			$rentVat       = $rentVatRaw !== '' ? (float) price2num(preg_replace('/[^0-9.,]/', '', $rentVatRaw)) : 0.0;
			$chargesRef    = trim(GETPOST('dolisci_charges_ref', 'alphanohtml')) ?: ('Charge-' . $targetGrpIdx . '_' . $propProduct->ref);
			$chargesAmount = (float) price2num(GETPOST('dolisci_charges_amount', 'alphanohtml'));
			$chargesVatRaw = (string) GETPOST('dolisci_charges_tva', 'alphanohtml');
			$chargesVat    = $chargesVatRaw !== '' ? (float) price2num(preg_replace('/[^0-9.,]/', '', $chargesVatRaw)) : 0.0;
			$depositRef    = trim(GETPOST('dolisci_deposit_ref', 'alphanohtml')) ?: ('Caution-' . $targetGrpIdx . '_' . $propProduct->ref);
			$depositAmount = (float) price2num(GETPOST('dolisci_deposit_amount', 'alphanohtml'));
			$depositVatRaw = (string) GETPOST('dolisci_deposit_tva', 'alphanohtml');
			$depositVat    = $depositVatRaw !== '' ? (float) price2num(preg_replace('/[^0-9.,]/', '', $depositVatRaw)) : 0.0;

			$servicesToCreate = [];
			if (!$isOnService) {
				$servicesToCreate[] = [
					'ref'      => $rentRef,
					'label'    => $langs->transnoentities('DoliSciRentServiceLabel', $propProduct->label),
					'nature'   => 'LOYER',
					'account'  => '706100',
					'tagConst' => 'DOLISCI_TAG_SERVICE_RENT',
					'amount'   => $rentAmount,
					'tva_tx'   => $rentVat,
				];
			}
			$servicesToCreate[] = [
				'ref'      => $chargesRef,
				'label'    => $langs->transnoentities('DoliSciChargesServiceLabel', $propProduct->label),
				'nature'   => 'CHARGES',
				'account'  => '708800',
				'tagConst' => 'DOLISCI_TAG_SERVICE_CHARGES',
				'amount'   => $chargesAmount,
				'tva_tx'   => $chargesVat,
			];
			$servicesToCreate[] = [
				'ref'      => $depositRef,
				'label'    => $langs->transnoentities('DoliSciDepositServiceLabel', $propProduct->label),
				'nature'   => 'DEPOT_GARANTIE',
				'account'  => '165000',
				'tagConst' => 'DOLISCI_TAG_SERVICE_DEPOSIT',
				'amount'   => $depositAmount,
				'tva_tx'   => $depositVat,
			];

			foreach ($servicesToCreate as $spec) {
				$service  = new Product($this->db);
				$existing = $service->fetch(0, $spec['ref']);
				if ($existing > 0) {
					$service->fetch_optionals();
					$service->array_options['options_dolisci_service_nature']      = $spec['nature'];
					$service->array_options['options_dolisci_fk_property_product'] = (int) $propProduct->id;
					if ($projectId > 0) {
						$service->array_options['options_dolisci_service_fk_project'] = $projectId;
					}
					$service->insertExtraFields();
					$service->updatePrice($spec['amount'], 'HT', $user, $spec['tva_tx']);
					dolisciAssignTag($this->db, $service, $spec['tagConst'], 'product');
					$createdLinks[] = $service->getNomUrl(1);
					continue;
				}

				$service->ref                   = $spec['ref'];
				$service->label                 = $spec['label'];
				$service->type                  = Product::TYPE_SERVICE;
				$service->status                = 1;
				$service->status_buy            = 0;
				$service->price                 = $spec['amount'];
				$service->price_base_type       = 'HT';
				$service->tva_tx                = $spec['tva_tx'];
				$service->accountancy_code_sell = $spec['account'];
				$service->array_options         = [
					'options_dolisci_service_nature'      => $spec['nature'],
					'options_dolisci_fk_property_product' => (int) $propProduct->id,
					'options_dolisci_service_fk_project'  => $projectId,
				];

				$res = $service->create($user);
				if ($res > 0) {
					$service->updatePrice($spec['amount'], 'HT', $user, $spec['tva_tx']);
					dolisciAssignTag($this->db, $service, $spec['tagConst'], 'product');
					$createdLinks[] = $service->getNomUrl(1);
				} else {
					setEventMessages($service->error, $service->errors, 'errors');
				}
			}

			if (!empty($createdLinks)) {
				setEventMessages($langs->trans('DoliSciRentalServicesGenerated', implode(', ', $createdLinks)), null);
			}
			$action = '';
		}

		// 5. Handle deletion of a Property Product: optionally delete associated Rental Services (Loyer, Charge, Caution),
		//    and if any deletion fails (e.g. used in invoices), propose setting the property and all its services out of sale/purchase.
		if (in_array('productcard', $contexts, true) && $object instanceof Product && (int) $object->type === Product::TYPE_PRODUCT && !empty($object->id)) {
			if ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && dolisciIsPropertyProduct($this->db, $object)) {
				if (!$user->hasRight('produit', 'supprimer')) {
					accessforbidden();
				}

				$langs->loadLangs(['products', 'dolisci@dolisci']);
				$deleteLinkedServices = (GETPOST('dolisci_delete_linked_services', 'alpha') === '1'
					|| GETPOST('dolisci_delete_linked_services', 'alpha') === 'yes'
					|| GETPOST('dolisci_delete_linked_services', 'alpha') === 'on');

				$rentalData  = dolisciGetPropertyRentalServices($this->db, $object);
				$services    = $rentalData['services'] ?? [];
				$deleteError = '';
				$deletedServ = [];

				$this->db->begin();

				if ($deleteLinkedServices && !empty($services)) {
					if (!$user->hasRight('service', 'supprimer')) {
						$deleteError = $langs->trans('ErrorForbidden');
					} else {
						foreach ($services as $sObj) {
							$serv = new Product($this->db);
							if ($serv->fetch((int) $sObj->rowid) > 0) {
								$resServ = $serv->delete($user);
								if ($resServ <= 0) {
									$errText     = !empty($serv->error) ? $langs->trans($serv->error) : $langs->trans('ProductIsUsed');
									$deleteError = $serv->ref . ' : ' . $errText;
									break;
								}
								$deletedServ[] = $serv->ref;
							}
						}
					}
				}

				if ($deleteError === '') {
					$resProd = $object->delete($user);
					if ($resProd <= 0) {
						$errText     = !empty($object->error) ? $langs->trans($object->error) : $langs->trans('ProductIsUsed');
						$deleteError = $object->ref . ' : ' . $errText;
					}
				}

				if ($deleteError === '') {
					$this->db->commit();
					if (!empty($deletedServ)) {
						setEventMessages($langs->trans('DoliSciServicesDeleted', implode(', ', $deletedServ)), null, 'mesgs');
					}
					header('Location: ' . dol_buildpath('/dolisci/product_list.php', 1) . '?type=0&delprod=' . urlencode($object->ref));
					exit;
				}

				// Rollback so no partial deletion occurs, then propose setting everything out of sale / out of purchase
				$this->db->rollback();
				setEventMessages($deleteError, null, 'errors');
				$this->deleteFailedReason = $deleteError;
				$action = 'ask_dolisci_disable_all';
				return 1;
			}

			if ($action === 'confirm_dolisci_disable_all' && GETPOST('confirm', 'alpha') === 'yes' && dolisciIsPropertyProduct($this->db, $object)) {
				if (!$user->hasRight('produit', 'creer')) {
					accessforbidden();
				}

				$langs->loadLangs(['products', 'dolisci@dolisci']);
				$rentalData    = dolisciGetPropertyRentalServices($this->db, $object);
				$services      = $rentalData['services'] ?? [];
				$disabledLinks = [];

				// Set Property Product out of sale & out of purchase
				$sqlProd = 'UPDATE ' . MAIN_DB_PREFIX . 'product SET tosell = 0, tobuy = 0 WHERE rowid = ' . ((int) $object->id);
				if ($this->db->query($sqlProd)) {
					$object->status     = 0;
					$object->status_buy = 0;
					$disabledLinks[]    = $object->getNomUrl(1);
				}

				// Set all associated Rental Services out of sale & out of purchase
				foreach ($services as $sObj) {
					$serv = new Product($this->db);
					if ($serv->fetch((int) $sObj->rowid) > 0) {
						$sqlServ = 'UPDATE ' . MAIN_DB_PREFIX . 'product SET tosell = 0, tobuy = 0 WHERE rowid = ' . ((int) $serv->id);
						if ($this->db->query($sqlServ)) {
							$serv->status     = 0;
							$serv->status_buy = 0;
							$disabledLinks[]  = $serv->getNomUrl(1);
						}
					}
				}

				if (!empty($disabledLinks)) {
					setEventMessages($langs->trans('DoliSciPropertyAndServicesDisabled', implode(', ', $disabledLinks)), null, 'mesgs');
				}
				$action = '';
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Overloading the formConfirm function: render modal popup to configure Rent & Charges amounts and VAT
	 * when clicking "Generate Rent + Charges Services" on a Property Product card
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $form, $langs, $mysoc, $user;

		$contexts = explode(':', $parameters['context'] ?? '');
		if (!in_array('productcard', $contexts, true) || !($object instanceof Product) || empty($object->id)) {
			return 0;
		}
		$isServiceTab = in_array('dolisciproductservices', $contexts, true);
		if ((int) $object->type !== Product::TYPE_PRODUCT && !$isServiceTab) {
			return 0;
		}
		if (in_array($action, ['create', 'edit'], true)) {
			return 0;
		}

		require_once __DIR__ . '/../lib/dolisci.lib.php';
		$propProduct = dolisciGetPropertyFromService($this->db, $object);
		if (!$propProduct || !dolisciIsPropertyProduct($this->db, $propProduct)) {
			return 0;
		}

		$langs->loadLangs(['products', 'bills', 'dolisci@dolisci']);
		if (!is_object($form)) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
			$form = new Form($this->db);
		}

		$rentalData   = dolisciGetPropertyRentalServices($this->db, $propProduct);
		$services     = $rentalData['services'] ?? [];
		$serviceRefs  = [];
		foreach ($services as $sObj) {
			$serviceRefs[] = (string) $sObj->ref;
		}
		$servicesLabelStr = implode(', ', $serviceRefs);

		// 1. If deletion failed, display modal proposing to set the property and all its associated services out of sale/purchase
		if ($action === 'ask_dolisci_disable_all' && (int) $object->type === Product::TYPE_PRODUCT) {
			$allRefs = array_merge([(string) $object->ref], $serviceRefs);
			$reason  = $this->deleteFailedReason !== '' ? $this->deleteFailedReason : $langs->trans('ProductIsUsed');
			$questionText = $langs->trans('DoliSciErrorDeleteProposeDisableConfirm', dolPrintHTML($reason), dolPrintHTML(implode(', ', $allRefs)));

			$this->resprints = $form->formconfirm(
				$_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id),
				$langs->trans('DoliSciErrorDeleteProposeDisableTitle'),
				$questionText,
				'confirm_dolisci_disable_all',
				'',
				'yes',
				1,
				250,
				580
			);
			return 1;
		}

		// 2. Replace standard Delete confirmation on Property Product card when associated services exist,
		//    adding the checkbox "Supprimer également les services associés (...)"
		$existingFormConfirm = (string) ($parameters['formConfirm'] ?? '');
		$returnCode          = 0;
		$outHtml             = '';

		if ((int) $object->type === Product::TYPE_PRODUCT && !$isServiceTab && !empty($serviceRefs)) {
			if (($action === 'delete' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile)))
				|| (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
				$deleteFormQuestion = [
					[
						'type'  => 'checkbox',
						'name'  => 'dolisci_delete_linked_services',
						'label' => $langs->trans('DoliSciDeleteAssociatedServicesLabel', $servicesLabelStr),
						'value' => 1,
					],
				];
				$customDeleteConfirm = $form->formconfirm(
					$_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id),
					$langs->trans('DeleteProduct'),
					$langs->trans('ConfirmDeleteProduct'),
					'confirm_delete',
					$deleteFormQuestion,
					'yes',
					'action-delete',
					260,
					580
				);
				if (strpos($existingFormConfirm, 'confirm_delete') !== false) {
					$clonePos = strpos($existingFormConfirm, '<!-- begin formconfirm');
					$secondPos = $clonePos !== false ? strpos($existingFormConfirm, '<!-- begin formconfirm', $clonePos + 20) : false;
					if ($secondPos !== false) {
						$outHtml = $customDeleteConfirm . substr($existingFormConfirm, $secondPos);
					} else {
						$outHtml = $customDeleteConfirm;
					}
					$returnCode = 1;
				} else {
					$outHtml .= $customDeleteConfirm;
				}
			}
		}

		// 3. Modal popup:
		//    - On a Property Product (type = 0): generate Rent + Charges + Deposit services (Loyer-N_<ref>, Charge-N_<ref>, Caution-N_<ref>)
		//    - On a Rental Service (type = 1): create or attach only the Charges + Deposit services for THIS service's group (Charge-N_<ref>, Caution-N_<ref>)
		if ($user->hasRight('service', 'creer')) {
			$isOnService  = ((int) $object->type === Product::TYPE_SERVICE);
			$existingRefs = [];
			$maxIndex     = 0;
			foreach ($services as $sObj) {
				$sRef = (string) $sObj->ref;
				$existingRefs[strtolower($sRef)] = true;
				if (preg_match('/^(?:Loyer|Charges?|Caution)-([0-9]+)_/i', $sRef, $m)) {
					$idx = (int) $m[1];
					if ($idx > $maxIndex) {
						$maxIndex = $idx;
					}
				} elseif (preg_match('/^(?:Loyer|Charges?|Caution)_(.+?)(?:_([0-9]+))?$/i', $sRef, $m)) {
					$idx = !empty($m[2]) ? (int) $m[2] : 1;
					if ($idx > $maxIndex) {
						$maxIndex = $idx;
					}
				}
			}

			if ($isOnService) {
				$setIndex = dolisciGetServiceGroupIndexFromRef((string) $object->ref);
			} else {
				// Always start at 1 (Loyer-1_<ref>) and increment from the highest existing index (Loyer-2_<ref>, Loyer-3_<ref>, ...)
				$setIndex = $maxIndex + 1;
				while (
					isset($existingRefs[strtolower('Loyer-' . $setIndex . '_' . $propProduct->ref)])
					|| isset($existingRefs[strtolower('Charge-' . $setIndex . '_' . $propProduct->ref)])
					|| isset($existingRefs[strtolower('Caution-' . $setIndex . '_' . $propProduct->ref)])
				) {
					$setIndex++;
				}
			}
			$numPrefix = '-' . $setIndex;

			// Default VAT fallback from company VAT settings (get_default_tva($mysoc, $mysoc))
			$companyDefaultVat = !empty($mysoc->tva_assuj) ? (float) price2num(get_default_tva($mysoc, $mysoc)) : 0.0;
			$baseVat           = (float) $propProduct->tva_tx > 0 ? (float) $propProduct->tva_tx : $companyDefaultVat;

			$rentRef       = 'Loyer' . $numPrefix . '_' . $propProduct->ref;
			$chargesRef    = 'Charge' . $numPrefix . '_' . $propProduct->ref;
			$depositRef    = 'Caution' . $numPrefix . '_' . $propProduct->ref;
			$rentAmount    = (float) $propProduct->price > 0 ? price2num($propProduct->price) : '0';
			$rentVat       = $baseVat;
			$chargesAmount = '0';
			$chargesVat    = $baseVat;
			$depositAmount = '0';
			$depositVat    = $baseVat;

			// If previous rental/charges/deposit services exist for this property, inherit their price & VAT rate
			foreach ($services as $sObj) {
				$sNature = (string) ($sObj->dolisci_service_nature ?? '');
				$sRef    = (string) ($sObj->ref ?? '');
				$sAcc    = (string) ($sObj->accountancy_code_sell ?? '');
				if ($sNature === 'LOYER' || stripos($sRef, 'Loyer_') === 0 || stripos($sRef, 'Loyer-') === 0 || $sAcc === '706100') {
					if ((float) $sObj->price > 0) {
						$rentAmount = price2num($sObj->price);
					}
					$rentVat = (float) $sObj->tva_tx;
				} elseif ($sNature === 'CHARGES' || stripos($sRef, 'Charge_') === 0 || stripos($sRef, 'Charge-') === 0 || stripos($sRef, 'Charges_') === 0 || stripos($sRef, 'Charges-') === 0 || $sAcc === '708800') {
					if ((float) $sObj->price > 0) {
						$chargesAmount = price2num($sObj->price);
					}
					$chargesVat = (float) $sObj->tva_tx;
				} elseif ($sNature === 'DEPOT_GARANTIE' || $sNature === 'CAUTION' || stripos($sRef, 'Caution_') === 0 || stripos($sRef, 'Caution-') === 0 || $sAcc === '165000') {
					if ((float) $sObj->price > 0) {
						$depositAmount = price2num($sObj->price);
					}
					$depositVat = (float) $sObj->tva_tx;
				}
			}

			// If viewed directly from a specific Rental Service card, prioritize that service's VAT rate and its existing child services
			if ($isOnService) {
				$objVat     = (float) $object->tva_tx;
				$rentVat    = $objVat;
				$chargesVat = $objVat;
				$depositVat = $objVat;

				$childServices = dolisciGetServiceChildServices($this->db, $object, $propProduct);
				foreach ($childServices as $cObj) {
					$cRank = (int) ($cObj->dolisci_nature_rank ?? 4);
					if ($cRank === 2) {
						$chargesRef    = (string) $cObj->ref;
						$chargesAmount = price2num($cObj->price);
						$chargesVat    = (float) $cObj->tva_tx;
					} elseif ($cRank === 3) {
						$depositRef    = (string) $cObj->ref;
						$depositAmount = price2num($cObj->price);
						$depositVat    = (float) $cObj->tva_tx;
					}
				}
			}

			$existingRent = new Product($this->db);
			if ($existingRent->fetch(0, $rentRef) > 0) {
				$rentAmount = price2num($existingRent->price);
				$rentVat    = (float) $existingRent->tva_tx;
			}
			$existingCharges = new Product($this->db);
			if ($existingCharges->fetch(0, $chargesRef) > 0) {
				$chargesAmount = price2num($existingCharges->price);
				$chargesVat    = (float) $existingCharges->tva_tx;
			}
			$existingDeposit = new Product($this->db);
			if ($existingDeposit->fetch(0, $depositRef) > 0) {
				$depositAmount = price2num($existingDeposit->price);
				$depositVat    = (float) $existingDeposit->tva_tx;
			}

			$rentVatSelect    = $form->load_tva('dolisci_rent_tva', $rentVat, $mysoc, null, 0, 0, '', false);
			$chargesVatSelect = $form->load_tva('dolisci_charges_tva', $chargesVat, $mysoc, null, 0, 0, '', false);
			$depositVatSelect = $form->load_tva('dolisci_deposit_tva', $depositVat, $mysoc, null, 0, 0, '', false);

			$tableHtml = '<table class="noborder centpercent">'
				. '<tr class="liste_titre">'
				. '<td>' . $langs->trans('Service') . '</td>'
				. '<td class="right">' . $langs->trans('AmountHT') . '</td>'
				. '<td class="right">' . $langs->trans('VATRate') . '</td>'
				. '</tr>';
			if (!$isOnService) {
				$tableHtml .= '<tr class="oddeven">'
					. '<td><input type="text" id="dolisci_rent_ref" name="dolisci_rent_ref" class="flat minwidth300" value="' . dolPrintHTMLForAttribute($rentRef) . '"></td>'
					. '<td class="right nowraponall"><input type="text" id="dolisci_rent_amount" name="dolisci_rent_amount" class="flat width75 right" value="' . dolPrintHTMLForAttribute((string) $rentAmount) . '"></td>'
					. '<td class="right nowraponall">' . $rentVatSelect . '</td>'
					. '</tr>';
			}
			$tableHtml .= '<tr class="oddeven">'
				. '<td><input type="text" id="dolisci_charges_ref" name="dolisci_charges_ref" class="flat minwidth300" value="' . dolPrintHTMLForAttribute($chargesRef) . '"></td>'
				. '<td class="right nowraponall"><input type="text" id="dolisci_charges_amount" name="dolisci_charges_amount" class="flat width75 right" value="' . dolPrintHTMLForAttribute((string) $chargesAmount) . '"></td>'
				. '<td class="right nowraponall">' . $chargesVatSelect . '</td>'
				. '</tr>'
				. '<tr class="oddeven">'
				. '<td><input type="text" id="dolisci_deposit_ref" name="dolisci_deposit_ref" class="flat minwidth300" value="' . dolPrintHTMLForAttribute($depositRef) . '"></td>'
				. '<td class="right nowraponall"><input type="text" id="dolisci_deposit_amount" name="dolisci_deposit_amount" class="flat width75 right" value="' . dolPrintHTMLForAttribute((string) $depositAmount) . '"></td>'
				. '<td class="right nowraponall">' . $depositVatSelect . '</td>'
				. '</tr>'
				. '</table>';

			$fieldNames = $isOnService
				? 'dolisci_charges_ref,dolisci_charges_amount,dolisci_charges_tva,dolisci_deposit_ref,dolisci_deposit_amount,dolisci_deposit_tva'
				: 'dolisci_rent_ref,dolisci_rent_amount,dolisci_rent_tva,dolisci_charges_ref,dolisci_charges_amount,dolisci_charges_tva,dolisci_deposit_ref,dolisci_deposit_amount,dolisci_deposit_tva';
			$formquestion = [
				[
					'type'  => 'onecolumn',
					'name'  => $fieldNames,
					'value' => $tableHtml,
				],
			];

			$modalTitle = $isOnService ? $langs->trans('DoliSciCreateOrAttachChargesDeposit') : $langs->trans('DoliSciGenerateRentalServices');
			$useAjax    = ($action === 'ask_dolisci_rental_services') ? 1 : 'action-dolisci-generate-services';
			$outHtml .= $form->formconfirm(
				$_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id),
				$modalTitle,
				'',
				'add_dolisci_rental_services',
				$formquestion,
				'yes',
				$useAjax,
				380,
				640
			);
		}

		$this->resprints = $outHtml;
		return $returnCode;
	}

	/**
	 * Overloading the addMoreActionsButtons function:
	 * - On a Property Product (type = 0): add button to generate Rent + Charges + Deposit services (always enabled)
	 * - On a Rental Service (type = 1, DoliSCI Services tab): add button to create or attach Charges + Deposit for this service
	 * - On a Real Estate Project (BIEN_IMMO): display tables of linked Property Products (fiscal lots) and Rental Services
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process
	 * @param  string       $action      Current action
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                       0
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $langs, $user;

		$contexts = explode(':', $parameters['context'] ?? '');
		$langs->load('dolisci@dolisci');

		// 1. Button on Property Product card (type == 0) or on the DoliSCI Services tab of a Rental Service
		$isServiceTab = in_array('dolisciproductservices', $contexts, true);
		if (in_array('productcard', $contexts, true) && $object instanceof Product && ((int) $object->type === Product::TYPE_PRODUCT || $isServiceTab)) {
			require_once __DIR__ . '/../lib/dolisci.lib.php';
			$propProduct = dolisciGetPropertyFromService($this->db, $object);
			if ($propProduct && dolisciIsPropertyProduct($this->db, $propProduct) && $user->hasRight('service', 'creer')) {
				$btnLabel = ((int) $object->type === Product::TYPE_SERVICE)
					? $langs->trans('DoliSciCreateOrAttachChargesDeposit')
					: $langs->trans('DoliSciGenerateRentalServices');
				if (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile)) {
					print dolGetButtonAction('', $btnLabel, 'default', '#', 'action-dolisci-generate-services', true);
				} else {
					$url = $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id) . '&action=ask_dolisci_rental_services&token=' . newToken();
					print dolGetButtonAction('', $btnLabel, 'default', $url, '');
				}
			}
		}

		// 2. Real estate overview tables on Project card (tagged "Immob." or in DoliSCI environment)
		if (in_array('projectcard', $contexts, true) && $object instanceof Project && !empty($object->id) && $action !== 'edit') {
			require_once __DIR__ . '/../lib/dolisci.lib.php';
			if ($this->isDoliSciProjectContext($object) || dolisciIsRealEstateProject($this->db, (int) $object->id)) {
				$this->printProjectRealEstateTables($object);
			}
		}

		return 0;
	}

	/**
	 * Render the tables of linked Property Products (purchased real estate / fiscal lots)
	 * and Rental Services (Rent + Charges provisions) on a Real Estate Project card or tab.
	 *
	 * @param  Project $project Project instance
	 * @return void
	 */
	public function renderProjectRealEstateTables(Project $project): void
	{
		$this->printProjectRealEstateTables($project);
	}

	/**
	 * Render the tables of linked Property Products (purchased real estate / fiscal lots)
	 * and Rental Services (Rent + Charges provisions) on a Real Estate Project card.
	 *
	 * @param  Project $project Project instance
	 * @return void
	 */
	protected function printProjectRealEstateTables(Project $project): void
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
		require_once __DIR__ . '/../lib/dolisci.lib.php';

		$efDefs          = dolisciGetExtrafieldDefinitions();
		$natureOpts      = $efDefs['ef:product:dolisci_property_nature']['param']['options'] ?? [];
		$regimeOpts      = $efDefs['ef:product:dolisci_legal_regime']['param']['options'] ?? [];
		$servNatureOpts  = $efDefs['ef:product:dolisci_service_nature']['param']['options'] ?? [];
		$leaseTypeOpts   = $efDefs['ef:product:dolisci_lease_type']['param']['options'] ?? [];

		$projectIds = [(int) $project->id];
		if (getDolGlobalInt('PROJECT_ENABLE_SUB_PROJECT')) {
			$children = $project->getChildren();
			if (is_array($children)) {
				foreach ($children as $child) {
					$projectIds[] = (int) $child->rowid;
				}
			}
		}
		$inProjectIds = implode(',', array_map('intval', $projectIds));

		// 1. Purchased Properties & Fiscal Lots (Products, fk_product_type = 0)
		// Single batch query (no N+1 queries inside loop) with multi-entity filter
		$sqlProp = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.surface,'
			. ' ef.dolisci_property_nature, ef.dolisci_legal_regime, ef.dolisci_fiscal_lot, ef.dolisci_tantiemes,'
			. ' ef.dolisci_floor_door, ef.dolisci_rooms, ef.dolisci_dpe_energy, ef.dolisci_dpe_ges, ef.dolisci_purchase_date, ef.dolisci_purchase_price'
			. ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
			. ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
			. ' WHERE p.entity IN (' . getEntity('product') . ')'
			. ' AND p.fk_product_type = 0'
			. ' AND ef.dolisci_fk_project IN (' . $inProjectIds . ')'
			. ' ORDER BY p.ref ASC LIMIT 100';

		$resProp = $this->db->query($sqlProp);

		print '<div class="clearboth"></div><br>';
		print load_fiche_titre($langs->trans('DoliSciProjectPropertiesTitle'), '', 'fa-key');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans('Product') . '</td>';
		print '<td>' . $langs->trans('DoliSciPropertyNature') . '</td>';
		print '<td>' . $langs->trans('DoliSciLegalRegime') . '</td>';
		print '<td>' . $langs->trans('DoliSciFiscalLot') . '</td>';
		print '<td>' . $langs->trans('DoliSciTantiemes') . '</td>';
		print '<td>' . $langs->trans('DoliSciFloorDoor') . '</td>';
		print '<td class="right">' . $langs->trans('Surface') . '</td>';
		print '<td class="center">' . $langs->trans('DoliSciRooms') . '</td>';
		print '<td class="center">DPE / GES</td>';
		print '<td class="right">' . $langs->trans('DoliSciPurchasePrice') . '</td>';
		print '</tr>';

		$nbProps = 0;
		if ($resProp) {
			while ($obj = $this->db->fetch_object($resProp)) {
				$nbProps++;
				$prod             = new Product($this->db);
				$prod->id         = (int) $obj->rowid;
				$prod->ref        = (string) $obj->ref;
				$prod->label      = (string) $obj->label;
				$prod->type       = (int) $obj->fk_product_type;
				$prod->entity     = (int) $obj->entity;
				$prod->status     = (int) $obj->tosell;
				$prod->status_buy = (int) $obj->tobuy;

				$natureKey   = (string) $obj->dolisci_property_nature;
				$natureLabel = isset($natureOpts[$natureKey]) ? $langs->trans((string) $natureOpts[$natureKey]) : $natureKey;
				$regimeKey   = (string) $obj->dolisci_legal_regime;
				$regimeLabel = isset($regimeOpts[$regimeKey]) ? $langs->trans((string) $regimeOpts[$regimeKey]) : $regimeKey;

				print '<tr class="oddeven">';
				print '<td class="nowraponall">' . dolisciGetObjectNomUrl($prod) . ' - ' . dolPrintHTML($prod->label) . '</td>';
				print '<td>' . dolPrintHTML($natureLabel) . '</td>';
				print '<td>' . dolPrintHTML($regimeLabel) . '</td>';
				print '<td>' . dolPrintHTML((string) $obj->dolisci_fiscal_lot) . '</td>';
				print '<td>' . dolPrintHTML((string) $obj->dolisci_tantiemes) . '</td>';
				print '<td>' . dolPrintHTML((string) $obj->dolisci_floor_door) . '</td>';
				print '<td class="right">' . ($obj->surface > 0 ? price($obj->surface) . ' m²' : '') . '</td>';
				print '<td class="center">' . ($obj->dolisci_rooms > 0 ? (int) $obj->dolisci_rooms : '') . '</td>';
				$dpe = trim(($obj->dolisci_dpe_energy ?: '-') . ' / ' . ($obj->dolisci_dpe_ges ?: '-'));
				print '<td class="center">' . dolPrintHTML($dpe) . '</td>';
				print '<td class="right">' . ($obj->dolisci_purchase_price !== null ? price($obj->dolisci_purchase_price, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
				print '</tr>';
			}
		}
		if ($nbProps === 0) {
			print '<tr class="oddeven"><td colspan="10" class="opacitymedium">' . $langs->trans('DoliSciNoPropertyLinked') . '</td></tr>';
		}
		print '</table></div>';

		// 2. Rental Services: Rent (706100) & Charges provisions (708800) (Services, fk_product_type = 1)
		// Single batch query with LEFT JOIN for the linked property product (no N+1 queries inside loop)
		$sqlServ = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.price, p.accountancy_code_sell,'
			. ' ef.dolisci_service_nature, ef.dolisci_fk_property_product, ef.dolisci_lease_type, ef.dolisci_deposit, ef.dolisci_irl_ref,'
			. ' lp.rowid AS lp_id, lp.ref AS lp_ref, lp.label AS lp_label, lp.fk_product_type AS lp_type, lp.entity AS lp_entity, lp.tosell AS lp_tosell, lp.tobuy AS lp_tobuy'
			. ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
			. ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
			. ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product AS lp ON lp.rowid = ef.dolisci_fk_property_product'
			. ' WHERE p.entity IN (' . getEntity('product') . ')'
			. ' AND p.fk_product_type = 1'
			. ' AND ef.dolisci_service_fk_project IN (' . $inProjectIds . ')'
			. " ORDER BY CASE"
			. " WHEN ef.dolisci_service_nature = 'LOYER' OR p.ref LIKE 'Loyer\\_%' OR p.ref LIKE 'Loyer-%\\_%' OR p.accountancy_code_sell = '706100' THEN 1"
			. " WHEN ef.dolisci_service_nature = 'CHARGES' OR p.ref LIKE 'Charge\\_%' OR p.ref LIKE 'Charge-%\\_%' OR p.ref LIKE 'Charges\\_%' OR p.ref LIKE 'Charges-%\\_%' OR p.accountancy_code_sell = '708800' THEN 2"
			. " WHEN ef.dolisci_service_nature IN ('DEPOT_GARANTIE', 'CAUTION') OR p.ref LIKE 'Caution\\_%' OR p.ref LIKE 'Caution-%\\_%' OR p.accountancy_code_sell = '165000' THEN 3"
			. " ELSE 4 END ASC, p.ref ASC LIMIT 100";

		$resServ = $this->db->query($sqlServ);

		print '<br>';
		print load_fiche_titre($langs->trans('DoliSciProjectRentalsTitle'), '', 'fa-file-invoice-dollar');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans('Service') . '</td>';
		print '<td>' . $langs->trans('DoliSciServiceNature') . '</td>';
		print '<td>' . $langs->trans('DoliSciLinkedPropertyProduct') . '</td>';
		print '<td>' . $langs->trans('DoliSciLeaseType') . '</td>';
		print '<td>' . $langs->trans('ProductAccountancySellCode') . '</td>';
		print '<td class="right">' . $langs->trans('SellingPriceHT') . '</td>';
		print '<td class="right">' . $langs->trans('DoliSciDeposit') . '</td>';
		print '<td>' . $langs->trans('DoliSciIrlRef') . '</td>';
		print '</tr>';

		$servRows = [];
		if ($resServ) {
			while ($obj = $this->db->fetch_object($resServ)) {
				$servRows[] = $obj;
			}
		}
		dolisciSortAndGroupRentalServices($servRows);

		$nbServs      = count($servRows);
		$prevGroupKey = null;
		foreach ($servRows as $obj) {
			$serv             = new Product($this->db);
			$serv->id         = (int) $obj->rowid;
			$serv->ref        = (string) $obj->ref;
			$serv->label      = (string) $obj->label;
			$serv->type       = (int) $obj->fk_product_type;
			$serv->entity     = (int) $obj->entity;
			$serv->status     = (int) $obj->tosell;
			$serv->status_buy = (int) $obj->tobuy;

			$linkedPropHtml = '';
			if (!empty($obj->lp_id)) {
				$linkedProp             = new Product($this->db);
				$linkedProp->id         = (int) $obj->lp_id;
				$linkedProp->ref        = (string) $obj->lp_ref;
				$linkedProp->label      = (string) $obj->lp_label;
				$linkedProp->type       = (int) $obj->lp_type;
				$linkedProp->entity     = (int) $obj->lp_entity;
				$linkedProp->status     = (int) $obj->lp_tosell;
				$linkedProp->status_buy = (int) $obj->lp_tobuy;
				$linkedPropHtml         = dolisciGetObjectNomUrl($linkedProp);
			}

			$servNatureKey   = (string) $obj->dolisci_service_nature;
			$servNatureLabel = isset($servNatureOpts[$servNatureKey]) ? $langs->trans((string) $servNatureOpts[$servNatureKey]) : $servNatureKey;
			$leaseKey        = (string) $obj->dolisci_lease_type;
			$leaseLabel      = isset($leaseTypeOpts[$leaseKey]) ? $langs->trans((string) $leaseTypeOpts[$leaseKey]) : $leaseKey;

			$isChild      = !empty($obj->dolisci_is_child);
			$currGroupKey = (string) ($obj->dolisci_prop_key ?? '') . ':' . ((int) ($obj->dolisci_group_index ?? 1));
			$isNewGroup   = ($prevGroupKey !== null && $prevGroupKey !== $currGroupKey);
			$prevGroupKey = $currGroupKey;

			$rowClasses = 'oddeven ' . ($isChild ? 'dolisci-rental-group-child' : 'dolisci-rental-group-parent') . ($isNewGroup ? ' dolisci-rental-group-sep' : '');
			$servPrefix = $isChild ? '<span class="dolisci-service-child-prefix">-</span>' : '';

			print '<tr class="' . $rowClasses . '">';
			print '<td class="nowraponall">' . $servPrefix . dolisciGetObjectNomUrl($serv, $servNatureKey) . ' - ' . dolPrintHTML($serv->label) . '</td>';
			print '<td>' . dolPrintHTML($servNatureLabel) . '</td>';
			print '<td class="nowraponall">' . $linkedPropHtml . '</td>';
			print '<td>' . dolPrintHTML($leaseLabel) . '</td>';
			print '<td><code>' . dolPrintHTML((string) $obj->accountancy_code_sell) . '</code></td>';
			print '<td class="right">' . price($obj->price, 1, $langs, 1, -1, -1, 'auto') . '</td>';
			print '<td class="right">' . ($obj->dolisci_deposit !== null ? price($obj->dolisci_deposit, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
			print '<td>' . dolPrintHTML((string) $obj->dolisci_irl_ref) . '</td>';
			print '</tr>';
		}
		if ($nbServs === 0) {
			print '<tr class="oddeven"><td colspan="8" class="opacitymedium">' . $langs->trans('DoliSciNoRentalServiceLinked') . '</td></tr>';
		}
		print '</table></div>';
	}
}
