<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\NextcloudSSO;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * Defines administrative system settings for the NextcloudSSO plugin.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $nextcloudUrl;

    /** @var Setting */
    public $providerType;

    /** @var Setting */
    public $clientId;

    /** @var Setting */
    public $clientSecret;

    /** @var Setting */
    public $scope;

    /** @var Setting */
    public $authorizeUrl;

    /** @var Setting */
    public $tokenUrl;

    /** @var Setting */
    public $userinfoUrl;

    /** @var Setting */
    public $autoCreateUser;

    /** @var Setting */
    public $syncUserInfo;

    /** @var Setting */
    public $adminGroup;

    /** @var Setting */
    public $defaultRole;

    /** @var Setting */
    public $defaultSites;

    /** @var Setting */
    public $buttonText;

    /** @var Setting */
    public $autoRedirect;

    /** @var Setting */
    public $allowLocalLogin;

    protected function init()
    {
        $this->nextcloudUrl = $this->createNextcloudUrlSetting();
        $this->providerType = $this->createProviderTypeSetting();
        $this->clientId = $this->createClientIdSetting();
        $this->clientSecret = $this->createClientSecretSetting();
        $this->scope = $this->createScopeSetting();

        $this->authorizeUrl = $this->createAuthorizeUrlSetting();
        $this->tokenUrl = $this->createTokenUrlSetting();
        $this->userinfoUrl = $this->createUserinfoUrlSetting();

        $this->autoCreateUser = $this->createAutoCreateUserSetting();
        $this->syncUserInfo = $this->createSyncUserInfoSetting();
        $this->adminGroup = $this->createAdminGroupSetting();
        $this->defaultRole = $this->createDefaultRoleSetting();
        $this->defaultSites = $this->createDefaultSitesSetting();

        $this->buttonText = $this->createButtonTextSetting();
        $this->autoRedirect = $this->createAutoRedirectSetting();
        $this->allowLocalLogin = $this->createAllowLocalLoginSetting();
    }

    private function createNextcloudUrlSetting(): Setting
    {
        return $this->makeSetting('nextcloudUrl', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingNextcloudUrl');
            $field->description = Piwik::translate('NextcloudSSO_SettingNextcloudUrlHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->transform = function ($value) {
                return rtrim(trim($value), '/');
            };
        });
    }

    private function createProviderTypeSetting(): Setting
    {
        return $this->makeSetting('providerType', 'oidc', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingProviderType');
            $field->description = Piwik::translate('NextcloudSSO_SettingProviderTypeHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = [
                'oidc'   => Piwik::translate('NextcloudSSO_ProviderTypeOidc'),
                'oauth2' => Piwik::translate('NextcloudSSO_ProviderTypeOAuth2'),
            ];
        });
    }

    private function createClientIdSetting(): Setting
    {
        return $this->makeSetting('clientId', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingClientId');
            $field->description = Piwik::translate('NextcloudSSO_SettingClientIdHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createClientSecretSetting(): Setting
    {
        return $this->makeSetting('clientSecret', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingClientSecret');
            $field->description = Piwik::translate('NextcloudSSO_SettingClientSecretHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createScopeSetting(): Setting
    {
        return $this->makeSetting('scope', 'openid profile email groups', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingScope');
            $field->description = Piwik::translate('NextcloudSSO_SettingScopeHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createAuthorizeUrlSetting(): Setting
    {
        return $this->makeSetting('authorizeUrl', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingAuthorizeUrl');
            $field->description = Piwik::translate('NextcloudSSO_SettingAuthorizeUrlHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createTokenUrlSetting(): Setting
    {
        return $this->makeSetting('tokenUrl', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingTokenUrl');
            $field->description = Piwik::translate('NextcloudSSO_SettingTokenUrlHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createUserinfoUrlSetting(): Setting
    {
        return $this->makeSetting('userinfoUrl', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingUserinfoUrl');
            $field->description = Piwik::translate('NextcloudSSO_SettingUserinfoUrlHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createAutoCreateUserSetting(): Setting
    {
        return $this->makeSetting('autoCreateUser', true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingAutoCreateUser');
            $field->description = Piwik::translate('NextcloudSSO_SettingAutoCreateUserHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createSyncUserInfoSetting(): Setting
    {
        return $this->makeSetting('syncUserInfo', true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingSyncUserInfo');
            $field->description = Piwik::translate('NextcloudSSO_SettingSyncUserInfoHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createAdminGroupSetting(): Setting
    {
        return $this->makeSetting('adminGroup', 'admin', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingAdminGroup');
            $field->description = Piwik::translate('NextcloudSSO_SettingAdminGroupHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createDefaultRoleSetting(): Setting
    {
        return $this->makeSetting('defaultRole', 'view', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingDefaultRole');
            $field->description = Piwik::translate('NextcloudSSO_SettingDefaultRoleHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = [
                'view'     => Piwik::translate('UsersManager_ApplyToViewSites'),
                'write'    => Piwik::translate('UsersManager_ApplyToEditSites'),
                'admin'    => Piwik::translate('UsersManager_ApplyToAdminSites'),
                'noaccess' => Piwik::translate('NextcloudSSO_RoleNoAccess'),
            ];
        });
    }

    private function createDefaultSitesSetting(): Setting
    {
        return $this->makeSetting('defaultSites', 'all', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingDefaultSites');
            $field->description = Piwik::translate('NextcloudSSO_SettingDefaultSitesHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createButtonTextSetting(): Setting
    {
        return $this->makeSetting('buttonText', 'Mit Nextcloud anmelden', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingButtonText');
            $field->description = Piwik::translate('NextcloudSSO_SettingButtonTextHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function createAutoRedirectSetting(): Setting
    {
        return $this->makeSetting('autoRedirect', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingAutoRedirect');
            $field->description = Piwik::translate('NextcloudSSO_SettingAutoRedirectHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createAllowLocalLoginSetting(): Setting
    {
        return $this->makeSetting('allowLocalLogin', true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('NextcloudSSO_SettingAllowLocalLogin');
            $field->description = Piwik::translate('NextcloudSSO_SettingAllowLocalLoginHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    public function isConfigured(): bool
    {
        return !empty($this->nextcloudUrl->getValue())
            && !empty($this->clientId->getValue())
            && !empty($this->clientSecret->getValue());
    }

    public function getEffectiveAuthorizeUrl(): string
    {
        $custom = trim($this->authorizeUrl->getValue());
        if (!empty($custom)) {
            return $custom;
        }

        $base = $this->nextcloudUrl->getValue();
        if ($this->providerType->getValue() === 'oauth2') {
            return $base . '/apps/oauth2/authorize';
        }

        return $base . '/apps/oidc/authorize';
    }

    public function getEffectiveTokenUrl(): string
    {
        $custom = trim($this->tokenUrl->getValue());
        if (!empty($custom)) {
            return $custom;
        }

        $base = $this->nextcloudUrl->getValue();
        if ($this->providerType->getValue() === 'oauth2') {
            return $base . '/apps/oauth2/api/v1/token';
        }

        return $base . '/apps/oidc/token';
    }

    public function getEffectiveUserinfoUrl(): string
    {
        $custom = trim($this->userinfoUrl->getValue());
        if (!empty($custom)) {
            return $custom;
        }

        $base = $this->nextcloudUrl->getValue();
        if ($this->providerType->getValue() === 'oauth2') {
            return $base . '/ocs/v2.php/cloud/user?format=json';
        }

        return $base . '/apps/oidc/userinfo';
    }
}
