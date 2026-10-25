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
 * \file    core/triggers/interface_99_modDoliSci_DoliSciTriggers.class.php
 * \ingroup dolisci
 * \brief   DoliSci triggers for real estate projects, property products, rental services, and invoices
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for DoliSci module
 */
class InterfaceDoliSciTriggers extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db);
        $this->name        = preg_replace('/^Interface/i', '', get_class($this));
        $this->family      = 'financial';
        $this->description = 'DoliSCI triggers (sync rental services with property projects, accounting codes, and invoice project assignment)';
        $this->version     = self::VERSIONS['prod'];
        $this->picto       = 'dolisci_color@dolisci';
    }

    /**
     * Function called when a Dolibarr business event is done.
     *
     * @param  string       $action Event action code
     * @param  CommonObject $object Object
     * @param  User         $user   Object user
     * @param  Translate    $langs  Object langs
     * @param  Conf         $conf   Object conf
     * @return int                  Return integer < 0 if KO, 0 if no triggered ran, > 0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
    {
        if (!isModEnabled('dolisci')) {
            return 0;
        }

        switch ($action) {
            case 'PROJECT_CREATE':
            case 'PROJECT_MODIFY':
                if ($object instanceof Project) {
                    require_once __DIR__ . '/../../lib/dolisci.lib.php';
                    $tagProjectId = getDolGlobalInt('DOLISCI_TAG_PROJECT_PROPERTY');
                    $postCats     = GETPOSTISARRAY('categories') ? GETPOST('categories', 'array:int') : [];
                    $isSciProject = GETPOST('mainmenu', 'aZ09') === 'dolisci'
                        || ($_SESSION['mainmenu'] ?? '') === 'dolisci'
                        || ($tagProjectId > 0 && in_array($tagProjectId, $postCats, true))
                        || (!empty($object->id) && dolisciIsRealEstateProject($this->db, (int) $object->id));
                    if ($isSciProject) {
                        $updates = [
                            'fk_statut = ' . ((int) Project::STATUS_VALIDATED),
                            'usage_opportunity = 0',
                            'usage_task = 1',
                            'usage_bill_time = 0',
                        ];
                        $object->statut            = Project::STATUS_VALIDATED;
                        $object->status            = Project::STATUS_VALIDATED;
                        $object->usage_opportunity = 0;
                        $object->usage_task        = 1;
                        $object->usage_bill_time   = 0;
                        $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'projet SET ' . implode(', ', $updates) . ' WHERE rowid = ' . ((int) $object->id);
                        $this->db->query($sql);
                        dolisciAssignTag($this->db, $object, 'DOLISCI_TAG_PROJECT_PROPERTY', 'project');
                    }
                }
                break;

            case 'PRODUCT_CREATE':
            case 'PRODUCT_MODIFY':
                if ($object instanceof Product && (int) $object->type === Product::TYPE_PRODUCT) {
                    require_once __DIR__ . '/../../lib/dolisci.lib.php';
                    $object->fetch_optionals();
                    $hasSciPropertyData = !empty($object->array_options['options_dolisci_fk_project'])
                        || !empty($object->array_options['options_dolisci_property_nature'])
                        || GETPOST('mainmenu', 'aZ09') === 'dolisci'
                        || ($_SESSION['mainmenu'] ?? '') === 'dolisci';
                    if ($hasSciPropertyData) {
                        dolisciAssignTag($this->db, $object, 'DOLISCI_TAG_PRODUCT_PROPERTY', 'product');
                    }
                } elseif ($object instanceof Product && (int) $object->type === Product::TYPE_SERVICE) {
                    require_once __DIR__ . '/../../lib/dolisci.lib.php';
                    $object->fetch_optionals();
                    $serviceNature     = (string) ($object->array_options['options_dolisci_service_nature'] ?? '');
                    $propertyProductId = (int) ($object->array_options['options_dolisci_fk_property_product'] ?? 0);
                    $serviceProjectId  = (int) ($object->array_options['options_dolisci_service_fk_project'] ?? 0);

                    if ($serviceNature === 'LOYER') {
                        dolisciAssignTag($this->db, $object, 'DOLISCI_TAG_SERVICE_RENT', 'product');
                    } elseif ($serviceNature === 'CHARGES') {
                        dolisciAssignTag($this->db, $object, 'DOLISCI_TAG_SERVICE_CHARGES', 'product');
                    }

                    // Auto-populate the SCI project on the rental service from the linked property product
                    if ($propertyProductId > 0 && $serviceProjectId <= 0) {
                        $propertyProduct = new Product($this->db);
                        if ($propertyProduct->fetch($propertyProductId) > 0) {
                            $propertyProduct->fetch_optionals();
                            $propProjId = (int) ($propertyProduct->array_options['options_dolisci_fk_project'] ?? 0);
                            if ($propProjId > 0) {
                                $object->array_options['options_dolisci_service_fk_project'] = $propProjId;
                                $object->insertExtraFields();
                            }
                        }
                    }

                    // Auto-assign the accounting code on the Service according to its rental nature:
                    // LOYER -> 706100, CHARGES -> 708800
                    $genericServiceAccount = getDolGlobalString('ACCOUNTING_SERVICE_SOLD_ACCOUNT');
                    if (empty($object->accountancy_code_sell) || $object->accountancy_code_sell === $genericServiceAccount || in_array($object->accountancy_code_sell, ['706100', '708800'], true)) {
                        $defaultAccount = '';
                        if ($serviceNature === 'LOYER') {
                            $defaultAccount = '706100';
                        } elseif ($serviceNature === 'CHARGES') {
                            $defaultAccount = '708800';
                        }
                        if ($defaultAccount !== '' && $object->accountancy_code_sell !== $defaultAccount) {
                            $sql = "UPDATE " . MAIN_DB_PREFIX . "product SET accountancy_code_sell = '" . $this->db->escape($defaultAccount) . "' WHERE rowid = " . ((int) $object->id);
                            $this->db->query($sql);
                            $object->accountancy_code_sell = $defaultAccount;
                        }
                    }
                }
                break;

            case 'LINEBILL_INSERT':
            case 'LINEBILLREC_INSERT':
            case 'LINEBILL_SUPPLIER_CREATE':
                if (!getDolGlobalInt('DOLISCI_AUTO_LINK_INVOICE_PROJECT', 1)) {
                    break;
                }
                $fkProduct = (int) ($object->fk_product ?? 0);
                if ($fkProduct <= 0) {
                    break;
                }

                $product = new Product($this->db);
                if ($product->fetch($fkProduct) <= 0) {
                    break;
                }
                $product->fetch_optionals();

                $targetProjectId = (int) ($product->array_options['options_dolisci_service_fk_project'] ?? 0);
                if ($targetProjectId <= 0) {
                    $targetProjectId = (int) ($product->array_options['options_dolisci_fk_project'] ?? 0);
                }
                if ($targetProjectId <= 0 && !empty($product->array_options['options_dolisci_fk_property_product'])) {
                    $propProd = new Product($this->db);
                    if ($propProd->fetch((int) $product->array_options['options_dolisci_fk_property_product']) > 0) {
                        $propProd->fetch_optionals();
                        $targetProjectId = (int) ($propProd->array_options['options_dolisci_fk_project'] ?? 0);
                    }
                }

                if ($targetProjectId <= 0) {
                    break;
                }

                if ($action === 'LINEBILL_INSERT' && !empty($object->fk_facture)) {
                    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
                    $invoice = new Facture($this->db);
                    if ($invoice->fetch((int) $object->fk_facture) > 0 && empty($invoice->fk_project)) {
                        $invoice->setProject($targetProjectId);
                    }
                } elseif ($action === 'LINEBILLREC_INSERT' && !empty($object->fk_facture)) {
                    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';
                    $invoiceRec = new FactureRec($this->db);
                    if ($invoiceRec->fetch((int) $object->fk_facture) > 0 && empty($invoiceRec->fk_project)) {
                        $invoiceRec->setProject($targetProjectId);
                    }
                } elseif ($action === 'LINEBILL_SUPPLIER_CREATE' && !empty($object->fk_facture_fourn)) {
                    require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
                    $supplierInvoice = new FactureFournisseur($this->db);
                    if ($supplierInvoice->fetch((int) $object->fk_facture_fourn) > 0 && empty($supplierInvoice->fk_project)) {
                        $supplierInvoice->setProject($targetProjectId);
                    }
                }
                break;
        }

        return 0;
    }
}
