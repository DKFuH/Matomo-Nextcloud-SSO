<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\NextcloudSSO;

use Exception;
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\FrontController;
use Piwik\Piwik;
use Piwik\Session;
use Piwik\Url;

/**
 * Main plugin class for NextcloudSSO.
 */
class NextcloudSSO extends \Piwik\Plugin
{
    /**
     * Subscribe to Matomo events and assign handlers.
     *
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            'Template.loginNav'               => 'renderLoginNav',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Session.beforeSessionStart'      => 'beforeSessionStart',
            'Request.dispatch'                => 'onDispatch',
        ];
    }

    /**
     * Decisions whether the RememberMe cookie should be handled during SSO callback.
     *
     * @return void
     */
    public function beforeSessionStart(): void
    {
        $module = Common::getRequestVar('module', false);
        $action = Common::getRequestVar('action', false);
        if ($module === 'NextcloudSSO' && $action === 'callback') {
            $cookieExpire = Config::getInstance()->General['login_cookie_expire'] ?? 1209600;
            Session::rememberMe($cookieExpire);
        }
    }

    /**
     * Registers the plugin's CSS file.
     *
     * @param array $files
     * @return void
     */
    public function getStylesheetFiles(array &$files): void
    {
        $files[] = 'plugins/NextcloudSSO/stylesheets/loginButton.css';
    }

    /**
     * Injects the Nextcloud login button into the Matomo login form.
     *
     * @param string $out
     * @param string|null $payload
     * @return void
     */
    public function renderLoginNav(string &$out, ?string $payload = null): void
    {
        $settings = new SystemSettings();
        if (!$settings->isConfigured()) {
            return;
        }

        // Render at bottom of form or when payload is empty
        if ($payload === 'bottom' || empty($payload)) {
            $content = FrontController::getInstance()->dispatch('NextcloudSSO', 'loginButton');
            if (!empty($content)) {
                $out .= $content;
            }
        }
    }

    /**
     * Handles automatic redirect to Nextcloud SSO if configured.
     *
     * @return void
     */
    public function onDispatch(): void
    {
        $settings = new SystemSettings();
        if (!$settings->isConfigured() || !$settings->autoRedirect->getValue()) {
            return;
        }

        $module = Common::getRequestVar('module', false);
        $action = Common::getRequestVar('action', false);

        // Check if user is accessing login view
        if ($module === 'Login' && ($action === 'index' || $action === 'login' || empty($action))) {
            $forceLocal = Common::getRequestVar('local', false);
            $hasError = Common::getRequestVar('login_error', false);
            $isLoggedOut = Common::getRequestVar('logged_out', false);

            if (!$forceLocal && !$hasError && !$isLoggedOut && Piwik::isUserIsAnonymous()) {
                Url::redirectToUrl('index.php?module=NextcloudSSO&action=signin');
            }
        }
    }

    /**
     * Creates database mapping table during plugin installation.
     *
     * @return void
     * @throws Exception
     */
    public function install(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS " . Common::prefixTable("nextcloudsso_provider") . " (
                `user` VARCHAR(100) NOT NULL,
                `nextcloud_user` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `date_connected` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`nextcloud_user`),
                KEY `user` (`user`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            Db::exec($sql);
        } catch (Exception $e) {
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    /**
     * Cleans up database table during uninstallation.
     *
     * @return void
     */
    public function uninstall(): void
    {
        Db::dropTables(Common::prefixTable('nextcloudsso_provider'));
    }
}
