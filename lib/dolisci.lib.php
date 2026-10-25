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
 * Return all DoliSCI extrafield definitions for Projects, Products (properties), and Services (rentals)
 * following Saturne/Evarisk extrafield conventions (positions prefixed by module numero 436371,
 * translation keys in select options, entity = 0, langfile = 'dolisci@dolisci', standard CSS classes).
 *
 * @return array<string,array<string,mixed>>
 */
function dolisciGetExtrafieldDefinitions(): array
{
    $moduleNumero     = 436371;
    $projectCondition = "isModEnabled('dolisci') && isModEnabled('project')";
    $productCondition = "isModEnabled('dolisci') && isModEnabled('product') && ((int) (\$objectoffield->type ?? \$object->type ?? 0) === 0)";
    $serviceCondition = "isModEnabled('dolisci') && isModEnabled('service') && ((int) (\$objectoffield->type ?? \$object->type ?? 0) === 1)";
    $defaultCss       = ['css' => 'minwidth100 maxwidth300 widthcentpercentminusx'];

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
        // 1. Project extrafields
        'ef:projet:dolisci_project_type' => [
            'group'       => 'project',
            'attrname'    => 'dolisci_project_type',
            'label'       => 'DoliSciProjectType',
            'type'        => 'select',
            'pos'         => (int) ($moduleNumero . '10'),
            'size'        => '',
            'elementtype' => 'projet',
            'param'       => [
                'options' => [
                    'BIEN_IMMO' => 'DoliSciProjectTypeProperty',
                    'TRAVAUX'   => 'DoliSciProjectTypeWorks',
                ],
            ],
            'list'        => '1',
            'help'        => 'DoliSciProjectTypeHelp',
            'enabled'     => $projectCondition,
            'moreparams'  => $defaultCss,
        ],

        // 2. Product extrafields (type == 0: Purchased property & fiscal lot)
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
    ];
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
    $tagId = getDolGlobalInt($constName);
    if ($tagId <= 0 || empty($object->id)) {
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
        $tagLangs = $langs;
        if ($tagLangs->defaultlang === 'en_US' && getDolGlobalString('MAIN_LANG_DEFAULT', 'fr_FR') === 'auto' && empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $tagLangs = new Translate('', $conf);
            $tagLangs->setDefaultLang('fr_FR');
        }
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
