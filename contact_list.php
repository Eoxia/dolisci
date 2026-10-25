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
 * \file    contact_list.php
 * \ingroup dolisci
 * \brief   DoliSCI Contacts / Addresses list wrapper (isolated contextpage = doliscicontactlist)
 */

$_GET['mainmenu']  = 'dolisci';
$_POST['mainmenu'] = 'dolisci';
$_GET['leftmenu']  = $_GET['leftmenu'] ?? 'dolisci_contacts';

$dolibarrRoot = is_dir(__DIR__ . '/../../contact') ? realpath(__DIR__ . '/../..') : realpath(__DIR__ . '/..');
chdir($dolibarrRoot . '/contact');
require $dolibarrRoot . '/contact/list.php';
