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
 * \file    admin/init.php
 * \ingroup dolisci
 * \brief   DoliSci data model initialization page (per-field and batch activation)
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
require_once __DIR__ . '/../lib/dolisci.lib.php';

// Global variables definitions
global $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['admin', 'projects', 'products', 'dolisci@dolisci']);

// Initialize technical object to manage hooks of page
$hookmanager->initHooks(['doliscisetup', 'globalsetup']);

// Parameters
$action     = GETPOST('action', 'aZ09');
$initSingle = GETPOST('init_single', 'alphanohtml');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissionToRead = $user->hasRight('dolisci', 'adminpage', 'read') || $user->admin;
if (!isModEnabled('dolisci') || !$permissionToRead) {
    accessforbidden();
}

/*
 * Actions
 */

if (!empty($initSingle) && $user->admin) {
    if (dolisciSyncItem($db, $initSingle) > 0) {
        setEventMessages($langs->trans('SetupSaved'), null);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'enable_item' && $user->admin) {
    $itemKey = GETPOST('item', 'alphanohtml');
    if (dolisciSyncItem($db, $itemKey) > 0) {
        setEventMessages($langs->trans('SetupSaved'), null);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'batch_init' && $user->admin) {
    $selectedItems = GETPOST('init_items', 'array:alphanohtml');
    $countSynced   = 0;
    if (is_array($selectedItems)) {
        foreach ($selectedItems as $itemKey) {
            if (dolisciSyncItem($db, (string) $itemKey) > 0) {
                $countSynced++;
            }
        }
    }
    if ($countSynced > 0) {
        setEventMessages($langs->trans('DoliSciDataModelSynced'), null);
    }
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
print dol_get_fiche_head($head, 'init', $title, -1, 'dolisci_color@dolisci');

print '<span class="opacitymedium">' . $langs->trans('DoliSciInitPageDesc') . '</span><br><br>';

// Fetch current state of extrafields and contact roles from DB
$existingEf = [];
$sqlEf = "SELECT elementtype, name, type, pos, list FROM " . MAIN_DB_PREFIX . "extrafields WHERE name LIKE 'dolisci_%' LIMIT 100";
$resEf = $db->query($sqlEf);
if ($resEf) {
    while ($obj = $db->fetch_object($resEf)) {
        $existingEf['ef:' . $obj->elementtype . ':' . $obj->name] = [
            'type' => (string) $obj->type,
            'pos'  => (int) $obj->pos,
            'list' => (string) $obj->list,
        ];
    }
}

$existingCt = [];
$sqlCt = "SELECT code, active, position FROM " . MAIN_DB_PREFIX . "c_type_contact WHERE element = 'project' AND code LIKE 'DOLISCI_%' LIMIT 100";
$resCt = $db->query($sqlCt);
if ($resCt) {
    while ($obj = $db->fetch_object($resCt)) {
        $existingCt['ct:' . $obj->code] = [
            'active'   => (int) $obj->active,
            'position' => (int) $obj->position,
        ];
    }
}

$efDefinitions  = dolisciGetExtrafieldDefinitions();
$ctDefinitions  = dolisciGetContactRoleDefinitions();
$tagDefinitions = dolisciGetTagDefinitions();

$existingTags = [];
$tagIds       = [];
foreach ($tagDefinitions as $itemKey => $def) {
    $cid = getDolGlobalInt($def['const']);
    if ($cid > 0) {
        $tagIds[$cid] = $itemKey;
    }
}
if (!empty($tagIds)) {
    $sqlTag = 'SELECT rowid, visible, color FROM ' . MAIN_DB_PREFIX . 'categorie WHERE rowid IN (' . implode(',', array_map('intval', array_keys($tagIds))) . ') LIMIT 100';
    $resTag = $db->query($sqlTag);
    if ($resTag) {
        while ($obj = $db->fetch_object($resTag)) {
            $itemKey = $tagIds[(int) $obj->rowid] ?? '';
            if ($itemKey !== '') {
                $existingTags[$itemKey] = [
                    'id'      => (int) $obj->rowid,
                    'visible' => (int) $obj->visible,
                    'color'   => (string) $obj->color,
                ];
            }
        }
    }
}

$groups = [
    'project' => [
        'title' => $langs->trans('DoliSciInitProjectExtrafields'),
        'picto' => 'project',
    ],
    'product' => [
        'title' => $langs->trans('DoliSciPropertySection'),
        'picto' => 'product',
    ],
    'service' => [
        'title' => $langs->trans('DoliSciRentalSection'),
        'picto' => 'service',
    ],
];

print '<form method="POST" action="' . dolPrintHTMLForAttributeUrl($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="batch_init">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="center" width="40"><input type="checkbox" id="dolisci_checkall" title="' . dolPrintHTMLForAttribute($langs->trans('SelectAll')) . '"></td>';
print '<td>' . $langs->trans('Label') . '</td>';
print '<td>' . $langs->trans('Code') . '</td>';
print '<td>' . $langs->trans('Type') . '</td>';
print '<td class="right" width="100">' . $langs->trans('Position') . '</td>';
print '<td class="center" width="180">' . $langs->trans('Status') . '</td>';
print '<td class="center" width="140">' . $langs->trans('Action') . '</td>';
print '</tr>';

foreach ($groups as $groupKey => $groupMeta) {
    print '<tr class="liste_titre">';
    print '<td colspan="7">' . img_picto('', $groupMeta['picto'], 'class="pictofixedwidth"') . '<strong>' . dolPrintHTML($groupMeta['title']) . '</strong></td>';
    print '</tr>';

    foreach ($efDefinitions as $itemKey => $def) {
        if ($def['group'] !== $groupKey) {
            continue;
        }
        $isCompliant = isset($existingEf[$itemKey])
            && $existingEf[$itemKey]['type'] === (string) $def['type']
            && $existingEf[$itemKey]['pos'] === (int) $def['pos']
            && $existingEf[$itemKey]['list'] !== '0';

        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" class="dolisci-init-check" name="init_items[]" value="' . dolPrintHTMLForAttribute($itemKey) . '"' . (!$isCompliant ? ' checked="checked"' : '') . '></td>';
        print '<td>' . $langs->trans((string) $def['label']) . '</td>';
        print '<td><code>' . dolPrintHTML((string) $def['attrname']) . '</code></td>';
        print '<td>' . dolPrintHTML((string) $def['type']) . '</td>';
        print '<td class="right">' . (int) $def['pos'] . '</td>';
        print '<td class="center">';
        if ($isCompliant) {
            print dolGetBadge($langs->trans('DoliSciStatusPresentAndCompliant'), '', 'status4');
        } else {
            print dolGetBadge($langs->trans('DoliSciStatusMissingOrNonCompliant'), '', 'status8');
        }
        print '</td>';
        print '<td class="center">';
        if ($user->admin) {
            $btnLabel = $isCompliant ? $langs->trans('DoliSciSynchronizeBtn') : $langs->trans('DoliSciInitializeBtn');
            $btnClass = $isCompliant ? 'button smallpaddingimp reposition' : 'butAction smallpaddingimp reposition';
            print '<button type="submit" class="' . $btnClass . '" name="init_single" value="' . dolPrintHTMLForAttribute($itemKey) . '">' . dolPrintHTML($btnLabel) . '</button>';
        }
        print '</td>';
        print '</tr>';
    }
}

// Contact roles section
print '<tr class="liste_titre">';
print '<td colspan="7">' . img_picto('', 'contact', 'class="pictofixedwidth"') . '<strong>' . dolPrintHTML($langs->trans('DoliSciInitContactRoles')) . '</strong></td>';
print '</tr>';

foreach ($ctDefinitions as $itemKey => $def) {
    $isCompliant = isset($existingCt[$itemKey])
        && $existingCt[$itemKey]['active'] === 1
        && $existingCt[$itemKey]['position'] === (int) $def['position'];
    $roleLabel   = $langs->trans('TypeContact_project_external_' . $def['code']);
    if ($roleLabel === 'TypeContact_project_external_' . $def['code']) {
        $roleLabel = $def['label'];
    }
    print '<tr class="oddeven">';
    print '<td class="center"><input type="checkbox" class="dolisci-init-check" name="init_items[]" value="' . dolPrintHTMLForAttribute($itemKey) . '"' . (!$isCompliant ? ' checked="checked"' : '') . '></td>';
    print '<td>' . dolPrintHTML($roleLabel) . '</td>';
    print '<td><code>' . dolPrintHTML($def['code']) . '</code></td>';
    print '<td>c_type_contact (project)</td>';
    print '<td class="right">' . (int) $def['position'] . '</td>';
    print '<td class="center">';
    if ($isCompliant) {
        print dolGetBadge($langs->trans('DoliSciStatusPresentAndCompliant'), '', 'status4');
    } else {
        print dolGetBadge($langs->trans('DoliSciStatusMissingOrNonCompliant'), '', 'status8');
    }
    print '</td>';
    print '<td class="center">';
    if ($user->admin) {
        $btnLabel = $isCompliant ? $langs->trans('DoliSciSynchronizeBtn') : $langs->trans('DoliSciInitializeBtn');
        $btnClass = $isCompliant ? 'button smallpaddingimp reposition' : 'butAction smallpaddingimp reposition';
        print '<button type="submit" class="' . $btnClass . '" name="init_single" value="' . dolPrintHTMLForAttribute($itemKey) . '">' . dolPrintHTML($btnLabel) . '</button>';
    }
    print '</td>';
    print '</tr>';
}

// Tags / Categories section
print '<tr class="liste_titre">';
print '<td colspan="7">' . img_picto('', 'category', 'class="pictofixedwidth"') . '<strong>' . dolPrintHTML($langs->trans('DoliSciInitTags')) . '</strong></td>';
print '</tr>';

foreach ($tagDefinitions as $itemKey => $def) {
    $isCompliant = isset($existingTags[$itemKey]) && $existingTags[$itemKey]['visible'] === 1;
    $tagLabel    = $langs->trans((string) $def['label']);
    print '<tr class="oddeven">';
    print '<td class="center"><input type="checkbox" class="dolisci-init-check" name="init_items[]" value="' . dolPrintHTMLForAttribute($itemKey) . '"' . (!$isCompliant ? ' checked="checked"' : '') . '></td>';
    print '<td><span class="badge badge-status" style="background-color:#' . dolPrintHTMLForAttribute((string) $def['color']) . ';color:#fff;">' . dolPrintHTML($tagLabel) . '</span></td>';
    print '<td><code>' . dolPrintHTML((string) $def['const']) . '</code></td>';
    print '<td>categorie (' . dolPrintHTML((string) $def['type']) . ')</td>';
    print '<td class="right">' . (int) $def['position'] . '</td>';
    print '<td class="center">';
    if ($isCompliant) {
        print dolGetBadge($langs->trans('DoliSciStatusPresentAndCompliant'), '', 'status4');
    } else {
        print dolGetBadge($langs->trans('DoliSciStatusMissingOrNonCompliant'), '', 'status8');
    }
    print '</td>';
    print '<td class="center">';
    if ($user->admin) {
        $btnLabel = $isCompliant ? $langs->trans('DoliSciSynchronizeBtn') : $langs->trans('DoliSciInitializeBtn');
        $btnClass = $isCompliant ? 'button smallpaddingimp reposition' : 'butAction smallpaddingimp reposition';
        print '<button type="submit" class="' . $btnClass . '" name="init_single" value="' . dolPrintHTMLForAttribute($itemKey) . '">' . dolPrintHTML($btnLabel) . '</button>';
    }
    print '</td>';
    print '</tr>';
}

print '</table>';
print '</div><br>';

if ($user->admin) {
    print '<div class="center">';
    print '<input type="submit" class="button button-save" value="' . dolPrintHTMLForAttribute($langs->trans('DoliSciInitializeBtn')) . '">';
    print '</div>';
}

print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
