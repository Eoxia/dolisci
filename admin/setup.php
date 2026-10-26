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
 * \file    admin/setup.php
 * \ingroup dolisci
 * \brief   DoliSci setup page
 */

if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

// Load DoliSci environment
if (file_exists('../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../dolisci.main.inc.php';
} elseif (file_exists('../../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../../dolisci.main.inc.php';
} else {
	die('Include of dolisci main fails');
}

// Load DoliSci libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
require_once __DIR__ . '/../lib/dolisci.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['admin', 'projects', 'products', 'bills', 'dolisci@dolisci']);

// Initialize technical object to manage hooks of page
$hookmanager->initHooks(['doliscisetup', 'globalsetup']);

// Parameters
$action     = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissionToRead = $user->hasRight('dolisci', 'adminpage', 'read') || $user->admin;
if (!isModEnabled('dolisci') || !$permissionToRead) {
	accessforbidden();
}

// Setup parameters via ModuleBuilder FormSetup
$formSetup = new FormSetup($db);

$formSetup->newItem('DoliSciSetupProjectsSection')->setAsTitle();

$item = $formSetup->newItem('DOLISCI_SIMPLIFIED_CARDS')->setAsYesNo();
$item->nameText = $langs->trans('DoliSciSimplifiedCards');
$item->helpText = $langs->trans('DoliSciSimplifiedCardsHelp');

$item = $formSetup->newItem('PROJECT_ENABLE_SUB_PROJECT')->setAsYesNo();
$item->nameText = $langs->trans('DoliSciEnableSubProjects');
$item->helpText = $langs->trans('DoliSciEnableSubProjectsHelp');

$item = $formSetup->newItem('DOLISCI_HIDE_TASKS_ON_SCI_PROJECTS')->setAsYesNo();
$item->nameText = $langs->trans('DoliSciHideTasksOnSciProjects');
$item->helpText = $langs->trans('DoliSciHideTasksOnSciProjectsHelp');

$item = $formSetup->newItem('DOLISCI_AUTO_LINK_INVOICE_PROJECT')->setAsYesNo();
$item->nameText = $langs->trans('DoliSciAutoLinkInvoiceProject');
$item->helpText = $langs->trans('DoliSciAutoLinkInvoiceProjectHelp');

/*
 * Actions
 */

if ($action === 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

if ($action === 'set_subprojects' && $user->admin) {
	$val = GETPOSTINT('value');
	dolibarr_set_const($db, 'PROJECT_ENABLE_SUB_PROJECT', $val, 'integer', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'set_hide_tasks' && $user->admin) {
	$val = GETPOSTINT('value');
	dolibarr_set_const($db, 'DOLISCI_HIDE_TASKS_ON_SCI_PROJECTS', $val, 'integer', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'set_auto_link_invoice' && $user->admin) {
	$val = GETPOSTINT('value');
	dolibarr_set_const($db, 'DOLISCI_AUTO_LINK_INVOICE_PROJECT', $val, 'integer', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'save_default_services' && $user->admin) {
	$srvDefs = dolisciGetDefaultServiceDefinitions();
	foreach ($srvDefs as $def) {
		$constName = $def['const'];
		if (GETPOSTISSET($constName)) {
			$srvId = GETPOSTINT($constName);
			dolibarr_set_const($db, $constName, $srvId, 'integer', 0, '', $conf->entity);
		}
	}
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'init_default_services' && $user->admin) {
	$srvDefs = dolisciGetDefaultServiceDefinitions();
	foreach (array_keys($srvDefs) as $itemKey) {
		dolisciSyncItem($db, $itemKey);
	}
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

/*
 * View
 */

$title   = $langs->trans('DoliSciSetup');
$helpUrl = 'FR:Module_DoliSci';

llxHeader('', $title, $helpUrl, '', 0, 0, '', '', '', 'mod-dolisci page-admin');

// Subheader
$linkBack = '<a href="' . ($backtopage ? dolPrintHTMLForAttributeUrl($backtopage) : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = dolisciAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'dolisci_color@dolisci');

print '<span class="opacitymedium">' . $langs->trans('DoliSciSetupPage') . '</span><br><br>';

if (!empty($formSetup->items)) {
	print $formSetup->generateOutput();
}

// Default Rental Services section (Bail, Loyer, Charge, Dépôt de garantie, Caution)
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
$srvDefs = dolisciGetDefaultServiceDefinitions();

// Auto-initialize default services if none are configured yet
foreach ($srvDefs as $itemKey => $def) {
	if (getDolGlobalInt($def['const']) <= 0) {
		dolisciSyncItem($db, $itemKey);
	}
}

$allServices   = [];
$byNature      = [];
$sqlAllServ    = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.price, p.accountancy_code_sell, ef.dolisci_service_nature'
	. ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
	. ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
	. ' WHERE p.entity IN (' . getEntity('product') . ')'
	. ' AND p.fk_product_type = 1'
	. ' ORDER BY p.ref ASC LIMIT 200';
$resAllServ = $db->query($sqlAllServ);
if ($resAllServ) {
	while ($objS = $db->fetch_object($resAllServ)) {
		$sid               = (int) $objS->rowid;
		$allServices[$sid] = $objS;
		$nat               = (string) ($objS->dolisci_service_nature ?? '');
		$sRef              = (string) $objS->ref;
		$sAcc              = (string) $objS->accountancy_code_sell;
		if ($nat === '') {
			if (stripos($sRef, 'Bail') === 0) {
				$nat = 'BAIL';
			} elseif (stripos($sRef, 'Loyer') === 0 || $sAcc === '706100') {
				$nat = 'LOYER';
			} elseif (stripos($sRef, 'Charge') === 0 || $sAcc === '708800') {
				$nat = 'CHARGES';
			} elseif (stripos($sRef, 'Depot') === 0 || $sAcc === '165000') {
				$nat = 'DEPOT_GARANTIE';
			} elseif (stripos($sRef, 'Caution') === 0) {
				$nat = 'ACTE_CAUTIONNEMENT';
			}
		}
		if ($nat !== '') {
			$byNature[$nat][$sid] = $objS;
		}
	}
}

$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

print '<br>';
print '<form method="POST" action="' . dolPrintHTMLForAttributeUrl($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save_default_services">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . dolPrintHTML($langs->trans('DoliSciSetupDefaultServicesSection')) . '</td>';
print '<td>' . dolPrintHTML($langs->trans('Service')) . '</td>';
print '<td class="right">' . dolPrintHTML($langs->trans('Value')) . '</td>';
print '</tr>';

foreach ($srvDefs as $def) {
	$constName = $def['const'];
	$natureKey = $def['nature'];
	$currentId = getDolGlobalInt($constName);
	$cands     = !empty($byNature[$natureKey]) ? $byNature[$natureKey] : $allServices;
	if ($currentId > 0 && isset($allServices[$currentId]) && !isset($cands[$currentId])) {
		$cands[$currentId] = $allServices[$currentId];
	}

	$currentLink = '<span class="opacitymedium">' . dolPrintHTML($langs->trans('None')) . '</span>';
	if ($currentId > 0 && isset($allServices[$currentId])) {
		$sObj             = $allServices[$currentId];
		$serv             = new Product($db);
		$serv->id         = (int) $sObj->rowid;
		$serv->ref        = (string) $sObj->ref;
		$serv->label      = (string) $sObj->label;
		$serv->type       = (int) $sObj->fk_product_type;
		$serv->entity     = (int) $sObj->entity;
		$serv->status     = (int) $sObj->tosell;
		$serv->status_buy = (int) $sObj->tobuy;
		$currentLink      = dolisciGetObjectNomUrl($serv, $natureKey) . ' - ' . dolPrintHTML($serv->label);
	}

	print '<tr class="oddeven">';
	print '<td>' . $picto . dolPrintHTML($langs->trans($def['settingLabel'])) . ' <span class="opacitymedium">(' . dolPrintHTML($constName) . ')</span></td>';
	print '<td class="nowraponall">' . $currentLink . '</td>';
	print '<td class="right nowraponall">';
	print '<select name="' . dolPrintHTMLForAttribute($constName) . '" class="flat minwidth300 maxwidth400" onchange="this.form.submit()">';
	print '<option value="0">&mdash; ' . dolPrintHTML($langs->trans('None')) . ' &mdash;</option>';
	foreach ($cands as $candObj) {
		$cid      = (int) $candObj->rowid;
		$selected = ($cid === $currentId) ? ' selected' : '';
		$optLabel = $candObj->ref . ' - ' . $candObj->label . ((float) $candObj->price > 0 ? ' (' . price2num($candObj->price) . ' HT)' : '');
		print '<option value="' . $cid . '"' . $selected . '>' . dolPrintHTML($optLabel) . '</option>';
	}
	print '</select>';
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
if ($user->admin) {
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="' . dolPrintHTMLForAttribute($langs->trans('Modify')) . '">';
	print '</div>';
}
print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
