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
 * \file    core/modules/product/doc/pdf_fichebien.modules.php
 * \ingroup dolisci
 * \brief   PDF template for DoliSCI Property Sheet (Fiche descriptive du bien à louer)
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/product/modules_product.class.php';
dol_include_once('/dolisci/lib/dolisci.pdf.lib.php');

/**
 * Class to generate the DoliSCI Property Sheet PDF (fichebien)
 */
class pdf_fichebien extends ModelePDFProduct
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var string Document type
	 */
	public $type;

	/**
	 * @var string Version
	 */
	public $version = 'dolibarr';

	/**
	 * @var Societe Issuer company
	 */
	public $emetteur;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(['main', 'companies', 'products', 'dolisci@dolisci']);

		$this->db          = $db;
		$this->name        = 'fichebien';
		$this->description = $langs->transnoentitiesnoconv('DoliSciPdfFicheBienDescription');
		$this->type        = 'pdf';

		$formatarray        = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format       = [$this->page_largeur, $this->page_hauteur];
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute  = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse  = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->option_logo  = 1;
		$this->option_multilang = 1;

		if (is_object($mysoc)) {
			$this->emetteur = $mysoc;
			if (empty($this->emetteur->country_code)) {
				$this->emetteur->country_code = substr($langs->defaultlang, -2);
			}
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Build PDF document onto disk
	 *
	 * @param  Product   $object          Object source to build document
	 * @param  Translate $outputlangs     Lang output object
	 * @param  string    $srctemplatepath Full path of source filename for generator using a template file
	 * @param  int<0,1>  $hidedetails     Do not show line details
	 * @param  int<0,1>  $hidedesc        Do not show desc
	 * @param  int<0,1>  $hideref         Do not show ref
	 * @return int<-1,1>                  1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		return dolisciWritePropertyOrLeasePdf(
			$this->db,
			$object,
			$outputlangs,
			'property',
			$this->format,
			(float) $this->marge_gauche,
			(float) $this->marge_droite,
			(float) $this->marge_haute,
			(float) $this->marge_basse,
			$this->error
		);
	}
}
