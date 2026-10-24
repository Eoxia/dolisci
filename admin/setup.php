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

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
