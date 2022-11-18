<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
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
 *	\file       dolisecu/dolisecuindex.php
 *	\ingroup    dolisecu
 *	\brief      Home page of dolisecu top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/memory.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/events.class.php';


// Load translation files required by the page
$langs->loadLangs(array("dolisecu@dolisecu"));
$langs->loadLangs(array("admin", "errors"));

$action = GETPOST('action', 'aZ09');


// Security check
// if (! $user->rights->dolisecu->myobject->read) {
// 	accessforbidden();
// }
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Parameters

$max            = 5;
$now            = dol_now();
$repair_conf    = false;
$repair_install = false;

$action         = GETPOST('action', 'alpha');
$backtopage     = GETPOST('backtopage', 'alpha');

$error          = 0;

$perms 			= fileperms($dolibarr_main_document_root.'/'.$conffile);
$installlock    = DOL_DATA_ROOT.'/install.lock';

/*
 *  Actions
*/

//Check if there is a security problem
if (($perms & 0x0004) || ($perms & 0x0002) || !file_exists($installlock)) {
	$need_repair = 1;
}

if ($action == 'check') {
	if (($perms & 0x0004) || ($perms & 0x0002)) {
		chmod($dolibarr_main_document_root.'/'.$conffile, 440);
		setEventMessage($langs->trans('ConfFileSetPermissions'));
	}
	if (!file_exists($installlock)) {
		fopen(DOL_DATA_ROOT. "/install.lock", "w");
		setEventMessage($langs->trans('InstallLockFileCreated'));
	}
	header("Location: " . $_SERVER['PHP_SELF']);
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("ModuleDoliSecuName"));

print load_fiche_titre($langs->trans("ModuleDoliSecuName"), '', 'dolisecu.png@dolisecu');

print '<a class="' . ($need_repair == 1 ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonCheck" title="' . ($need_repair == 1 ? '' : dol_escape_htmltag($langs->trans("NoSecurityProblem"))) . '" href="' . ($need_repair == 1 ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=check') : '#') . '">' . $langs->trans("Repair") . ' ' . $langs->trans("SecurityProblem") . '</a>';

print '<br>';
print '<br>';

// $conffile is defined into filefunc.inc.php
print '<strong>'.$langs->trans("PermissionsOnFile", $conffile).'</strong>: ';
if ($perms) {
	if (($perms & 0x0004) || ($perms & 0x0002)) {
		print img_warning().' '.$langs->trans("ConfFileIsReadableOrWritableByAnyUsers");
		// Web user group by default
		$labeluser = dol_getwebuser('user');
		$labelgroup = dol_getwebuser('group');
		print ' '.$langs->trans("User").': '.$labeluser.':'.$labelgroup;
		if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
			$arrayofinfoofuser = posix_getpwuid(posix_geteuid());
			print ' <span class="opacitymedium">(POSIX '.$arrayofinfoofuser['name'].':'.$arrayofinfoofuser['gecos'].':'.$arrayofinfoofuser['dir'].':'.$arrayofinfoofuser['shell'].')</span>';
		}
	} else {
		print img_picto('', 'tick').' '.$langs->trans("ConfFileHasGoodPermissions");
	}
} else {
	print img_warning().' '.$langs->trans("FailedToReadFile", $conffile);
}
print '<br>';
print '<br>';

print '<strong>'.$langs->trans("DolibarrSetup").'</strong>: ';
if (file_exists($installlock)) {
	print img_picto('', 'tick').' '.$langs->trans("InstallAndUpgradeLockedBy", $installlock);
} else {
	print img_warning().' '.$langs->trans("WarningLockFileDoesNotExists", DOL_DATA_ROOT);
}
print '<br>';

print '<div class="fichecenter"><div class="fichethirdleft">';
print '</div><div class="fichetwothirdright">';


$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$max = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

print '</div></div>';

// End of page
llxFooter();
$db->close();
