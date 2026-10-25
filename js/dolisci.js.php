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
 * \file    js/dolisci.js.php
 * \ingroup dolisci
 * \brief   JavaScript file for module DoliSci
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');

// Load DoliSci environment
if (file_exists('../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../dolisci.main.inc.php';
} elseif (file_exists('../../dolisci.main.inc.php')) {
	require_once __DIR__ . '/../../dolisci.main.inc.php';
} else {
	die('Include of dolisci main fails');
}

// Define js type
top_httphead('text/javascript; charset=UTF-8');
// Important: Following code is to avoid page request by browser and PHP CPU at each Dolibarr page access.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
?>

/* Javascript library of module DoliSci */
document.addEventListener('DOMContentLoaded', function () {
	// 1. Admin Init page: "Select all" checkbox for extrafields and contact roles
	var checkAll = document.getElementById('dolisci_checkall');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			var boxes = document.querySelectorAll('.dolisci-init-check');
			for (var i = 0; i < boxes.length; i++) {
				boxes[i].checked = checkAll.checked;
			}
		});
	}

	// 2. Service card (create/edit): auto-fill accountancy_code_sell according to dolisci_service_nature
	var natureSelect = document.getElementById('options_dolisci_service_nature');
	if (natureSelect) {
		var applyNatureAccount = function () {
			var val = natureSelect.value;
			var code = '';
			if (val === 'LOYER') {
				code = '706100';
			} else if (val === 'CHARGES') {
				code = '708800';
			} else if (val === 'DEPOT_GARANTIE' || val === 'CAUTION') {
				code = '165000';
			}
			if (!code) {
				return;
			}
			var accField = document.querySelector('[name="accountancy_code_sell"]');
			if (accField) {
				accField.value = code;
				if (window.jQuery) {
					window.jQuery(accField).trigger('change');
				}
			}
		};
		natureSelect.addEventListener('change', applyNatureAccount);
		if (window.jQuery) {
			window.jQuery(natureSelect).on('change select2:select', applyNatureAccount);
		}
	}

	// 3. Simplified Project card in DoliSCI: replace "Create draft" button text since status is always Validated
	//    and append an inline "+" toggle button & inline Address creation card right below #options_dolisci_fk_socpeople
	var simplifiedProjectForm = document.querySelector('.dolisci-simplified-project-form');
	if (simplifiedProjectForm) {
		var form = simplifiedProjectForm.closest('form');
		if (form) {
			var submitBtn = form.querySelector('input[type="submit"].button-save');
			if (submitBtn && submitBtn.value && submitBtn.value.toLowerCase().indexOf('brouillon') !== -1) {
				submitBtn.value = 'Créer';
			}
		}
		var addrSelect = document.getElementById('options_dolisci_fk_socpeople');
		var inlineAddrCard = document.getElementById('dolisci-project-inline-address-card');
		if (addrSelect && addrSelect.parentNode && inlineAddrCard) {
			var addrTd = addrSelect.closest('td') || addrSelect.parentNode;
			var plusBtn = document.createElement('a');
			plusBtn.href = '#';
			plusBtn.className = 'paddingleft';
			plusBtn.innerHTML = '<span class="fa fa-plus-circle valignmiddle" style="font-size:1.15em; color:#2563eb;" title="Ajouter une nouvelle adresse du bien ci-dessous"></span>';
			addrSelect.parentNode.appendChild(plusBtn);
			addrTd.appendChild(inlineAddrCard);

			plusBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var isHidden = (inlineAddrCard.style.display === 'none' || inlineAddrCard.style.display === '');
				inlineAddrCard.style.display = isHidden ? 'block' : 'none';
				if (isHidden) {
					var lastInput = document.getElementById('dolisci_addr_lastname');
					var titleInput = document.querySelector('input[name="title"]');
					if (lastInput && lastInput.value.trim() === '' && titleInput && titleInput.value.trim() !== '') {
						lastInput.value = titleInput.value.trim();
					}
					var addrInput = document.getElementById('dolisci_addr_address');
					if (addrInput) {
						addrInput.focus();
					}
				}
			});
		}
	}

	// 3b. Simplified Property Product card in DoliSCI: place "Projet immobilier rattaché" right after "Libellé"
	var simplifiedProductForm = document.querySelector('.dolisci-simplified-product-form');
	if (simplifiedProductForm) {
		var labelInput = document.querySelector('input[name="label"]');
		var projField = document.querySelector('[name="options_dolisci_fk_project"]');
		if (labelInput && projField) {
			var labelRow = labelInput.closest('tr');
			var projRow = projField.closest('tr');
			if (labelRow && projRow && labelRow.parentNode === projRow.parentNode) {
				labelRow.parentNode.insertBefore(projRow, labelRow.nextSibling);
			}
		}
	}

	// 3b. 3-section Thirdparty + Tenant Contact + Guarantor Contact creation form
	var tpCreateWrapper = document.querySelector('.dolisci-tp-create-wrapper');
	if (tpCreateWrapper) {
		if (window.jQuery) {
			window.jQuery('#selectcountry_id').off('change');
		}
		var syncPairs = [
			['name', 'dolisci_tenant_lastname'],
			['address', 'dolisci_tenant_address'],
			['zipcode', 'dolisci_tenant_zipcode'],
			['town', 'dolisci_tenant_town']
		];
		syncPairs.forEach(function (pair) {
			var srcEl = document.getElementById(pair[0]);
			var dstEl = document.getElementById(pair[1]);
			if (srcEl && dstEl) {
				dstEl.addEventListener('input', function () {
					dstEl.setAttribute('data-user-edited', dstEl.value.trim() !== '' ? '1' : '0');
				});
				var syncFn = function () {
					if (dstEl.getAttribute('data-user-edited') !== '1') {
						dstEl.value = srcEl.value;
					}
				};
				srcEl.addEventListener('input', syncFn);
				srcEl.addEventListener('change', syncFn);
				srcEl.addEventListener('blur', syncFn);
			}
		});
	}

	// 3c. Simplify Thirdparty edit form & Contact creation/edition form: group Name|Firstname and Address|Zip|Town|Country inline
	var tpMarker = document.querySelector('tr.dolisci-simplified-thirdparty-form');
	var contactMarker = document.querySelector('tr.dolisci-simplified-contact-form');
	if (tpMarker || contactMarker) {
		if (contactMarker) {
			var lastEl = document.getElementById('lastname');
			var firstEl = document.getElementById('firstname');
			if (lastEl && firstEl) {
				var lastRow = lastEl.closest('tr');
				var firstRow = firstEl.closest('tr');
				var lastTd = lastEl.closest('td');
				if (lastRow && firstRow && lastTd && lastRow !== firstRow) {
					var labelTd = lastRow.querySelector('td:first-child');
					if (labelTd) {
						labelTd.innerHTML = '<label for="lastname">Nom | Prénom</label>';
					}
					lastEl.setAttribute('placeholder', 'Nom');
					firstEl.setAttribute('placeholder', 'Prénom');
					var inlineNameDiv = document.createElement('div');
					inlineNameDiv.className = 'dolisci-inline-fields';
					lastEl.parentNode.insertBefore(inlineNameDiv, lastEl);
					inlineNameDiv.appendChild(lastEl);
					inlineNameDiv.appendChild(firstEl);
					firstRow.style.display = 'none';
				}
			}
		}
		var addrEl = document.getElementById('address');
		var zipEl = document.getElementById('zipcode');
		var townEl = document.getElementById('town');
		var countryEl = document.getElementById('selectcountry_id');
		if (addrEl && zipEl) {
			var addrRow = addrEl.closest('tr');
			var zipRow = zipEl.closest('tr');
			var countryRow = countryEl ? countryEl.closest('tr') : null;
			var addrTd = addrEl.closest('td');
			if (addrRow && addrTd) {
				var addrLabelTd = addrRow.querySelector('td:first-child');
				if (addrLabelTd) {
					addrLabelTd.innerHTML = '<label for="address">Adresse | Code postal | Pays</label>';
				}
				var inlineAddrDiv = document.createElement('div');
				inlineAddrDiv.className = 'dolisci-inline-fields';
				if (addrEl.tagName.toLowerCase() === 'textarea') {
					var addrInput = document.createElement('input');
					addrInput.type = 'text';
					addrInput.name = addrEl.name;
					addrInput.id = addrEl.id;
					addrInput.value = addrEl.value;
					addrInput.placeholder = 'Adresse';
					addrInput.style.flex = '2';
					addrInput.style.minWidth = '220px';
					addrEl.parentNode.replaceChild(addrInput, addrEl);
					addrEl = addrInput;
				}
				zipEl.setAttribute('placeholder', 'Code postal');
				if (townEl) {
					townEl.setAttribute('placeholder', 'Ville');
				}
				addrEl.parentNode.insertBefore(inlineAddrDiv, addrEl);
				inlineAddrDiv.appendChild(addrEl);
				inlineAddrDiv.appendChild(zipEl);
				if (townEl) {
					inlineAddrDiv.appendChild(townEl);
				}
				if (countryRow && countryRow !== addrRow) {
					var countryTd = countryEl.closest('td');
					if (countryTd) {
						var countryWrap = document.createElement('span');
						while (countryTd.firstChild) {
							countryWrap.appendChild(countryTd.firstChild);
						}
						inlineAddrDiv.appendChild(countryWrap);
					}
					countryRow.style.display = 'none';
				}
				if (zipRow && zipRow !== addrRow) {
					zipRow.style.display = 'none';
				}
			}
		}
	}

	// 4. Replace fa-tag with DoliSCI menu icons in category <option data-html> and rendered category badges
	var tagColorIconMap = {
		'#0284c7': 'fa-city',
		'rgb(2, 132, 199)': 'fa-city',
		'#0d9488': 'fa-key',
		'rgb(13, 148, 136)': 'fa-key',
		'#16a34a': 'fa-file-invoice-dollar',
		'rgb(22, 163, 74)': 'fa-file-invoice-dollar',
		'#d97706': 'fa-file-invoice-dollar',
		'rgb(217, 119, 6)': 'fa-file-invoice-dollar',
		'#7c3aed': 'fa-map-marker-alt',
		'rgb(124, 58, 237)': 'fa-map-marker-alt',
		'#2563eb': 'fa-user',
		'rgb(37, 99, 235)': 'fa-user',
		'#0891b2': 'fa-user-shield',
		'rgb(8, 145, 178)': 'fa-user-shield',
		'#e11d48': 'fa-building',
		'rgb(225, 29, 72)': 'fa-building',
		'#475569': 'fa-gavel',
		'rgb(71, 85, 105)': 'fa-gavel'
	};
	var optionsWithHtml = document.querySelectorAll('option[data-html]');
	for (var j = 0; j < optionsWithHtml.length; j++) {
		var dataHtml = optionsWithHtml[j].getAttribute('data-html') || '';
		for (var colorHex in tagColorIconMap) {
			if (dataHtml.toLowerCase().indexOf(colorHex) !== -1) {
				optionsWithHtml[j].setAttribute('data-html', dataHtml.replace('fa-tag', tagColorIconMap[colorHex]));
				break;
			}
		}
	}
	var badgeContainers = document.querySelectorAll('.noborderoncategories[style], span.fa-tag[style]');
	for (var k = 0; k < badgeContainers.length; k++) {
		var styleStr = (badgeContainers[k].getAttribute('style') || '').toLowerCase();
		for (var colorKey in tagColorIconMap) {
			if (styleStr.indexOf(colorKey) !== -1) {
				var iconEl = badgeContainers[k].classList.contains('fa-tag') ? badgeContainers[k] : badgeContainers[k].querySelector('.fa-tag');
				if (iconEl) {
					iconEl.classList.remove('fa-tag');
					iconEl.classList.add(tagColorIconMap[colorKey]);
				}
				break;
			}
		}
	}
});
