# FAQ

## Can I still log in with a Matomo password?
Yes. Unless you disable "show local login", the regular form stays available. Use `?local=1` on the login URL to bypass auto-redirect.

## Which Nextcloud apps are supported?
"OIDC Identity Provider" (recommended) and the built-in "OAuth 2.0" app.

## How do I make someone a super user?
Add them to the Nextcloud group configured as admin group (default `admin`). The role is applied at login.

## Which redirect URI do I register?
`https://<your-matomo-domain>/index.php?module=NextcloudSSO&action=callback`
