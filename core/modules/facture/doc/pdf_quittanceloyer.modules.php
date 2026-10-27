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
 * \file    core/modules/facture/doc/pdf_quittanceloyer.modules.php
 * \ingroup dolisci
 * \brief   Rent receipt laid out as the ANIL model, filled from the invoice
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once __DIR__ . '/../../../../lib/dolisci.lib.php';

/**
 * Rent receipt laid out as the model of the ANIL ("Location vide ou meublée à titre de résidence
 * principale : quittance de loyer"), every field filled from the invoice.
 *
 * A rent receipt proves a payment, so the document follows the payment state of the invoice:
 * paid, it is a rent receipt ("quittance"); partially paid, a receipt for the sum received ("reçu",
 * art. 21 of the law of 6 July 1989); unpaid, a rent due notice ("avis d'échéance").
 *
 * @phpstan-type ReceiptData array{state:string,title:string,invoiceRef:string,landlordName:string,landlordAddress:string,signatory:string,tenantName:string,tenantAddress:string,doneAt:string,propertyAddress:string,statement:string,detailTitle:string,rows:list<array{0:string,1:float}>,total:float,received:float,remain:float,paymentLine:string,legalNotice:string}
 */
class pdf_quittanceloyer extends ModelePDFFactures
{
	/**
	 * Paid invoice: rent receipt
	 */
	const STATE_RECEIPT = 'receipt';

	/**
	 * Partially paid invoice: receipt for the sum received
	 */
	const STATE_PARTIAL = 'partial';

	/**
	 * Unpaid invoice: rent due notice
	 */
	const STATE_NOTICE = 'notice';

	/**
	 * @var array<int,Product|null> Products of the invoice lines, by id
	 */
	private $products = [];

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(['main', 'bills', 'dolisci@dolisci']);

		$this->db                    = $db;
		$this->name                  = 'quittanceloyer';
		$this->description           = $langs->trans('DoliSciRentReceiptDescription');
		$this->update_main_doc_field = 1;

		$this->type         = 'pdf';
		$formatArray        = pdf_getFormat();
		$this->page_largeur = $formatArray['width'];
		$this->page_hauteur = $formatArray['height'];
		$this->format       = [$this->page_largeur, $this->page_hauteur];
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute  = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse  = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->option_logo            = 1;
		$this->option_multilang       = 1;
		$this->option_draft_watermark = 1;
		$this->watermark              = '';

		if ($mysoc === null) {
			dol_syslog(get_class($this) . '::__construct() Global $mysoc should not be null.', LOG_ERR);
			return;
		}
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}
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
		global $action, $conf, $hookmanager, $langs, $user;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(['main', 'bills', 'companies', 'dict', 'dolisci@dolisci']);

		// Credit notes, down payments, proformas and situation invoices bill no rent term: they keep
		// the invoice layout of the "quittance" template
		if (!in_array((int) $object->type, [Facture::TYPE_STANDARD, Facture::TYPE_REPLACEMENT], true) || !empty($object->situation_cycle_ref)) {
			require_once __DIR__ . '/pdf_quittance.modules.php';
			$invoiceTemplate = new pdf_quittance($this->db);
			$result          = $invoiceTemplate->write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
			$this->result    = $invoiceTemplate->result;
			$this->error     = $invoiceTemplate->error;
			$this->errors    = $invoiceTemplate->errors;
			$this->warnings  = $invoiceTemplate->warnings;
			return $result;
		}

		if (empty($conf->facture->multidir_output[$conf->entity])) {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'FAC_OUTPUTDIR');
			return 0;
		}

		$object->fetch_thirdparty();

		if ($object->specimen) {
			$dir  = $conf->facture->multidir_output[$conf->entity];
			$file = $dir . '/SPECIMEN.pdf';
		} else {
			$objectRef = dol_sanitizeFileName($object->ref);
			$dir       = $conf->facture->multidir_output[$object->entity ?? $conf->entity] . '/' . $objectRef;
			$file      = $dir . '/' . $objectRef . '.pdf';
		}
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(['pdfgeneration']);
		$parameters = ['file' => $file, 'object' => $object, 'outputlangs' => $outputlangs];
		$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

		$this->watermark = '';
		if ((int) $object->status === Facture::STATUS_DRAFT && getDolGlobalString('FACTURE_DRAFT_WATERMARK')) {
			$this->watermark = getDolGlobalString('FACTURE_DRAFT_WATERMARK');
		}

		$receipt = $this->getReceiptData($object, $outputlangs);

		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->Open();
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($receipt['title']);
		$pdf->SetCreator('Dolibarr ' . DOL_VERSION);
		$pdf->SetAuthor($this->emetteur->name . ($user->id > 0 ? ' - ' . $outputlangs->convToOutputCharset($user->getFullName($outputlangs)) : ''));
		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . ' ' . $receipt['title'] . ' ' . $outputlangs->convToOutputCharset($object->thirdparty->name));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		// Same room as the core templates for the legal footer
		$showFootDetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
		$heightForFooter = $this->marge_basse + ($showFootDetails ? 22 : 12);
		$pdf->SetAutoPageBreak(true, $heightForFooter);

		$pdf->AddPage();
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		$this->writeContent($pdf, $object, $receipt, $outputlangs, $heightForFooter);

		$nbPages = $pdf->getNumPages();
		for ($page = 1; $page <= $nbPages; $page++) {
			$pdf->setPage($page);
			// setPage() restores the automatic page break saved with the page: the footer, written
			// below that limit, would open a new page
			$pdf->SetAutoPageBreak(false, 0);
			// INVOICE_FREE_TEXT is written for invoices (late payment penalties...): left out
			pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showFootDetails, 1, $this->page_largeur, $this->watermark);
		}

		$pdf->Close();
		$pdf->Output($file, 'F');

		$hookmanager->initHooks(['pdfgeneration']);
		$parameters     = ['file' => $file, 'object' => $object, 'outputlangs' => $outputlangs];
		$reshook        = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		$this->warnings = $hookmanager->warnings;
		dolChmod($file);
		if ($reshook < 0) {
			$this->error  = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$this->result = ['fullpath' => $file];

		return 1;
	}

	/**
	 * Everything the receipt prints, read from the invoice
	 *
	 * @param  Facture   $object      Invoice, lines and thirdparty loaded
	 * @param  Translate $outputlangs Output language
	 * @return ReceiptData            Values to print
	 */
	public function getReceiptData(Facture $object, Translate $outputlangs): array
	{
		global $conf;

		$outputlangs->loadLangs(['main', 'bills', 'companies', 'dict', 'dolisci@dolisci']);

		$this->products = [];
		$currency       = (string) $conf->currency;
		$total          = (float) $object->total_ttc;

		// Same sums as the "Paid" and "Remain to pay" lines of the core templates
		$paid         = (float) $object->getSommePaiement() + (float) $object->getSumCreditNotesUsed() + (float) $object->getSumDepositsUsed();
		$remain       = (float) price2num($total - $paid, 'MT');
		$closedAsPaid = (int) $object->status === Facture::STATUS_CLOSED && $object->close_code !== Facture::CLOSECODE_BADDEBT;
		if ($remain <= 0 || $closedAsPaid) {
			$state = self::STATE_RECEIPT;
		} elseif ($paid > 0) {
			$state = self::STATE_PARTIAL;
		} else {
			$state = self::STATE_NOTICE;
		}

		// Mode 1: payments only, without the credit notes and down payments used
		$paymentDates = [];
		foreach ($object->getListOfPayments('', 0, 1) as $payment) {
			// Documented as a timestamp, returned as the SQL date (Dolibarr 23 and 24): both accepted
			$paymentDate    = $payment['date'];
			$paymentDates[] = is_numeric($paymentDate) ? (int) $paymentDate : (int) $this->db->jdate((string) $paymentDate);
		}
		$paymentDates = array_values(array_unique(array_filter($paymentDates)));
		sort($paymentDates);

		[$periodStart, $periodEnd] = dolisciGetInvoicePeriod($object);
		$invoiceDate               = dol_print_date($object->date, 'day', false, $outputlangs);
		if ($periodStart > 0 && $periodEnd > 0) {
			$periodText = $outputlangs->transnoentitiesnoconv('DoliSciRentForPeriod', dol_print_date($periodStart, 'day', false, $outputlangs), dol_print_date($periodEnd, 'day', false, $outputlangs));
		} else {
			$periodText = $outputlangs->transnoentitiesnoconv('DoliSciRentForInvoice', $outputlangs->convToOutputCharset($object->ref), $invoiceDate);
		}

		$titleKeys = [self::STATE_RECEIPT => 'DoliSciRentReceiptTitle', self::STATE_PARTIAL => 'DoliSciRentPartialReceiptTitle', self::STATE_NOTICE => 'DoliSciRentNoticeTitle'];
		$title     = $outputlangs->transnoentitiesnoconv($titleKeys[$state]);
		if ($periodStart > 0 && $periodEnd > 0) {
			if (dol_print_date($periodStart, '%Y%m', false) === dol_print_date($periodEnd, '%Y%m', false)) {
				$month = dol_print_date($periodStart, '%B %Y', false, $outputlangs);
				// Dolibarr capitalizes French month names, written in lowercase within a sentence
				if (strpos((string) $outputlangs->defaultlang, 'fr_') === 0) {
					$month = dol_strtolower($month);
				}
				// "du mois d'octobre", "du mois de mai"
				$title =$outputlangs->transnoentitiesnoconv(preg_match('/^[aeiouy]/i', dol_string_unaccent($month)) ? 'DoliSciRentTitleMonthElided' : 'DoliSciRentTitleMonth', $title, $month);
			} else {
				$title = $outputlangs->transnoentitiesnoconv('DoliSciRentTitlePeriod', $title, dol_print_date($periodStart, 'day', false, $outputlangs), dol_print_date($periodEnd, 'day', false, $outputlangs));
			}
		}

		$landlordName = $outputlangs->convToOutputCharset($this->emetteur->name);
		$managers     = trim((string) $this->emetteur->managers);
		$signatory    = $managers !== '' ? $outputlangs->convToOutputCharset($managers) : $landlordName;
		$landlord     = $managers !== '' ? $outputlangs->transnoentitiesnoconv('DoliSciRentLandlordOnBehalf', $signatory, $landlordName) : $landlordName;

		// Same recipient as the core templates: the billing contact when there is one
		$useContact      = false;
		$billingContacts = $object->getIdContact('external', 'BILLING');
		if (count($billingContacts) > 0 && $object->fetch_contact($billingContacts[0]) > 0) {
			$useContact = true;
		}
		$recipient = $object->thirdparty;
		if ($useContact && $object->contact->socid != $object->thirdparty->id && getDolGlobalString('MAIN_USE_COMPANY_NAME_OF_CONTACT', '1')) {
			$recipient = $object->contact;
		}
		$tenantName    = pdfBuildThirdpartyName($recipient, $outputlangs);
		$tenantAddress = trim(pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($useContact ? $object->contact : ''), ($useContact ? 1 : 0), 'target', $object));
		$tenant        = $useContact ? $outputlangs->convToOutputCharset($object->contact->getFullName($outputlangs, 1)) : $tenantName;

		$rows        = [];
		$rowIndexes  = [];
		$designation = '';
		foreach ($object->lines as $line) {
			// Titles and subtotals added by other modules
			if ((int) $line->product_type === 9) {
				continue;
			}
			[$label, $lineDesignation] = $this->getLineLabel($line, $outputlangs);
			if (!isset($rowIndexes[$label])) {
				$rowIndexes[$label] = count($rows);
				$rows[]             = [$label, 0.0];
			}
			$rows[$rowIndexes[$label]][1] += (float) $line->total_ttc;
			if ($designation === '') {
				$designation = $lineDesignation;
			}
		}

		$amount     = $state === self::STATE_NOTICE ? $total : min($paid, $total);
		$amountWords = dolisciAmountToWords($amount, $outputlangs, $currency);
		$amountText  = $this->formatAmount($amount, $outputlangs, $currency);
		if ($amountWords !== '') {
			$amountText = $amountWords . ' (' . $amountText . ')';
		}
		if ($state === self::STATE_RECEIPT) {
			$statement = $outputlangs->transnoentitiesnoconv('DoliSciRentReceiptStatement', $landlord, $tenant, $amountText, $periodText);
		} elseif ($state === self::STATE_PARTIAL) {
			$statement = $outputlangs->transnoentitiesnoconv('DoliSciRentPartialStatement', $landlord, $tenant, $amountText, $periodText, $this->formatAmount($total, $outputlangs, $currency));
		} else {
			$statement = $outputlangs->transnoentitiesnoconv('DoliSciRentNoticeStatement', $periodText, $amountText, dol_print_date($object->date_lim_reglement, 'day', false, $outputlangs));
		}

		if ($state === self::STATE_NOTICE) {
			$paymentLine = $outputlangs->transnoentitiesnoconv('DoliSciRentDueDate', dol_print_date($object->date_lim_reglement, 'day', false, $outputlangs));
		} elseif (count($paymentDates) > 1) {
			$paymentLine = $outputlangs->transnoentitiesnoconv('DoliSciRentPaymentDates', implode(', ', array_map(static function (int $date) use ($outputlangs): string {
				return dol_print_date($date, 'day', false, $outputlangs);
			}, $paymentDates)));
		} elseif (count($paymentDates) === 1) {
			$paymentLine = $outputlangs->transnoentitiesnoconv('DoliSciRentPaymentDate', dol_print_date($paymentDates[0], 'day', false, $outputlangs));
		} else {
			$paymentLine = '';
		}

		// Dated the day of the last payment: the receipt cannot predate the money
		$doneDate = $state !== self::STATE_NOTICE && count($paymentDates) > 0 ? $paymentDates[count($paymentDates) - 1] : (int) $object->date;
		$town     = trim((string) $this->emetteur->town);
		$doneAt   = $town !== '' ? $outputlangs->transnoentitiesnoconv('DoliSciRentDoneAtOn', $outputlangs->convToOutputCharset($town), dol_print_date($doneDate, 'day', false, $outputlangs)) : $outputlangs->transnoentitiesnoconv('DoliSciRentDoneOn', dol_print_date($doneDate, 'day', false, $outputlangs));

		$propertyAddress = $this->getPropertyAddress($object, $outputlangs);

		return [
			'state'           => $state,
			'title'           => $title,
			'invoiceRef'      => $outputlangs->transnoentitiesnoconv('DoliSciRentInvoiceRef', $outputlangs->convToOutputCharset($object->ref), $invoiceDate),
			'landlordName'    => $landlordName,
			'landlordAddress' => trim(pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object)),
			'signatory'       => $signatory,
			'tenantName'      => $tenantName,
			'tenantAddress'   => $tenantAddress,
			'doneAt'          => $doneAt,
			'propertyAddress' => $propertyAddress . ($designation !== '' ? "\n" . $designation : ''),
			'statement'       => $statement,
			'detailTitle'     => $outputlangs->transnoentitiesnoconv($state === self::STATE_NOTICE ? 'DoliSciRentNoticeDetail' : 'DoliSciRentPaymentDetail'),
			'rows'            => $rows,
			'total'           => $total,
			'received'        => $paid,
			'remain'          => $remain,
			'paymentLine'     => $paymentLine,
			'legalNotice'     => $state === self::STATE_RECEIPT ? $outputlangs->transnoentitiesnoconv('DoliSciRentReceiptLegalNotice') : '',
		];
	}

	/**
	 * Label of an invoice line in the payment detail, and the designation of the rented property
	 * when the line bills a DoliSCI rental service linked to it
	 *
	 * @param  CommonInvoiceLine $line        Invoice line
	 * @param  Translate         $outputlangs Output language
	 * @return array{0:string,1:string}       Label ("Loyer", "Provision pour charges"...), designation or ''
	 */
	protected function getLineLabel($line, Translate $outputlangs): array
	{
		$product = $this->fetchProduct((int) $line->fk_product);
		if ($product === null) {
			$lines = preg_split('/\R/', trim(dol_string_nohtmltag((string) $line->desc)));
			return [dol_trunc(is_array($lines) ? $lines[0] : '', 60), ''];
		}

		// The nature set by DoliSCI on its rental services, else the accounting account it uses
		$natureKeys  = ['LOYER' => 'DoliSciRentLineRent', 'CHARGES' => 'DoliSciRentLineCharges', 'REGUL_CHARGES' => 'DoliSciRentLineChargesRegul', 'DEPOT_GARANTIE' => 'DoliSciRentLineDeposit'];
		$accountKeys = ['706100' => 'DoliSciRentLineRent', '708800' => 'DoliSciRentLineCharges'];
		$nature      = (string) ($product->array_options['options_dolisci_service_nature'] ?? '');
		$account     = (string) $product->accountancy_code_sell;
		if (isset($natureKeys[$nature])) {
			$label = $outputlangs->transnoentitiesnoconv($natureKeys[$nature]);
		} elseif (isset($accountKeys[$account])) {
			$label = $outputlangs->transnoentitiesnoconv($accountKeys[$account]);
		} else {
			$label = (string) ($product->multilangs[$outputlangs->defaultlang]['label'] ?? '');
			$label = $outputlangs->convToOutputCharset(dol_trunc($label !== '' ? $label : $product->label, 60));
		}

		$designation = '';
		$property    = $this->fetchProduct((int) ($product->array_options['options_dolisci_fk_property_product'] ?? 0));
		if ($property !== null) {
			$floorDoor   = trim((string) ($property->array_options['options_dolisci_floor_door'] ?? ''));
			$designation = $outputlangs->convToOutputCharset($property->label . ($floorDoor !== '' ? ' - ' . $floorDoor : ''));
		}

		return [$label, $designation];
	}

	/**
	 * Address of the rented property: the "property address" contact of the invoice project (field
	 * of the project, or contact with the DoliSCI role), else of its parent project, as a lot may
	 * hold no address of its own while its building does. Without one, the tenant address: for a
	 * main residence, it is the rented home.
	 *
	 * @param  Facture   $object      Invoice, thirdparty loaded
	 * @param  Translate $outputlangs Output language
	 * @return string                 Address, several lines
	 */
	protected function getPropertyAddress(Facture $object, Translate $outputlangs): string
	{
		$projectId = (int) $object->fk_project;
		for ($depth = 0; $projectId > 0 && $depth < 5; $depth++) {
			$project = new Project($this->db);
			if ($project->fetch($projectId) <= 0) {
				break;
			}
			$project->fetch_optionals();
			$contactIds = array_merge([(int) ($project->array_options['options_dolisci_fk_socpeople'] ?? 0)], $project->getIdContact('external', 'DOLISCI_PROPERTY_ADDRESS'));
			foreach ($contactIds as $contactId) {
				$contact = new Contact($this->db);
				if ((int) $contactId > 0 && $contact->fetch((int) $contactId) > 0 && trim($contact->address . $contact->zip . $contact->town) !== '') {
					return $this->formatAddress($contact, $outputlangs);
				}
			}
			$projectId = (int) $project->fk_project;
		}

		return $this->formatAddress($object->thirdparty, $outputlangs);
	}

	/**
	 * Postal address of a contact or thirdparty, with its country when abroad
	 *
	 * @param  Contact|Societe $addressee   Contact or thirdparty
	 * @param  Translate       $outputlangs Output language
	 * @return string                       Address, several lines
	 */
	protected function formatAddress($addressee, Translate $outputlangs): string
	{
		$withCountry = !empty($addressee->country_code) && $addressee->country_code !== $this->emetteur->country_code ? 1 : 0;

		return $outputlangs->convToOutputCharset(dol_format_address($addressee, $withCountry, "\n", $outputlangs));
	}

	/**
	 * Amount in figures that a line break cannot split: "1 320,00 €"
	 *
	 * @param  float     $amount      Amount
	 * @param  Translate $outputlangs Output language
	 * @param  string    $currency    Currency code
	 * @return string                 Amount, with non-breaking spaces
	 */
	protected function formatAmount(float $amount, Translate $outputlangs, string $currency): string
	{
		// TCPDF breaks lines on every space but U+00A0
		return str_replace([' ', "\u{202F}"], "\u{00A0}", price($amount, 0, $outputlangs, 1, -1, 2, $currency));
	}

	/**
	 * Product of an invoice line, fetched once per document
	 *
	 * @param  int          $productId Product id
	 * @return Product|null            Product, null when none or not found
	 */
	protected function fetchProduct(int $productId): ?Product
	{
		if ($productId <= 0) {
			return null;
		}
		if (!array_key_exists($productId, $this->products)) {
			$product                    = new Product($this->db);
			$this->products[$productId] = $product->fetch($productId) > 0 ? $product : null;
		}

		return $this->products[$productId];
	}

	/**
	 * Write the receipt, in the order of the ANIL model
	 *
	 * @param  TCPDF                $pdf             PDF
	 * @param  Facture              $object          Invoice
	 * @param  ReceiptData          $receipt         Values from getReceiptData()
	 * @param  Translate            $outputlangs     Output language
	 * @param  float                $heightForFooter Height kept for the legal footer, bottom margin included
	 * @return void
	 */
	protected function writeContent(&$pdf, Facture $object, array $receipt, Translate $outputlangs, float $heightForFooter): void
	{
		global $conf;

		$fontSize    = pdf_getPDFFontSize($outputlangs);
		$left        = $this->marge_gauche;
		$width       = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$blockWidth  = 85;
		$rightX      = $this->page_largeur - $this->marge_droite - $blockWidth;
		$posY        = $this->marge_haute;
		$currency    = (string) $conf->currency;

		// Logo, as on the core templates
		$logoHeight = 0;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && !empty($this->emetteur->logo)) {
			$logoDir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity ?? $conf->entity])) {
				$logoDir = $conf->mycompany->multidir_output[$object->entity ?? $conf->entity];
			}
			$logo = $logoDir . '/logos/' . (getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $this->emetteur->logo : 'thumbs/' . $this->emetteur->logo_small);
			if (is_readable($logo)) {
				$logoHeight = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $left, $posY, 0, $logoHeight);
			}
		}
		$posY += $logoHeight > 0 ? $logoHeight + 4 : 6;

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $fontSize + 8);
		$pdf->SetXY($left, $posY);
		$pdf->MultiCell($width, 8, $receipt['title'], 0, 'C');
		$pdf->SetTextColor(90, 90, 90);
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetX($left);
		$pdf->MultiCell($width, 4, $receipt['invoiceRef'], 0, 'C');
		$pdf->SetTextColor(0, 0, 0);
		$posY = $pdf->GetY() + 8;

		// Landlord on the left, tenant on the right and a little lower, as in the model
		$pdf->SetXY($left, $posY);
		$pdf->SetFont('', 'B', $fontSize);
		$pdf->MultiCell($blockWidth, 5, $receipt['landlordName'], 0, 'L');
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetX($left);
		$pdf->MultiCell($blockWidth, 4, $receipt['landlordAddress'], 0, 'L');
		$landlordBottom = $pdf->GetY();

		$pdf->SetXY($rightX, $posY + 14);
		$pdf->SetFont('', 'B', $fontSize);
		$pdf->MultiCell($blockWidth, 5, $receipt['tenantName'], 0, 'L');
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetX($rightX);
		$pdf->MultiCell($blockWidth, 4, $receipt['tenantAddress'], 0, 'L');
		$posY = max($landlordBottom, $pdf->GetY()) + 6;

		$pdf->SetFont('', '', $fontSize);
		$pdf->SetXY($rightX, $posY);
		$pdf->MultiCell($blockWidth, 5, $receipt['doneAt'], 0, 'L');
		$posY = $pdf->GetY() + 6;

		$pdf->SetXY($left, $posY);
		$pdf->SetFont('', 'BU', $fontSize);
		$pdf->MultiCell($width, 5, $outputlangs->transnoentitiesnoconv('DoliSciRentPropertyAddress'), 0, 'L');
		$pdf->SetFont('', '', $fontSize);
		$pdf->SetX($left);
		$pdf->MultiCell($width, 5, $receipt['propertyAddress'], 0, 'L');
		$posY = $pdf->GetY() + 10;

		// Left aligned: TCPDF justifies the last line of a paragraph too
		$pdf->SetXY($left, $posY);
		$pdf->MultiCell($width, 5, $receipt['statement'], 0, 'L');
		$posY = $pdf->GetY() + 10;

		// Payment detail
		$labelWidth  = 120;
		$amountWidth = 40;
		$pdf->SetXY($left, $posY);
		$pdf->SetFont('', 'BU', $fontSize);
		$pdf->MultiCell($width, 6, $receipt['detailTitle'], 0, 'L');
		$pdf->SetFont('', '', $fontSize);
		foreach ($receipt['rows'] as $row) {
			$pdf->SetX($left + 2);
			$pdf->Cell($labelWidth, 6, $row[0], 0, 0, 'L');
			$pdf->Cell($amountWidth, 6, price($row[1], 0, $outputlangs, 1, -1, 2, $currency), 0, 1, 'R');
		}
		$lineY = $pdf->GetY() + 0.5;
		$pdf->Line($left + 2, $lineY, $left + 2 + $labelWidth + $amountWidth, $lineY);
		$pdf->SetY($lineY + 0.5);
		$pdf->SetFont('', 'B', $fontSize);
		$pdf->SetX($left + 2);
		$pdf->Cell($labelWidth, 6, $outputlangs->transnoentitiesnoconv('DoliSciRentTotal'), 0, 0, 'L');
		$pdf->Cell($amountWidth, 6, price($receipt['total'], 0, $outputlangs, 1, -1, 2, $currency), 0, 1, 'R');
		$pdf->SetFont('', '', $fontSize);
		if ($receipt['state'] === self::STATE_PARTIAL) {
			$pdf->SetX($left + 2);
			$pdf->Cell($labelWidth, 6, $outputlangs->transnoentitiesnoconv('DoliSciRentAlreadyPaid'), 0, 0, 'L');
			$pdf->Cell($amountWidth, 6, price($receipt['received'], 0, $outputlangs, 1, -1, 2, $currency), 0, 1, 'R');
			$pdf->SetFont('', 'B', $fontSize);
			$pdf->SetX($left + 2);
			$pdf->Cell($labelWidth, 6, $outputlangs->transnoentitiesnoconv('DoliSciRentRemainToPay'), 0, 0, 'L');
			$pdf->Cell($amountWidth, 6, price($receipt['remain'], 0, $outputlangs, 1, -1, 2, $currency), 0, 1, 'R');
			$pdf->SetFont('', '', $fontSize);
		}
		if ($receipt['paymentLine'] !== '') {
			$pdf->SetXY($left, $pdf->GetY() + 4);
			$pdf->MultiCell($width, 5, $receipt['paymentLine'], 0, 'L');
		}

		if (!empty($object->note_public)) {
			$substitutions = pdf_getSubstitutionArray($outputlangs, null, $object);
			complete_substitutions_array($substitutions, $outputlangs, $object);
			$note = make_substitutions((string) $object->note_public, $substitutions, $outputlangs);
			$pdf->SetFont('', '', $fontSize - 1);
			$pdf->writeHTMLCell($width, 4, $left, $pdf->GetY() + 4, dol_htmlentitiesbr($note), 0, 1);
			$pdf->SetFont('', '', $fontSize);
		}

		// Room left to sign by hand
		$pdf->SetXY($rightX, $pdf->GetY() + 8);
		$pdf->MultiCell($blockWidth, 5, $outputlangs->transnoentitiesnoconv('DoliSciRentLandlord') . "\n" . $receipt['signatory'], 0, 'L');
		$signatureBottom = $pdf->GetY() + 22;

		// Notice the model puts at the bottom of the page
		if ($receipt['legalNotice'] !== '') {
			$pdf->SetFont('', 'I', $fontSize - 2);
			$boxHeight = $pdf->getStringHeight($width - 6, $receipt['legalNotice']) + 4;
			$boxY      = $this->page_hauteur - $heightForFooter - $boxHeight - 4;
			if ($signatureBottom > $boxY) {
				$pdf->AddPage();
				pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
				$boxY = $this->page_hauteur - $heightForFooter - $boxHeight - 4;
			}
			$pdf->Rect($left, $boxY, $width, $boxHeight);
			$pdf->SetXY($left + 3, $boxY + 2);
			$pdf->MultiCell($width - 6, 4, $receipt['legalNotice'], 0, 'L');
			$pdf->SetFont('', '', $fontSize);
		}
	}
}
