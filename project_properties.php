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
 * \file    project_properties.php
 * \ingroup dolisci
 * \brief   DoliSCI tab on Real Estate Project cards listing linked Property Products (Biens) and Rental Services (Loyers, Charges, Cautions)
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
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once __DIR__ . '/lib/dolisci.lib.php';

/**
 * @var Conf        $conf
 * @var DoliDB      $db
 * @var HookManager $hookmanager
 * @var Translate   $langs
 * @var User        $user
 */

$langs->loadLangs(['projects', 'products', 'companies', 'categories', 'dolisci@dolisci']);

$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new Project($db);
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error, $object->errors);
		exit;
	}
	$object->fetch_optionals();
	$id = (int) $object->id;
}

// Security check
$socid = 0;
if ($user->socid > 0) {
	$socid = (int) $user->socid;
}
restrictedArea($user, 'projet', $object->id, 'projet&project');

$hookmanager->initHooks(['projectcard', 'dolisciprojectproperties']);

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
$title = $langs->trans('Project') . ' - ' . $object->ref . ' - ' . $langs->trans('DoliSciTabProjectProperties');

llxHeader('', $title, '', '', 0, 0, '', ['/dolisci/css/dolisci.css']);

if ($object->id > 0) {
	$head = project_prepare_head($object);
	print dol_get_fiche_head($head, 'dolisci_project_properties', $langs->trans('Project'), -1, ($object->public ? 'projectpub' : 'project'));

	$linkback = '<a href="' . dol_buildpath('/dolisci/project_list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';

	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= dol_escape_htmltag($object->title);
	if (getDolGlobalInt('PROJECT_ENABLE_SUB_PROJECT') && !empty($object->fk_project)) {
		$parent = new Project($db);
		if ($parent->fetch((int) $object->fk_project) > 0) {
			$morehtmlref .= '<br>' . $langs->trans('Child of') . ' ' . $parent->getNomUrl(1, 'project') . ' ' . dol_escape_htmltag($parent->title);
		}
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield centpercent">';

	// Property Address
	$pictoPath = dol_buildpath('/dolisci/img/dolisci_color.png', 1);
	$picto     = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');
	$addrHtml  = '<span class="opacitymedium">' . $langs->trans('None') . '</span>';
	$addrId    = (int) ($object->array_options['options_dolisci_fk_socpeople'] ?? 0);
	if ($addrId > 0) {
		$contact = new Contact($db);
		if ($contact->fetch($addrId) > 0) {
			$parts    = array_filter([
				trim($contact->getFullName($langs)),
				trim((string) $contact->address),
				trim(trim((string) $contact->zip) . ' ' . trim((string) $contact->town)),
			]);
			$addrHtml = $contact->getNomUrl(1) . (!empty($parts) ? ' - ' . dolPrintHTML(implode(' ', $parts)) : '');
		}
	}
	print '<tr><td class="titlefield">' . $picto . $langs->trans('DoliSciPropertyAddress') . '</td><td>' . $addrHtml . '</td></tr>';

	// Categories / Tags
	if (isModEnabled('category')) {
		print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
		print $form->showCategories($object->id, Categorie::TYPE_PROJECT, 1);
		print '</td></tr>';
	}

	// Description
	if (!empty($object->description)) {
		print '<tr><td>' . $langs->trans('Description') . '</td><td>' . dolPrintHTML($object->description) . '</td></tr>';
	}

	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	// Action buttons: New Property Product & New Rent / Charges / Deposit Service pre-linked to this Project
	print '<div class="tabsAction">';
	if ($user->hasRight('produit', 'creer')) {
		$newProdUrl = DOL_URL_ROOT . '/product/card.php?action=create&type=0&mainmenu=dolisci&leftmenu=dolisci_products_new&options_dolisci_fk_project=' . ((int) $object->id);
		print dolGetButtonAction('', $langs->trans('DoliSciMenuNewProduct'), 'default', $newProdUrl, '');
	}
	if ($user->hasRight('service', 'creer')) {
		$newRentUrl = DOL_URL_ROOT . '/product/card.php?action=create&type=1&mainmenu=dolisci&leftmenu=dolisci_services_new_rent&options_dolisci_service_nature=LOYER&options_dolisci_service_fk_project=' . ((int) $object->id);
		print dolGetButtonAction('', $langs->trans('DoliSciMenuNewRentService'), 'default', $newRentUrl, '');
	}
	print '</div>';

	// Render the tables of linked Property Products and Rental Services
	require_once __DIR__ . '/class/actions_dolisci.class.php';
	$actionsDoliSci = new ActionsDoliSci($db);
	$actionsDoliSci->renderProjectRealEstateTables($object);
}

llxFooter();
$db->close();
