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
 * \file    admin/setup.php
 * \ingroup dolisecu
 * \brief   DoliSecu setup page
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

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(['admin', 'dolisecu@dolisecu']);

// Security check - Protection if external user
$permissionToRead = $user->hasRight('dolisecu', 'adminpage', 'read');
if (isModEnabled('dolisecu') < 1 || !$permissionToRead) {
    accessforbidden();
}

/*
 * Actions
 */

$action = GETPOST('action', 'aZ09');
if ($action == 'update_login_lock') {
    // Security-wide setting: administrators only.
    if (!$user->admin) {
        accessforbidden();
    }
    // Read before login, when the entity is not known yet: stored for all entities.
    dolibarr_set_const($db, 'DOLISECU_LOGIN_MAX_ATTEMPTS', max(0, GETPOSTINT('DOLISECU_LOGIN_MAX_ATTEMPTS')), 'integer', 0, '', 0);
    dolibarr_set_const($db, 'DOLISECU_LOGIN_LOCK_MINUTES', max(1, GETPOSTINT('DOLISECU_LOGIN_LOCK_MINUTES')), 'integer', 0, '', 0);
    setEventMessages($langs->trans('SetupSaved'), null);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if ($action == 'update_url_login') {
    // Security-wide setting: administrators only.
    if (!$user->admin) {
        accessforbidden();
    }
    // Read before login, when the entity is not known yet: stored for all entities, as logins.
    $allowedLogins = array_values(array_unique(array_filter(array_map('trim', GETPOST('DOLISECU_URL_LOGIN_ALLOWED_LOGINS', 'array')))));
    dolibarr_set_const($db, 'DOLISECU_URL_LOGIN_ALLOWED_LOGINS', implode(',', $allowedLogins), 'chaine', 0, '', 0);
    setEventMessages($langs->trans('SetupSaved'), null);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$form = new Form($db);

$title   = $langs->trans('DoliSecuSetup');
$helpUrl = 'FR:Module_DoliSecu';

llxHeader('', $title, $helpUrl);

// Subheader
$linkBack = '<a href="' . DOL_URL_ROOT . '/admin/modules.php' . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = dolisecuAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, 0, 'dolisecu_color@dolisecu');

print '<span class="opacitymedium">' . $langs->trans('DoliSecuSetupPage') . '</span>';

// Login lock
print load_fiche_titre($langs->trans('DoliSecuLoginLock'), '', '');
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_login_lock">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . $langs->trans('Parameter') . '</td><td>' . $langs->trans('Value') . '</td></tr>';
print '<tr class="oddeven"><td>' . $form->textwithpicto($langs->trans('DoliSecuLoginMaxAttempts'), $langs->trans('DoliSecuLoginMaxAttemptsHelp')) . '</td>';
print '<td><input type="number" min="0" class="maxwidth75" name="DOLISECU_LOGIN_MAX_ATTEMPTS" value="' . getDolGlobalInt('DOLISECU_LOGIN_MAX_ATTEMPTS') . '"></td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans('DoliSecuLoginLockMinutes') . '</td>';
print '<td><input type="number" min="1" class="maxwidth75" name="DOLISECU_LOGIN_LOCK_MINUTES" value="' . getDolGlobalInt('DOLISECU_LOGIN_LOCK_MINUTES', 15) . '"></td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// URL login allowlist
print load_fiche_titre($langs->trans('DoliSecuUrlLogin'), '', '');
print '<span class="opacitymedium">' . $langs->trans('DoliSecuUrlLoginDesc') . '</span><br>';
if (defined('MAIN_AUTHENTICATION_POST_METHOD') && constant('MAIN_AUTHENTICATION_POST_METHOD') == 2) {
    print '<div class="warning">' . img_warning() . ' ' . $langs->trans('DoliSecuUrlLoginPostMethodWarning') . '</div>';
}

// Active users, keyed by login: the login is what the setting stores and what the hook compares at login time.
$userLogins = [];
$sql  = 'SELECT login, firstname, lastname FROM ' . MAIN_DB_PREFIX . 'user';
$sql .= ' WHERE entity IN (' . getEntity('user') . ')';
$sql .= " AND statut = 1 AND login <> ''";
$sql .= ' ORDER BY login';
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $fullname = trim($obj->firstname . ' ' . $obj->lastname);
        $userLogins[$obj->login] = ($fullname !== '' ? $fullname . ' (' . $obj->login . ')' : $obj->login);
    }
}
$selectedLogins = array_filter(array_map('trim', explode(',', getDolGlobalString('DOLISECU_URL_LOGIN_ALLOWED_LOGINS'))));
// Keep an already allowed login that no longer matches an active user, so saving does not silently drop it.
foreach ($selectedLogins as $login) {
    if (!isset($userLogins[$login])) {
        $userLogins[$login] = $login;
    }
}

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_url_login">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>' . $langs->trans('Parameter') . '</td><td>' . $langs->trans('Value') . '</td></tr>';
print '<tr class="oddeven"><td>' . $form->textwithpicto($langs->trans('DoliSecuUrlLoginAllowedLogins'), $langs->trans('DoliSecuUrlLoginAllowedLoginsHelp')) . '</td>';
print '<td>' . $form->multiselectarray('DOLISECU_URL_LOGIN_ALLOWED_LOGINS', $userLogins, $selectedLogins, 0, 0, 'minwidth400') . '</td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
