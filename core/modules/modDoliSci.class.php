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
 * \brief    Tools for property companies (SCI): rent receipt invoice template.
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
        $this->family          = 'financial';
        $this->module_position = '90';
        $this->name            = preg_replace('/^mod/i', '', get_class($this));
        $this->description     = 'DoliSciDescription';
        $this->descriptionlong = 'DoliSciDescriptionLong';
        $this->editor_name     = 'Evarisk';
        $this->editor_url      = 'https://evarisk.com';
        $this->version         = '23.0.0';
        $this->const_name      = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto           = 'bill';

        // Makes Dolibarr look for document templates in core/modules/<element>/doc/ of the module
        $this->module_parts = [
            'models' => 1,
        ];

        $this->dirs            = [];
        $this->config_page_url = [];

        $this->depends      = ['modFacture'];
        $this->requiredby   = [];
        $this->conflictwith = [];

        $this->langfiles             = ['dolisci@dolisci'];
        $this->phpmin                = [7, 4];
        $this->need_dolibarr_version = [23, 0];

        $this->const  = [];
        $this->tabs   = [];
        $this->boxes  = [];
        $this->rights = [];
        $this->menu   = [];
    }

    /**
     * Function called when module is enabled
     *
     * @param  string $options Options when enabling module ('', 'noboxes')
     * @return int             1 if OK, 0 if KO
     */
    public function init($options = ''): int
    {
        global $conf;

        // Same as modFacture with its default template: the rent receipt template is enabled
        // for the current entity along with the module, without a trip to the invoice setup
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
