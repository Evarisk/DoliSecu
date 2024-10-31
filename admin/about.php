<?php
/* Copyright (C) 2022-2024 EVARISK <technique@evarisk.com>
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
 * \file    dolisecu/admin/about.php
 * \ingroup dolisecu
 * \brief   About page of module DoliSecu
 */

// Load DoliSecu environment
if (file_exists('../dolisecu.main.inc.php')) {
    require_once __DIR__ . '/../dolisecu.main.inc.php';
} elseif (file_exists('../../dolisecu.main.inc.php')) {
    require_once __DIR__ . '/../../dolisecu.main.inc.php';
} else {
    die('Include of dolisecu main fails');
}

// Load DoliSecu libraries
require_once __DIR__ . '/../lib/dolisecu.lib.php';
require_once __DIR__ . '/../core/modules/modDoliSecu.class.php';

// Global variables definitions
global $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['dolisecu@dolisecu']);

// Initialize technical objects
$modDoliSecu = new modDoliSecu($db);

// Security check - Protection if external user
$permissionToRead = $user->hasRight('dolisecu', 'adminpage', 'read');
if (isModEnabled('dolisecu') < 1 || !$permissionToRead) {
    accessforbidden();
}

/*
 * View
 */

$title   = $langs->trans('DoliSecuSetup');
$helpUrl = 'FR:Module_DoliSecu';

llxHeader('', $title, $help_url);

// Subheader
$linkBack = '<a href="' . DOL_URL_ROOT .'/admin/modules.php' . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = dolisecuAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, 0, 'dolisecu_color@dolisecu');

print $modDoliSecu->getDescLong();

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
