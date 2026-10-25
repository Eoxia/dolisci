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
 * \defgroup dolisci     Module DoliSci
 * \brief    Tools for property companies (SCI): simplified property, fiscal lot, and rental management.
 *
 * \file     core/modules/modDoliSci.class.php
 * \ingroup  dolisci
 * \brief    Description and activation file for module DoliSci
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliSci
 */
class modDoliSci extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->numero          = 436371;
        $this->rights_class    = 'dolisci';
        $this->family          = '';
        $this->module_position = '';
        $this->familyinfo      = ['Eoxia' => ['position' => '01', 'label' => 'Eoxia']];
        $this->name            = preg_replace('/^mod/i', '', get_class($this));
        $this->description     = 'DoliSciDescription';
        $this->descriptionlong = 'DoliSciDescriptionLong';
        $this->editor_name     = 'Eoxia';
        $this->editor_url      = 'https://www.eoxia.com';
        $this->version         = '23.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'dolisci_color@dolisci';

        // Makes Dolibarr look for document templates, triggers, CSS, JS, and hooks of the module
        $this->module_parts = [
            'triggers' => 1,
            'models'   => 1,
            'css'      => [
                '/dolisci/css/dolisci.css',
            ],
            'js'       => [
                '/dolisci/js/dolisci.js.php',
            ],
            'hooks'    => [
                'projectcard',
                'projectlist',
                'productcard',
                'productservicelist',
                'contactcard',
                'contactlist',
                'invoicecard',
                'invoicereccard',
                'main',
            ],
        ];

        $this->dirs            = [];
        $this->config_page_url = ['setup.php@dolisci'];

        $this->depends      = ['modFacture', 'modProjet', 'modProduct', 'modService', 'modCategorie'];
        $this->requiredby   = [];
        $this->conflictwith = [];

        $this->langfiles             = ['dolisci@dolisci'];
        $this->phpmin                = [7, 4];
        $this->need_dolibarr_version = [23, 0];

        $i = 0;
        $this->const = [
            $i++ => ['DOLISCI_HIDE_TASKS_ON_SCI_PROJECTS', 'integer', 1, 'Hide tasks and time spent tabs on real estate projects', 0, 'current'],
            $i   => ['DOLISCI_AUTO_LINK_INVOICE_PROJECT', 'integer', 1, 'Automatically link invoices to the property project via product/service', 0, 'current'],
        ];

        $this->tabs   = [];
        $this->boxes  = [];
        $this->rights = [];
        $r = 0;

        /* DOLISCI PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'ReadAdminPageDoliSci';
        $this->rights[$r][4] = 'adminpage';
        $this->rights[$r][5] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = 'ReadModuleDoliSci';
        $this->rights[$r][4] = 'read';

        // Main menu and Left menu entries for DoliSCI
        $this->menu = [];
        $r = 0;

        // 1. Top menu: DoliSCI
        $this->menu[$r++] = [
            'fk_menu'  => '',
            'type'     => 'top',
            'titre'    => 'DoliSCI',
            'prefix'   => '<i class="fas fa-building pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => '',
            'url'      => '/dolisci/dolisciindex.php',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\')',
            'perms'    => '$user->hasRight(\'projet\', \'lire\') || $user->hasRight(\'dolisci\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        // Dashboard
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuDashboard',
            'prefix'   => '<i class="fas fa-home pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_home',
            'url'      => '/dolisci/dolisciindex.php',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\')',
            'perms'    => '$user->hasRight(\'projet\', \'lire\') || $user->hasRight(\'dolisci\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        // 2. Projets de type biens (Real Estate Projects)
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuProjects',
            'prefix'   => '<i class="fas fa-city pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_projects',
            'url'      => '/projet/list.php?mainmenu=dolisci&leftmenu=dolisci_projects&contextpage=dolisciprojectlist',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'project\')',
            'perms'    => '$user->hasRight(\'projet\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_projects',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuNewProject',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_projects_new',
            'url'      => '/projet/card.php?mainmenu=dolisci&leftmenu=dolisci_projects&action=create',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'project\')',
            'perms'    => '$user->hasRight(\'projet\', \'creer\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_projects',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListProjects',
            'prefix'   => '<i class="fas fa-list pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_projects_list',
            'url'      => '/projet/list.php?mainmenu=dolisci&leftmenu=dolisci_projects&contextpage=dolisciprojectlist',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'project\')',
            'perms'    => '$user->hasRight(\'projet\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        // 3. Produits type bien (Property Products / Fiscal Lots)
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuProducts',
            'prefix'   => '<i class="fas fa-key pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_products',
            'url'      => '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_products&type=0',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'product\')',
            'perms'    => '$user->hasRight(\'produit\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_products',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuNewProduct',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_products_new',
            'url'      => '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_products&action=create&type=0',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'product\')',
            'perms'    => '$user->hasRight(\'produit\', \'creer\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_products',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListProducts',
            'prefix'   => '<i class="fas fa-list pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_products_list',
            'url'      => '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_products&type=0',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'product\')',
            'perms'    => '$user->hasRight(\'produit\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        // 4. Services type loyer / charges (Rental Services)
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuServices',
            'prefix'   => '<i class="fas fa-file-invoice-dollar pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_services',
            'url'      => '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_services&type=1',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'service\')',
            'perms'    => '$user->hasRight(\'service\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_services',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuNewRentService',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_services_new_rent',
            'url'      => '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_services&action=create&type=1&options_dolisci_service_nature=LOYER&accountancy_code_sell=706100',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'service\')',
            'perms'    => '$user->hasRight(\'service\', \'creer\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_services',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuNewChargesService',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_services_new_charges',
            'url'      => '/product/card.php?mainmenu=dolisci&leftmenu=dolisci_services&action=create&type=1&options_dolisci_service_nature=CHARGES&accountancy_code_sell=708800',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'service\')',
            'perms'    => '$user->hasRight(\'service\', \'creer\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_services',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListServices',
            'prefix'   => '<i class="fas fa-list pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_services_list',
            'url'      => '/product/list.php?mainmenu=dolisci&leftmenu=dolisci_services&type=1',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'service\')',
            'perms'    => '$user->hasRight(\'service\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        // 5. Contacts / Adresses (Addresses, Tenants, Co-tenants, Guarantors, Syndics, Notaries)
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuContacts',
            'prefix'   => '<i class="fas fa-address-book pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuNewContact',
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_new',
            'url'      => '/contact/card.php?mainmenu=dolisci&leftmenu=dolisci_contacts&action=create',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'creer\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactAddresses',
            'prefix'   => '<i class="fas fa-map-marker-alt pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_address',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_address',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactTenants',
            'prefix'   => '<i class="fas fa-user pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_tenant',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_tenant',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactCoTenants',
            'prefix'   => '<i class="fas fa-user-friends pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_cotenant',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_cotenant',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactGuarantors',
            'prefix'   => '<i class="fas fa-user-shield pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_guarantor',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_guarantor',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactSyndics',
            'prefix'   => '<i class="fas fa-building pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_syndic',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_syndic',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci,fk_leftmenu=dolisci_contacts',
            'type'     => 'left',
            'titre'    => 'DoliSciMenuListContactNotaries',
            'prefix'   => '<i class="fas fa-gavel pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_contacts_notary',
            'url'      => '/contact/list.php?mainmenu=dolisci&leftmenu=dolisci_contacts_notary',
            'langs'    => 'dolisci@dolisci',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\') && isModEnabled(\'societe\')',
            'perms'    => '$user->hasRight(\'societe\', \'contact\', \'lire\')',
            'target'   => '',
            'user'     => 0,
        ];

        // 6. Configuration
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisci',
            'type'     => 'left',
            'titre'    => 'Setup',
            'prefix'   => '<i class="fas fa-cog pictofixedwidth"></i>',
            'mainmenu' => 'dolisci',
            'leftmenu' => 'dolisci_setup',
            'url'      => '/dolisci/admin/setup.php?mainmenu=dolisci&leftmenu=dolisci_setup',
            'langs'    => 'admin',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'dolisci\')',
            'perms'    => '$user->hasRight(\'dolisci\', \'adminpage\', \'read\') || $user->admin',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     * Function called when module is enabled
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function init($options = ''): int
    {
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
        require_once __DIR__ . '/../../lib/dolisci.lib.php';

        $langs->load('dolisci@dolisci');

        // Enable sub-projects natively so buildings (parent projects) can group individual units/lots
        dolibarr_set_const($this->db, 'PROJECT_ENABLE_SUB_PROJECT', 1, 'integer', 0, '', $conf->entity);

        foreach (array_keys(dolisciGetTagDefinitions()) as $itemKey) {
            dolisciSyncItem($this->db, $itemKey);
        }
        foreach (array_keys(dolisciGetExtrafieldDefinitions()) as $itemKey) {
            dolisciSyncItem($this->db, $itemKey);
        }
        foreach (array_keys(dolisciGetContactRoleDefinitions()) as $itemKey) {
            dolisciSyncItem($this->db, $itemKey);
        }

        // Same as modFacture with its default template: the rent receipt template is enabled
        // for the current entity along with the module
        $sql = [
            "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'quittance' AND type = 'invoice' AND entity = " . ((int) $conf->entity),
            "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES ('quittance', 'invoice', " . ((int) $conf->entity) . ")",
        ];

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled
     *
     * @param  string $options Options when disabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function remove($options = ''): int
    {
        return $this->_remove([], $options);
    }
}
