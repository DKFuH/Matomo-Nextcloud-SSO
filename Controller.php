<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\NextcloudSSO;

use Exception;
use Piwik\Access;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Url;
use Piwik\View;

/**
 * Controller handling the Nextcloud SSO authorization flow and callbacks.
 */
class Controller extends \Piwik\Plugin\Controller
{
    private const SESSION_STATE_KEY = 'nextcloudsso_state';
    private const SESSION_CODE_VERIFIER_KEY = 'nextcloudsso_code_verifier';
    private const SESSION_RETURN_URL_KEY = 'nextcloudsso_return_url';

    /**
     * True only while this controller is provisioning/syncing an SSO-authenticated account.
     * Read by NextcloudSSO::skipPasswordConfirmationDuringProvisioning() to bypass Matomo's
     * re-authentication requirement for accounts that have no password to confirm with.
     *
     * @var bool
     */
    public static bool $isProvisioningViaSso = false;

    /**
     * Renders the Nextcloud SSO login button on the Matomo login screen.
     *
     * @return string
     */
    public function loginButton(): string
    {
        $settings = new SystemSettings();

        $view = new View('@NextcloudSSO/loginButton');
        $view->buttonText = $settings->buttonText->getValue();
        $view->signinUrl = 'index.php?module=NextcloudSSO&action=signin';
        $view->allowLocalLogin = (bool) $settings->allowLocalLogin->getValue();

        return $view->render();
    }

    /**
     * Initiates the SSO authorization redirect to Nextcloud.
     *
     * @return void
     * @throws Exception
     */
    public function signin(): void
    {
        $settings = new SystemSettings();
        if (!$settings->isConfigured()) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorNotConfigured'));
        }

        // Generate cryptographic CSRF state
        $state = Common::getRandomString(32);
        $_SESSION[self::SESSION_STATE_KEY] = $state;

        // Generate PKCE code_verifier and code_challenge
        $rawVerifier = bin2hex(random_bytes(32));
        $codeVerifier = rtrim(strtr(base64_encode($rawVerifier), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $_SESSION[self::SESSION_CODE_VERIFIER_KEY] = $codeVerifier;

        // Save optional return URL
        $returnUrl = Common::getRequestVar('url', '', 'string');
        if (!empty($returnUrl) && Url::isLocalUrl($returnUrl)) {
            $_SESSION[self::SESSION_RETURN_URL_KEY] = $returnUrl;
        }

        $params = [
            'response_type'         => 'code',
            'client_id'             => $settings->clientId->getValue(),
            'redirect_uri'          => $settings->getRedirectUri(),
            'scope'                 => $settings->scope->getValue(),
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        $authUrl = $settings->getEffectiveAuthorizeUrl();
        $separator = (strpos($authUrl, '?') !== false) ? '&' : '?';
        $targetUrl = $authUrl . $separator . http_build_query($params);

        Url::redirectToUrl($targetUrl);
    }

    /**
     * Handles the OAuth2 / OIDC callback from Nextcloud.
     *
     * @return void
     * @throws Exception
     */
    public function callback(): void
    {
        $settings = new SystemSettings();
        if (!$settings->isConfigured()) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorNotConfigured'));
        }

        // Check for error sent from Nextcloud
        $error = Common::getRequestVar('error', '', 'string');
        if (!empty($error)) {
            $errorDesc = Common::getRequestVar('error_description', $error, 'string');
            Url::redirectToUrl('index.php?module=Login&login_error=' . urlencode($errorDesc));
            return;
        }

        // Validate state against CSRF
        $storedState = $_SESSION[self::SESSION_STATE_KEY] ?? null;
        $receivedState = Common::getRequestVar('state', '', 'string');

        if (empty($storedState) || empty($receivedState) || !hash_equals($storedState, $receivedState)) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorStateMismatch'));
        }
        unset($_SESSION[self::SESSION_STATE_KEY]);

        // Validate authorization code
        $code = Common::getRequestVar('code', '', 'string');
        if (empty($code)) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorCodeMissing'));
        }

        $codeVerifier = $_SESSION[self::SESSION_CODE_VERIFIER_KEY] ?? null;
        unset($_SESSION[self::SESSION_CODE_VERIFIER_KEY]);

        // Exchange code for Access Token
        $tokenData = $this->requestAccessToken($settings, $code, $codeVerifier);
        $accessToken = $tokenData['access_token'] ?? null;
        if (empty($accessToken)) {
            $msg = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown OAuth error');
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorTokenFailed') . ': ' . $msg);
        }

        // Fetch Userinfo from Nextcloud
        $userInfo = $this->requestUserInfo($settings, $accessToken);
        $userProfile = $this->parseUserProfile($userInfo);

        if (empty($userProfile['id'])) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorNoUserId'));
        }

        // Provision or resolve Matomo user, and sync metadata & roles/groups.
        // Accounts provisioned via SSO have no password, so Matomo's re-authentication
        // requirement for sensitive changes (new user, SuperUser access, site access) is
        // bypassed for the duration of this block only.
        self::$isProvisioningViaSso = true;
        try {
            $matomoLogin = $this->resolveOrProvisionUser($settings, $userProfile);
            $this->syncUserAndRoles($settings, $matomoLogin, $userProfile);
        } finally {
            self::$isProvisioningViaSso = false;
        }

        // Initialize authenticated Matomo session
        $this->authenticateSession($matomoLogin);

        // Redirect to destination
        $returnUrl = $_SESSION[self::SESSION_RETURN_URL_KEY] ?? 'index.php';
        unset($_SESSION[self::SESSION_RETURN_URL_KEY]);

        Url::redirectToUrl($returnUrl);
    }

    /**
     * Exchanges authorization code for access token via HTTP POST.
     *
     * @param SystemSettings $settings
     * @param string $code
     * @param string|null $codeVerifier
     * @return array
     * @throws Exception
     */
    private function requestAccessToken(SystemSettings $settings, string $code, ?string $codeVerifier): array
    {
        $payload = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $settings->getRedirectUri(),
            'client_id'     => $settings->clientId->getValue(),
            'client_secret' => $settings->clientSecret->getValue(),
        ];

        if (!empty($codeVerifier)) {
            $payload['code_verifier'] = $codeVerifier;
        }

        $body = http_build_query($payload);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
            'Accept: application/json',
            'User-Agent: Matomo-NextcloudSSO-Plugin',
        ];

        $response = $this->executeHttpRequest($settings->getEffectiveTokenUrl(), 'POST', $headers, $body);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorInvalidResponse'));
        }

        return $decoded;
    }

    /**
     * Fetches user profile from Nextcloud userinfo / OCS endpoint.
     *
     * @param SystemSettings $settings
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    private function requestUserInfo(SystemSettings $settings, string $accessToken): array
    {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'OCS-APIRequest: true',
            'Accept: application/json',
            'User-Agent: Matomo-NextcloudSSO-Plugin',
        ];

        $response = $this->executeHttpRequest($settings->getEffectiveUserinfoUrl(), 'GET', $headers);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorInvalidResponse'));
        }

        return $decoded;
    }

    /**
     * Normalizes user profile from either OIDC UserInfo or Nextcloud OCS User API.
     *
     * @param array $data
     * @return array{id: string, name: string, email: string, groups: array<string>}
     */
    private function parseUserProfile(array $data): array
    {
        // 1. Nextcloud OCS JSON response format
        if (isset($data['ocs']['data']) && is_array($data['ocs']['data'])) {
            $ocs = $data['ocs']['data'];
            return [
                'id'     => (string) ($ocs['id'] ?? ''),
                'name'   => (string) ($ocs['displayname'] ?? ($ocs['id'] ?? '')),
                'email'  => (string) ($ocs['email'] ?? ''),
                'groups' => isset($ocs['groups']) && is_array($ocs['groups']) ? $ocs['groups'] : [],
            ];
        }

        // 2. Standard OIDC UserInfo format
        $sub = (string) ($data['sub'] ?? ($data['preferred_username'] ?? ($data['id'] ?? '')));
        $name = (string) ($data['name'] ?? ($data['preferred_username'] ?? $sub));
        $email = (string) ($data['email'] ?? '');
        $groups = [];

        if (isset($data['groups']) && is_array($data['groups'])) {
            $groups = $data['groups'];
        } elseif (isset($data['roles']) && is_array($data['roles'])) {
            $groups = $data['roles'];
        }

        return [
            'id'     => $sub,
            'name'   => $name,
            'email'  => $email,
            'groups' => $groups,
        ];
    }

    /**
     * Resolves existing user mapping or provisions a new Matomo user account.
     *
     * @param SystemSettings $settings
     * @param array $profile
     * @return string Matomo login name
     * @throws Exception
     */
    private function resolveOrProvisionUser(SystemSettings $settings, array $profile): string
    {
        $nextcloudUserId = $profile['id'];
        $email = $profile['email'];

        // 1. Check local mapping table
        $mappedLogin = $this->getMappedUser($nextcloudUserId);
        if (!empty($mappedLogin)) {
            $user = $this->getUser($mappedLogin);
            if (!empty($user['login'])) {
                return $user['login'];
            }
        }

        // 2. Check if a user already exists with matching username or email
        $cleanLogin = $this->sanitizeLogin($nextcloudUserId);
        $userByLogin = $this->getUser($cleanLogin);
        if (!empty($userByLogin['login'])) {
            $this->linkUser($userByLogin['login'], $nextcloudUserId, $email);
            return $userByLogin['login'];
        }

        if (!empty($email)) {
            $userByEmail = $this->getUserByEmail($email);
            if (!empty($userByEmail['login'])) {
                $this->linkUser($userByEmail['login'], $nextcloudUserId, $email);
                return $userByEmail['login'];
            }
        }

        // 3. User does not exist - check if auto-signup is enabled
        if (!$settings->autoCreateUser->getValue()) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorUserNotFoundNoSignup'));
        }

        // 4. Create new Matomo user
        $matomoLogin = $this->createUser($cleanLogin, $email);
        $this->linkUser($matomoLogin, $nextcloudUserId, $email);

        return $matomoLogin;
    }

    /**
     * Synchronizes display name, email, and group-based permissions.
     *
     * @param SystemSettings $settings
     * @param string $matomoLogin
     * @param array $profile
     * @return void
     */
    private function syncUserAndRoles(SystemSettings $settings, string $matomoLogin, array $profile): void
    {
        $email = $profile['email'];
        $groups = $profile['groups'];

        // Sync metadata if enabled (Matomo 5.x no longer supports a separate display name/alias field)
        if ($settings->syncUserInfo->getValue() && !empty($email)) {
            Access::doAsSuperUser(function () use ($matomoLogin, $email) {
                try {
                    UsersManagerAPI::getInstance()->updateUser($matomoLogin, null, $email);
                } catch (\Throwable $e) {
                    // Ignore non-critical update errors
                }
            });
        }

        // Role & Group Mapping
        $adminGroup = trim((string) $settings->adminGroup->getValue());
        $isAdmin = !empty($adminGroup) && in_array($adminGroup, $groups, true);

        Access::doAsSuperUser(function () use ($matomoLogin, $isAdmin, $settings) {
            $api = UsersManagerAPI::getInstance();

            if ($isAdmin) {
                // Grant SuperUser access in Matomo
                $api->setSuperUserAccess($matomoLogin, true);
            } else {
                // Remove superuser if previously assigned by SSO
                // And assign default site permissions if configured
                $defaultRole = (string) $settings->defaultRole->getValue();
                if ($defaultRole !== 'noaccess') {
                    $siteSetting = trim((string) $settings->defaultSites->getValue());
                    $siteIds = [];

                    if (strtolower($siteSetting) === 'all') {
                        $sites = SitesManagerAPI::getInstance()->getAllSites();
                        $siteIds = array_map(function ($s) {
                            return (int) $s['idsite'];
                        }, $sites);
                    } elseif (!empty($siteSetting)) {
                        $siteIds = array_filter(array_map('intval', explode(',', $siteSetting)));
                    }

                    if (!empty($siteIds)) {
                        try {
                            $api->setUserAccess($matomoLogin, $defaultRole, $siteIds);
                        } catch (\Throwable $e) {
                            // Non-critical
                        }
                    }
                }
            }
        });
    }

    /**
     * Starts the authenticated session for the resolved Matomo user.
     *
     * @param string $matomoLogin
     * @return void
     * @throws Exception
     */
    private function authenticateSession(string $matomoLogin): void
    {
        $auth = new Auth();
        $auth->setLogin($matomoLogin);
        $auth->setAuthenticatedViaNextcloud(true);

        if (class_exists('Piwik\Session\SessionInitializer')) {
            $sessionInitializer = new \Piwik\Session\SessionInitializer();
        } else {
            $sessionInitializer = new \Piwik\Plugins\Login\SessionInitializer();
        }

        $sessionInitializer->initSession($auth);
    }

    /**
     * Executes an HTTP request using cURL with stream fallback.
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param string|null $body
     * @return string
     * @throws Exception
     */
    private function executeHttpRequest(string $url, string $method, array $headers = [], ?string $body = null): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new Exception(Piwik::translate('NextcloudSSO_ErrorHttpRequestFailed') . ': ' . $error);
            }

            if ($httpCode >= 400) {
                throw new Exception(Piwik::translate('NextcloudSSO_ErrorHttpRequestFailed') . " (HTTP $httpCode): " . $response);
            }

            return (string) $response;
        }

        // Fallback to stream context
        $options = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new Exception(Piwik::translate('NextcloudSSO_ErrorHttpRequestFailed'));
        }

        return (string) $result;
    }

    /**
     * Queries the nextcloudsso_provider table for an existing user mapping.
     *
     * @param string $nextcloudUserId
     * @return string|null
     */
    private function getMappedUser(string $nextcloudUserId): ?string
    {
        $sql = 'SELECT `user` FROM ' . Common::prefixTable('nextcloudsso_provider') . ' WHERE `nextcloud_user` = ? LIMIT 1';
        $user = Db::fetchOne($sql, [$nextcloudUserId]);
        return !empty($user) ? (string) $user : null;
    }

    /**
     * Creates or updates mapping between Matomo login and Nextcloud user ID.
     *
     * @param string $matomoLogin
     * @param string $nextcloudUserId
     * @param string $email
     * @return void
     */
    private function linkUser(string $matomoLogin, string $nextcloudUserId, string $email): void
    {
        $table = Common::prefixTable('nextcloudsso_provider');
        $sql = "INSERT INTO $table (`user`, `nextcloud_user`, `email`, `date_connected`)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE `user` = VALUES(`user`), `email` = VALUES(`email`), `date_connected` = NOW()";
        Db::query($sql, [$matomoLogin, $nextcloudUserId, $email]);
    }

    /**
     * Retrieves user by login via UsersManager API.
     *
     * @param string $login
     * @return array|null
     */
    private function getUser(string $login): ?array
    {
        return Access::doAsSuperUser(function () use ($login) {
            try {
                return UsersManagerAPI::getInstance()->getUser($login);
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Retrieves user by email via UsersManager API.
     *
     * @param string $email
     * @return array|null
     */
    private function getUserByEmail(string $email): ?array
    {
        return Access::doAsSuperUser(function () use ($email) {
            try {
                $user = UsersManagerAPI::getInstance()->getUserByEmail($email);
                return !empty($user) ? $user : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Creates a new user in Matomo.
     *
     * @param string $login
     * @param string $email
     * @return string
     * @throws Exception
     */
    private function createUser(string $login, string $email): string
    {
        return Access::doAsSuperUser(function () use ($login, $email) {
            $api = UsersManagerAPI::getInstance();
            $candidate = $login;
            $counter = 1;

            // Ensure unique username (getUser() throws for a non-existent login, so use userExists() here)
            while ($api->userExists($candidate)) {
                $candidate = $login . '_' . $counter++;
            }

            $randomPassword = Common::getRandomString(32);
            $safeEmail = !empty($email) ? $email : ($candidate . '@localhost');

            $api->addUser($candidate, $randomPassword, $safeEmail);

            return $candidate;
        });
    }

    /**
     * Sanitizes raw Nextcloud usernames to ensure compatibility with Matomo login rules.
     *
     * @param string $raw
     * @return string
     */
    private function sanitizeLogin(string $raw): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', trim($raw));
        if (empty($cleaned)) {
            $cleaned = 'nc_user_' . substr(md5($raw), 0, 8);
        }
        return substr($cleaned, 0, 95);
    }
}
