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
 * \file    product_services.php
 * \ingroup dolisci
 * \brief   Tab on Property Product card showing linked Rental & Charges services
 */

// Load DoliSci environment
if (file_exists('dolisci.main.inc.php')) {
	require_once __DIR__ . '/dolisci.main.inc.php';
} elseif (file_exists('../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../dolisci.main.inc.php';
} else {
	die('Include of dolisci main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once __DIR__ . '/lib/dolisci.lib.php';

/**
 * @var Conf        $conf
 * @var DoliDB      $db
 * @var HookManager $hookmanager
 * @var Translate   $langs
 * @var User        $user
 */

$langs->loadLangs(['products', 'projects', 'bills', 'categories', 'dolisci@dolisci']);

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new Product($db);
if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
	$object->fetch_optionals();
}

// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype  = (!empty($ref) ? 'ref' : 'rowid');
if ($user->socid) {
	$socid = $user->socid;
}
restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);

$hookmanager->initHooks(['productcard', 'dolisciproductservices', 'globalcard']);

/*
 * Actions
 */

$parameters = ['id' => $object->id];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */

$form  = new Form($db);
$title = $langs->trans('ProductServiceCard') . ' - ' . $langs->trans('DoliSciTabRentalServices');

llxHeader('', $title, '', '', 0, 0, '', ['/dolisci/css/dolisci.css']);

if ($object->id > 0) {
	$isService   = ((int) $object->type === Product::TYPE_SERVICE);
	$propProduct = dolisciGetPropertyFromService($db, $object);

	$head = product_prepare_head($object);
	print dol_get_fiche_head($head, 'dolisci_services', $langs->trans($isService ? 'Service' : 'Product'), -1, $isService ? 'service' : 'product');

	$parameters = [];
	$reshook    = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
	if (!empty($hookmanager->resPrint)) {
		print $hookmanager->resPrint;
	}

	$listPage = $isService ? '/dolisci/service_list.php' : '/dolisci/product_list.php';
	$linkback = '<a href="' . dol_buildpath($listPage, 1) . '?restore_lastsearch_values=1&type=' . ((int) $object->type) . '">' . $langs->trans('BackToList') . '</a>';
	$object->next_prev_filter = 'fk_product_type:=:' . ((int) $object->type);
	$shownav = ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) ? 0 : 1;

	dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield centpercent">';

	$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
	$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

	// When viewing a Rental Service, show the linked Property Product
	if ($isService) {
		$propHtml = '<span class="opacitymedium">' . $langs->trans('None') . '</span>';
		if ($propProduct instanceof Product && $propProduct->id > 0) {
			$propHtml = dolisciGetObjectNomUrl($propProduct) . ' - ' . dolPrintHTML($propProduct->label);
		}
		print '<tr><td class="titlefield">' . $picto . $langs->trans('DoliSciPropertyProduct') . '</td><td>' . $propHtml . '</td></tr>';
	}

	// Linked Real Estate Project
	$projectId = (int) ($object->array_options['options_dolisci_fk_project'] ?? $object->array_options['options_dolisci_service_fk_project'] ?? 0);
	if ($projectId <= 0 && $propProduct instanceof Product && $propProduct->id > 0) {
		$projectId = (int) ($propProduct->array_options['options_dolisci_fk_project'] ?? 0);
	}
	$projectHtml = '<span class="opacitymedium">' . $langs->trans('None') . '</span>';
	if ($projectId > 0) {
		$proj = new Project($db);
		if ($proj->fetch($projectId) > 0) {
			$projectHtml = dolisciGetObjectNomUrl($proj) . ' - ' . dolPrintHTML($proj->title);
		}
	}
	print '<tr><td class="titlefield">' . $picto . $langs->trans('DoliSciPropertyProject') . '</td><td>' . $projectHtml . '</td></tr>';

	// Selling price HT
	print '<tr><td>' . $langs->trans('SellingPrice') . '</td><td>' . price($object->price, 1, $langs, 1, -1, -1, $conf->currency) . ' ' . $langs->trans('HT') . ' (' . $langs->trans('VATRate') . ' : ' . vatrate($object->tva_tx, true) . ')</td></tr>';

	// Categories / Tags
	if (isModEnabled('category')) {
		print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
		print $form->showCategories($object->id, Categorie::TYPE_PRODUCT, 1);
		print '</td></tr>';
	}

	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	// Action buttons
	print '<div class="tabsAction">';
	$parameters = [];
	$reshook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	print '</div>';

	// Table of linked Rental, Charges & Deposit services
	$rentalData     = ($propProduct instanceof Product && $propProduct->id > 0) ? dolisciGetPropertyRentalServices($db, $propProduct) : ['services' => []];
	$servicesToShow = $isService
		? dolisciGetServiceChildServices($db, $object, $propProduct)
		: ($rentalData['services'] ?? []);
	$efDefs         = dolisciGetExtrafieldDefinitions();
	$servNatureOpts = $efDefs['ef:product:dolisci_service_nature']['param']['options'] ?? [];

	$listTitle = $isService ? $langs->trans('DoliSciServiceChargesDepositListTitle') : $langs->trans('DoliSciPropertyRentalServicesListTitle');
	print load_fiche_titre($listTitle, '', 'fa-file-invoice-dollar');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans('Service') . '</td>';
	print '<td>' . $langs->trans('Label') . '</td>';
	print '<td>' . $langs->trans('DoliSciServiceNature') . '</td>';
	print '<td>' . $langs->trans('DoliSciServiceProject') . '</td>';
	print '<td>' . $langs->trans('ProductAccountancySellCode') . '</td>';
	print '<td class="right">' . $langs->trans('SellingPriceHT') . '</td>';
	print '<td class="right">' . $langs->trans('VATRate') . '</td>';
	if (isModEnabled('category')) {
		print '<td>' . $langs->trans('Categories') . '</td>';
	}
	print '<td class="right">' . $langs->trans('Status') . ' (' . $langs->trans('Sell') . ')</td>';
	print '</tr>';

	if (!empty($servicesToShow)) {
		$prevGroupKey = null;
		foreach ($servicesToShow as $obj) {
			$serv             = new Product($db);
			$serv->id         = (int) $obj->rowid;
			$serv->ref        = (string) $obj->ref;
			$serv->label      = (string) $obj->label;
			$serv->type       = (int) $obj->fk_product_type;
			$serv->entity     = (int) $obj->entity;
			$serv->status     = (int) $obj->tosell;
			$serv->status_buy = (int) $obj->tobuy;

			$servNatureKey = (string) ($obj->dolisci_service_nature ?? '');
			if ($servNatureKey === '' && (stripos($serv->ref, 'Loyer_') === 0 || stripos($serv->ref, 'Loyer-') === 0)) {
				$servNatureKey = 'LOYER';
			} elseif ($servNatureKey === '' && (stripos($serv->ref, 'Charge_') === 0 || stripos($serv->ref, 'Charge-') === 0)) {
				$servNatureKey = 'CHARGES';
			} elseif ($servNatureKey === '' && (stripos($serv->ref, 'Caution_') === 0 || stripos($serv->ref, 'Caution-') === 0)) {
				$servNatureKey = 'DEPOT_GARANTIE';
			}
			$servNatureLabel = isset($servNatureOpts[$servNatureKey]) ? $langs->trans((string) $servNatureOpts[$servNatureKey]) : $servNatureKey;

			$servProjHtml = '';
			if (!empty($obj->pr_id)) {
				$servProj         = new Project($db);
				$servProj->id     = (int) $obj->pr_id;
				$servProj->ref    = (string) $obj->pr_ref;
				$servProj->title  = (string) $obj->pr_title;
				$servProj->statut = (int) $obj->pr_status;
				$servProjHtml     = dolisciGetObjectNomUrl($servProj) . ' - ' . dolPrintHTML($servProj->title);
			}

			$isChild      = !empty($obj->dolisci_is_child) || $servNatureKey !== 'LOYER';
			$currGroupKey = (string) ($obj->dolisci_prop_key ?? '') . ':' . ((int) ($obj->dolisci_group_index ?? 1));
			$isNewGroup   = ($prevGroupKey !== null && $prevGroupKey !== $currGroupKey);
			$prevGroupKey = $currGroupKey;

			$rowClasses = 'oddeven ' . ($isChild ? 'dolisci-rental-group-child' : 'dolisci-rental-group-parent') . ($isNewGroup ? ' dolisci-rental-group-sep' : '');
			$servPrefix = $isChild ? '<span class="dolisci-service-child-prefix">-</span>' : '';

			print '<tr class="' . $rowClasses . '">';
			print '<td class="nowraponall">' . $servPrefix . dolisciGetObjectNomUrl($serv, $servNatureKey) . '</td>';
			print '<td>' . dolPrintHTML($serv->label) . '</td>';
			print '<td>' . dolPrintHTML($servNatureLabel) . '</td>';
			print '<td class="nowraponall">' . $servProjHtml . '</td>';
			print '<td><code>' . dolPrintHTML((string) $obj->accountancy_code_sell) . '</code></td>';
			print '<td class="right nowraponall">' . price($obj->price, 1, $langs, 1, -1, -1, 'auto') . '</td>';
			print '<td class="right nowraponall">' . vatrate($obj->tva_tx, true) . '</td>';
			if (isModEnabled('category')) {
				print '<td>' . $form->showCategories($serv->id, Categorie::TYPE_PRODUCT, 1) . '</td>';
			}
			print '<td class="right nowraponall">' . $serv->getLibStatut(5, 0) . '</td>';
			print '</tr>';
		}
	} else {
		$colspan  = isModEnabled('category') ? 9 : 8;
		$emptyMsg = $isService ? $langs->trans('DoliSciNoChargesDepositForService') : $langs->trans('DoliSciNoRentalServiceForProperty');
		print '<tr class="oddeven"><td colspan="' . $colspan . '" class="opacitymedium">' . $emptyMsg . '</td></tr>';
	}

	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
