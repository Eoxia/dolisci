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
 * \file    core/modules/facture/doc/pdf_quittance.modules.php
 * \ingroup dolisci
 * \brief   Rent receipt invoice template: the core Sponge template titled "Quittance"
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/doc/pdf_sponge.modules.php';

/**
 * Rent receipt invoice template.
 *
 * Until 2026 this template was a full copy of pdf_sponge (Dolibarr 15.0.2) dropped into the core
 * folder, with only its name and title changed. Extending the core template instead keeps every
 * fix of Sponge and changes only the title and the rent period shown in the header.
 *
 * The name stays "quittance": it is the value stored in llx_facture.model_pdf and
 * llx_document_model, so existing invoices keep regenerating with it.
 */
class pdf_quittance extends pdf_sponge
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs;

        parent::__construct($db);

        $langs->load('dolisci@dolisci');

        $this->name        = 'quittance';
        $this->description = $langs->trans('DoliSciQuittanceDescription');
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     * Build the PDF onto disk
     *
     * @param  Facture   $object          Object to generate
     * @param  Translate $outputlangs     Lang output object
     * @param  string    $srctemplatepath Full path of source filename for generator using a template file
     * @param  int<0,1>  $hidedetails     Do not show line details
     * @param  int<0,1>  $hidedesc        Do not show desc
     * @param  int<0,1>  $hideref         Do not show ref
     * @return int<-1,1>                  1 = OK, <= 0 = KO
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        // phpcs:enable
        global $langs;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        // Loaded first, so that the swap below is not overwritten when Sponge loads "bills" itself
        $outputlangs->loadLangs(['main', 'bills', 'dolisci@dolisci']);

        // Sponge reads its title (and the PDF subject and keywords) from PdfInvoiceTitle. The
        // translation is swapped for this generation only: $outputlangs is often the global
        // $langs, and the rest of the page must keep reading "Invoice"
        $invoiceTitle = $outputlangs->tab_translate['PdfInvoiceTitle'] ?? null;
        $outputlangs->tab_translate['PdfInvoiceTitle'] = $outputlangs->transnoentitiesnoconv('DoliSciQuittanceTitle');

        // Sponge has no slot for it in its header: ActionsDoliSci::pdf_getLinkedObjects() prints it
        // as a linked object line, so that Sponge moves the address frames down by itself
        $rentPeriod = $this->getRentPeriod($object, $outputlangs);
        if ($rentPeriod !== '') {
            $object->context['dolisci_rent_period'] = $rentPeriod;
        }

        try {
            return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
        } finally {
            if ($invoiceTitle === null) {
                unset($outputlangs->tab_translate['PdfInvoiceTitle']);
            } else {
                $outputlangs->tab_translate['PdfInvoiceTitle'] = $invoiceTitle;
            }
            unset($object->context['dolisci_rent_period']);
        }
    }

    /**
     * Rent period covered by the invoice: from the earliest start date to the latest end date of
     * its lines (service dates, filled automatically by recurring invoices whose lines have
     * "Set start date" / "Set end date" checked)
     *
     * @param  Facture   $object      Invoice
     * @param  Translate $outputlangs Lang output object
     * @return string                 "From 01/10/2026 to 31/10/2026", '' when no line has a date
     */
    public function getRentPeriod($object, $outputlangs): string
    {
        $dateStart = 0;
        $dateEnd   = 0;
        foreach ($object->lines as $line) {
            $lineStart = (int) $line->date_start;
            $lineEnd   = (int) $line->date_end;
            if ($lineStart > 0 && ($dateStart === 0 || $lineStart < $dateStart)) {
                $dateStart = $lineStart;
            }
            if ($lineEnd > 0 && $lineEnd > $dateEnd) {
                $dateEnd = $lineEnd;
            }
        }

        // Same wording and date format as the period Sponge writes under each line
        if ($dateStart > 0 && $dateEnd > 0) {
            return $outputlangs->transnoentitiesnoconv('DateFromTo', dol_print_date($dateStart, 'day', false, $outputlangs), dol_print_date($dateEnd, 'day', false, $outputlangs));
        }
        if ($dateStart > 0) {
            return $outputlangs->transnoentitiesnoconv('DateFrom', dol_print_date($dateStart, 'day', false, $outputlangs));
        }
        if ($dateEnd > 0) {
            return $outputlangs->transnoentitiesnoconv('DateUntil', dol_print_date($dateEnd, 'day', false, $outputlangs));
        }
        return '';
    }
}
