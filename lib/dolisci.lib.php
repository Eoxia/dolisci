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
 * \file    lib/dolisci.lib.php
 * \ingroup dolisci
 * \brief   Library files with common functions and data model definitions for DoliSCI
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function dolisciAdminPrepareHead(): array
{
	global $conf, $langs;

	$langs->load('dolisci@dolisci');

	$h    = 0;
	$head = [];

	$head[$h][0] = dol_buildpath('dolisci/admin/setup.php', 1);
	$head[$h][1] = '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('dolisci/admin/init.php', 1);
	$head[$h][1] = '<i class="fas fa-sync-alt pictofixedwidth"></i>' . $langs->trans('DoliSciInitialization');
	$head[$h][2] = 'init';
	$h++;

	$head[$h][0] = dol_buildpath('dolisci/admin/about.php', 1);
	$head[$h][1] = '<i class="fab fa-readme pictofixedwidth"></i>' . $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolisci@dolisci');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolisci@dolisci', 'remove');

	return $head;
}

/**
 * Synchronize the DoliSCI session & runtime environment variable (DOLISCI_ENV)
 * according to the current top/left menu navigation.
 *
 * @return bool True if the user is currently inside the DoliSCI environment
 */
function dolisciSyncEnvironment(): bool
{
	$reqMainMenu = GETPOST('mainmenu', 'aZ09');
	$reqLeftMenu = GETPOST('leftmenu', 'alphanohtml');
	$scriptPath  = (string) ($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));

	if ($reqMainMenu === 'dolisci' || strpos($reqLeftMenu, 'dolisci_') === 0 || strpos($scriptPath, '/dolisci/') !== false) {
		$_SESSION['DOLISCI_ENV'] = 1;
		$_SESSION['mainmenu']    = 'dolisci';
	} elseif ($reqMainMenu !== '') {
		$_SESSION['DOLISCI_ENV'] = 0;
	} elseif (isset($_SESSION['mainmenu'])) {
		$_SESSION['DOLISCI_ENV'] = ($_SESSION['mainmenu'] === 'dolisci') ? 1 : 0;
	}

	$inEnv = !empty($_SESSION['DOLISCI_ENV']);
	$_ENV['DOLISCI_ENV'] = $inEnv ? '1' : '0';
	if (function_exists('putenv')) {
		@putenv('DOLISCI_ENV=' . ($inEnv ? '1' : '0'));
	}

	if ($inEnv) {
		$_SESSION['pageforbacktolist']['project'] = dol_buildpath('/dolisci/project_list.php', 1);
	}

	return $inEnv;
}

/**
 * Check whether simplified DoliSCI cards should be displayed (when inside the DoliSCI environment
 * and DOLISCI_SIMPLIFIED_CARDS is enabled).
 *
 * @return bool True if simplified cards should be active
 */
function dolisciIsSimplifiedEnvironment(): bool
{
	$inEnv = dolisciSyncEnvironment();
	return $inEnv && getDolGlobalInt('DOLISCI_SIMPLIFIED_CARDS', 1) > 0;
}

/**
 * Return all DoliSCI extrafield definitions for Projects, Products (properties), and Services (rentals)
 * following Saturne/Evarisk extrafield conventions (positions prefixed by module numero 436371,
 * translation keys in select options, entity = 0, langfile = 'dolisci@dolisci', standard CSS classes).
 *
 * @return array<string,array<string,mixed>>
 */
function dolisciGetExtrafieldDefinitions(): array
{
	$moduleNumero         = 436371;
	$projectCondition     = "isModEnabled('dolisci') && isModEnabled('project')";
	$productCondition     = "isModEnabled('dolisci') && isModEnabled('product') && ((int) (\$objectoffield->type ?? \$object->type ?? 0) === 0)";
	$serviceCondition     = "isModEnabled('dolisci') && isModEnabled('service') && ((int) (\$objectoffield->type ?? \$object->type ?? 0) === 1)";
	$defaultCss           = ['css' => 'minwidth100 maxwidth300 widthcentpercentminusx'];
	$tagContactAddressId  = getDolGlobalInt('DOLISCI_TAG_CONTACT_ADDRESS');
	$contactAddressFilter = $tagContactAddressId > 0 ? '::(rowid:IN:SELECT fk_socpeople FROM ' . MAIN_DB_PREFIX . 'categorie_contact WHERE fk_categorie = ' . $tagContactAddressId . ')' : '';

	$dpeOptions = [
		'options' => [
			'A'  => 'A',
			'B'  => 'B',
			'C'  => 'C',
			'D'  => 'D',
			'E'  => 'E',
			'F'  => 'F',
			'G'  => 'G',
			'NA' => 'DoliSciDpeNotApplicable',
		],
	];

	return [
		// 0. Project extrafields (Real estate property address linked to Contact/Address tagged "Adresse-Bien")
		'ef:projet:dolisci_fk_socpeople' => [
			'group'       => 'project',
			'attrname'    => 'dolisci_fk_socpeople',
			'label'       => 'DoliSciPropertyAddress',
			'type'        => 'sellist',
			'pos'         => (int) ($moduleNumero . '05'),
			'size'        => '',
			'elementtype' => 'projet',
			'param'       => ['options' => ['socpeople:lastname|address|zip|town:rowid' . $contactAddressFilter => null]],
			'list'        => '1',
			'help'        => 'DoliSciPropertyAddressHelp',
			'enabled'     => $projectCondition,
			'moreparams'  => $defaultCss,
		],

		// 1. Product extrafields (type == 0: Purchased property & fiscal lot)
		'ef:product:dolisci_property_sep' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_property_sep',
			'label'       => 'DoliSciPropertySection',
			'type'        => 'separate',
			'pos'         => (int) ($moduleNumero . '05'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => ['options' => [1 => 1]],
			'list'        => '3',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => [],
		],
		'ef:product:dolisci_fk_project' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_fk_project',
			'label'       => 'DoliSciPropertyProject',
			'type'        => 'link',
			'pos'         => (int) ($moduleNumero . '10'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => ['options' => ['Project:projet/class/project.class.php:0:(t.entity:IN:__SHARED_ENTITIES__)' => null]],
			'list'        => '1',
			'help'        => 'DoliSciPropertyProjectHelp',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_property_nature' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_property_nature',
			'label'       => 'DoliSciPropertyNature',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '15'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => [
				'options' => [
					'IMMEUBLE'  => 'DoliSciNatureBuilding',
					'APPT'      => 'DoliSciNatureApartment',
					'MAISON'    => 'DoliSciNatureHouse',
					'LOCAL_PRO' => 'DoliSciNatureCommercial',
					'GARAGE'    => 'DoliSciNatureParking',
					'CAVE'      => 'DoliSciNatureCellar',
					'TERRAIN'   => 'DoliSciNatureLand',
				],
			],
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_legal_regime' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_legal_regime',
			'label'       => 'DoliSciLegalRegime',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '20'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => [
				'options' => [
					'COPRO' => 'DoliSciRegimeCoOwnership',
					'MONO'  => 'DoliSciRegimeSingleOwnership',
				],
			],
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_fiscal_lot' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_fiscal_lot',
			'label'       => 'DoliSciFiscalLot',
			'type'        => 'varchar',
			'pos'         => (int) ($moduleNumero . '25'),
			'size'        => '128',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => 'DoliSciFiscalLotHelp',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_tantiemes' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_tantiemes',
			'label'       => 'DoliSciTantiemes',
			'type'        => 'varchar',
			'pos'         => (int) ($moduleNumero . '30'),
			'size'        => '64',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => 'DoliSciTantiemesHelp',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_floor_door' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_floor_door',
			'label'       => 'DoliSciFloorDoor',
			'type'        => 'varchar',
			'pos'         => (int) ($moduleNumero . '35'),
			'size'        => '128',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_rooms' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_rooms',
			'label'       => 'DoliSciRooms',
			'type'        => 'int',
			'pos'         => (int) ($moduleNumero . '40'),
			'size'        => '10',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_dpe_energy' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_dpe_energy',
			'label'       => 'DoliSciDpeEnergy',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '45'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => $dpeOptions,
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_dpe_ges' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_dpe_ges',
			'label'       => 'DoliSciDpeGes',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '50'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => $dpeOptions,
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_purchase_date' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_purchase_date',
			'label'       => 'DoliSciPurchaseDate',
			'type'        => 'date',
			'pos'         => (int) ($moduleNumero . '55'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_purchase_price' => [
			'group'       => 'product',
			'attrname'    => 'dolisci_purchase_price',
			'label'       => 'DoliSciPurchasePrice',
			'type'        => 'price',
			'pos'         => (int) ($moduleNumero . '60'),
			'size'        => '24,8',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => '',
			'enabled'     => $productCondition,
			'moreparams'  => $defaultCss,
		],

		// 3. Service extrafields (type == 1: Rental services - Rent & Charges provision)
		'ef:product:dolisci_rental_sep' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_rental_sep',
			'label'       => 'DoliSciRentalSection',
			'type'        => 'separate',
			'pos'         => (int) ($moduleNumero . '65'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => ['options' => [1 => 1]],
			'list'        => '3',
			'help'        => '',
			'enabled'     => $serviceCondition,
			'moreparams'  => [],
		],
		'ef:product:dolisci_service_nature' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_service_nature',
			'label'       => 'DoliSciServiceNature',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '70'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => [
				'options' => [
					'LOYER'          => 'DoliSciServiceNatureRent',
					'CHARGES'        => 'DoliSciServiceNatureCharges',
					'REGUL_CHARGES'  => 'DoliSciServiceNatureChargesRegul',
					'DEPOT_GARANTIE' => 'DoliSciServiceNatureDeposit',
				],
			],
			'list'        => '1',
			'help'        => 'DoliSciServiceNatureHelp',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_fk_property_product' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_fk_property_product',
			'label'       => 'DoliSciLinkedPropertyProduct',
			'type'        => 'link',
			'pos'         => (int) ($moduleNumero . '75'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => ['options' => ['Product:product/class/product.class.php:0:(t.entity:=:__ENTITY__) AND (t.fk_product_type:=:0)' => null]],
			'list'        => '1',
			'help'        => 'DoliSciLinkedPropertyProductHelp',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_service_fk_project' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_service_fk_project',
			'label'       => 'DoliSciPropertyProject',
			'type'        => 'link',
			'pos'         => (int) ($moduleNumero . '80'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => ['options' => ['Project:projet/class/project.class.php:0:(t.entity:IN:__SHARED_ENTITIES__)' => null]],
			'list'        => '1',
			'help'        => 'DoliSciServiceProjectHelp',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_lease_type' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_lease_type',
			'label'       => 'DoliSciLeaseType',
			'type'        => 'select',
			'pos'         => (int) ($moduleNumero . '85'),
			'size'        => '',
			'elementtype' => 'product',
			'param'       => [
				'options' => [
					'NU'      => 'DoliSciLeaseUnfurnished',
					'MEUBLE'  => 'DoliSciLeaseFurnished',
					'COLOC'   => 'DoliSciLeaseShared',
					'PRO'     => 'DoliSciLeaseCommercial',
					'PARKING' => 'DoliSciLeaseParking',
				],
			],
			'list'        => '1',
			'help'        => '',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_deposit' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_deposit',
			'label'       => 'DoliSciDeposit',
			'type'        => 'price',
			'pos'         => (int) ($moduleNumero . '90'),
			'size'        => '24,8',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => '',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
		'ef:product:dolisci_irl_ref' => [
			'group'       => 'service',
			'attrname'    => 'dolisci_irl_ref',
			'label'       => 'DoliSciIrlRef',
			'type'        => 'varchar',
			'pos'         => (int) ($moduleNumero . '95'),
			'size'        => '64',
			'elementtype' => 'product',
			'param'       => '',
			'list'        => '1',
			'help'        => 'DoliSciIrlRefHelp',
			'enabled'     => $serviceCondition,
			'moreparams'  => $defaultCss,
		],
	];
}

/**
 * Return all DoliSCI project contact role definitions
 *
 * @return array<string,array{code:string,label:string,position:int}>
 */
function dolisciGetContactRoleDefinitions(): array
{
	$moduleNumero = '436371';

	return [
		'ct:DOLISCI_PROPERTY_ADDRESS' => [
			'code'     => 'DOLISCI_PROPERTY_ADDRESS',
			'label'    => 'Adresse du bien immobilier',
			'position' => (int) ($moduleNumero . '10'),
		],
		'ct:DOLISCI_TENANT' => [
			'code'     => 'DOLISCI_TENANT',
			'label'    => 'Locataire',
			'position' => (int) ($moduleNumero . '20'),
		],
		'ct:DOLISCI_COTENANT' => [
			'code'     => 'DOLISCI_COTENANT',
			'label'    => 'Colocataire',
			'position' => (int) ($moduleNumero . '30'),
		],
		'ct:DOLISCI_GUARANTOR' => [
			'code'     => 'DOLISCI_GUARANTOR',
			'label'    => 'Garant / Caution',
			'position' => (int) ($moduleNumero . '40'),
		],
		'ct:DOLISCI_SYNDIC' => [
			'code'     => 'DOLISCI_SYNDIC',
			'label'    => 'Syndic de copropriété',
			'position' => (int) ($moduleNumero . '50'),
		],
		'ct:DOLISCI_NOTARY' => [
			'code'     => 'DOLISCI_NOTARY',
			'label'    => 'Notaire',
			'position' => (int) ($moduleNumero . '60'),
		],
	];
}

/**
 * Return all DoliSCI Tag / Category definitions used to isolate Real Estate Projects,
 * Property Products (fiscal lots), Rent Services (706100), and Charges Services (708800).
 *
 * @return array<string,array{const:string,type:string,label:string,color:string,icon:string,position:int}>
 */
function dolisciGetTagDefinitions(): array
{
	$moduleNumero = '436371';

	return [
		'tag:DOLISCI_TAG_PROJECT_PROPERTY' => [
			'const'    => 'DOLISCI_TAG_PROJECT_PROPERTY',
			'type'     => 'project',
			'label'    => 'DoliSciTagProjectProperty',
			'color'    => '0284c7',
			'icon'     => 'fa-city',
			'position' => (int) ($moduleNumero . '10'),
		],
		'tag:DOLISCI_TAG_PRODUCT_PROPERTY' => [
			'const'    => 'DOLISCI_TAG_PRODUCT_PROPERTY',
			'type'     => 'product',
			'label'    => 'DoliSciTagProductProperty',
			'color'    => '0d9488',
			'icon'     => 'fa-key',
			'position' => (int) ($moduleNumero . '20'),
		],
		'tag:DOLISCI_TAG_SERVICE_RENT' => [
			'const'    => 'DOLISCI_TAG_SERVICE_RENT',
			'type'     => 'product',
			'label'    => 'DoliSciTagServiceRent',
			'color'    => '16a34a',
			'icon'     => 'fa-file-invoice-dollar',
			'position' => (int) ($moduleNumero . '30'),
		],
		'tag:DOLISCI_TAG_SERVICE_CHARGES' => [
			'const'    => 'DOLISCI_TAG_SERVICE_CHARGES',
			'type'     => 'product',
			'label'    => 'DoliSciTagServiceCharges',
			'color'    => 'd97706',
			'icon'     => 'fa-file-invoice-dollar',
			'position' => (int) ($moduleNumero . '40'),
		],
		'tag:DOLISCI_TAG_SERVICE_DEPOSIT' => [
			'const'    => 'DOLISCI_TAG_SERVICE_DEPOSIT',
			'type'     => 'product',
			'label'    => 'DoliSciTagServiceDeposit',
			'color'    => '0891b2',
			'icon'     => 'fa-file-invoice-dollar',
			'position' => (int) ($moduleNumero . '42'),
		],
		'tag:DOLISCI_TAG_CUSTOMER_TENANT' => [
			'const'    => 'DOLISCI_TAG_CUSTOMER_TENANT',
			'type'     => 'customer',
			'label'    => 'DoliSciTagCustomerTenant',
			'color'    => '2563eb',
			'icon'     => 'fa-user',
			'position' => (int) ($moduleNumero . '45'),
		],
		'tag:DOLISCI_TAG_CONTACT_ADDRESS' => [
			'const'    => 'DOLISCI_TAG_CONTACT_ADDRESS',
			'type'     => 'contact',
			'label'    => 'DoliSciTagContactPropertyAddress',
			'color'    => '7c3aed',
			'icon'     => 'fa-map-marker-alt',
			'position' => (int) ($moduleNumero . '50'),
		],
		'tag:DOLISCI_TAG_CONTACT_TENANT' => [
			'const'    => 'DOLISCI_TAG_CONTACT_TENANT',
			'type'     => 'contact',
			'label'    => 'DoliSciTagContactTenant',
			'color'    => '2563eb',
			'icon'     => 'fa-user',
			'position' => (int) ($moduleNumero . '55'),
		],
		'tag:DOLISCI_TAG_CONTACT_GUARANTOR' => [
			'const'    => 'DOLISCI_TAG_CONTACT_GUARANTOR',
			'type'     => 'contact',
			'label'    => 'DoliSciTagContactGuarantor',
			'color'    => '0891b2',
			'icon'     => 'fa-user-shield',
			'position' => (int) ($moduleNumero . '65'),
		],
		'tag:DOLISCI_TAG_CONTACT_SYNDIC' => [
			'const'    => 'DOLISCI_TAG_CONTACT_SYNDIC',
			'type'     => 'contact',
			'label'    => 'DoliSciTagContactSyndic',
			'color'    => 'e11d48',
			'icon'     => 'fa-building',
			'position' => (int) ($moduleNumero . '70'),
		],
		'tag:DOLISCI_TAG_CONTACT_NOTARY' => [
			'const'    => 'DOLISCI_TAG_CONTACT_NOTARY',
			'type'     => 'contact',
			'label'    => 'DoliSciTagContactNotary',
			'color'    => '475569',
			'icon'     => 'fa-gavel',
			'position' => (int) ($moduleNumero . '75'),
		],
	];
}

/**
 * Return the category ID for a DoliSCI tag constant, automatically creating/syncing it if not yet initialized
 *
 * @param  DoliDB $db        Database handler
 * @param  string $constName Tag constant name (e.g. 'DOLISCI_TAG_SERVICE_DEPOSIT')
 * @return int               Category ID (>0) or 0
 */
function dolisciEnsureTagId(DoliDB $db, string $constName): int
{
	$tagId = getDolGlobalInt($constName);
	if ($tagId > 0) {
		return $tagId;
	}
	$itemKey = 'tag:' . $constName;
	$tagDefs = dolisciGetTagDefinitions();
	if (isset($tagDefs[$itemKey])) {
		dolisciSyncItem($db, $itemKey);
		return getDolGlobalInt($constName);
	}
	return 0;
}

/**
 * Assign a DoliSCI category/tag (by its constant name) to a Dolibarr object if not already linked
 *
 * @param  DoliDB       $db        Database handler
 * @param  CommonObject $object    Target object (Project or Product)
 * @param  string       $constName Constant storing the category ID
 * @param  string       $categType Category type ('project' or 'product')
 * @return int                     1 if OK, <=0 if skipped/KO
 */
function dolisciAssignTag(DoliDB $db, CommonObject $object, string $constName, string $categType): int
{
	$tagId = dolisciEnsureTagId($db, $constName);
	if ($tagId <= 0 || empty($object->id)) {
		return 0;
	}

	// When cloning an object with "clone_categories" enabled, Dolibarr's CommonObject::cloneCategories()
	// executes a raw INSERT INTO llx_categorie_* SELECT ... right after create($user) without ignoring
	// existing rows. Skip inserting the tag during createfromclone so cloneCategories() does not fail
	// with a PRIMARY KEY duplicate error.
	if (!empty($object->context['createfromclone']) && GETPOST('clone_categories', 'alphanohtml')) {
		return 0;
	}

	require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
	$cat = new Categorie($db);
	if ($cat->fetch($tagId) > 0) {
		return $cat->add_type($object, $categType) >= 0 ? 1 : -1;
	}

	return 0;
}

/**
 * Check whether a Project is tagged with the DoliSCI real estate project tag (DOLISCI_TAG_PROJECT_PROPERTY)
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project ID
 * @return bool              True if tagged as DoliSCI real estate project
 */
function dolisciIsRealEstateProject(DoliDB $db, int $projectId): bool
{
	$tagId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
	if ($tagId <= 0 || $projectId <= 0) {
		return false;
	}

	$sql = 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_project WHERE fk_project = ' . ((int) $projectId) . ' AND fk_categorie = ' . ((int) $tagId) . ' LIMIT 1';
	$res = $db->query($sql);

	return ($res && $db->num_rows($res) > 0);
}

/**
 * Check whether a Product (type = 0) is a DoliSCI Property Product
 *
 * @param  DoliDB  $db      Database handler
 * @param  Product $product Product instance
 * @return bool             True if DoliSCI property product
 */
function dolisciIsPropertyProduct(DoliDB $db, Product $product): bool
{
	if ((int) $product->type !== Product::TYPE_PRODUCT || empty($product->id)) {
		return false;
	}
	if (empty($product->array_options)) {
		$product->fetch_optionals();
	}
	if (!empty($product->array_options['options_dolisci_fk_project']) || !empty($product->array_options['options_dolisci_property_nature'])) {
		return true;
	}

	$tagProductId = getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY');
	if ($tagProductId > 0) {
		$sql = 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_product WHERE fk_product = ' . ((int) $product->id) . ' AND fk_categorie = ' . ((int) $tagProductId) . ' LIMIT 1';
		$res = $db->query($sql);
		if ($res && $db->num_rows($res) > 0) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether a Service (type = 1) is a DoliSCI Rental / Charges / Deposit Service
 *
 * @param  DoliDB  $db      Database handler
 * @param  Product $service Product instance (type = 1)
 * @return bool             True if DoliSCI rental/charges/deposit service
 */
function dolisciIsRentalService(DoliDB $db, Product $service): bool
{
	if ((int) $service->type !== Product::TYPE_SERVICE || empty($service->id)) {
		return false;
	}
	if (empty($service->array_options)) {
		$service->fetch_optionals();
	}
	if (!empty($service->array_options['options_dolisci_fk_property_product'])
		|| !empty($service->array_options['options_dolisci_service_fk_project'])
		|| !empty($service->array_options['options_dolisci_service_nature'])
	) {
		return true;
	}
	if (preg_match('/^(?:Loyer|Charges?|Caution)(?:-[0-9]+)?_/i', (string) $service->ref)) {
		return true;
	}

	$serviceTags = array_values(array_filter([
		getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT'),
		getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES'),
		getDolGlobalInt('DOLISCI_TAG_SERVICE_DEPOSIT'),
	]));
	if (!empty($serviceTags)) {
		$sql = 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_product WHERE fk_product = ' . ((int) $service->id)
			. ' AND fk_categorie IN (' . implode(',', array_map('intval', $serviceTags)) . ') LIMIT 1';
		$res = $db->query($sql);
		if ($res && $db->num_rows($res) > 0) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve the Property Product (type = 0) from either a Property Product or a linked DoliSCI Service (type = 1)
 *
 * @param  DoliDB  $db      Database handler
 * @param  Product $product Product or Service instance
 * @return Product|null     Property Product instance (type = 0) or null if not found
 */
function dolisciGetPropertyFromService(DoliDB $db, Product $product): ?Product
{
	if ((int) $product->type === Product::TYPE_PRODUCT && !empty($product->id)) {
		if (empty($product->array_options)) {
			$product->fetch_optionals();
		}
		return $product;
	}

	if ((int) $product->type !== Product::TYPE_SERVICE || empty($product->id)) {
		return null;
	}
	if (empty($product->array_options)) {
		$product->fetch_optionals();
	}

	$propId = (int) ($product->array_options['options_dolisci_fk_property_product'] ?? 0);
	if ($propId > 0) {
		$prop = new Product($db);
		if ($prop->fetch($propId) > 0 && (int) $prop->type === Product::TYPE_PRODUCT) {
			$prop->fetch_optionals();
			return $prop;
		}
	}

	if (preg_match('/^(?:Loyer|Charges?|Caution)(?:-[0-9]+)?_(.+?)(?:_[0-9]+)?$/i', (string) $product->ref, $matches)) {
		$propRef = trim($matches[1]);
		if ($propRef !== '') {
			$prop = new Product($db);
			if ($prop->fetch(0, $propRef) > 0 && (int) $prop->type === Product::TYPE_PRODUCT) {
				$prop->fetch_optionals();
				return $prop;
			}
		}
	}
	if (preg_match('/^(?:Loyer|Charges?|Caution)(?:-[0-9]+)?_(.+)$/i', (string) $product->ref, $matches)) {
		$propRef = trim($matches[1]);
		if ($propRef !== '') {
			$prop = new Product($db);
			if ($prop->fetch(0, $propRef) > 0 && (int) $prop->type === Product::TYPE_PRODUCT) {
				$prop->fetch_optionals();
				return $prop;
			}
		}
	}

	return null;
}

/**
 * Extract the rental group index (1, 2, 3...) from a Rental Service reference (e.g. Loyer-1_<ref> -> 1, Loyer-2_<ref> -> 2)
 *
 * @param  string $ref Service reference
 * @return int         Group index (>= 1)
 */
function dolisciGetServiceGroupIndexFromRef(string $ref): int
{
	if (preg_match('/^(?:Loyer|Charges?|Caution)(?:-([0-9]+))?_(.+?)(?:_([0-9]+))?$/i', $ref, $m)) {
		if (!empty($m[1])) {
			return (int) $m[1];
		}
		if (!empty($m[3])) {
			return (int) $m[3];
		}
	}
	return 1;
}

/**
 * Return only the Charges and Deposit (Caution) services attached to the same rental group as the given Service
 *
 * @param  DoliDB       $db          Database handler
 * @param  Product      $service     Rental service instance (type = 1, e.g. Loyer-1_<ref>)
 * @param  Product|null $propProduct Optional resolved Property Product instance (type = 0)
 * @return array<int,stdClass>       List of child services (Charge & Caution) for this service's group
 */
function dolisciGetServiceChildServices(DoliDB $db, Product $service, ?Product $propProduct = null): array
{
	if ((int) $service->type !== Product::TYPE_SERVICE || empty($service->id)) {
		return [];
	}
	if ($propProduct === null) {
		$propProduct = dolisciGetPropertyFromService($db, $service);
	}
	if (!($propProduct instanceof Product) || empty($propProduct->id)) {
		return [];
	}

	$rentalData    = dolisciGetPropertyRentalServices($db, $propProduct);
	$allServices   = $rentalData['services'] ?? [];
	$targetGroup   = dolisciGetServiceGroupIndexFromRef((string) $service->ref);
	$childServices = [];

	foreach ($allServices as $obj) {
		$grpIdx     = (int) ($obj->dolisci_group_index ?? 1);
		$natureRank = (int) ($obj->dolisci_nature_rank ?? 4);
		if ($grpIdx === $targetGroup && $natureRank > 1 && (int) $obj->rowid !== (int) $service->id) {
			$childServices[] = $obj;
		}
	}

	return $childServices;
}

/**
 * Return a link to a Project, Property Product (type = 0) or Rental Service (type = 1)
 * using the DoliSCI pictos (fa-city for Project, fa-key for Property, fa-file-invoice-dollar for Service).
 *
 * @param  CommonObject $object        Project or Product/Service instance
 * @param  string       $serviceNature Optional service nature ('LOYER', 'CHARGES', 'DEPOT_GARANTIE')
 * @return string                      HTML link with DoliSCI picto
 */
function dolisciGetObjectNomUrl(CommonObject $object, string $serviceNature = ''): string
{
	if ($object instanceof Project) {
		return '<i class="fas fa-city pictofixedwidth" style="color: #0284c7;"></i>' . $object->getNomUrl(0);
	}
	if ($object instanceof Product) {
		if ((int) $object->type === Product::TYPE_PRODUCT) {
			return '<i class="fas fa-key pictofixedwidth" style="color: #0d9488;"></i>' . $object->getNomUrl(0);
		}
		$ref   = (string) ($object->ref ?? '');
		$color = '#16a34a';
		if ($serviceNature === 'CHARGES' || $serviceNature === 'CHARGES_REGUL' || stripos($ref, 'Charge_') === 0 || stripos($ref, 'Charge-') === 0 || stripos($ref, 'Charges_') === 0 || stripos($ref, 'Charges-') === 0) {
			$color = '#d97706';
		} elseif ($serviceNature === 'DEPOT_GARANTIE' || $serviceNature === 'CAUTION' || stripos($ref, 'Caution_') === 0 || stripos($ref, 'Caution-') === 0) {
			$color = '#0284c7';
		}
		return '<i class="fas fa-file-invoice-dollar pictofixedwidth" style="color: ' . $color . ';"></i>' . $object->getNomUrl(0);
	}
	return method_exists($object, 'getNomUrl') ? $object->getNomUrl(1) : '';
}

/**
 * Sort and annotate rental services by rental group (1, 2, 3...) and by nature within each group (1: Loyer, 2: Charge, 3: Caution)
 *
 * @param  array<int,stdClass> $services List of service rows (modified in-place)
 * @return void
 */
function dolisciSortAndGroupRentalServices(array &$services): void
{
	foreach ($services as $obj) {
		$nature = (string) ($obj->dolisci_service_nature ?? '');
		$ref    = (string) ($obj->ref ?? '');
		$acc    = (string) ($obj->accountancy_code_sell ?? '');

		$natureRank = 4;
		if ($nature === 'LOYER' || stripos($ref, 'Loyer_') === 0 || stripos($ref, 'Loyer-') === 0 || $acc === '706100') {
			$natureRank = 1;
		} elseif ($nature === 'CHARGES' || stripos($ref, 'Charge_') === 0 || stripos($ref, 'Charge-') === 0 || stripos($ref, 'Charges_') === 0 || stripos($ref, 'Charges-') === 0 || $acc === '708800') {
			$natureRank = 2;
		} elseif ($nature === 'DEPOT_GARANTIE' || $nature === 'CAUTION' || stripos($ref, 'Caution_') === 0 || stripos($ref, 'Caution-') === 0 || $acc === '165000') {
			$natureRank = 3;
		}

		$groupIndex = 1;
		$propKey    = '';
		if (preg_match('/^(?:Loyer|Charges?|Caution)(?:-([0-9]+))?_(.+?)(?:_([0-9]+))?$/i', $ref, $m)) {
			if (!empty($m[1])) {
				$groupIndex = (int) $m[1];
			} elseif (!empty($m[3])) {
				$groupIndex = (int) $m[3];
			}
			$propKey = strtolower(trim($m[2]));
		}

		$fkProp = (int) ($obj->dolisci_fk_property_product ?? 0);
		$obj->dolisci_group_index = $groupIndex;
		$obj->dolisci_nature_rank = $natureRank;
		$obj->dolisci_is_child    = ($natureRank > 1);
		$obj->dolisci_prop_key    = $fkProp > 0 ? ('id_' . $fkProp) : ('ref_' . $propKey);
	}

	usort($services, static function ($a, $b): int {
		$propCmp = strcmp((string) ($a->dolisci_prop_key ?? ''), (string) ($b->dolisci_prop_key ?? ''));
		if ($propCmp !== 0) {
			return $propCmp;
		}
		$grpCmp = ((int) ($a->dolisci_group_index ?? 1)) <=> ((int) ($b->dolisci_group_index ?? 1));
		if ($grpCmp !== 0) {
			return $grpCmp;
		}
		$rankCmp = ((int) ($a->dolisci_nature_rank ?? 4)) <=> ((int) ($b->dolisci_nature_rank ?? 4));
		if ($rankCmp !== 0) {
			return $rankCmp;
		}
		return strcasecmp((string) ($a->ref ?? ''), (string) ($b->ref ?? ''));
	});
}

/**
 * Fetch linked rental, charges & deposit services for a given Property Product
 *
 * @param  DoliDB  $db              Database handler
 * @param  Product $propertyProduct Property product instance (type = 0)
 * @return array{count:int,rentCount:int,hasRent:bool,hasCharges:bool,hasDeposit:bool,hasAll:bool,hasBoth:bool,services:array<int,stdClass>}
 */
function dolisciGetPropertyRentalServices(DoliDB $db, Product $propertyProduct): array
{
	$result = [
		'count'      => 0,
		'rentCount'  => 0,
		'hasRent'    => false,
		'hasCharges' => false,
		'hasDeposit' => false,
		'hasAll'     => false,
		'hasBoth'    => false,
		'services'   => [],
	];

	if (empty($propertyProduct->id) || (int) $propertyProduct->type !== Product::TYPE_PRODUCT) {
		return $result;
	}

	$escapedPropRef = $db->escape($propertyProduct->ref);
	$rentPrefix     = 'Loyer_' . $escapedPropRef;
	$chargesPrefix  = 'Charge_' . $escapedPropRef;
	$charges2Prefix = 'Charges_' . $escapedPropRef;
	$depositPrefix  = 'Caution_' . $escapedPropRef;

	$sql = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.price, p.tva_tx, p.accountancy_code_sell,'
		. ' ef.dolisci_service_nature, ef.dolisci_fk_property_product, ef.dolisci_service_fk_project,'
		. ' pr.rowid AS pr_id, pr.ref AS pr_ref, pr.title AS pr_title, pr.fk_statut AS pr_status'
		. ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
		. ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
		. ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet AS pr ON pr.rowid = ef.dolisci_service_fk_project'
		. ' WHERE p.entity IN (' . getEntity('product') . ')'
		. ' AND p.fk_product_type = 1'
		. ' AND (ef.dolisci_fk_property_product = ' . ((int) $propertyProduct->id)
		. " OR p.ref = '" . $rentPrefix . "' OR p.ref LIKE '" . $rentPrefix . "\\_%' OR p.ref LIKE 'Loyer-%\\_" . $escapedPropRef . "'"
		. " OR p.ref = '" . $chargesPrefix . "' OR p.ref LIKE '" . $chargesPrefix . "\\_%' OR p.ref LIKE 'Charge-%\\_" . $escapedPropRef . "'"
		. " OR p.ref = '" . $charges2Prefix . "' OR p.ref LIKE '" . $charges2Prefix . "\\_%' OR p.ref LIKE 'Charges-%\\_" . $escapedPropRef . "'"
		. " OR p.ref = '" . $depositPrefix . "' OR p.ref LIKE '" . $depositPrefix . "\\_%' OR p.ref LIKE 'Caution-%\\_" . $escapedPropRef . "')"
		. " ORDER BY p.ref ASC LIMIT 100";

	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$result['services'][] = $obj;
			$nature = (string) ($obj->dolisci_service_nature ?? '');
			$ref    = (string) ($obj->ref ?? '');
			$acc    = (string) ($obj->accountancy_code_sell ?? '');

			if ($nature === 'LOYER' || stripos($ref, 'Loyer_') === 0 || stripos($ref, 'Loyer-') === 0 || $acc === '706100') {
				$result['hasRent'] = true;
				$result['rentCount']++;
			}
			if ($nature === 'CHARGES' || stripos($ref, 'Charge_') === 0 || stripos($ref, 'Charge-') === 0 || stripos($ref, 'Charges_') === 0 || stripos($ref, 'Charges-') === 0 || $acc === '708800') {
				$result['hasCharges'] = true;
			}
			if ($nature === 'DEPOT_GARANTIE' || $nature === 'CAUTION' || stripos($ref, 'Caution_') === 0 || stripos($ref, 'Caution-') === 0 || $acc === '165000') {
				$result['hasDeposit'] = true;
			}
		}
	}

	dolisciSortAndGroupRentalServices($result['services']);

	$result['count']   = count($result['services']);
	$result['hasAll']  = ($result['hasRent'] && $result['hasCharges'] && $result['hasDeposit']);
	$result['hasBoth'] = $result['hasAll'];

	return $result;
}

/**
 * Count linked Property Products (type = 0) for a given Real Estate Project (including sub-projects if enabled)
 *
 * @param  DoliDB  $db      Database handler
 * @param  Project $project Project instance
 * @return int              Number of linked property products
 */
function dolisciGetProjectPropertiesCount(DoliDB $db, Project $project): int
{
	if (empty($project->id)) {
		return 0;
	}

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

	$sql = 'SELECT COUNT(p.rowid) AS nb'
		. ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
		. ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
		. ' WHERE p.entity IN (' . getEntity('product') . ')'
		. ' AND p.fk_product_type = 0'
		. ' AND ef.dolisci_fk_project IN (' . $inProjectIds . ')';

	$res = $db->query($sql);
	if ($res && ($obj = $db->fetch_object($res))) {
		return (int) $obj->nb;
	}

	return 0;
}

/**
 * Create or update a single DoliSCI extrafield, contact role, or tag by its item key
 * following Saturne/Evarisk pattern (addExtraField + updateExtraField).
 *
 * @param  DoliDB $db      Database handler
 * @param  string $itemKey Item key ('ef:...', 'ct:...', or 'tag:...')
 * @return int             1 if OK, <=0 if KO
 */
function dolisciSyncItem(DoliDB $db, string $itemKey): int
{
	global $conf, $langs, $user;

	require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
	require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

	$efDefs = dolisciGetExtrafieldDefinitions();
	if (isset($efDefs[$itemKey])) {
		$def         = $efDefs[$itemKey];
		$extraFields = new ExtraFields($db);
		$moreparams  = is_array($def['moreparams'] ?? null) ? $def['moreparams'] : [];

		// 1. Add extrafield if not yet present
		$resAdd = $extraFields->addExtraField(
			(string) $def['attrname'],
			(string) $def['label'],
			(string) $def['type'],
			(int) $def['pos'],
			(string) $def['size'],
			(string) $def['elementtype'],
			0,
			0,
			'',
			$def['param'],
			1,
			'',
			(string) $def['list'],
			(string) $def['help'],
			'',
			'0',
			'dolisci@dolisci',
			(string) $def['enabled'],
			0,
			0,
			$moreparams
		);
		if ($resAdd < 0) {
			return $resAdd;
		}

		// 2. Always synchronize definition (pos, label, param, enabled, css, etc.) as in saturne_manage_extrafields()
		return $extraFields->updateExtraField(
			(string) $def['attrname'],
			(string) $def['label'],
			(string) $def['type'],
			(int) $def['pos'],
			(string) $def['size'],
			(string) $def['elementtype'],
			0,
			0,
			'',
			$def['param'],
			1,
			'',
			(string) $def['list'],
			(string) $def['help'],
			'',
			'0',
			'dolisci@dolisci',
			(string) $def['enabled'],
			0,
			0,
			$moreparams
		);
	}

	$ctDefs = dolisciGetContactRoleDefinitions();
	if (isset($ctDefs[$itemKey])) {
		$def  = $ctDefs[$itemKey];
		$code = $db->escape($def['code']);
		$lbl  = $db->escape($def['label']);
		$pos  = (int) $def['position'];

		$sqlCheck = "SELECT rowid FROM " . MAIN_DB_PREFIX . "c_type_contact WHERE element = 'project' AND source = 'external' AND code = '" . $code . "'";
		$resCheck = $db->query($sqlCheck);
		if ($resCheck && $db->num_rows($resCheck) > 0) {
			$sqlUpd = "UPDATE " . MAIN_DB_PREFIX . "c_type_contact SET active = 1, libelle = '" . $lbl . "', position = " . $pos . " WHERE element = 'project' AND source = 'external' AND code = '" . $code . "'";
			return $db->query($sqlUpd) ? 1 : -1;
		}
		$sqlIns = "INSERT INTO " . MAIN_DB_PREFIX . "c_type_contact (element, source, code, libelle, active, module, position) VALUES ('project', 'external', '" . $code . "', '" . $lbl . "', 1, 'dolisci', " . $pos . ")";
		return $db->query($sqlIns) ? 1 : -1;
	}

	$tagDefs = dolisciGetTagDefinitions();
	if (isset($tagDefs[$itemKey])) {
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		$tagLangs = new Translate('', $conf);
		$tagLangs->setDefaultLang('fr_FR');
		$tagLangs->load('dolisci@dolisci');

		$category   = new Categorie($db);
		$def        = $tagDefs[$itemKey];
		$tagLabel   = $tagLangs->transnoentities((string) $def['label']);
		$typeNum    = (int) ($category->MAP_ID[$def['type']] ?? 0);
		$existingId = getDolGlobalInt($def['const']);

		// 1. Check if stored constant ID still exists in llx_categorie
		if ($existingId > 0) {
			$sqlCat = "SELECT rowid FROM " . MAIN_DB_PREFIX . "categorie WHERE rowid = " . ((int) $existingId);
			$resCat = $db->query($sqlCat);
			if ($resCat && $db->num_rows($resCat) > 0) {
				$sqlUpd = "UPDATE " . MAIN_DB_PREFIX . "categorie SET label = '" . $db->escape($tagLabel) . "', color = '" . $db->escape($def['color']) . "', visible = 1 WHERE rowid = " . ((int) $existingId);
				$db->query($sqlUpd);
				return 1;
			}
		}

		// 2. Check if a category with this label and type already exists in current entity
		$sqlFind = "SELECT rowid FROM " . MAIN_DB_PREFIX . "categorie WHERE entity IN (" . getEntity('category') . ") AND type = " . ((int) $typeNum) . " AND label = '" . $db->escape($tagLabel) . "' LIMIT 1";
		$resFind = $db->query($sqlFind);
		if ($resFind && ($objFind = $db->fetch_object($resFind))) {
			$foundId = (int) $objFind->rowid;
			$sqlUpd  = "UPDATE " . MAIN_DB_PREFIX . "categorie SET color = '" . $db->escape($def['color']) . "', visible = 1 WHERE rowid = " . $foundId;
			$db->query($sqlUpd);
			dolibarr_set_const($db, $def['const'], $foundId, 'integer', 0, '', $conf->entity);
			return 1;
		}

		// 3. Create new category and store its positive ID in llx_const
		$category->label   = $tagLabel;
		$category->type    = $def['type'];
		$category->color   = $def['color'];
		$category->visible = 1;
		$catId             = $category->create($user);
		if ($catId > 0) {
			dolibarr_set_const($db, $def['const'], $catId, 'integer', 0, '', $conf->entity);
			return 1;
		}

		return -1;
	}

	return 0;
}

/**
 * Disable/hide a single DoliSCI extrafield or contact role by its item key
 *
 * @param  DoliDB $db      Database handler
 * @param  string $itemKey Item key ('ef:...' or 'ct:...')
 * @return int             1 if OK, <=0 if KO
 */
function dolisciDisableItem(DoliDB $db, string $itemKey): int
{
	$efDefs = dolisciGetExtrafieldDefinitions();
	if (isset($efDefs[$itemKey])) {
		$def  = $efDefs[$itemKey];
		$name = $db->escape((string) $def['attrname']);
		$elem = $db->escape((string) $def['elementtype']);
		$sql  = "UPDATE " . MAIN_DB_PREFIX . "extrafields SET list = '0' WHERE elementtype = '" . $elem . "' AND name = '" . $name . "'";
		return $db->query($sql) ? 1 : -1;
	}

	$ctDefs = dolisciGetContactRoleDefinitions();
	if (isset($ctDefs[$itemKey])) {
		$def  = $ctDefs[$itemKey];
		$code = $db->escape($def['code']);
		$sql  = "UPDATE " . MAIN_DB_PREFIX . "c_type_contact SET active = 0 WHERE element = 'project' AND source = 'external' AND code = '" . $code . "'";
		return $db->query($sql) ? 1 : -1;
	}

	return 0;
}
