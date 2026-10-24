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
    var simplifiedProjectForm = document.querySelector('.dolisci-simplified-project-form');
    if (simplifiedProjectForm) {
        var form = simplifiedProjectForm.closest('form');
        if (form) {
            var submitBtn = form.querySelector('input[type="submit"].button-save');
            if (submitBtn && submitBtn.value && submitBtn.value.toLowerCase().indexOf('brouillon') !== -1) {
                submitBtn.value = 'Créer';
            }
        }
    }
});
