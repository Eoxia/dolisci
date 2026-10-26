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

$modDoliSci = new modDoliSci($db);
$efDefs     = dolisciGetExtrafieldDefinitions();
$natureOpts = $efDefs['ef:product:dolisci_property_nature']['param']['options'] ?? [];

/*
 * View
 */

$title   = $langs->trans('ModuleDoliSciName');
$helpUrl = 'FR:Module_DoliSci';

llxHeader('', $title, $helpUrl, '', 0, 0, '', ['/dolisci/css/dolisci.css'], '', 'mod-dolisci page-index');

$newProjectUrl = DOL_URL_ROOT . '/projet/card.php?mainmenu=dolisci&leftmenu=dolisci_projects&action=create';
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

$tagProjectId   = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
$tagProductId   = getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY');
$tagRentId      = getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT');
$tagChargesId   = getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES');
$tagDepositId   = getDolGlobalInt('DOLISCI_TAG_SERVICE_DEPOSIT');
$tagLeaseId     = getDolGlobalInt('DOLISCI_TAG_SERVICE_LEASE');
$tagGuaranteeId = getDolGlobalInt('DOLISCI_TAG_SERVICE_GUARANTEE');
$serviceTagIds  = array_values(array_filter([$tagRentId, $tagChargesId, $tagDepositId, $tagLeaseId, $tagGuaranteeId]));

// 1. Fetch Real Estate Projects (tagged "Immob.") and their linked Property Address (dolisci_fk_socpeople)
$projects         = [];
$projectAddresses = [];
if ($tagProjectId > 0) {
	$sqlProj = "SELECT p.rowid, p.ref, p.title, p.description, p.fk_statut, p.fk_project, p.public, p.entity,"
		. " pa.rowid AS parent_id, pa.ref AS parent_ref, pa.title AS parent_title,"
		. " spa.lastname AS addr_name, spa.address AS addr_street, spa.zip AS addr_zip, spa.town AS addr_town"
		. " FROM " . MAIN_DB_PREFIX . "projet AS p"
		. " INNER JOIN " . MAIN_DB_PREFIX . "categorie_project AS cp ON cp.fk_project = p.rowid AND cp.fk_categorie = " . ((int) $tagProjectId)
		. " LEFT JOIN " . MAIN_DB_PREFIX . "projet AS pa ON pa.rowid = p.fk_project"
		. " LEFT JOIN " . MAIN_DB_PREFIX . "projet_extrafields AS pef ON pef.fk_object = p.rowid"
		. " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople AS spa ON spa.rowid = pef.dolisci_fk_socpeople"
		. " WHERE p.entity IN (" . getEntity('project') . ")"
		. " ORDER BY p.ref ASC LIMIT 100";
	$resProj = $db->query($sqlProj);
	if ($resProj) {
		while ($obj = $db->fetch_object($resProj)) {
			$pid            = (int) $obj->rowid;
			$projects[$pid] = $obj;
			$addrLabel      = trim($obj->addr_street . ' ' . $obj->addr_zip . ' ' . $obj->addr_town);
			if ($addrLabel === '' && !empty($obj->addr_name)) {
				$addrLabel = trim((string) $obj->addr_name);
			}
			if ($addrLabel !== '') {
				$projectAddresses[$pid] = $addrLabel;
			}
		}
	}
}

// Fetch project contacts (DOLISCI_SYNDIC and DOLISCI_PROPERTY_ADDRESS) in a single batch query
$projectSyndics = [];
if (!empty($projects)) {
	$inProjectIds = implode(',', array_map('intval', array_keys($projects)));
	$sqlContacts  = "SELECT ec.element_id AS fk_project, tc.code, sp.firstname, sp.lastname, sp.address, sp.zip, sp.town, s.nom AS socname"
		. " FROM " . MAIN_DB_PREFIX . "element_contact AS ec"
		. " INNER JOIN " . MAIN_DB_PREFIX . "c_type_contact AS tc ON tc.rowid = ec.fk_c_type_contact"
		. " INNER JOIN " . MAIN_DB_PREFIX . "socpeople AS sp ON sp.rowid = ec.fk_socpeople"
		. " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS s ON s.rowid = sp.fk_soc"
		. " WHERE tc.element = 'project' AND tc.source = 'external'"
		. " AND tc.code IN ('DOLISCI_SYNDIC', 'DOLISCI_PROPERTY_ADDRESS')"
		. " AND ec.element_id IN (" . $inProjectIds . ") LIMIT 200";
	$resContacts = $db->query($sqlContacts);
	if ($resContacts) {
		while ($objC = $db->fetch_object($resContacts)) {
			$pid = (int) $objC->fk_project;
			if ($objC->code === 'DOLISCI_SYNDIC') {
				$syndicLabel = trim(($objC->socname ?: '') . ' ' . trim($objC->firstname . ' ' . $objC->lastname));
				if ($syndicLabel !== '') {
					$projectSyndics[$pid] = $syndicLabel;
				}
			} elseif ($objC->code === 'DOLISCI_PROPERTY_ADDRESS') {
				$addrLabel = trim($objC->address . ' ' . $objC->zip . ' ' . $objC->town);
				if ($addrLabel !== '') {
					$projectAddresses[$pid] = $addrLabel;
				}
			}
		}
	}
}

// 2. Fetch Property Products (type == 0 with DoliSCI data or tagged "Bien")
$properties = [];
$sqlProp = "SELECT p.rowid, p.ref, p.label, p.description, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.surface,"
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

// 3. Fetch Rental Services (type == 1 with DoliSCI data or tagged "Loyer" / "Charge")
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
	. " ORDER BY CASE"
	. " WHEN ef.dolisci_service_nature = 'BAIL' OR p.ref LIKE 'Bail\\_%' OR p.ref LIKE 'Bail-%\\_%' THEN 1"
	. " WHEN ef.dolisci_service_nature = 'LOYER' OR p.ref LIKE 'Loyer\\_%' OR p.ref LIKE 'Loyer-%\\_%' OR p.accountancy_code_sell = '706100' THEN 2"
	. " WHEN ef.dolisci_service_nature = 'CHARGES' OR p.ref LIKE 'Charge\\_%' OR p.ref LIKE 'Charge-%\\_%' OR p.ref LIKE 'Charges\\_%' OR p.ref LIKE 'Charges-%\\_%' OR p.accountancy_code_sell = '708800' THEN 3"
	. " WHEN ef.dolisci_service_nature = 'DEPOT_GARANTIE' OR p.ref LIKE 'Depot\\_%' OR p.ref LIKE 'Depot-%\\_%' OR p.accountancy_code_sell = '165000' THEN 4"
	. " WHEN ef.dolisci_service_nature IN ('ACTE_CAUTIONNEMENT', 'CAUTION') OR p.ref LIKE 'Caution\\_%' OR p.ref LIKE 'Caution-%\\_%' THEN 5"
	. " ELSE 6 END ASC, p.ref ASC LIMIT 100";
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

// Group properties by project ID and services by property ID (or project ID)
$propsByProject   = [];
$unlinkedProps    = [];
foreach ($properties as $propId => $propObj) {
	$pid = (int) $propObj->dolisci_fk_project;
	if ($pid > 0) {
		if (!isset($propsByProject[$pid])) {
			$propsByProject[$pid] = [];
		}
		$propsByProject[$pid][$propId] = $propObj;
	} else {
		$unlinkedProps[$propId] = $propObj;
	}
}

$servsByProp    = [];
$servsByProject = [];
foreach ($services as $servId => $servObj) {
	$propId = (int) $servObj->dolisci_fk_property_product;
	$projId = (int) $servObj->dolisci_service_fk_project;
	if ($propId > 0) {
		if (!isset($servsByProp[$propId])) {
			$servsByProp[$propId] = [];
		}
		$servsByProp[$propId][$servId] = $servObj;
	} elseif ($projId > 0) {
		if (!isset($servsByProject[$projId])) {
			$servsByProject[$projId] = [];
		}
		$servsByProject[$projId][$servId] = $servObj;
	}
}

// =========================================================================
// Table 1: Synthèse Projets / Biens / Loyers & Charges / Syndic
// =========================================================================
$listProjectsUrl = dol_buildpath('/dolisci/project_list.php?mainmenu=dolisci&leftmenu=dolisci_projects', 1);
$listProductsUrl = dol_buildpath('/dolisci/product_list.php?mainmenu=dolisci&leftmenu=dolisci_products', 1);
$listServicesUrl = dol_buildpath('/dolisci/service_list.php?mainmenu=dolisci&leftmenu=dolisci_services', 1);

$matrixLinks = '<a class="marginrightonly" href="' . $listProjectsUrl . '">' . $langs->trans('DoliSciMenuListProjects') . '</a>'
	. ' | <a class="marginleftonly marginrightonly" href="' . $listProductsUrl . '">' . $langs->trans('DoliSciMenuListProducts') . '</a>'
	. ' | <a class="marginleftonly" href="' . $listServicesUrl . '">' . $langs->trans('DoliSciMenuListServices') . '</a>';

print load_fiche_titre($langs->trans('DoliSciPatrimonyMatrixTitle'), $matrixLinks, 'fa-city');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td><i class="fas fa-city pictofixedwidth" style="color: #0284c7;"></i>' . $langs->trans('DoliSciMenuProjects') . '</td>';
print '<td><i class="fas fa-key pictofixedwidth" style="color: #0d9488;"></i>' . $langs->trans('DoliSciMenuProducts') . '</td>';
print '<td><i class="fas fa-file-invoice-dollar pictofixedwidth" style="color: #16a34a;"></i>' . $langs->trans('DoliSciMenuServices') . '</td>';
print '<td><i class="fas fa-user-tie pictofixedwidth"></i>' . $langs->trans('DoliSciSyndicAndChargesCol') . '</td>';
print '</tr>';

$matrixRowsPrinted = 0;

foreach ($projects as $projId => $projObj) {
	$proj         = new Project($db);
	$proj->id     = (int) $projObj->rowid;
	$proj->ref    = (string) $projObj->ref;
	$proj->title  = (string) $projObj->title;
	$proj->statut = (int) $projObj->fk_statut;
	$proj->status = (int) $projObj->fk_statut;
	$proj->public = (int) $projObj->public;
	$projHtml     = dolisciGetObjectNomUrl($proj) . ' - ' . dolPrintHTML($proj->title);

	$syndicText = isset($projectSyndics[$projId]) ? dolPrintHTML($projectSyndics[$projId]) : '-';
	$linkedProps = $propsByProject[$projId] ?? [];

	if (empty($linkedProps)) {
		$projServs = array_values($servsByProject[$projId] ?? []);
		dolisciSortAndGroupRentalServices($projServs);
		$nbRows    = max(1, count($projServs));
		for ($i = 0; $i < $nbRows; $i++) {
			$matrixRowsPrinted++;
			$servHtml  = '-';
			$servClass = 'nowraponall';
			if (isset($projServs[$i])) {
				$s             = new Product($db);
				$s->id         = (int) $projServs[$i]->rowid;
				$s->ref        = (string) $projServs[$i]->ref;
				$s->label      = (string) $projServs[$i]->label;
				$s->type       = 1;
				$s->entity     = (int) $projServs[$i]->entity;
				$s->status     = (int) $projServs[$i]->tosell;
				$s->status_buy = (int) $projServs[$i]->tobuy;
				$sNature       = (string) ($projServs[$i]->dolisci_service_nature ?? '');
				$isChild       = !empty($projServs[$i]->dolisci_is_child);
				$servPrefix    = $isChild ? '<span class="dolisci-service-child-prefix">-</span>' : '';
				$servClass    .= $isChild ? ' dolisci-matrix-service-child' : ' dolisci-matrix-service-parent';
				$servHtml      = $servPrefix . dolisciGetObjectNomUrl($s, $sNature) . ' - ' . dolPrintHTML($s->label) . ' (' . price($projServs[$i]->price, 1, $langs, 1, -1, -1, 'auto') . ')';
			}
			print '<tr class="oddeven">';
			print '<td class="nowraponall">' . ($i === 0 ? $projHtml : '-') . '</td>';
			print '<td>-</td>';
			print '<td class="' . $servClass . '">' . $servHtml . '</td>';
			print '<td>' . ($i === 0 ? $syndicText : '') . '</td>';
			print '</tr>';
		}
		continue;
	}

	foreach ($linkedProps as $propId => $propObj) {
		$prod             = new Product($db);
		$prod->id         = (int) $propObj->rowid;
		$prod->ref        = (string) $propObj->ref;
		$prod->label      = (string) $propObj->label;
		$prod->type       = 0;
		$prod->entity     = (int) $propObj->entity;
		$prod->status     = (int) $propObj->tosell;
		$prod->status_buy = (int) $propObj->tobuy;
		$propHtml         = dolisciGetObjectNomUrl($prod) . ' - ' . dolPrintHTML($prod->label);

		$propServs = array_values($servsByProp[$propId] ?? []);
		dolisciSortAndGroupRentalServices($propServs);
		$nbRows    = max(1, count($propServs));
		$tantiemes = !empty($propObj->dolisci_tantiemes) ? $langs->trans('DoliSciTantiemes') . ' : ' . dolPrintHTML((string) $propObj->dolisci_tantiemes) : '';

		for ($i = 0; $i < $nbRows; $i++) {
			$matrixRowsPrinted++;
			$servHtml  = '-';
			$servClass = 'nowraponall';
			if (isset($propServs[$i])) {
				$s             = new Product($db);
				$s->id         = (int) $propServs[$i]->rowid;
				$s->ref        = (string) $propServs[$i]->ref;
				$s->label      = (string) $propServs[$i]->label;
				$s->type       = 1;
				$s->entity     = (int) $propServs[$i]->entity;
				$s->status     = (int) $propServs[$i]->tosell;
				$s->status_buy = (int) $propServs[$i]->tobuy;
				$sNature       = (string) ($propServs[$i]->dolisci_service_nature ?? '');
				$isChild       = !empty($propServs[$i]->dolisci_is_child);
				$servPrefix    = $isChild ? '<span class="dolisci-service-child-prefix">-</span>' : '';
				$servClass    .= $isChild ? ' dolisci-matrix-service-child' : ' dolisci-matrix-service-parent';
				$servHtml      = $servPrefix . dolisciGetObjectNomUrl($s, $sNature) . ' - ' . dolPrintHTML($s->label) . ' (' . price($propServs[$i]->price, 1, $langs, 1, -1, -1, 'auto') . ')';
			}

			$col4 = '';
			if ($i === 0) {
				$col4 = $syndicText;
			} elseif ($i === 1 && $tantiemes !== '') {
				$col4 = $tantiemes;
			}

			print '<tr class="oddeven">';
			print '<td class="nowraponall">' . ($i === 0 ? $projHtml : '-') . '</td>';
			print '<td class="nowraponall">' . ($i === 0 ? $propHtml : '-') . '</td>';
			print '<td class="' . $servClass . '">' . $servHtml . '</td>';
			print '<td>' . $col4 . '</td>';
			print '</tr>';
		}
	}
}

// Also render any unlinked properties in the matrix
foreach ($unlinkedProps as $propId => $propObj) {
	$prod             = new Product($db);
	$prod->id         = (int) $propObj->rowid;
	$prod->ref        = (string) $propObj->ref;
	$prod->label      = (string) $propObj->label;
	$prod->type       = 0;
	$prod->entity     = (int) $propObj->entity;
	$prod->status     = (int) $propObj->tosell;
	$prod->status_buy = (int) $propObj->tobuy;
	$propHtml         = dolisciGetObjectNomUrl($prod) . ' - ' . dolPrintHTML($prod->label);

	$propServs = array_values($servsByProp[$propId] ?? []);
	dolisciSortAndGroupRentalServices($propServs);
	$nbRows    = max(1, count($propServs));
	for ($i = 0; $i < $nbRows; $i++) {
		$matrixRowsPrinted++;
		$servHtml  = '-';
		$servClass = 'nowraponall';
		if (isset($propServs[$i])) {
			$s             = new Product($db);
			$s->id         = (int) $propServs[$i]->rowid;
			$s->ref        = (string) $propServs[$i]->ref;
			$s->label      = (string) $propServs[$i]->label;
			$s->type       = 1;
			$s->entity     = (int) $propServs[$i]->entity;
			$s->status     = (int) $propServs[$i]->tosell;
			$s->status_buy = (int) $propServs[$i]->tobuy;
			$sNature       = (string) ($propServs[$i]->dolisci_service_nature ?? '');
			$isChild       = !empty($propServs[$i]->dolisci_is_child);
			$servPrefix    = $isChild ? '<span class="dolisci-service-child-prefix">-</span>' : '';
			$servClass    .= $isChild ? ' dolisci-matrix-service-child' : ' dolisci-matrix-service-parent';
			$servHtml      = $servPrefix . dolisciGetObjectNomUrl($s, $sNature) . ' - ' . dolPrintHTML($s->label) . ' (' . price($propServs[$i]->price, 1, $langs, 1, -1, -1, 'auto') . ')';
		}
		print '<tr class="oddeven">';
		print '<td>-</td>';
		print '<td class="nowraponall">' . ($i === 0 ? $propHtml : '-') . '</td>';
		print '<td class="' . $servClass . '">' . $servHtml . '</td>';
		print '<td>-</td>';
		print '</tr>';
	}
}

if ($matrixRowsPrinted === 0) {
	print '<tr class="oddeven"><td colspan="4" class="opacitymedium">' . $langs->trans('None') . '</td></tr>';
}
print '</table></div><br>';

// =========================================================================
// Table 2: Présentation des biens au <date> (Nom | Adresse | Descriptif | Superficie + Total surface)
// =========================================================================
$todayStr = dol_print_date(dol_now(), 'day');
print load_fiche_titre($langs->trans('DoliSciPropertiesPresentationTitle', $todayStr), '<a href="' . $listProductsUrl . '">' . $langs->trans('DoliSciMenuListProducts') . '</a>', 'fa-key');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('DoliSciAddress') . '</td>';
print '<td>' . $langs->trans('DoliSciDescriptionCol') . '</td>';
print '<td class="right">' . $langs->trans('DoliSciSurfaceCol') . '</td>';
print '</tr>';

$totalSurface = 0.0;
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

		$projId  = (int) $obj->proj_id;
		$address = $projectAddresses[$projId] ?? ((string) ($obj->proj_title ?: '-'));

		$natureKey   = (string) $obj->dolisci_property_nature;
		$natureLabel = isset($natureOpts[$natureKey]) ? $langs->trans((string) $natureOpts[$natureKey]) : '';
		$descText    = trim(dol_string_nohtmltag((string) $obj->description));
		$descriptif  = $descText !== '' ? $descText : ($natureLabel !== '' ? $natureLabel : '-');

		$surfaceVal  = (float) $obj->surface;
		$surfaceHtml = 'NA';
		if ($surfaceVal > 0) {
			$totalSurface += $surfaceVal;
			$surfaceHtml   = price($surfaceVal) . ' m²';
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">' . dolisciGetObjectNomUrl($prod) . ' - ' . dolPrintHTML($prod->label) . '</td>';
		print '<td>' . dolPrintHTML($address) . '</td>';
		print '<td>' . dolPrintHTML($descriptif) . '</td>';
		print '<td class="right nowraponall">' . $surfaceHtml . '</td>';
		print '</tr>';
	}
	print '<tr class="liste_total">';
	print '<td></td>';
	print '<td></td>';
	print '<td class="right"><strong>' . dolPrintHTML($langs->trans('DoliSciTotalSurface')) . '</strong></td>';
	print '<td class="right nowraponall"><strong>' . price($totalSurface) . ' m²</strong></td>';
	print '</tr>';
} else {
	print '<tr class="oddeven"><td colspan="4" class="opacitymedium">' . $langs->trans('DoliSciNoPropertyLinked') . '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
