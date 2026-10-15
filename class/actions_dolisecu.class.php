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
 * \file    class/actions_dolisecu.class.php
 * \ingroup dolisecu
 * \brief   DoliSecu hook overload.
 */

/**
 * Class ActionsDolisecu
 */
class ActionsDolisecu
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var string[] Errors
     */
    public $errors = [];

    /**
     * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = [];

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Refuse a login attempt that does not come from the login form when the captcha is enabled
     *
     * The captcha is only checked for the regular login form: action « login » with the username posted.
     * Any other attempt carrying a username is refused here, with the same message, log and trigger as
     * a wrong captcha, so that the captcha cannot be skipped.
     *
     * @param  array<string,mixed> $parameters Hook metadata (context, etc...)
     * @param  object|null         $object     Current user
     * @param  string              $action     Current action
     * @param  HookManager         $hookmanager Hook manager
     * @return int                             0 to go on, -1 to refuse the login attempt
     */
    public function beforeLoginAuthentication(array $parameters, $object, string &$action, HookManager $hookmanager): int
    {
        global $langs, $user;

        if (!getDolGlobalString('MAIN_SECURITY_ENABLECAPTCHA') || isset($_SESSION['dol_bypass_antispam'])) {
            return 0;
        }

        // No username received: this is not a login attempt (e.g. display of the login page).
        $username = GETPOST('username', 'alpha', 3);
        if ($username === '') {
            return 0;
        }

        // Regular login form: Dolibarr checks the captcha itself. Return of an OAuth login: no captcha.
        if ((GETPOST('actionlogin', 'aZ09') == 'login' && GETPOST('username', 'alpha', 2) !== '') || GETPOST('afteroauthloginreturn', 'alphanohtml', 1)) {
            return 0;
        }

        dol_syslog('--- Security warning: login attempt outside the login form while captcha is enabled, connection refused', LOG_NOTICE);
        $langs->loadLangs(['main', 'errors']);
        $_SESSION['dol_loginmesg'] = (empty($_SESSION['dol_loginmesg']) ? '' : $_SESSION['dol_loginmesg'] . "<br>\n") . $langs->transnoentitiesnoconv('ErrorBadValueForCode');

        // Same trace as a wrong captcha in the security events log.
        if (is_object($user)) {
            $user->context['audit'] = 'ErrorBadValueForCode - login=' . $username;
            $user->call_trigger('USER_LOGIN_FAILED', $user);
        }

        $this->errors[] = 'ErrorBadValueForCode';
        return -1;
    }
}
