<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\NextcloudSSO;

use Piwik\Auth as AuthInterface;
use Piwik\AuthResult;
use Piwik\Plugins\UsersManager\Model;

/**
 * Authentication adapter for users authenticated via Nextcloud SSO.
 */
class Auth implements AuthInterface
{
    private ?string $login = null;
    private ?string $token_auth = null;
    private ?string $password = null;
    private ?string $passwordHash = null;
    private bool $authenticatedViaNextcloud = false;

    public function getName(): string
    {
        return 'NextcloudSSO';
    }

    public function setLogin($login): void
    {
        $this->login = $login;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setTokenAuth(
        #[\SensitiveParameter]
        $token_auth
    ): void {
        $this->token_auth = $token_auth;
    }

    public function getTokenAuth(): ?string
    {
        return $this->token_auth;
    }

    public function getTokenAuthSecret(): ?string
    {
        return $this->passwordHash;
    }

    public function setPassword(
        #[\SensitiveParameter]
        $password
    ): void {
        $this->password = $password;
    }

    public function setPasswordHash(
        #[\SensitiveParameter]
        $passwordHash
    ): void {
        $this->passwordHash = $passwordHash;
    }

    public function setAuthenticatedViaNextcloud(bool $authenticated): void
    {
        $this->authenticatedViaNextcloud = $authenticated;
    }

    public function isAuthenticatedViaNextcloud(): bool
    {
        return $this->authenticatedViaNextcloud;
    }

    public function authenticate(): AuthResult
    {
        if (empty($this->login)) {
            return new AuthResult(AuthResult::FAILURE, null, null);
        }

        $userModel = new Model();
        $user = $userModel->getUser($this->login);

        if (empty($user['login'])) {
            return new AuthResult(AuthResult::FAILURE, $this->login, null);
        }

        if (empty($this->token_auth)) {
            $this->token_auth = $userModel->generateRandomTokenAuth();
        }

        $code = ((int) ($user['superuser_access'] ?? 0) === 1)
            ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE
            : AuthResult::SUCCESS;

        return new AuthResult($code, $user['login'], $this->token_auth);
    }
}
