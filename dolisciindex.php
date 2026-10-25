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
 * \file    dolisciindex.php
 * \ingroup dolisci
 * \brief   Home and dashboard page of DoliSCI top menu
 */

// Load DoliSci environment
if (file_exists('dolisci.main.inc.php')) {
	require_once __DIR__ . '/dolisci.main.inc.php';
} elseif (file_exists('../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../dolisci.main.inc.php';
} else {
	die('Include of dolisci main fails');
}

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/core/modules/modDoliSci.class.php';
require_once __DIR__ . '/lib/dolisci.lib.php';

global $db, $langs, $user;

$langs->loadLangs(['projects', 'products', 'bills', 'dolisci@dolisci']);

if (!isModEnabled('dolisci') || (!$user->hasRight('dolisci', 'read') && !$user->hasRight('projet', 'lire') && !$user->admin)) {
	accessforbidden();
}

$modDoliSci     = new modDoliSci($db);
$efDefs         = dolisciGetExtrafieldDefinitions();
$natureOpts     = $efDefs['ef:product:dolisci_property_nature']['param']['options'] ?? [];
$regimeOpts     = $efDefs['ef:product:dolisci_legal_regime']['param']['options'] ?? [];
$servNatureOpts = $efDefs['ef:product:dolisci_service_nature']['param']['options'] ?? [];
$leaseTypeOpts  = $efDefs['ef:product:dolisci_lease_type']['param']['options'] ?? [];

/*
 * View
 */

$title   = $langs->trans('ModuleDoliSciName');
$helpUrl = 'FR:Module_DoliSci';

llxHeader('', $title, $helpUrl, '', 0, 0, '', ['/dolisci/css/dolisci.css'], '', 'mod-dolisci page-index');

$newProjectUrl = DOL_URL_ROOT . '/projet/card.php?mainmenu=dolisci&leftmenu=dolisci_projects&action=create&options_dolisci_project_type=BIEN_IMMO';
$newProductUrl = DOL_URL_ROOT . '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_products&action=create&type=0';
$newRentUrl    = DOL_URL_ROOT . '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_services&action=create&type=1&options_dolisci_service_nature=LOYER&accountancy_code_sell=706100';
$newChargesUrl = DOL_URL_ROOT . '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_services&action=create&type=1&options_dolisci_service_nature=CHARGES&accountancy_code_sell=708800';

$moreHtmlRight = '';
if ($user->hasRight('projet', 'creer')) {
	$moreHtmlRight .= dolGetButtonTitle($langs->trans('DoliSciMenuNewProject'), '', 'fa fa-city', $newProjectUrl);
}
if ($user->hasRight('produit', 'creer')) {
	$moreHtmlRight .= dolGetButtonTitle($langs->trans('DoliSciMenuNewProduct'), '', 'fa fa-key', $newProductUrl);
}
if ($user->hasRight('service', 'creer')) {
	$moreHtmlRight .= dolGetButtonTitle($langs->trans('DoliSciMenuNewRentService'), '', 'fa fa-file-invoice-dollar', $newRentUrl);
	$moreHtmlRight .= dolGetButtonTitle($langs->trans('DoliSciMenuNewChargesService'), '', 'fa fa-coins', $newChargesUrl);
}

print load_fiche_titre($title . ' ' . $modDoliSci->version, $moreHtmlRight, 'dolisci_color.png@dolisci', 0, '', 'nogreyscale');

$tagProjectId  = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
$tagProductId  = getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY');
$tagRentId     = getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT');
$tagChargesId  = getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES');
$serviceTagIds = array_values(array_filter([$tagRentId, $tagChargesId]));

// 1. Fetch Real Estate Projects (BIEN_IMMO or tagged "Bien immobilier")
$projects = [];
$sqlProj = "SELECT p.rowid, p.ref, p.title, p.fk_statut, p.fk_project, p.public, p.entity,"
	. " pa.rowid AS parent_id, pa.ref AS parent_ref, pa.title AS parent_title"
	. " FROM " . MAIN_DB_PREFIX . "projet AS p"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "projet_extrafields AS ef ON ef.fk_object = p.rowid"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "projet AS pa ON pa.rowid = p.fk_project"
	. " WHERE p.entity IN (" . getEntity('project') . ")"
	. " AND (ef.dolisci_project_type = 'BIEN_IMMO'"
	. ($tagProjectId > 0 ? " OR EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "categorie_project AS cp WHERE cp.fk_project = p.rowid AND cp.fk_categorie = " . ((int) $tagProjectId) . ")" : "")
	. ")"
	. " ORDER BY p.ref ASC LIMIT 100";
$resProj = $db->query($sqlProj);
if ($resProj) {
	while ($obj = $db->fetch_object($resProj)) {
		$projects[(int) $obj->rowid] = $obj;
	}
}

// 2. Fetch Property Products (type == 0 with DoliSCI data or tagged "Bien / Lot immobilier")
$properties = [];
$sqlProp = "SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.surface,"
	. " ef.dolisci_fk_project, ef.dolisci_property_nature, ef.dolisci_legal_regime, ef.dolisci_fiscal_lot,"
	. " ef.dolisci_tantiemes, ef.dolisci_floor_door, ef.dolisci_rooms, ef.dolisci_dpe_energy, ef.dolisci_dpe_ges, ef.dolisci_purchase_price,"
	. " pr.rowid AS proj_id, pr.ref AS proj_ref, pr.title AS proj_title, pr.fk_statut AS proj_statut"
	. " FROM " . MAIN_DB_PREFIX . "product AS p"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "product_extrafields AS ef ON ef.fk_object = p.rowid"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "projet AS pr ON pr.rowid = ef.dolisci_fk_project"
	. " WHERE p.entity IN (" . getEntity('product') . ")"
	. " AND p.fk_product_type = 0"
	. " AND (ef.dolisci_fk_project > 0 OR (ef.dolisci_property_nature IS NOT NULL AND ef.dolisci_property_nature <> '')"
	. ($tagProductId > 0 ? " OR EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "categorie_product AS cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie = " . ((int) $tagProductId) . ")" : "")
	. ")"
	. " ORDER BY p.ref ASC LIMIT 100";
$resProp = $db->query($sqlProp);
if ($resProp) {
	while ($obj = $db->fetch_object($resProp)) {
		$properties[(int) $obj->rowid] = $obj;
	}
}

// 3. Fetch Rental Services (type == 1 with DoliSCI data or tagged "Loyer (706100)" / "Charges locatives (708800)")
$services     = [];
$totalRent    = 0.0;
$totalCharges = 0.0;
$sqlServ = "SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.price, p.accountancy_code_sell,"
	. " ef.dolisci_service_nature, ef.dolisci_fk_property_product, ef.dolisci_service_fk_project, ef.dolisci_lease_type, ef.dolisci_deposit, ef.dolisci_irl_ref,"
	. " lp.rowid AS lp_id, lp.ref AS lp_ref, lp.label AS lp_label, lp.fk_product_type AS lp_type, lp.entity AS lp_entity, lp.tosell AS lp_tosell, lp.tobuy AS lp_tobuy,"
	. " pr.rowid AS proj_id, pr.ref AS proj_ref, pr.title AS proj_title, pr.fk_statut AS proj_statut"
	. " FROM " . MAIN_DB_PREFIX . "product AS p"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "product_extrafields AS ef ON ef.fk_object = p.rowid"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "product AS lp ON lp.rowid = ef.dolisci_fk_property_product"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "projet AS pr ON pr.rowid = ef.dolisci_service_fk_project"
	. " WHERE p.entity IN (" . getEntity('product') . ")"
	. " AND p.fk_product_type = 1"
	. " AND (ef.dolisci_service_fk_project > 0 OR ef.dolisci_fk_property_product > 0 OR (ef.dolisci_service_nature IS NOT NULL AND ef.dolisci_service_nature <> '')"
	. (!empty($serviceTagIds) ? " OR EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "categorie_product AS cp WHERE cp.fk_product = p.rowid AND cp.fk_categorie IN (" . implode(',', array_map('intval', $serviceTagIds)) . "))" : "")
	. ")"
	. " ORDER BY p.ref ASC LIMIT 100";
$resServ = $db->query($sqlServ);
if ($resServ) {
	while ($obj = $db->fetch_object($resServ)) {
		$services[(int) $obj->rowid] = $obj;
		if ($obj->dolisci_service_nature === 'LOYER' || $obj->accountancy_code_sell === '706100') {
			$totalRent += (float) $obj->price;
		} elseif ($obj->dolisci_service_nature === 'CHARGES' || $obj->accountancy_code_sell === '708800') {
			$totalCharges += (float) $obj->price;
		}
	}
}

// KPI Cards
print '<div class="dolisci-kpi-grid">';
print '<div class="dolisci-kpi-card"><div class="dolisci-kpi-label"><i class="fas fa-city pictofixedwidth" style="color: #0284c7;"></i>' . dolPrintHTML($langs->trans('DoliSciMenuProjects')) . '</div><div class="dolisci-kpi-value">' . count($projects) . '</div></div>';
print '<div class="dolisci-kpi-card kpi-teal"><div class="dolisci-kpi-label"><i class="fas fa-key pictofixedwidth" style="color: #0d9488;"></i>' . dolPrintHTML($langs->trans('DoliSciMenuProducts')) . '</div><div class="dolisci-kpi-value">' . count($properties) . '</div></div>';
print '<div class="dolisci-kpi-card kpi-amber"><div class="dolisci-kpi-label"><i class="fas fa-file-invoice-dollar pictofixedwidth" style="color: #d97706;"></i>' . dolPrintHTML($langs->trans('DoliSciMenuServices')) . '</div><div class="dolisci-kpi-value">' . count($services) . '</div></div>';
print '<div class="dolisci-kpi-card kpi-green"><div class="dolisci-kpi-label"><i class="fas fa-file-invoice-dollar pictofixedwidth" style="color: #16a34a;"></i>' . dolPrintHTML($langs->trans('DoliSciKpiTotalRentHT')) . '</div><div class="dolisci-kpi-value">' . price($totalRent, 1, $langs, 1, -1, -1, 'auto') . '</div></div>';
print '<div class="dolisci-kpi-card kpi-amber"><div class="dolisci-kpi-label"><i class="fas fa-file-invoice-dollar pictofixedwidth" style="color: #d97706;"></i>' . dolPrintHTML($langs->trans('DoliSciKpiTotalChargesHT')) . '</div><div class="dolisci-kpi-value">' . price($totalCharges, 1, $langs, 1, -1, -1, 'auto') . '</div></div>';
print '</div>';

// Table 1: Real Estate Projects
print load_fiche_titre($langs->trans('DoliSciMenuProjects'), '<a href="' . DOL_URL_ROOT . '/projet/list.php?mainmenu=dolisci&leftmenu=dolisci_projects&contextpage=dolisciprojectlist">' . $langs->trans('DoliSciMenuListProjects') . '</a>', 'fa-city');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Project') . '</td>';
print '<td>' . $langs->trans('Label') . '</td>';
print '<td>' . $langs->trans('Parent') . '</td>';
print '<td class="right">' . $langs->trans('Status') . '</td>';
print '</tr>';

if (!empty($projects)) {
	foreach ($projects as $obj) {
		$proj            = new Project($db);
		$proj->id        = (int) $obj->rowid;
		$proj->ref       = (string) $obj->ref;
		$proj->title     = (string) $obj->title;
		$proj->statut    = (int) $obj->fk_statut;
		$proj->status    = (int) $obj->fk_statut;
		$proj->public    = (int) $obj->public;

		$parentHtml = '';
		if (!empty($obj->parent_id)) {
			$parentProj        = new Project($db);
			$parentProj->id    = (int) $obj->parent_id;
			$parentProj->ref   = (string) $obj->parent_ref;
			$parentProj->title = (string) $obj->parent_title;
			$parentHtml        = $parentProj->getNomUrl(1) . ' - ' . dolPrintHTML($parentProj->title);
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">' . $proj->getNomUrl(1) . '</td>';
		print '<td>' . dolPrintHTML($proj->title) . '</td>';
		print '<td>' . $parentHtml . '</td>';
		print '<td class="right">' . $proj->getLibStatut(5) . '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="4" class="opacitymedium">' . $langs->trans('None') . '</td></tr>';
}
print '</table></div><br>';

// Table 2: Property Products (Purchased Properties & Fiscal Lots)
print load_fiche_titre($langs->trans('DoliSciMenuProducts'), '<a href="' . DOL_URL_ROOT . '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_products&type=0">' . $langs->trans('DoliSciMenuListProducts') . '</a>', 'fa-key');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Product') . '</td>';
print '<td>' . $langs->trans('DoliSciPropertyProject') . '</td>';
print '<td>' . $langs->trans('DoliSciPropertyNature') . '</td>';
print '<td>' . $langs->trans('DoliSciLegalRegime') . '</td>';
print '<td>' . $langs->trans('DoliSciFiscalLot') . '</td>';
print '<td>' . $langs->trans('DoliSciTantiemes') . '</td>';
print '<td class="right">' . $langs->trans('Surface') . '</td>';
print '<td class="center">DPE / GES</td>';
print '<td class="right">' . $langs->trans('DoliSciPurchasePrice') . '</td>';
print '</tr>';

if (!empty($properties)) {
	foreach ($properties as $obj) {
		$prod             = new Product($db);
		$prod->id         = (int) $obj->rowid;
		$prod->ref        = (string) $obj->ref;
		$prod->label      = (string) $obj->label;
		$prod->type       = (int) $obj->fk_product_type;
		$prod->entity     = (int) $obj->entity;
		$prod->status     = (int) $obj->tosell;
		$prod->status_buy = (int) $obj->tobuy;

		$projHtml = '';
		if (!empty($obj->proj_id)) {
			$proj         = new Project($db);
			$proj->id     = (int) $obj->proj_id;
			$proj->ref    = (string) $obj->proj_ref;
			$proj->title  = (string) $obj->proj_title;
			$proj->statut = (int) $obj->proj_statut;
			$proj->status = (int) $obj->proj_statut;
			$projHtml     = $proj->getNomUrl(1) . ' - ' . dolPrintHTML($proj->title);
		}

		$natureKey   = (string) $obj->dolisci_property_nature;
		$natureLabel = isset($natureOpts[$natureKey]) ? $langs->trans((string) $natureOpts[$natureKey]) : $natureKey;
		$regimeKey   = (string) $obj->dolisci_legal_regime;
		$regimeLabel = isset($regimeOpts[$regimeKey]) ? $langs->trans((string) $regimeOpts[$regimeKey]) : $regimeKey;
		$dpe         = trim(($obj->dolisci_dpe_energy ?: '-') . ' / ' . ($obj->dolisci_dpe_ges ?: '-'));

		print '<tr class="oddeven">';
		print '<td class="nowraponall">' . $prod->getNomUrl(1) . ' - ' . dolPrintHTML($prod->label) . '</td>';
		print '<td>' . $projHtml . '</td>';
		print '<td>' . dolPrintHTML($natureLabel) . '</td>';
		print '<td>' . dolPrintHTML($regimeLabel) . '</td>';
		print '<td>' . dolPrintHTML((string) $obj->dolisci_fiscal_lot) . '</td>';
		print '<td>' . dolPrintHTML((string) $obj->dolisci_tantiemes) . '</td>';
		print '<td class="right">' . ($obj->surface > 0 ? price($obj->surface) . ' m²' : '') . '</td>';
		print '<td class="center">' . dolPrintHTML($dpe) . '</td>';
		print '<td class="right">' . ($obj->dolisci_purchase_price !== null ? price($obj->dolisci_purchase_price, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="9" class="opacitymedium">' . $langs->trans('DoliSciNoPropertyLinked') . '</td></tr>';
}
print '</table></div><br>';

// Table 3: Rental Services (Rent 706100 & Charges 708800)
print load_fiche_titre($langs->trans('DoliSciMenuServices'), '<a href="' . DOL_URL_ROOT . '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_services&type=1">' . $langs->trans('DoliSciMenuListServices') . '</a>', 'fa-file-invoice-dollar');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Service') . '</td>';
print '<td>' . $langs->trans('DoliSciServiceNature') . '</td>';
print '<td>' . $langs->trans('DoliSciServiceProject') . '</td>';
print '<td>' . $langs->trans('DoliSciLinkedPropertyProduct') . '</td>';
print '<td>' . $langs->trans('DoliSciLeaseType') . '</td>';
print '<td>' . $langs->trans('ProductAccountancySellCode') . '</td>';
print '<td class="right">' . $langs->trans('SellingPriceHT') . '</td>';
print '<td class="right">' . $langs->trans('DoliSciDeposit') . '</td>';
print '<td>' . $langs->trans('DoliSciIrlRef') . '</td>';
print '</tr>';

if (!empty($services)) {
	foreach ($services as $obj) {
		$serv             = new Product($db);
		$serv->id         = (int) $obj->rowid;
		$serv->ref        = (string) $obj->ref;
		$serv->label      = (string) $obj->label;
		$serv->type       = (int) $obj->fk_product_type;
		$serv->entity     = (int) $obj->entity;
		$serv->status     = (int) $obj->tosell;
		$serv->status_buy = (int) $obj->tobuy;

		$projHtml = '';
		if (!empty($obj->proj_id)) {
			$proj         = new Project($db);
			$proj->id     = (int) $obj->proj_id;
			$proj->ref    = (string) $obj->proj_ref;
			$proj->title  = (string) $obj->proj_title;
			$proj->statut = (int) $obj->proj_statut;
			$proj->status = (int) $obj->proj_statut;
			$projHtml     = $proj->getNomUrl(1) . ' - ' . dolPrintHTML($proj->title);
		}

		$linkedPropHtml = '';
		if (!empty($obj->lp_id)) {
			$linkedProp             = new Product($db);
			$linkedProp->id         = (int) $obj->lp_id;
			$linkedProp->ref        = (string) $obj->lp_ref;
			$linkedProp->label      = (string) $obj->lp_label;
			$linkedProp->type       = (int) $obj->lp_type;
			$linkedProp->entity     = (int) $obj->lp_entity;
			$linkedProp->status     = (int) $obj->lp_tosell;
			$linkedProp->status_buy = (int) $obj->lp_tobuy;
			$linkedPropHtml         = $linkedProp->getNomUrl(1);
		}

		$servNatureKey   = (string) $obj->dolisci_service_nature;
		$servNatureLabel = isset($servNatureOpts[$servNatureKey]) ? $langs->trans((string) $servNatureOpts[$servNatureKey]) : $servNatureKey;
		$leaseKey        = (string) $obj->dolisci_lease_type;
		$leaseLabel      = isset($leaseTypeOpts[$leaseKey]) ? $langs->trans((string) $leaseTypeOpts[$leaseKey]) : $leaseKey;

		print '<tr class="oddeven">';
		print '<td class="nowraponall">' . $serv->getNomUrl(1) . ' - ' . dolPrintHTML($serv->label) . '</td>';
		print '<td>' . dolPrintHTML($servNatureLabel) . '</td>';
		print '<td>' . $projHtml . '</td>';
		print '<td class="nowraponall">' . $linkedPropHtml . '</td>';
		print '<td>' . dolPrintHTML($leaseLabel) . '</td>';
		print '<td><code>' . dolPrintHTML((string) $obj->accountancy_code_sell) . '</code></td>';
		print '<td class="right">' . price($obj->price, 1, $langs, 1, -1, -1, 'auto') . '</td>';
		print '<td class="right">' . ($obj->dolisci_deposit !== null ? price($obj->dolisci_deposit, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
		print '<td>' . dolPrintHTML((string) $obj->dolisci_irl_ref) . '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="9" class="opacitymedium">' . $langs->trans('DoliSciNoRentalServiceLinked') . '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
