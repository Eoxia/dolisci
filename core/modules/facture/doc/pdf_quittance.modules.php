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
 * \brief   Rent due notice invoice template: the core Sponge template titled "Avis d'échéance"
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/doc/pdf_sponge.modules.php';
require_once __DIR__ . '/../../../../lib/dolisci.lib.php';

/**
 * Rent due notice invoice template, for the tenants billed with VAT details: companies renting
 * offices, and parking spaces.
 *
 * Until 2026 this template was a full copy of pdf_sponge (Dolibarr 15.0.2) dropped into the core
 * folder, with only its name and title changed. Extending the core template instead keeps every
 * fix of Sponge and changes only the title, the header lines (rent period, property addresses)
 * and the payment information (bank account and online payment link).
 *
 * The name stays "quittance": it is the value stored in llx_facture.model_pdf and
 * llx_document_model, so existing invoices keep regenerating with it. The label shown in the
 * lists of templates is "Avis d'échéance" (llx_document_model.libelle, set by the module).
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
        // $langs, and the rest of the page must keep reading "Invoice". Once paid, the notice is
        // "acquitté", as a receipted invoice
        $invoiceTitle = $outputlangs->tab_translate['PdfInvoiceTitle'] ?? null;
        $outputlangs->tab_translate['PdfInvoiceTitle'] = $outputlangs->transnoentitiesnoconv(dolisciIsInvoiceSettled($object) ? 'DoliSciNoticePaidTitle' : 'DoliSciNoticeTitle');

        // Sponge has no slot for them in its header: ActionsDoliSci::pdf_getLinkedObjects() prints
        // them as linked object lines, so that Sponge moves the address frames down by itself
        $rentPeriod = $this->getRentPeriod($object, $outputlangs);
        if ($rentPeriod !== '') {
            $object->context['dolisci_rent_period'] = $rentPeriod;
        }
        $object->context['dolisci_property_addresses'] = $this->getPropertyAddresses($object, $outputlangs);

        try {
            return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
        } finally {
            if ($invoiceTitle === null) {
                unset($outputlangs->tab_translate['PdfInvoiceTitle']);
            } else {
                $outputlangs->tab_translate['PdfInvoiceTitle'] = $invoiceTitle;
            }
            unset($object->context['dolisci_rent_period'], $object->context['dolisci_property_addresses']);
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
        [$dateStart, $dateEnd] = dolisciGetInvoicePeriod($object);

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

    /**
     * Addresses of the properties the invoice bills, one line each for the header of Sponge
     *
     * @param  Facture   $object      Invoice
     * @param  Translate $outputlangs Lang output object
     * @return list<string>           Addresses; "not set" when no product of the lines has one, to spot the service to complete
     */
    public function getPropertyAddresses($object, $outputlangs): array
    {
        $addresses = dolisciGetPropertyAddresses($this->db, $object);
        if (count($addresses) === 0) {
            // Printed after the title "Property address :"
            return [$outputlangs->transnoentitiesnoconv('DoliSciPropertyAddressNotSet')];
        }

        return array_map(static function (string $address) use ($outputlangs): string {
            return $outputlangs->convToOutputCharset(str_replace("\n", ', ', $address));
        }, $addresses);
    }

    /**
     * Show the payment terms, the bank account and the online payment link
     *
     * Sponge shows the bank account for a transfer only, and the online payment link for a card
     * payment only. A tenant may pay any notice by transfer, and by card when Stripe is on: both
     * are added when Sponge left them out.
     *
     * @param  TCPDF      $pdf            PDF
     * @param  Facture    $object         Invoice
     * @param  float      $posy           Top of the area
     * @param  Translate  $outputlangs    Lang output object
     * @param  ?Translate $outputlangsbis Lang object for the second language
     * @return float                      Bottom of the area
     */
    protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
    {
        $posy = parent::drawInfoTable($pdf, $object, $posy, $outputlangs, $outputlangsbis);
        if ((int) $object->type === Facture::TYPE_CREDIT_NOTE) {
            return $posy;
        }

        $fontSize = pdf_getPDFFontSize($outputlangs);
        $modeCode = (string) $object->mode_reglement_code;

        if ($modeCode !== '' && $modeCode !== 'VIR') {
            $account = dolisciGetInvoiceBankAccount($this->db, $object);
            if ($account !== null) {
                $posy = pdf_bank($pdf, $outputlangs, $this->marge_gauche, $posy, $account, 0, $fontSize) + 2;
            }
        }

        $paymentUrl = dolisciGetOnlinePaymentUrl($object);
        if ($paymentUrl !== '' && !$this->showsOnlinePaymentLink($object)) {
            // Same width as the left side of Sponge, which ends at 110 mm
            $posy = dolisciPdfWritePaymentLink($pdf, $outputlangs, $paymentUrl, $this->marge_gauche, $posy, 110 - $this->marge_gauche, $fontSize - 2) + 1;
        }

        return $posy;
    }

    /**
     * Whether Sponge has written its own online payment link: same conditions as its drawInfoTable()
     *
     * @param  Facture $object Invoice
     * @return bool            True when written
     */
    protected function showsOnlinePaymentLink($object): bool
    {
        if (!in_array((string) $object->mode_reglement_code, ['', 'CB', 'VAD'], true) || !getDolGlobalString('PDF_SHOW_LINK_TO_ONLINE_PAYMENT') || (int) $object->status === Facture::STATUS_DRAFT) {
            return false;
        }
        include_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';

        return count(getValidOnlinePaymentMethods('')) > 0;
    }
}
