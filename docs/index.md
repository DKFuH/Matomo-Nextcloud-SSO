# NextcloudSSO

Single Sign-On for Matomo using Nextcloud as identity provider (OIDC / OAuth 2.0).

## Requirements

* Matomo 6, PHP 8.1 or newer
* Nextcloud with the app **OIDC Identity Provider** (app ID `oidc`, recommended) or the built-in **OAuth 2.0**

## Setup in Nextcloud

1. Create a new client (name e.g. `Matomo`).
2. Redirect URI: `https://<your-matomo-domain>/index.php?module=NextcloudSSO&action=callback` (also shown on the plugin settings page).
3. Scopes: `openid`, `profile`, `email`, `groups`.
4. Copy the client ID and client secret.

## Setup in Matomo

1. Activate the plugin (Administration → Plugins, or `./console plugin:activate NextcloudSSO`).
2. Open Administration → System → General settings → **Nextcloud SSO**.
3. Enter the Nextcloud base URL, authentication method, client ID and client secret.

## Settings

* **Create users automatically** – creates a Matomo account on first login.
* **Sync user data** – updates display name and email on every login.
* **Nextcloud admin group** (default `admin`) – members become Matomo super users.
* **Default role / default websites** – access for all other users (`view`, `write`, `admin`, `noaccess`; `all` or site IDs like `1, 2`).
* **Login button label**, **auto-redirect** and **show local login (dual mode)**.

## Emergency access

If auto-redirect is enabled and Nextcloud is unreachable, force the local login with
`https://<your-matomo-domain>/index.php?module=Login&local=1`.

## Security

CSRF state validation and PKCE (SHA-256). The plugin only contacts the configured Nextcloud instance.
