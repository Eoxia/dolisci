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
 * \file    product_property.php
 * \ingroup dolisci
 * \brief   Tab on Property Product card showing & editing all dedicated DoliSCI property extrafields
 */

// Load DoliSCI environment
if (file_exists('dolisci.main.inc.php')) {
	require_once __DIR__ . '/dolisci.main.inc.php';
} elseif (file_exists('../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../dolisci.main.inc.php';
} else {
	die('Include of dolisci main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/lib/dolisci.lib.php';

/**
 * @var Conf        $conf
 * @var DoliDB      $db
 * @var HookManager $hookmanager
 * @var Translate   $langs
 * @var User        $user
 */

$langs->loadLangs(['products', 'projects', 'dolisci@dolisci']);

$id        = GETPOSTINT('id');
$ref       = GETPOST('ref', 'alpha');
$action    = GETPOST('action', 'aZ09');
$attribute = GETPOST('attribute', 'aZ09');
$cancel    = GETPOST('cancel', 'alpha');

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

$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// Keep only dedicated DoliSCI property extrafields visible on this tab
$propertyExtrafieldKeys = [
	'dolisci_fk_project',
	'dolisci_property_nature',
	'dolisci_legal_regime',
	'dolisci_cadastre_section',
	'dolisci_cadastre_number',
	'dolisci_cadastre_lieudit',
	'dolisci_cadastre_surface',
	'dolisci_subdivision',
	'dolisci_fiscal_lot',
	'dolisci_tantiemes',
	'dolisci_floor_door',
	'dolisci_rooms',
	'dolisci_surface_carrez',
	'dolisci_dpe_energy',
	'dolisci_dpe_ges',
	'dolisci_purchase_date',
	'dolisci_purchase_price',
	'dolisci_ownership_quota',
	'dolisci_notary',
];

if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label'])) {
	foreach (array_keys($extrafields->attributes[$object->table_element]['label']) as $efKey) {
		$keep = in_array((string) $efKey, $propertyExtrafieldKeys, true);
		$extrafields->attributes[$object->table_element]['enabled'][$efKey] = $keep ? '1' : '0';
		$extrafields->attributes[$object->table_element]['list'][$efKey]    = $keep ? '1' : '0';
	}
	$tagProjectId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
	if ($tagProjectId > 0) {
		$projectIds = dolisciGetCategoryIdsFilterList($db, 'project', $tagProjectId);
		$filterProj = 'projet:ref|title:rowid::(rowid:IN:' . $projectIds . ')';
		$extrafields->attributes[$object->table_element]['type']['dolisci_fk_project']             = 'sellist';
		$extrafields->attributes[$object->table_element]['param']['dolisci_fk_project']['options'] = [$filterProj => null];
	}
	$notaryIds    = dolisciGetNotarySocIdsFilterList($db);
	$filterNotary = 'societe:nom:rowid::(rowid:IN:' . $notaryIds . ')';
	$extrafields->attributes[$object->table_element]['type']['dolisci_notary']             = 'chkbxlst';
	$extrafields->attributes[$object->table_element]['param']['dolisci_notary']['options'] = [$filterNotary => null];
	$rawNotaryVal = (string) ($object->array_options['options_dolisci_notary'] ?? '');
	if ($rawNotaryVal !== '' && !preg_match('/^[0-9]+(,[0-9]+)*$/', $rawNotaryVal)) {
		$object->array_options['options_dolisci_notary'] = '';
	}
}

$hookmanager->initHooks(['dolisciproductproperty', 'globalcard']);

$permissiontoedit = $user->hasRight('produit', 'creer');

/*
 * Actions
 */

if ($cancel) {
	$action = '';
}

if ($action === 'update_extras' && $permissiontoedit && $object->id > 0) {
	$error = 0;
	$object->oldcopy = dol_clone($object, 2);
	$ret = $extrafields->setOptionalsFromPost(null, $object, $attribute);
	if ($ret < 0) {
		$error++;
	}
	if (!$error) {
		$result = $object->updateExtraField($attribute, 'PRODUCT_MODIFY');
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
			$error++;
		}
	}
	if ($error) {
		$action = 'edit_extras';
	} else {
		header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id));
		exit;
	}
}

if ($action === 'update' && $permissiontoedit && $object->id > 0) {
	$object->oldcopy = dol_clone($object, 2);
	$ret = $extrafields->setOptionalsFromPost(null, $object);
	if ($ret < 0) {
		setEventMessages($extrafields->error, $extrafields->errors, 'errors');
		$action = 'edit';
	} else {
		$res = $object->insertExtraFields();
		if ($res < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'edit';
		} else {
			setEventMessages($langs->trans('RecordSaved'), null);
			header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id));
			exit;
		}
	}
}

/*
 * View
 */

$form  = new Form($db);
$title = $langs->trans('Product') . ' - ' . $langs->trans('DoliSciTabPropertyDetails');

llxHeader('', $title, '', '', 0, 0, '', ['/dolisci/css/dolisci.css']);

if ($object->id > 0) {
	$head = product_prepare_head($object);
	print dol_get_fiche_head($head, 'dolisci_property', $langs->trans('Product'), -1, 'product');

	$linkback = '<a href="' . dol_buildpath('/dolisci/product_list.php', 1) . '?restore_lastsearch_values=1&type=0">' . $langs->trans('BackToList') . '</a>';
	$object->next_prev_filter = 'fk_product_type:=:0';
	$shownav = ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) ? 0 : 1;

	dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	if ($action === 'edit' && $permissiontoedit) {
		print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id) . '">';
		print '<input type="hidden" name="token" value="' . newToken() . '">';
		print '<input type="hidden" name="action" value="update">';
		print '<table class="border centpercent tableforfieldcreate">';
		print $object->showOptionals($extrafields, 'edit');
		print '</table>';
		print $form->buttonsSaveCancel();
		print '</form>';
	} else {
		print '<table class="border tableforfield centpercent">';
		$cols = 2;
		include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';
		print '</table>';
	}

	print '</div>';
	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	if ($action !== 'edit') {
		print '<div class="tabsAction">';
		if ($permissiontoedit) {
			print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id) . '&action=edit&token=' . newToken(), '');
		}
		print '</div>';
	}
}

llxFooter();
$db->close();
