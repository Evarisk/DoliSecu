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
     * Checks run before Dolibarr tries the credentials: temporary lock, then captcha enforcement
     *
     * @param  array<string,mixed> $parameters Hook metadata (context, etc...)
     * @param  object|null         $object     Current user
     * @param  string              $action     Current action
     * @param  HookManager         $hookmanager Hook manager
     * @return int                             0 to go on, -1 to refuse the login attempt
     */
    public function beforeLoginAuthentication(array $parameters, $object, string &$action, HookManager $hookmanager): int
    {
        // No username received: this is not a login attempt (e.g. display of the login page).
        $username = GETPOST('username', 'alpha', 3);
        if ($username === '') {
            return 0;
        }

        if ($this->refuseIfLocked($username) < 0) {
            return -1;
        }

        return $this->refuseOutsideLoginForm($username);
    }

    /**
     * Record a failed login attempt (wrong credentials, wrong captcha...)
     *
     * @param  array<string,mixed> $parameters Hook metadata (context, etc...)
     * @param  object|null         $object     Current user
     * @param  string              $action     Current action
     * @param  HookManager         $hookmanager Hook manager
     * @return int                             0
     */
    public function afterLoginFailed(array $parameters, $object, string &$action, HookManager $hookmanager): int
    {
        $username = GETPOST('username', 'alpha', 3);
        if ($username === '' || getDolGlobalInt('DOLISECU_LOGIN_MAX_ATTEMPTS') <= 0) {
            return 0;
        }

        $now   = dol_now();
        $table = MAIN_DB_PREFIX . 'dolisecu_login_attempt';

        // Purge what no longer counts, so that the table stays small.
        $this->db->query('DELETE FROM ' . $table . " WHERE datec < '" . $this->db->idate($now - $this->lockSeconds()) . "'");

        $sql  = 'INSERT INTO ' . $table . ' (login, ip, datec) VALUES (';
        $sql .= "'" . $this->db->escape(dol_trunc($username, 128, 'right', 'UTF-8', 1)) . "', ";
        $sql .= "'" . $this->db->escape(dol_trunc(getUserRemoteIP(), 250, 'right', 'UTF-8', 1)) . "', ";
        $sql .= "'" . $this->db->idate($now) . "')";
        if (!$this->db->query($sql)) {
            dol_syslog(__METHOD__ . ' ' . $this->db->lasterror(), LOG_ERR);
        }

        return 0;
    }

    /**
     * Clear the failed attempts of a login once it succeeds, so that a typo is not held against the user
     *
     * @param  array<string,mixed> $parameters Hook metadata (context, etc...)
     * @param  object|null         $object     Logged user
     * @param  string              $action     Current action
     * @param  HookManager         $hookmanager Hook manager
     * @return int                             0
     */
    public function afterLogin(array $parameters, $object, string &$action, HookManager $hookmanager): int
    {
        $username = GETPOST('username', 'alpha', 3);
        if ($username !== '') {
            $this->db->query('DELETE FROM ' . MAIN_DB_PREFIX . "dolisecu_login_attempt WHERE login = '" . $this->db->escape($username) . "'");
        }

        return 0;
    }

    /**
     * Duration of the lock, in seconds
     *
     * @return int
     */
    private function lockSeconds(): int
    {
        return max(1, getDolGlobalInt('DOLISECU_LOGIN_LOCK_MINUTES', 15)) * 60;
    }

    /**
     * Refuse the attempt while the login, or the IP, has too many recent failures
     *
     * The IP threshold is four times the login one: an office IP is shared, a colleague's typos must
     * not lock everybody, while a password spray from one address is still stopped.
     *
     * @param  string $username Login received
     * @return int              0 to go on, -1 if locked
     */
    private function refuseIfLocked(string $username): int
    {
        global $langs;

        $max = getDolGlobalInt('DOLISECU_LOGIN_MAX_ATTEMPTS');
        if ($max <= 0) {
            return 0;
        }

        $since = $this->db->idate(dol_now() - $this->lockSeconds());
        $table = MAIN_DB_PREFIX . 'dolisecu_login_attempt';
        $checks = [
            "login = '" . $this->db->escape($username) . "'" => $max,
            "ip = '" . $this->db->escape(getUserRemoteIP()) . "'" => $max * 4,
        ];

        foreach ($checks as $where => $threshold) {
            $resql = $this->db->query('SELECT COUNT(*) AS nb, MAX(datec) AS last FROM ' . $table . ' WHERE ' . $where . " AND datec >= '" . $since . "'");
            $obj   = $resql ? $this->db->fetch_object($resql) : null;
            if (empty($obj) || (int) $obj->nb < $threshold) {
                continue;
            }

            $minutes = max(1, (int) ceil(($this->db->jdate($obj->last) + $this->lockSeconds() - dol_now()) / 60));
            dol_syslog('--- Security warning: too many failed logins (' . $where . '), connection refused for ' . $minutes . ' min', LOG_NOTICE);
            $langs->loadLangs(['main', 'dolisecu@dolisecu']);
            $_SESSION['dol_loginmesg'] = $langs->transnoentitiesnoconv('DoliSecuTooManyLoginAttempts', $minutes);
            $this->errors[] = 'DoliSecuTooManyLoginAttempts';
            return -1;
        }

        return 0;
    }

    /**
     * Refuse a login attempt that does not come from the login form when the captcha is enabled
     *
     * The captcha is only checked for the regular login form: action « login » with the username posted.
     * Any other attempt carrying a username is refused here, with the same message, log and trigger as
     * a wrong captcha, so that the captcha cannot be skipped.
     *
     * Exception: a login listed in DOLISECU_URL_LOGIN_ALLOWED_LOGINS keeps the right to authenticate
     * from the URL (e.g. demo accounts), the captcha being skipped for it alone. The brute-force lock
     * checked before this method still applies to it.
     *
     * @param  string $username Login received
     * @return int              0 to go on, -1 to refuse the login attempt
     */
    private function refuseOutsideLoginForm(string $username): int
    {
        global $langs, $user;

        if (!getDolGlobalString('MAIN_SECURITY_ENABLECAPTCHA') || isset($_SESSION['dol_bypass_antispam'])) {
            return 0;
        }

        // Allowlist: some logins (e.g. demo accounts) may still authenticate from the URL, the captcha
        // being skipped for them alone. Compared lowercase, as Dolibarr authenticates the login case-insensitively.
        $allowedLogins = array_filter(array_map('trim', explode(',', getDolGlobalString('DOLISECU_URL_LOGIN_ALLOWED_LOGINS'))));
        if (!empty($allowedLogins) && in_array(strtolower($username), array_map('strtolower', $allowedLogins), true)) {
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
