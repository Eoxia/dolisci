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
 * \file    lib/dolisci.pdf.lib.php
 * \ingroup dolisci
 * \brief   Shared helpers to build DoliSCI Property Sheet (fichebien) and Lease Sheet (fichebail) PDFs
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once __DIR__ . '/dolisci.lib.php';

/**
 * Resolve property, project, address contact, and lease/financial services for a Product or Service.
 *
 * @param  DoliDB    $db          Database handler
 * @param  Product   $object      Current Product or Service
 * @param  Translate $outputlangs Output language
 * @param  string    $mode        'property' or 'lease'
 * @return array<string,mixed>    Structured data array for PDF rendering
 */
function dolisciCollectPdfSheetData(DoliDB $db, Product $object, Translate $outputlangs, string $mode): array
{
	global $mysoc;

	$outputlangs->loadLangs(['main', 'dict', 'companies', 'products', 'projects', 'dolisci@dolisci']);
	if (empty($object->array_options)) {
		$object->fetch_optionals();
	}

	$propProduct = null;
	if ((int) $object->type === Product::TYPE_PRODUCT) {
		$propProduct = $object;
	} else {
		$propProduct = dolisciGetPropertyFromService($db, $object);
		if ($propProduct instanceof Product && empty($propProduct->array_options)) {
			$propProduct->fetch_optionals();
		}
	}

	// Resolve Project
	$projectId = 0;
	if ($propProduct instanceof Product) {
		$projectId = (int) ($propProduct->array_options['options_dolisci_fk_project'] ?? 0);
	}
	if ($projectId <= 0) {
		$projectId = (int) ($object->array_options['options_dolisci_service_fk_project'] ?? 0);
	}
	$project = null;
	if ($projectId > 0) {
		$proj = new Project($db);
		if ($proj->fetch($projectId) > 0) {
			$proj->fetch_optionals();
			$project = $proj;
		}
	}

	// Resolve Property Address Contact
	$addrContact = null;
	if ($project instanceof Project) {
		$contactId = (int) ($project->array_options['options_dolisci_fk_socpeople'] ?? 0);
		if ($contactId <= 0) {
			$contacts = $project->liste_contact(-1, 'external', 0, 'DOLISCI_PROPERTY_ADDRESS');
			if (is_array($contacts) && !empty($contacts[0]['id'])) {
				$contactId = (int) $contacts[0]['id'];
			}
		}
		if ($contactId > 0) {
			$c = new Contact($db);
			if ($c->fetch($contactId) > 0) {
				$addrContact = $c;
			}
		}
	}

	// Resolve Lease (BAIL) and child services (LOYER, CHARGES, DEPOT_GARANTIE, ACTE_CAUTIONNEMENT)
	$leaseObj      = null;
	$rentAmount    = 0.0;
	$chargesAmount = 0.0;
	$depositAmount = 0.0;
	$guaranteeAmt  = 0.0;
	$hasRent       = false;
	$hasCharges    = false;
	$hasDeposit    = false;
	$hasGuarantee  = false;

	if ((int) $object->type === Product::TYPE_SERVICE) {
		$nature = (string) ($object->array_options['options_dolisci_service_nature'] ?? '');
		if ($nature === 'BAIL' || stripos((string) $object->ref, 'Bail') === 0) {
			$leaseObj = $object;
		} else {
			$leaseId = (int) ($object->array_options['options_dolisci_fk_lease'] ?? 0);
			if ($leaseId > 0) {
				$tmpLease = new Product($db);
				if ($tmpLease->fetch($leaseId) > 0) {
					$tmpLease->fetch_optionals();
					$leaseObj = $tmpLease;
				}
			}
		}
		$targetForChildren = $leaseObj ?? $object;
		$children = dolisciGetServiceChildServices($db, $targetForChildren, $propProduct);
		if ($leaseObj === null && $nature === 'LOYER') {
			$rentAmount = (float) $object->price;
			$hasRent    = true;
		} elseif ($leaseObj === null && $nature === 'CHARGES') {
			$chargesAmount = (float) $object->price;
			$hasCharges    = true;
		} elseif ($leaseObj === null && $nature === 'DEPOT_GARANTIE') {
			$depositAmount = (float) $object->price;
			$hasDeposit    = true;
		}
		foreach ($children as $child) {
			$cNat = (string) ($child->dolisci_service_nature ?? '');
			if ($cNat === 'LOYER') {
				$rentAmount += (float) $child->price;
				$hasRent     = true;
			} elseif ($cNat === 'CHARGES' || $cNat === 'REGUL_CHARGES') {
				$chargesAmount += (float) $child->price;
				$hasCharges     = true;
			} elseif ($cNat === 'DEPOT_GARANTIE') {
				$depositAmount += (float) $child->price;
				$hasDeposit     = true;
			} elseif ($cNat === 'ACTE_CAUTIONNEMENT') {
				$guaranteeAmt += (float) $child->price;
				$hasGuarantee  = true;
			}
		}
	} elseif ($propProduct instanceof Product) {
		$rentalData = dolisciGetPropertyRentalServices($db, $propProduct);
		$services   = $rentalData['services'] ?? [];
		$defaultLeaseId = (int) ($propProduct->array_options['options_dolisci_fk_default_lease'] ?? 0);
		$firstBailId    = 0;
		foreach ($services as $sObj) {
			if ((string) ($sObj->dolisci_service_nature ?? '') === 'BAIL') {
				if ($firstBailId === 0) {
					$firstBailId = (int) $sObj->rowid;
				}
				if ($defaultLeaseId > 0 && (int) $sObj->rowid === $defaultLeaseId) {
					$firstBailId = $defaultLeaseId;
					break;
				}
			}
		}
		if ($firstBailId > 0) {
			$tmpLease = new Product($db);
			if ($tmpLease->fetch($firstBailId) > 0) {
				$tmpLease->fetch_optionals();
				$leaseObj = $tmpLease;
			}
		}
		foreach ($services as $sObj) {
			$sLeaseId = (int) ($sObj->dolisci_fk_lease ?? 0);
			if ($firstBailId > 0 && $sLeaseId > 0 && $sLeaseId !== $firstBailId) {
				continue;
			}
			$cNat = (string) ($sObj->dolisci_service_nature ?? '');
			if ($cNat === 'LOYER') {
				$rentAmount += (float) $sObj->price;
				$hasRent     = true;
			} elseif ($cNat === 'CHARGES' || $cNat === 'REGUL_CHARGES') {
				$chargesAmount += (float) $sObj->price;
				$hasCharges     = true;
			} elseif ($cNat === 'DEPOT_GARANTIE') {
				$depositAmount += (float) $sObj->price;
				$hasDeposit     = true;
			} elseif ($cNat === 'ACTE_CAUTIONNEMENT') {
				$guaranteeAmt += (float) $sObj->price;
				$hasGuarantee  = true;
			}
		}
	}

	if (!$hasDeposit && $leaseObj instanceof Product) {
		$efDeposit = (float) ($leaseObj->array_options['options_dolisci_deposit'] ?? 0);
		if ($efDeposit > 0) {
			$depositAmount = $efDeposit;
			$hasDeposit    = true;
		}
	}

	// Property extrafields
	$propOpts = $propProduct instanceof Product ? $propProduct->array_options : [];
	$natureLabels = [
		'IMMEUBLE'  => $outputlangs->transnoentitiesnoconv('DoliSciNatureBuilding'),
		'APPT'      => $outputlangs->transnoentitiesnoconv('DoliSciNatureApartment'),
		'MAISON'    => $outputlangs->transnoentitiesnoconv('DoliSciNatureHouse'),
		'LOCAL_PRO' => $outputlangs->transnoentitiesnoconv('DoliSciNatureCommercial'),
		'GARAGE'    => $outputlangs->transnoentitiesnoconv('DoliSciNatureParking'),
		'CAVE'      => $outputlangs->transnoentitiesnoconv('DoliSciNatureCellar'),
		'TERRAIN'   => $outputlangs->transnoentitiesnoconv('DoliSciNatureLand'),
	];
	$regimeLabels = [
		'COPRO' => $outputlangs->transnoentitiesnoconv('DoliSciRegimeCoOwnership'),
		'MONO'  => $outputlangs->transnoentitiesnoconv('DoliSciRegimeSingleOwnership'),
	];
	$leaseTypeLabels = [
		'NU'      => $outputlangs->transnoentitiesnoconv('DoliSciLeaseUnfurnished'),
		'MEUBLE'  => $outputlangs->transnoentitiesnoconv('DoliSciLeaseFurnished'),
		'COLOC'   => $outputlangs->transnoentitiesnoconv('DoliSciLeaseShared'),
		'PRO'     => $outputlangs->transnoentitiesnoconv('DoliSciLeaseCommercial'),
		'PARKING' => $outputlangs->transnoentitiesnoconv('DoliSciLeaseParking'),
	];

	$propNatureCode  = (string) ($propOpts['options_dolisci_property_nature'] ?? '');
	$legalRegimeCode = (string) ($propOpts['options_dolisci_legal_regime'] ?? '');
	$leaseTypeCode   = $leaseObj instanceof Product ? (string) ($leaseObj->array_options['options_dolisci_lease_type'] ?? '') : '';

	$cadastreParts = array_filter([
		trim((string) ($propOpts['options_dolisci_cadastre_section'] ?? '')),
		trim((string) ($propOpts['options_dolisci_cadastre_number'] ?? '')),
	]);
	$cadastreRef = !empty($cadastreParts) ? implode(' / ', $cadastreParts) : '-';

	$lotParts = array_filter([
		trim((string) ($propOpts['options_dolisci_floor_door'] ?? '')),
		trim((string) ($propOpts['options_dolisci_fiscal_lot'] ?? '')),
	]);
	$lotLocation = !empty($lotParts) ? implode(' — ', $lotParts) : '-';

	$carrezRaw = $propOpts['options_dolisci_surface_carrez'] ?? '';
	$carrezStr = ($carrezRaw !== '' && (float) $carrezRaw > 0) ? price((float) $carrezRaw, 0, $outputlangs, 1, 2, 2) . ' m²' : '-';

	$roomsRaw = $propOpts['options_dolisci_rooms'] ?? '';
	$roomsStr = ($roomsRaw !== '' && (int) $roomsRaw > 0) ? (string) ((int) $roomsRaw) : '-';

	$purchaseDateRaw = $propOpts['options_dolisci_purchase_date'] ?? '';
	$purchaseDateStr = !empty($purchaseDateRaw) ? dol_print_date($purchaseDateRaw, 'day', false, $outputlangs) : '-';

	$purchasePriceRaw = $propOpts['options_dolisci_purchase_price'] ?? '';
	$purchasePriceStr = ($purchasePriceRaw !== '' && (float) $purchasePriceRaw > 0) ? price((float) $purchasePriceRaw, 0, $outputlangs, 1, -1, 2) . ' €' : '-';

	$dpeEnergy = (string) ($propOpts['options_dolisci_dpe_energy'] ?? '');
	$dpeGes    = (string) ($propOpts['options_dolisci_dpe_ges'] ?? '');

	// DDT / Documents checklist summary
	$ddtList = [];
	if ($propProduct instanceof Product) {
		$docDefs  = dolisciGetPropertyRequiredDocuments();
		$docState = dolisciGetPropertyDocumentsChecklistState($propProduct);
		foreach ($docDefs as $docKey => $docDef) {
			$st = $docState[$docKey] ?? null;
			if (!empty($st['concerned']) && !empty($st['provided'])) {
				$ddtList[] = $outputlangs->transnoentitiesnoconv($docDef['label']);
			}
		}
	}

	// Landlord (SCI)
	$sciName    = is_object($mysoc) && !empty($mysoc->name) ? (string) $mysoc->name : '-';
	$sciSiret   = is_object($mysoc) ? trim((string) $mysoc->idprof2) : '';
	$sciRcs     = is_object($mysoc) ? trim((string) $mysoc->idprof4) : '';
	$sciIdStr   = implode(' — ', array_filter([$sciSiret, $sciRcs])) ?: '-';
	$sciAddr    = is_object($mysoc) ? trim(implode(', ', array_filter([(string) $mysoc->address, trim((string) $mysoc->zip . ' ' . (string) $mysoc->town)]))) : '-';
	$sciContact = is_object($mysoc) ? trim(implode(' / ', array_filter([(string) $mysoc->phone, (string) $mysoc->email]))) : '-';

	// Property address
	$propAddress = $addrContact instanceof Contact && !empty($addrContact->address) ? (string) $addrContact->address : (string) ($propOpts['options_dolisci_cadastre_lieudit'] ?? '-');
	$propZipTown = $addrContact instanceof Contact ? trim((string) $addrContact->zip . ' ' . (string) $addrContact->town) : '';
	if ($propZipTown === '') {
		$propZipTown = '-';
	}

	$descText = trim((string) $object->description);
	if ($descText === '' && $propProduct instanceof Product && $propProduct->id !== $object->id) {
		$descText = trim((string) $propProduct->description);
	}

	return [
		'mode'               => $mode,
		'object'             => $object,
		'property'           => $propProduct,
		'project'            => $project,
		'lease'              => $leaseObj,
		'sci_name'           => $sciName,
		'sci_siret_rcs'      => $sciIdStr,
		'sci_address'        => $sciAddr !== '' ? $sciAddr : '-',
		'sci_contact'        => $sciContact !== '' ? $sciContact : '-',
		'prop_address'       => $propAddress !== '' ? $propAddress : '-',
		'prop_ziptown'       => $propZipTown,
		'lot_location'       => $lotLocation,
		'cadastre_ref'       => $cadastreRef,
		'cadastre_surface'   => trim((string) ($propOpts['options_dolisci_cadastre_surface'] ?? '')) ?: '-',
		'subdivision'        => trim((string) ($propOpts['options_dolisci_subdivision'] ?? '')) ?: '-',
		'tantiemes'          => trim((string) ($propOpts['options_dolisci_tantiemes'] ?? '')) ?: '-',
		'property_nature'    => $natureLabels[$propNatureCode] ?? ($propNatureCode ?: '-'),
		'legal_regime'       => $regimeLabels[$legalRegimeCode] ?? ($legalRegimeCode ?: '-'),
		'surface_carrez'     => $carrezStr,
		'rooms'              => $roomsStr,
		'purchase_date'      => $purchaseDateStr,
		'purchase_price'     => $purchasePriceStr,
		'ownership_quota'    => trim((string) ($propOpts['options_dolisci_ownership_quota'] ?? '')) ?: '-',
		'notary'             => trim((string) ($propOpts['options_dolisci_notary'] ?? '')) ?: '-',
		'dpe_energy'         => $dpeEnergy !== '' ? $dpeEnergy : 'NA',
		'dpe_ges'            => $dpeGes !== '' ? $dpeGes : 'NA',
		'ddt_summary'        => !empty($ddtList) ? implode(', ', $ddtList) : $outputlangs->transnoentitiesnoconv('DoliSciPdfNoDdtAttached'),
		'lease_ref'          => $leaseObj instanceof Product ? $leaseObj->ref . ' — ' . $leaseObj->label : '-',
		'lease_type'         => $leaseTypeLabels[$leaseTypeCode] ?? ($leaseTypeCode ?: '-'),
		'irl_ref'            => $leaseObj instanceof Product ? (trim((string) ($leaseObj->array_options['options_dolisci_irl_ref'] ?? '')) ?: '-') : '-',
		'rent_amount'        => $hasRent ? price($rentAmount, 0, $outputlangs, 1, -1, 2) . ' €' : '-',
		'charges_amount'     => $hasCharges ? price($chargesAmount, 0, $outputlangs, 1, -1, 2) . ' €' : '-',
		'total_rent_amount'  => ($hasRent || $hasCharges) ? price($rentAmount + $chargesAmount, 0, $outputlangs, 1, -1, 2) . ' €' : '-',
		'deposit_amount'     => $hasDeposit ? price($depositAmount, 0, $outputlangs, 1, -1, 2) . ' €' : '-',
		'guarantee_amount'   => $hasGuarantee ? price($guaranteeAmt, 0, $outputlangs, 1, -1, 2) . ' €' : '-',
		'description'        => $descText,
	];
}

/**
 * Draw the page footer for DoliSCI Property / Lease sheets.
 *
 * @param  TCPDF               $pdf         TCPDF instance
 * @param  array<string,mixed> $data        Sheet data from dolisciCollectPdfSheetData()
 * @param  Translate           $outputlangs Output language
 * @param  float               $margeGauche Left margin
 * @param  float               $margeDroite Right margin
 * @param  float               $pageLargeur Page width
 * @param  float               $pageHauteur Page height
 * @param  int                 $pageNum     Current page number
 * @param  int                 $totalPages  Total page count
 * @return void
 */
function dolisciPdfDrawSheetFooter(TCPDF $pdf, array $data, Translate $outputlangs, float $margeGauche, float $margeDroite, float $pageLargeur, float $pageHauteur, int $pageNum, int $totalPages): void
{
	/** @var Product $object */
	$object  = $data['object'];
	$footerY = $pageHauteur - 14;

	$pdf->SetDrawColor(197, 213, 216);
	$pdf->Line($margeGauche, $footerY - 2, $pageLargeur - $margeDroite, $footerY - 2);
	$pdf->SetFont('', '', 8);
	$pdf->SetTextColor(100, 110, 120);

	$leftKey = ($data['mode'] === 'lease') ? 'DoliSciPdfFooterLease' : 'DoliSciPdfFooterProperty';
	$leftTxt = $outputlangs->transnoentitiesnoconv($leftKey, (string) $data['sci_name'], (string) $object->ref);
	$pdf->SetXY($margeGauche, $footerY);
	$pdf->Cell(($pageLargeur - $margeGauche - $margeDroite) * 0.75, 5, $outputlangs->convToOutputCharset($leftTxt), 0, 0, 'L');

	$rightTxt = $outputlangs->transnoentitiesnoconv('DoliSciPdfPageOf', $pageNum, $totalPages);
	$pdf->Cell(($pageLargeur - $margeGauche - $margeDroite) * 0.25, 5, $outputlangs->convToOutputCharset($rightTxt), 0, 0, 'R');
	$pdf->SetTextColor(31, 41, 51);
}

/**
 * Build and write a DoliSCI Property Sheet (fichebien) or Lease Sheet (fichebail) PDF to disk.
 *
 * @param  DoliDB        $db          Database handler
 * @param  Product       $object      Product or Service
 * @param  Translate     $outputlangs Output language
 * @param  string        $mode        'property' or 'lease'
 * @param  array<float>  $format      Page format [width, height]
 * @param  float         $margeGauche Left margin
 * @param  float         $margeDroite Right margin
 * @param  float         $margeHaute  Top margin
 * @param  float         $margeBasse  Bottom margin
 * @param  string        $errorOut    Error message output
 * @return int                        1 if OK, <= 0 if KO
 */
function dolisciWritePropertyOrLeasePdf(DoliDB $db, Product $object, Translate $outputlangs, string $mode, array $format, float $margeGauche, float $margeDroite, float $margeHaute, float $margeBasse, string &$errorOut): int
{
	global $conf, $user, $hookmanager, $langs;

	if (!is_object($outputlangs)) {
		$outputlangs = $langs;
	}
	if (getDolGlobalString('MAIN_USE_FPDF')) {
		$outputlangs->charset_output = 'ISO-8859-1';
	}

	$data = dolisciCollectPdfSheetData($db, $object, $outputlangs, $mode);

	$dirOut = !empty($conf->product->multidir_output[$object->entity ?? $conf->entity])
		? $conf->product->multidir_output[$object->entity ?? $conf->entity]
		: $conf->product->dir_output;
	if (empty($dirOut)) {
		return 0;
	}

	$objectref = dol_sanitizeFileName((string) $object->ref);
	if (!empty($object->specimen)) {
		$dir  = $dirOut;
		$file = $dir . '/SPECIMEN.pdf';
	} else {
		$dir  = $dirOut . '/' . $objectref;
		$file = $dir . '/' . $objectref . '.pdf';
	}

	if (!file_exists($dir)) {
		if (dol_mkdir($dir) < 0) {
			$errorOut = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return -1;
		}
	}

	if (!is_object($hookmanager)) {
		include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
		$hookmanager = new HookManager($db);
	}
	$hookmanager->initHooks(['pdfgeneration']);
	$parameters = ['file' => $file, 'object' => $object, 'outputlangs' => $outputlangs];
	global $action;
	$hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

	$pdf = pdf_getInstance($format);
	if (!is_object($pdf)) {
		$errorOut = 'Failed to initialize TCPDF instance';
		return -1;
	}
	$defaultFontSize = pdf_getPDFFontSize($outputlangs);
	$pdf->setAutoPageBreak(false, 0);
	if (class_exists('TCPDF')) {
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
	}
	$pdf->SetFont(pdf_getPDFFont($outputlangs));
	if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
		$pdf->SetCompression(false);
	}

	$pageLargeur = $format[0];
	$pageHauteur = $format[1];
	$contentW    = $pageLargeur - $margeGauche - $margeDroite;

	$pdf->Open();
	$pdf->SetMargins($margeGauche, $margeHaute, $margeDroite);
	$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
	$pdf->SetSubject($outputlangs->transnoentities($mode === 'lease' ? 'DoliSciPdfLeaseTitle' : 'DoliSciPdfPropertyTitle'));
	$pdf->SetCreator('Dolibarr ' . DOL_VERSION);
	$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getAnonymisableFullName($outputlangs)));

	$e = static function (string $val): string {
		return dol_escape_htmltag($val);
	};

	$tableStyle = 'border-collapse:collapse;font-size:8.5pt;color:#1F2933;';
	$thStyle    = 'background-color:#0F4C5C;color:#FFFFFF;font-weight:bold;padding:4px 6px;border:1px solid #0F4C5C;';
	$tdLblStyle = 'background-color:#F4F8F9;font-weight:bold;padding:4px 6px;border:1px solid #C5D5D8;color:#1F2933;';
	$tdValStyle = 'background-color:#FFFFFF;padding:4px 6px;border:1px solid #C5D5D8;color:#1F2933;';
	$secTitle   = static function (string $title) use ($e): string {
		return '<div style="font-size:10.5pt;font-weight:bold;color:#0F4C5C;margin-top:6px;margin-bottom:3px;">' . $e($title) . '</div>';
	};

	// ==================== PAGE 1 ====================
	$pdf->AddPage();

	$mainTitle = $outputlangs->transnoentitiesnoconv($mode === 'lease' ? 'DoliSciPdfLeaseTitle' : 'DoliSciPdfPropertyTitle');
	$subTitle  = $outputlangs->transnoentitiesnoconv($mode === 'lease' ? 'DoliSciPdfLeaseSubtitle' : 'DoliSciPdfPropertySubtitle');
	$refLabel  = $outputlangs->transnoentitiesnoconv($mode === 'lease' ? 'DoliSciPdfLeaseRef' : 'DoliSciPdfPropertyRef');
	$statusTxt = ((int) $object->status > 0) ? $outputlangs->transnoentitiesnoconv('DoliSciPdfStatusAvailable') : $outputlangs->transnoentitiesnoconv('DoliSciPdfStatusUnavailable');
	$dateTxt   = dol_print_date(dol_now(), 'day', false, $outputlangs);

	$headerHtml = '<table cellpadding="4" cellspacing="0" width="100%" style="border-bottom:2px solid #0F4C5C;">'
		. '<tr>'
		. '<td width="64%" style="vertical-align:top;">'
		. '<span style="font-size:15pt;font-weight:bold;color:#0F4C5C;">' . $e($mainTitle) . '</span><br>'
		. '<span style="font-size:8.5pt;color:#52606D;">' . $e($subTitle) . '</span>'
		. '</td>'
		. '<td width="36%" style="vertical-align:top;border-left:3px solid #0F4C5C;padding-left:8px;font-size:8.5pt;color:#1F2933;">'
		. '<b>' . $e($refLabel) . ' :</b> ' . $e((string) $object->ref) . '<br>'
		. '<b>' . $e($outputlangs->transnoentitiesnoconv('Status')) . ' :</b> ' . $e($statusTxt) . '<br>'
		. '<b>' . $e($outputlangs->transnoentitiesnoconv('Date')) . ' :</b> ' . $e($dateTxt)
		. '</td>'
		. '</tr></table>';

	$pdf->SetXY($margeGauche, $margeHaute);
	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $margeHaute, $headerHtml, 0, 1);

	// Section 1: Bailleur & Localisation du bien
	$sec1Html = $secTitle($outputlangs->transnoentitiesnoconv('DoliSciPdfSec1Title'))
		. '<table cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">'
		. '<tr>'
		. '<th width="50%" colspan="2" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfLandlordHeader')) . '</th>'
		. '<th width="50%" colspan="2" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfLocationHeader')) . '</th>'
		. '</tr>'
		. '<tr>'
		. '<td width="18%" style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfCompanyName')) . '</td>'
		. '<td width="32%" style="' . $tdValStyle . '">' . $e((string) $data['sci_name']) . '</td>'
		. '<td width="18%" style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfPropertyAddress')) . '</td>'
		. '<td width="32%" style="' . $tdValStyle . '">' . $e((string) $data['prop_address']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfSiretRcs')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['sci_siret_rcs']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfZipTown')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['prop_ziptown']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfHeadOffice')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['sci_address']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfBuildingLot')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['lot_location']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfManagementContact')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['sci_contact']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfCadastreRef')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['cadastre_ref']) . '</td>'
		. '</tr>'
		. '</table>';

	$pdf->SetY($pdf->GetY() + 3);
	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $pdf->GetY(), $sec1Html, 0, 1);

	// Section 2: Caractéristiques techniques & Surfaces
	$sec2Html = $secTitle($outputlangs->transnoentitiesnoconv('DoliSciPdfSec2Title'))
		. '<table cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">'
		. '<tr><th colspan="4" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfTechHeader')) . '</th></tr>'
		. '<tr>'
		. '<td width="22%" style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPropertyNature')) . '</td>'
		. '<td width="28%" style="' . $tdValStyle . '">' . $e((string) $data['property_nature']) . '</td>'
		. '<td width="22%" style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciSurfaceCarrez')) . '</td>'
		. '<td width="28%" style="' . $tdValStyle . '">' . $e((string) $data['surface_carrez']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciLegalRegime')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['legal_regime']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciCadastreSurface')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['cadastre_surface']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciRooms')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['rooms']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciSubdivision')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['subdivision']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciTantiemes')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['tantiemes']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPurchaseDate')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['purchase_date']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciOwnershipQuota')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['ownership_quota']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciNotary')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['notary']) . '</td>'
		. '</tr>'
		. '</table>';

	$pdf->SetY($pdf->GetY() + 3);
	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $pdf->GetY(), $sec2Html, 0, 1);

	// Section 3: Diagnostics techniques & Performance énergétique
	$dpeLabel = ($data['dpe_energy'] === 'NA') ? $outputlangs->transnoentitiesnoconv('DoliSciDpeNotApplicable') : 'Classe ' . $data['dpe_energy'];
	$gesLabel = ($data['dpe_ges'] === 'NA') ? $outputlangs->transnoentitiesnoconv('DoliSciDpeNotApplicable') : 'Classe ' . $data['dpe_ges'];

	$sec3Html = $secTitle($outputlangs->transnoentitiesnoconv('DoliSciPdfSec3Title'))
		. '<table cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">'
		. '<tr>'
		. '<th width="34%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfDiagCol')) . '</th>'
		. '<th width="22%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfDiagClassCol')) . '</th>'
		. '<th width="44%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfDiagDetailsCol')) . '</th>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciDpeEnergy')) . '</td>'
		. '<td style="' . $tdValStyle . '"><b>' . $e($dpeLabel) . '</b></td>'
		. '<td style="' . $tdValStyle . '">-</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciDpeGes')) . '</td>'
		. '<td style="' . $tdValStyle . '"><b>' . $e($gesLabel) . '</b></td>'
		. '<td style="' . $tdValStyle . '">-</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfDdtLabel')) . '</td>'
		. '<td colspan="2" style="' . $tdValStyle . '">' . $e((string) $data['ddt_summary']) . '</td>'
		. '</tr>'
		. '</table>';

	$pdf->SetY($pdf->GetY() + 3);
	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $pdf->GetY(), $sec3Html, 0, 1);

	dolisciPdfDrawSheetFooter($pdf, $data, $outputlangs, $margeGauche, $margeDroite, $pageLargeur, $pageHauteur, 1, 2);

	// ==================== PAGE 2 ====================
	$pdf->AddPage();
	$pdf->SetXY($margeGauche, $margeHaute);

	// Section 4: Conditions financières & Modalités du bail
	$sec4Html = $secTitle($outputlangs->transnoentitiesnoconv('DoliSciPdfSec4Title'))
		. '<table cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">'
		. '<tr>'
		. '<th width="22%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfLeaseParamsHeader')) . '</th>'
		. '<th width="28%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('Value')) . '</th>'
		. '<th width="30%" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfFinancialHeader')) . '</th>'
		. '<th width="20%" align="right" style="' . $thStyle . '">' . $e($outputlangs->transnoentitiesnoconv('Amount')) . '</th>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciLeaseType')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['lease_type']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfMonthlyRentExcl')) . '</td>'
		. '<td align="right" style="' . $tdValStyle . '">' . $e((string) $data['rent_amount']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciLinkedLease')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['lease_ref']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfMonthlyCharges')) . '</td>'
		. '<td align="right" style="' . $tdValStyle . '">' . $e((string) $data['charges_amount']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciIrlRef')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['irl_ref']) . '</td>'
		. '<td style="' . $tdLblStyle . '"><b>' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfMonthlyRentIncl')) . '</b></td>'
		. '<td align="right" style="' . $tdValStyle . '"><b>' . $e((string) $data['total_rent_amount']) . '</b></td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciPurchasePrice')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['purchase_price']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciDeposit')) . '</td>'
		. '<td align="right" style="' . $tdValStyle . '">' . $e((string) $data['deposit_amount']) . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciFiscalLot')) . '</td>'
		. '<td style="' . $tdValStyle . '">' . $e((string) $data['lot_location']) . '</td>'
		. '<td style="' . $tdLblStyle . '">' . $e($outputlangs->transnoentitiesnoconv('DoliSciServiceNatureGuaranteeAct')) . '</td>'
		. '<td align="right" style="' . $tdValStyle . '">' . $e((string) $data['guarantee_amount']) . '</td>'
		. '</tr>'
		. '</table>';

	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $margeHaute, $sec4Html, 0, 1);

	// Section 5: Description détaillée & Observations
	$descContent = $data['description'] !== ''
		? dol_htmlentitiesbr($data['description'])
		: $e($outputlangs->transnoentitiesnoconv('DoliSciPdfNoDescription'));

	$sec5Html = $secTitle($outputlangs->transnoentitiesnoconv('DoliSciPdfSec5Title'))
		. '<table cellpadding="8" cellspacing="0" width="100%" style="border:1px solid #C5D5D8;background-color:#FFFFFF;font-size:8.5pt;color:#1F2933;">'
		. '<tr><td>' . $descContent . '</td></tr>'
		. '</table>'
		. '<div style="font-size:8pt;color:#52606D;margin-top:8px;">'
		. '<b>' . $e($outputlangs->transnoentitiesnoconv('DoliSciPdfContactVisits')) . ' :</b> '
		. $e((string) $data['sci_name']) . ' — ' . $e((string) $data['sci_contact'])
		. '</div>';

	$pdf->SetY($pdf->GetY() + 4);
	$pdf->writeHTMLCell($contentW, 0, $margeGauche, $pdf->GetY(), $sec5Html, 0, 1);

	dolisciPdfDrawSheetFooter($pdf, $data, $outputlangs, $margeGauche, $margeDroite, $pageLargeur, $pageHauteur, 2, 2);

	$pdf->Close();
	$pdf->Output($file, 'F');

	$hookmanager->executeHooks('afterPDFCreation', ['file' => $file, 'object' => $object, 'outputlangs' => $outputlangs], $object, $action);

	dolChmod($file);

	return 1;
}
