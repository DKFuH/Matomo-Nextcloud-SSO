# Nextcloud SSO Plugin für Matomo

Dieses Plugin ermöglicht die nahtlose Anmeldung und Authentifizierung an Matomo über Nextcloud als zentralen Identity Provider (Single Sign-On via OIDC / OAuth2).

Es orientiert sich an den unternehmensweiten SSO-Standards (Nextcloud als zentraler IdP, Scopes `openid profile email groups`, Berechtigungsbasis über Gruppen wie `admin`) und implementiert das Nextcloud-Hub Design System (`#0082c9`) auf der Login-Maske.

---

## Funktionen

- **Single Sign-On (SSO):** Ein-Klick-Anmeldung über Nextcloud mit optisch abgestimmtem SSO-Button auf der Login-Maske.
- **Dual-Mode (Ausfallsicherheit):** Der reguläre Matomo-Login bleibt als Fallback sichtbar, sodass Admins bei Wartungsarbeiten oder Störungen der Nextcloud-Instanz jederzeit Zugriff behalten.
- **Just-in-Time Provisioning:** Neue Benutzer, die sich über Nextcloud anmelden, werden bei Bedarf automatisch in Matomo angelegt.
- **Gruppen- & Rollen-Mapping:**
  - Mitglieder der Nextcloud-Admin-Gruppe (Standard: `admin`) erhalten automatisch Matomo SuperUser-/Admin-Rechte.
  - Nicht-Admin-Benutzer erhalten eine konfigurierbare Standard-Rolle (`view`, `write`, `admin` oder manuell freizuschalten) auf definierte Websites (`all` oder spezifische IDs wie `1, 2, 5`).
- **Profil-Synchronisation:** Aktualisiert bei jedem Login automatisch Anzeigename und E-Mail-Adresse aus dem Nextcloud-Profil.
- **Nextcloud Kompatibilität:** Unterstützt sowohl die moderne Nextcloud-App **`OIDC Identity Provider`** als auch die integrierte Nextcloud-App **`OAuth 2.0`**.
- **Höchste Sicherheit:** Vollständige CSRF-State-Prüfung und PKCE (Proof Key for Code Exchange mit SHA-256).

---

## 1. Einrichtung in Nextcloud

### Variante A: Nextcloud App `OIDC Identity Provider` (Empfohlen)

1. In Nextcloud unter **Apps** die App **`OIDC Identity Provider`** (App-ID `oidc`, von H2CK) installieren und aktivieren.
2. In Nextcloud als Administrator zu **Verwaltungseinstellungen &rarr; Sicherheit &rarr; OpenID Connect** navigieren.
3. Einen neuen Client anlegen:
   - **Client-Name:** `Matomo Analytics`
   - **Redirect-URI:** `https://<deine-matomo-domain>/index.php?module=NextcloudSSO&action=callback`
   - **Scopes:** `openid`, `profile`, `email`, `groups`
4. Die generierte **Client ID** und das **Client Secret** kopieren.

### Variante B: Nextcloud Integriertes OAuth 2.0

1. In Nextcloud zu **Verwaltungseinstellungen &rarr; Sicherheit &rarr; OAuth 2.0-Clients** navigieren.
2. Einen neuen Client hinzufügen:
   - **Name:** `Matomo Analytics`
   - **Weiterleitungs-URL:** `https://<deine-matomo-domain>/index.php?module=NextcloudSSO&action=callback`
3. Die angezeigte **Client-ID** und das **Secret** kopieren.

---

## 2. Installation in Matomo

### Manueller Upload / Git Clone

1. Den Inhalt dieses Repositories in das Matomo-Plugin-Verzeichnis ablegen:
   ```bash
   cd /pfad/zu/matomo/plugins
   git clone https://github.com/daniel-klas/matomo-plugin-NextcloudSSO.git NextcloudSSO
   ```
   *(Der Ordnername muss exakt `NextcloudSSO` lauten)*

2. Dateirechte sicherstellen (so dass der Webserver-Benutzer Leserechte hat).

3. Plugin in Matomo aktivieren:
   - **Über die Weboberfläche:** Unter **Administration (Zahnrad) &rarr; Plattform &rarr; Plugins** das Plugin `NextcloudSSO` suchen und auf **Aktivieren** klicken.
   - **Oder per CLI (Konsole):**
     ```bash
     ./console plugin:activate NextcloudSSO
     ```

---

## 3. Konfiguration in Matomo

Navigieren Sie in Matomo zu:
**Administration &rarr; System &rarr; Allgemeine Einstellungen &rarr; Nextcloud SSO**

Folgende Einstellungen stehen zur Verfügung:

| Einstellung | Beschreibung | Standardwert |
| :--- | :--- | :--- |
| **Nextcloud Basis-URL** | Die vollständige URL Ihrer Nextcloud-Instanz (z. B. `https://cloud.meinedomain.de`) | *(leer)* |
| **Authentifizierungs-Methode** | `Nextcloud OIDC Identity Provider` (empfohlen) oder `OAuth 2.0` | `OIDC` |
| **Client ID** | Client Identifier aus Nextcloud | *(leer)* |
| **Client Secret** | Client Secret aus Nextcloud | *(leer)* |
| **OAuth Scopes** | Angeforderte Berechtigungen | `openid profile email groups` |
| **Benutzer automatisch anlegen** | Erstellt bei Erstanmeldung automatisch ein Matomo-Konto | `Aktiviert` |
| **Benutzerdaten synchronisieren** | Aktualisiert Name & E-Mail bei jedem Login | `Aktiviert` |
| **Nextcloud-Admin-Gruppe** | Nextcloud-Gruppe für SuperUser-Zugriff in Matomo | `admin` |
| **Standard-Rolle für neue Benutzer** | Rolle für Nicht-Admins (`view`, `write`, `admin`, `noaccess`) | `view` |
| **Standard-Websites** | `all` für Zugriff auf alle Websites oder kommagetrennte IDs (`1, 2`) | `all` |
| **Beschriftung des Login-Buttons** | Text auf der Login-Schaltfläche | `Mit Nextcloud anmelden` |
| **Automatisch zu Nextcloud weiterleiten** | Leitet Login-Seite direkt zu Nextcloud weiter | `Deaktiviert` |
| **Lokalen Login anzeigen (Dual-Mode)** | Zeigt reguläres Benutzer/Passwort-Formular weiterhin an | `Aktiviert` |

---

## 4. Berechtigungen & Gruppen-Mapping

Nextcloud dient als Single Source of Truth für Benutzer und Rollen:

- **SuperUser / Administratoren:** Benutzer, die in Nextcloud Mitglied der Gruppe `admin` sind, erhalten in Matomo automatisch die SuperUser-Berechtigung.
- **Standard-Benutzer:** Benutzer ohne Admin-Gruppe erhalten die unter **Standard-Rolle** konfigurierte Berechtigung (z. B. Ansichtsrechte auf Websites).

---

## 5. Notfall-Zugriff (Fallback)

Falls **Automatisch zu Nextcloud weiterleiten** aktiviert ist und Nextcloud einmal nicht erreichbar sein sollte:

Der lokale Passwort-Login kann jederzeit erzwungen werden, indem an die Matomo-URL der Parameter `?local=1` angehängt wird:
```text
https://<deine-matomo-domain>/index.php?module=Login&local=1
```

---

## Lizenz

GPL v3 or later (GNU General Public License v3.0). Siehe [LICENSE](LICENSE).
