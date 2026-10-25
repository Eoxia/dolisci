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
 * \file    class/actions_dolisci.class.php
 * \ingroup dolisci
 * \brief   DoliSci hooks for real estate projects, property products, and rental services
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonhookactions.class.php';

/**
 * Class ActionsDoliSci
 */
class ActionsDoliSci extends CommonHookActions
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Check whether the current Project card is viewed/created/edited within DoliSCI context
     *
     * @param  CommonObject|null $object Current object
     * @return bool                      True if in DoliSCI context
     */
    protected function isDoliSciProjectContext($object = null): bool
    {
        if (GETPOST('mainmenu', 'aZ09') === 'dolisci' || ($_SESSION['mainmenu'] ?? '') === 'dolisci') {
            return true;
        }
        if (GETPOST('options_dolisci_project_type', 'alphanohtml') === 'BIEN_IMMO') {
            return true;
        }
        if ($object instanceof Project) {
            if (empty($object->array_options) && !empty($object->id)) {
                $object->fetch_optionals();
            }
            if (($object->array_options['options_dolisci_project_type'] ?? '') === 'BIEN_IMMO') {
                return true;
            }
        }
        return false;
    }

    /**
     * Keep only DoliSCI extrafields enabled/visible on the Project card when in DoliSCI context
     * (Preserve keys in 'label' array so third-party hooks like ReedCRM do not trigger Undefined array key warnings)
     *
     * @return void
     */
    protected function filterProjectExtrafieldsForDoliSci(): void
    {
        global $extrafields;

        if (isset($extrafields->attributes['projet']['label']) && is_array($extrafields->attributes['projet']['label'])) {
            foreach (array_keys($extrafields->attributes['projet']['label']) as $efKey) {
                if (strpos((string) $efKey, 'dolisci_') !== 0) {
                    $extrafields->attributes['projet']['enabled'][$efKey] = '0';
                    $extrafields->attributes['projet']['list'][$efKey]    = '0';
                }
            }
        }
    }

    /**
     * Overloading the formObjectOptions function: inject marker row to simplify the Project form in DoliSCI
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager): int
    {
        $contexts = explode(':', $parameters['context'] ?? '');
        if (in_array('projectcard', $contexts, true) && $this->isDoliSciProjectContext($object)) {
            $this->filterProjectExtrafieldsForDoliSci();
            if ($action === 'create' || $action === 'edit') {
                $this->resprints = '<tr class="dolisci-simplified-project-form" style="display:none"><td colspan="2">'
                    . '<style>'
                    . 'table:has(.dolisci-simplified-project-form) tr:has(#socid),'
                    . 'table:has(.dolisci-simplified-project-form) tr.classuseopportunity,'
                    . 'table:has(.dolisci-simplified-project-form) tr.classuseorganizeevent,'
                    . 'table:has(.dolisci-simplified-project-form) tr:has(input[name="budget_amount"]),'
                    . 'table:has(.dolisci-simplified-project-form) tr:has(#projectstart),'
                    . 'table:has(.dolisci-simplified-project-form) tr:has(#public),'
                    . 'table:has(.dolisci-simplified-project-form) tr:has(#typeofcontact)'
                    . '{ display: none !important; }'
                    . '</style>'
                    . '</td></tr>';
            }
        }
        return 0;
    }

    /**
     * Overloading the showOptionals function: ensure Product::$type matches GETPOST('type') on creation form
     * and prefill DoliSCI project extrafields
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0
     */
    public function showOptionals($parameters, &$object, &$action, $hookmanager): int
    {
        $contexts = explode(':', $parameters['context'] ?? '');
        if (in_array('productcard', $contexts, true) && $object instanceof Product) {
            if (GETPOSTISSET('type')) {
                $object->type = GETPOSTINT('type');
            }
            if ($action === 'create' && GETPOSTISSET('options_dolisci_service_nature')) {
                $object->array_options['options_dolisci_service_nature'] = GETPOST('options_dolisci_service_nature', 'alphanohtml');
            }
        }
        if (in_array('projectcard', $contexts, true) && $object instanceof Project) {
            if ($this->isDoliSciProjectContext($object)) {
                $this->filterProjectExtrafieldsForDoliSci();
                if ($action === 'create' && empty($object->array_options['options_dolisci_project_type'])) {
                    $object->array_options['options_dolisci_project_type'] = GETPOST('options_dolisci_project_type', 'alphanohtml') ?: 'BIEN_IMMO';
                }
            }
        }
        return 0;
    }

    /**
     * Overloading the printFieldListWhere function: fallback filter on Product / Service lists when DoliSCI tags are not yet initialized
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0
     */
    public function printFieldListWhere($parameters, &$object, &$action, $hookmanager): int
    {
        $contexts = explode(':', $parameters['context'] ?? '');
        if (in_array('productservicelist', $contexts, true)) {
            $leftmenu = GETPOST('leftmenu', 'aZ09');
            if (strpos($leftmenu, 'dolisci_products') === 0 && getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY') <= 0) {
                $this->resprints = " AND (ef.dolisci_fk_project > 0 OR (ef.dolisci_property_nature IS NOT NULL AND ef.dolisci_property_nature <> ''))";
            } elseif (strpos($leftmenu, 'dolisci_services') === 0 && getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT') <= 0 && getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES') <= 0) {
                $this->resprints = " AND (ef.dolisci_service_fk_project > 0 OR ef.dolisci_fk_property_product > 0 OR (ef.dolisci_service_nature IS NOT NULL AND ef.dolisci_service_nature <> ''))";
            }
        }
        return 0;
    }

    /**
     * Overloading the completeTabsHead function: hide Tasks and TimeSpent tabs on SCI real estate projects
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0 or 1 to replace $head
     */
    public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager): int
    {
        if (!getDolGlobalInt('DOLISCI_HIDE_TASKS_ON_SCI_PROJECTS', 1)) {
            return 0;
        }
        if (($parameters['mode'] ?? '') !== 'remove' || !($object instanceof Project) || empty($object->id)) {
            return 0;
        }

        if (empty($object->array_options)) {
            $object->fetch_optionals();
        }
        $projectType = $object->array_options['options_dolisci_project_type'] ?? '';
        if ($projectType !== 'BIEN_IMMO') {
            return 0;
        }

        if (!empty($parameters['head']) && is_array($parameters['head'])) {
            foreach ($parameters['head'] as $key => $tab) {
                if (isset($tab[2]) && in_array($tab[2], ['tasks', 'timespent'], true)) {
                    unset($parameters['head'][$key]);
                }
            }
            $this->results = $parameters['head'];
            return 1;
        }

        return 0;
    }

    /**
     * Overloading the doActions function: handle 1-click generation of Rent & Charges services from a Property Product
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0
     */
    public function doActions($parameters, &$object, &$action, $hookmanager): int
    {
        global $conf, $contextpage, $langs, $search_category_array, $searchCategoryProductList, $searchCategoryProductOperator, $status, $user;

        $contexts       = explode(':', $parameters['context'] ?? '');
        $leftmenu       = GETPOST('leftmenu', 'aZ09');
        $isRemoveFilter = GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha');

        // 1. Pre-filter Project list by DoliSCI tag "Bien immobilier" and set default columns when accessed in DoliSCI context
        $isDoliSciProjectList = strpos($leftmenu, 'dolisci_projects') === 0
            || GETPOST('contextpage', 'aZ') === 'dolisciprojectlist'
            || GETPOST('mainmenu', 'aZ09') === 'dolisci'
            || ($_SESSION['mainmenu'] ?? '') === 'dolisci';

        if (in_array('projectlist', $contexts, true) && $isDoliSciProjectList) {
            $contextpage = 'dolisciprojectlist';

            if (!$isRemoveFilter) {
                $tagProjectId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
                if ($tagProjectId > 0 && empty($search_category_array)) {
                    $search_category_array = [$tagProjectId];
                }
            }

            // Default columns on DoliSCI project list: Ref, Label, ReedCRM columns (if ReedCRM enabled), and SCI Project Type
            $defaultCheckedFields = [
                'p.ref',
                'p.title',
                'ef.dolisci_project_type',
            ];
            if (isModEnabled('reedcrm')) {
                $defaultCheckedFields[] = 'ef.commrelaunch';
                $defaultCheckedFields[] = 'ef.reedcrm_website';
                $defaultCheckedFields[] = 'ef.projectaddress';
            }
            if (isset($parameters['arrayfields']) && is_array($parameters['arrayfields'])) {
                foreach ($parameters['arrayfields'] as $fieldKey => &$fieldDef) {
                    $fieldDef['checked'] = in_array((string) $fieldKey, $defaultCheckedFields, true) ? '1' : '0';
                }
                unset($fieldDef);
            }
        }

        // 2. Pre-filter Product / Service list by DoliSCI tags ("Bien / Lot immobilier" or "Loyer (706100)" / "Charges locatives (708800)")
        if (in_array('productservicelist', $contexts, true) && !$isRemoveFilter) {
            if (strpos($leftmenu, 'dolisci_products') === 0) {
                $tagProductId = getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY');
                if ($tagProductId > 0 && empty($searchCategoryProductList)) {
                    $searchCategoryProductList = [$tagProductId];
                }
            } elseif (strpos($leftmenu, 'dolisci_services') === 0) {
                $tagRentId    = getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT');
                $tagChargesId = getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES');
                $serviceTags  = array_values(array_filter([$tagRentId, $tagChargesId]));
                if (!empty($serviceTags) && empty($searchCategoryProductList)) {
                    $searchCategoryProductList     = $serviceTags;
                    $searchCategoryProductOperator = 1;
                }
            }
        }

        // 3. Simplify Project card when in DoliSCI context: force Validated status, uncheck usage by default, pre-select "Bien immobilier" tag
        if (in_array('projectcard', $contexts, true) && $this->isDoliSciProjectContext($object)) {
            $status = Project::STATUS_VALIDATED;
            $conf->global->PROJECT_CREATE_NO_DRAFT = 1;
            $this->filterProjectExtrafieldsForDoliSci();

            if ($action === 'create') {
                if (!GETPOSTISSET('usage_opportunity')) {
                    $_POST['usage_opportunity'] = '';
                    $_POST['usage_task']        = '';
                    $_POST['usage_bill_time']   = '';
                }
                $tagProjectId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
                if ($tagProjectId > 0 && !GETPOSTISARRAY('categories')) {
                    $_POST['categories'] = [$tagProjectId];
                }
            }
        }

        // 4. Pre-select DoliSCI tag on Product / Service creation form when in DoliSCI context
        if (in_array('productcard', $contexts, true) && $action === 'create' && !GETPOSTISARRAY('categories')) {
            if (GETPOST('mainmenu', 'aZ09') === 'dolisci' || ($_SESSION['mainmenu'] ?? '') === 'dolisci' || strpos($leftmenu, 'dolisci_') === 0) {
                $prodType = GETPOSTINT('type');
                if ($prodType === Product::TYPE_PRODUCT) {
                    $tagProductId = getDolGlobalInt('DOLISCI_TAG_PRODUCT_PROPERTY');
                    if ($tagProductId > 0) {
                        $_POST['categories'] = [$tagProductId];
                    }
                } elseif ($prodType === Product::TYPE_SERVICE) {
                    $servNature = GETPOST('options_dolisci_service_nature', 'alphanohtml');
                    if ($servNature === 'LOYER' && getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT') > 0) {
                        $_POST['categories'] = [getDolGlobalInt('DOLISCI_TAG_SERVICE_RENT')];
                    } elseif ($servNature === 'CHARGES' && getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES') > 0) {
                        $_POST['categories'] = [getDolGlobalInt('DOLISCI_TAG_SERVICE_CHARGES')];
                    }
                }
            }
        }

        if (in_array('productcard', $contexts, true) && $action === 'add_dolisci_rental_services' && $object instanceof Product) {
            if (!$user->hasRight('service', 'creer')) {
                accessforbidden();
            }

            // Explicit CSRF token check (in addition to main.inc.php check on 'add_*' actions)
            $token        = GETPOST('token', 'alpha');
            $sessionToken = $_SESSION['token'] ?? '';
            if (empty($token) || ($sessionToken !== '' && $token !== $sessionToken)) {
                setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
                $action = '';
                return 0;
            }

            require_once __DIR__ . '/../lib/dolisci.lib.php';
            $langs->load('dolisci@dolisci');
            $object->fetch_optionals();

            $projectId    = (int) ($object->array_options['options_dolisci_fk_project'] ?? 0);
            $createdLinks = [];

            $servicesToCreate = [
                [
                    'ref'      => 'SERV_LOYER_' . $object->ref,
                    'label'    => $langs->transnoentities('DoliSciRentServiceLabel', $object->label),
                    'nature'   => 'LOYER',
                    'account'  => '706100',
                    'tagConst' => 'DOLISCI_TAG_SERVICE_RENT',
                ],
                [
                    'ref'      => 'SERV_PROV_CHARGES_' . $object->ref,
                    'label'    => $langs->transnoentities('DoliSciChargesServiceLabel', $object->label),
                    'nature'   => 'CHARGES',
                    'account'  => '708800',
                    'tagConst' => 'DOLISCI_TAG_SERVICE_CHARGES',
                ],
            ];

            foreach ($servicesToCreate as $spec) {
                $service  = new Product($this->db);
                $existing = $service->fetch(0, $spec['ref']);
                if ($existing > 0) {
                    dolisciAssignTag($this->db, $service, $spec['tagConst'], 'product');
                    $createdLinks[] = $service->getNomUrl(1);
                    continue;
                }

                $service->ref                   = $spec['ref'];
                $service->label                 = $spec['label'];
                $service->type                  = Product::TYPE_SERVICE;
                $service->status                = 1;
                $service->status_buy            = 0;
                $service->tva_tx                = 0;
                $service->accountancy_code_sell = $spec['account'];
                $service->array_options         = [
                    'options_dolisci_service_nature'      => $spec['nature'],
                    'options_dolisci_fk_property_product' => (int) $object->id,
                    'options_dolisci_service_fk_project'  => $projectId,
                ];

                $res = $service->create($user);
                if ($res > 0) {
                    dolisciAssignTag($this->db, $service, $spec['tagConst'], 'product');
                    $createdLinks[] = $service->getNomUrl(1);
                } else {
                    setEventMessages($service->error, $service->errors, 'errors');
                }
            }

            if (!empty($createdLinks)) {
                setEventMessages($langs->trans('DoliSciRentalServicesGenerated', implode(', ', $createdLinks)), null);
            }
            $action = '';
        }

        return 0;
    }

    /**
     * Overloading the addMoreActionsButtons function:
     * - On a Property Product (type = 0): add button to generate Rent + Charges services
     * - On a Real Estate Project (BIEN_IMMO): display tables of linked Property Products (fiscal lots) and Rental Services
     *
     * @param  array        $parameters  Hook metadatas (context, etc...)
     * @param  CommonObject $object      The object to process
     * @param  string       $action      Current action
     * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return int                       0
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager): int
    {
        global $langs, $user;

        $contexts = explode(':', $parameters['context'] ?? '');
        $langs->load('dolisci@dolisci');

        // 1. Button on Property Product card (type == 0)
        if (in_array('productcard', $contexts, true) && $object instanceof Product && (int) $object->type === Product::TYPE_PRODUCT) {
            if (empty($object->array_options)) {
                $object->fetch_optionals();
            }
            $hasSciData = !empty($object->array_options['options_dolisci_fk_project']) || !empty($object->array_options['options_dolisci_property_nature']);
            if ($hasSciData && $user->hasRight('service', 'creer')) {
                $url = $_SERVER['PHP_SELF'] . '?id=' . ((int) $object->id) . '&action=add_dolisci_rental_services&token=' . newToken();
                print dolGetButtonAction('', $langs->trans('DoliSciGenerateRentalServices'), 'default', $url, '');
            }
        }

        // 2. Real estate overview tables on Project card (BIEN_IMMO)
        if (in_array('projectcard', $contexts, true) && $object instanceof Project && !empty($object->id) && $action !== 'edit') {
            if (empty($object->array_options)) {
                $object->fetch_optionals();
            }
            if (($object->array_options['options_dolisci_project_type'] ?? '') === 'BIEN_IMMO') {
                $this->printProjectRealEstateTables($object);
            }
        }

        return 0;
    }

    /**
     * Render the tables of linked Property Products (purchased real estate / fiscal lots)
     * and Rental Services (Rent + Charges provisions) on a Real Estate Project card.
     *
     * @param  Project $project Project instance
     * @return void
     */
    protected function printProjectRealEstateTables(Project $project): void
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
        require_once __DIR__ . '/../lib/dolisci.lib.php';

        $efDefs          = dolisciGetExtrafieldDefinitions();
        $natureOpts      = $efDefs['ef:product:dolisci_property_nature']['param']['options'] ?? [];
        $regimeOpts      = $efDefs['ef:product:dolisci_legal_regime']['param']['options'] ?? [];
        $servNatureOpts  = $efDefs['ef:product:dolisci_service_nature']['param']['options'] ?? [];
        $leaseTypeOpts   = $efDefs['ef:product:dolisci_lease_type']['param']['options'] ?? [];

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

        // 1. Purchased Properties & Fiscal Lots (Products, fk_product_type = 0)
        // Single batch query (no N+1 queries inside loop) with multi-entity filter
        $sqlProp = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.surface,'
            . ' ef.dolisci_property_nature, ef.dolisci_legal_regime, ef.dolisci_fiscal_lot, ef.dolisci_tantiemes,'
            . ' ef.dolisci_floor_door, ef.dolisci_rooms, ef.dolisci_dpe_energy, ef.dolisci_dpe_ges, ef.dolisci_purchase_date, ef.dolisci_purchase_price'
            . ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
            . ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
            . ' WHERE p.entity IN (' . getEntity('product') . ')'
            . ' AND p.fk_product_type = 0'
            . ' AND ef.dolisci_fk_project IN (' . $inProjectIds . ')'
            . ' ORDER BY p.ref ASC LIMIT 100';

        $resProp = $this->db->query($sqlProp);

        print '<div class="clearboth"></div><br>';
        print load_fiche_titre($langs->trans('DoliSciProjectPropertiesTitle'), '', 'fa-key');
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans('Product') . '</td>';
        print '<td>' . $langs->trans('DoliSciPropertyNature') . '</td>';
        print '<td>' . $langs->trans('DoliSciLegalRegime') . '</td>';
        print '<td>' . $langs->trans('DoliSciFiscalLot') . '</td>';
        print '<td>' . $langs->trans('DoliSciTantiemes') . '</td>';
        print '<td>' . $langs->trans('DoliSciFloorDoor') . '</td>';
        print '<td class="right">' . $langs->trans('Surface') . '</td>';
        print '<td class="center">' . $langs->trans('DoliSciRooms') . '</td>';
        print '<td class="center">DPE / GES</td>';
        print '<td class="right">' . $langs->trans('DoliSciPurchasePrice') . '</td>';
        print '</tr>';

        $nbProps = 0;
        if ($resProp) {
            while ($obj = $this->db->fetch_object($resProp)) {
                $nbProps++;
                $prod             = new Product($this->db);
                $prod->id         = (int) $obj->rowid;
                $prod->ref        = (string) $obj->ref;
                $prod->label      = (string) $obj->label;
                $prod->type       = (int) $obj->fk_product_type;
                $prod->entity     = (int) $obj->entity;
                $prod->status     = (int) $obj->tosell;
                $prod->status_buy = (int) $obj->tobuy;

                $natureKey   = (string) $obj->dolisci_property_nature;
                $natureLabel = isset($natureOpts[$natureKey]) ? $langs->trans((string) $natureOpts[$natureKey]) : $natureKey;
                $regimeKey   = (string) $obj->dolisci_legal_regime;
                $regimeLabel = isset($regimeOpts[$regimeKey]) ? $langs->trans((string) $regimeOpts[$regimeKey]) : $regimeKey;

                print '<tr class="oddeven">';
                print '<td class="nowraponall">' . $prod->getNomUrl(1) . ' - ' . dolPrintHTML($prod->label) . '</td>';
                print '<td>' . dolPrintHTML($natureLabel) . '</td>';
                print '<td>' . dolPrintHTML($regimeLabel) . '</td>';
                print '<td>' . dolPrintHTML((string) $obj->dolisci_fiscal_lot) . '</td>';
                print '<td>' . dolPrintHTML((string) $obj->dolisci_tantiemes) . '</td>';
                print '<td>' . dolPrintHTML((string) $obj->dolisci_floor_door) . '</td>';
                print '<td class="right">' . ($obj->surface > 0 ? price($obj->surface) . ' m²' : '') . '</td>';
                print '<td class="center">' . ($obj->dolisci_rooms > 0 ? (int) $obj->dolisci_rooms : '') . '</td>';
                $dpe = trim(($obj->dolisci_dpe_energy ?: '-') . ' / ' . ($obj->dolisci_dpe_ges ?: '-'));
                print '<td class="center">' . dolPrintHTML($dpe) . '</td>';
                print '<td class="right">' . ($obj->dolisci_purchase_price !== null ? price($obj->dolisci_purchase_price, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
                print '</tr>';
            }
        }
        if ($nbProps === 0) {
            print '<tr class="oddeven"><td colspan="10" class="opacitymedium">' . $langs->trans('DoliSciNoPropertyLinked') . '</td></tr>';
        }
        print '</table></div>';

        // 2. Rental Services: Rent (706100) & Charges provisions (708800) (Services, fk_product_type = 1)
        // Single batch query with LEFT JOIN for the linked property product (no N+1 queries inside loop)
        $sqlServ = 'SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.entity, p.tosell, p.tobuy, p.price, p.accountancy_code_sell,'
            . ' ef.dolisci_service_nature, ef.dolisci_fk_property_product, ef.dolisci_lease_type, ef.dolisci_deposit, ef.dolisci_irl_ref,'
            . ' lp.rowid AS lp_id, lp.ref AS lp_ref, lp.label AS lp_label, lp.fk_product_type AS lp_type, lp.entity AS lp_entity, lp.tosell AS lp_tosell, lp.tobuy AS lp_tobuy'
            . ' FROM ' . MAIN_DB_PREFIX . 'product AS p'
            . ' INNER JOIN ' . MAIN_DB_PREFIX . 'product_extrafields AS ef ON ef.fk_object = p.rowid'
            . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product AS lp ON lp.rowid = ef.dolisci_fk_property_product'
            . ' WHERE p.entity IN (' . getEntity('product') . ')'
            . ' AND p.fk_product_type = 1'
            . ' AND ef.dolisci_service_fk_project IN (' . $inProjectIds . ')'
            . ' ORDER BY p.ref ASC LIMIT 100';

        $resServ = $this->db->query($sqlServ);

        print '<br>';
        print load_fiche_titre($langs->trans('DoliSciProjectRentalsTitle'), '', 'fa-file-invoice-dollar');
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans('Service') . '</td>';
        print '<td>' . $langs->trans('DoliSciServiceNature') . '</td>';
        print '<td>' . $langs->trans('DoliSciLinkedPropertyProduct') . '</td>';
        print '<td>' . $langs->trans('DoliSciLeaseType') . '</td>';
        print '<td>' . $langs->trans('ProductAccountancySellCode') . '</td>';
        print '<td class="right">' . $langs->trans('SellingPriceHT') . '</td>';
        print '<td class="right">' . $langs->trans('DoliSciDeposit') . '</td>';
        print '<td>' . $langs->trans('DoliSciIrlRef') . '</td>';
        print '</tr>';

        $nbServs = 0;
        if ($resServ) {
            while ($obj = $this->db->fetch_object($resServ)) {
                $nbServs++;
                $serv             = new Product($this->db);
                $serv->id         = (int) $obj->rowid;
                $serv->ref        = (string) $obj->ref;
                $serv->label      = (string) $obj->label;
                $serv->type       = (int) $obj->fk_product_type;
                $serv->entity     = (int) $obj->entity;
                $serv->status     = (int) $obj->tosell;
                $serv->status_buy = (int) $obj->tobuy;

                $linkedPropHtml = '';
                if (!empty($obj->lp_id)) {
                    $linkedProp             = new Product($this->db);
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
                print '<td class="nowraponall">' . $linkedPropHtml . '</td>';
                print '<td>' . dolPrintHTML($leaseLabel) . '</td>';
                print '<td><code>' . dolPrintHTML((string) $obj->accountancy_code_sell) . '</code></td>';
                print '<td class="right">' . price($obj->price, 1, $langs, 1, -1, -1, 'auto') . '</td>';
                print '<td class="right">' . ($obj->dolisci_deposit !== null ? price($obj->dolisci_deposit, 1, $langs, 1, -1, -1, 'auto') : '') . '</td>';
                print '<td>' . dolPrintHTML((string) $obj->dolisci_irl_ref) . '</td>';
                print '</tr>';
            }
        }
        if ($nbServs === 0) {
            print '<tr class="oddeven"><td colspan="8" class="opacitymedium">' . $langs->trans('DoliSciNoRentalServiceLinked') . '</td></tr>';
        }
        print '</table></div>';
    }
}
