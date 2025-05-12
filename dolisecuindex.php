<?php
/* Copyright (C) 2022-2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolisecuindex.php
 * \ingroup dolisecu
 * \brief   Home page of dolisecu top menu
 */

// Load DoliSecu environment
if (file_exists('../dolisecu.main.inc.php')) {
	require_once __DIR__ . '/../dolisecu.main.inc.php';
} elseif (file_exists('../../dolisecu.main.inc.php')) {
	require_once __DIR__ . '/../../dolisecu.main.inc.php';
} else {
	die('Include of dolisecu main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';

// Global variables definitions
global $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['dolisecu@dolisecu', 'admin', 'errors']);

// Get parameters
$action = GETPOST('action', 'aZ09');

$confPath    = $dolibarr_main_document_root . '/' .$conffile;
$perms       = fileperms($confPath);
$installlock = DOL_DATA_ROOT . '/install.lock';

// Security check - Protection if external user
$permissionToRead = $user->hasRight('dolisecu', 'adminpage', 'read') && $user->admin;
if (isModEnabled('dolisecu') < 1 || !$permissionToRead) {
	accessforbidden();
}

/*
 *  Actions
*/

$need_repair = 0;
//Check if there is a security problem
if (($perms & 0x0004) || ($perms & 0x0002) || !file_exists($installlock)) {
	$need_repair = 1;
}

if ($action == 'check') {
	if (($perms & 0x0004) || ($perms & 0x0002)) {
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			chmod($confPath, 0400);
		} else {
			exec('icacls "' . $confPath . '" /inheritance:r /grant SYSTEM:R /grant Administrators:R /remove "Users"');
			setEventMessage($langs->trans('YouAreRunningOnWindows'), 'warnings');
		}
		setEventMessage($langs->trans('ConfFileSetPermissions'));
	}
	if (!file_exists($installlock)) {
		fopen(DOL_DATA_ROOT . '/install.lock', 'w');
		setEventMessage($langs->trans('InstallLockFileCreated'));
	}
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'toggle_prod') {
	// Retrieve content of conf.php
	$confContent = file_get_contents($confPath);
	// Search for line $dolibarr_main_prod
	$pattern     = '/\$dolibarr_main_prod\s*=\s*\'?\d+\'?\s*;/';

	if ($dolibarr_main_prod == 0) {
		$replacement = '$dolibarr_main_prod = 1;';
	} else {
		$replacement = '$dolibarr_main_prod = 0;';
	}

	// Replace content of conf.php with good value
	$updateConfContent = preg_replace($pattern, $replacement, $confContent);

	// Change perms to update file content
	chmod($confPath, 0666);
	$result = file_put_contents($confPath, $updateConfContent);
	chmod($confPath, 0400);

	if ($result > 0) {
		setEventMessage($langs->trans('SuccessfullyChangeProdMod'));
	} else {
		setEventMessages($langs->trans('CouldNotSetProd'), [], 'errors');
	}

	header('Location:' . $_SERVER['PHP_SELF']);
	exit;
}

/*
 * View
 */

$help_url = 'FR:Module_DoliSecu';
$title    = $langs->trans('DoliSecuArea');

llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'dolisecu_color.png@dolisecu');

print load_fiche_titre($langs->trans('SecurityProblem'), '', '');

// $conffile is defined into filefunc.inc.php
print '<strong>' . $langs->trans('PermissionsOnFile', $conffile) . '</strong> : ';
if ($perms) {
	if (($perms & 0x0004) || ($perms & 0x0002)) {
		print img_warning() . ' ' .$langs->trans('ConfFileIsReadableOrWritableByAnyUsers');
		// Web user group by default
		$labeluser  = dol_getwebuser('user');
		$labelgroup = dol_getwebuser('group');
		($labeluser || $labelgroup ? print ' ' . $langs->trans('User') . ' : ' . $labeluser . ' : ' . $labelgroup : '');
		if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
			$arrayofinfoofuser = posix_getpwuid(posix_geteuid());
			print ' <span class="opacitymedium">(POSIX ' . $arrayofinfoofuser['name'] . ' : ' . $arrayofinfoofuser['gecos'] . ' : ' . $arrayofinfoofuser['dir'] . ' : ' . $arrayofinfoofuser['shell'] . ')</span>';
		}
	} else {
		print img_picto('', 'tick') . ' ' . $langs->trans('ConfFileHasGoodPermissions');
	}
	print '<br>' . $langs->trans('FilePerms') . ': ' . decoct($perms & 0x1FF);
} else {
	print img_warning() . ' ' . $langs->trans('FailedToReadFile', $conffile);
}

print '<br><br>';

print '<strong>' . $langs->trans('DolibarrSetup') . '</strong> : ';
if (file_exists($installlock)) {
	print img_picto('', 'tick') . ' ' . $langs->trans('InstallAndUpgradeLockedBy', $installlock);
} else {
	print img_warning() . ' ' . $langs->trans('WarningLockFileDoesNotExists', DOL_DATA_ROOT);
}

print '<div class="tabsAction">';
// Repair security problem
if ($need_repair) {
    print '<a class="butAction" id="actionButtonCheck" href="' . $_SERVER['PHP_SELF'] . '?action=check' . '">' . $langs->trans('RepairSecurityProblem') . '</a>';
} else {
    print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('NoSecurityProblem')) . '">' . $langs->trans('RepairSecurityProblem') . '</span>';
}
print '</div>';

//Check if $dolibarr_main_prod is true
print '<strong>$dolibarr_main_prod</strong>: '.($dolibarr_main_prod ? $dolibarr_main_prod : '0');
if (empty($dolibarr_main_prod)) {
	print img_picto('', 'warning').' '.$langs->trans("IfYouAreOnAProductionSetThis", 1);
} else {
	print ' ' . img_picto('', 'tick') . ' ' . $langs->trans('MyDolibarrIsInProd', $installlock);
}

print '<div class="tabsAction">';
// Repair security problem
if ($dolibarr_main_prod == 0) {
	print '<a class="butAction" id="actionButtonCheck" href="' . $_SERVER['PHP_SELF'] . '?action=toggle_prod&token=' . newToken() . '">' . $langs->trans('SetMyDolibarrInProd') . '</a>';
} else {
	print '<a class="butAction" id="actionButtonCheck" href="' . $_SERVER['PHP_SELF'] . '?action=toggle_prod&token=' . newToken() . '">' . $langs->trans('SetMyDolibarrInDraft') . '</a>';
}
print '</div>';

// End of page
llxFooter();
$db->close();
