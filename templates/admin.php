<?php
/** @var array $_ */
script('mail2folder', 'admin');
style('mail2folder', 'style');
?>

<div id="mail2folder-admin" class="section">
    <h2>Mail2Folder</h2>
    <p class="settings-hint">
        Mail2Folder pollt ein IMAP-Postfach und speichert E-Mail-Anhänge automatisch
        in den Nextcloud-Ordnern der Benutzer. Der Ziel-Benutzer wird über den
        Betreff der E-Mail bestimmt (z.B. <code>[benutzername] Rechnung Mai</code>).
    </p>

    <h3>IMAP-Server</h3>
    <div class="mail2folder-form">
        <div class="mail2folder-field">
            <label for="mail2folder-imap-host">IMAP-Host</label>
            <input type="text" id="mail2folder-imap-host" name="imap_host"
                   value="<?php p($_['imap_host']); ?>"
                   placeholder="imap.ionos.de" />
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-imap-port">Port</label>
            <input type="number" id="mail2folder-imap-port" name="imap_port"
                   value="<?php p($_['imap_port']); ?>"
                   placeholder="993" />
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-imap-encryption">Verschlüsselung</label>
            <select id="mail2folder-imap-encryption" name="imap_encryption">
                <option value="ssl" <?php if ($_['imap_encryption'] === 'ssl') echo 'selected'; ?>>SSL/TLS</option>
                <option value="ssl-novalidate" <?php if ($_['imap_encryption'] === 'ssl-novalidate') echo 'selected'; ?>>SSL (ohne Zertifikatsprüfung)</option>
                <option value="starttls" <?php if ($_['imap_encryption'] === 'starttls') echo 'selected'; ?>>STARTTLS</option>
                <option value="none" <?php if ($_['imap_encryption'] === 'none') echo 'selected'; ?>>Keine</option>
            </select>
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-imap-username">Benutzername / E-Mail-Adresse</label>
            <input type="text" id="mail2folder-imap-username" name="imap_username"
                   value="<?php p($_['imap_username']); ?>"
                   placeholder="upload@meine-domain.de" autocomplete="off" />
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-imap-password">Passwort</label>
            <input type="password" id="mail2folder-imap-password" name="imap_password"
                   value="<?php if ($_['imap_password_set']) echo '********'; ?>"
                   placeholder="IMAP-Passwort" autocomplete="new-password" />
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-imap-folder">IMAP-Ordner</label>
            <input type="text" id="mail2folder-imap-folder" name="imap_folder"
                   value="<?php p($_['imap_folder']); ?>"
                   placeholder="INBOX" />
        </div>
    </div>

    <h3>Benutzer-Zuordnung</h3>
    <p class="settings-hint">
        Der Nextcloud-Benutzername wird aus dem E-Mail-Betreff extrahiert.
        Absender schreiben z.B. <code>[anna] Rechnung Mai</code>, damit die Anhänge
        bei Benutzerin „anna" abgelegt werden.
    </p>
    <div class="mail2folder-form">
        <div class="mail2folder-field">
            <label for="mail2folder-subject-pattern">Betreff-Muster</label>
            <select id="mail2folder-subject-pattern" name="subject_pattern">
                <option value="[{username}]" <?php if ($_['subject_pattern'] === '[{username}]') echo 'selected'; ?>>[benutzername] — Eckige Klammern</option>
                <option value="@{username}" <?php if ($_['subject_pattern'] === '@{username}') echo 'selected'; ?>>@benutzername — At-Zeichen</option>
                <option value="user:{username}" <?php if ($_['subject_pattern'] === 'user:{username}') echo 'selected'; ?>>user:benutzername — Präfix</option>
            </select>
            <p class="mail2folder-hint">
                Beispiel-Betreff: <code id="mail2folder-pattern-example">[anna] Rechnung Mai 2025</code>
            </p>
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-fallback-user">Fallback-Benutzer (optional)</label>
            <input type="text" id="mail2folder-fallback-user" name="fallback_user"
                   value="<?php p($_['fallback_user']); ?>"
                   placeholder="z.B. admin" />
            <p class="mail2folder-hint">
                Wenn kein Benutzer im Betreff erkannt wird, werden Anhänge bei diesem
                Benutzer abgelegt. Leer lassen = E-Mails ohne gültigen Benutzer werden ignoriert.
            </p>
        </div>
    </div>

    <h3>Verhalten</h3>
    <div class="mail2folder-form">
        <div class="mail2folder-field">
            <label for="mail2folder-poll-interval">Abfrage-Intervall (Sekunden)</label>
            <input type="number" id="mail2folder-poll-interval" name="poll_interval"
                   value="<?php p($_['poll_interval']); ?>"
                   min="60" step="60" placeholder="300" />
            <p class="mail2folder-hint">Wie oft das Postfach geprüft wird. Minimum: 60 Sekunden.</p>
        </div>

        <div class="mail2folder-field">
            <label for="mail2folder-subfolder-mode">Unterordner-Struktur</label>
            <select id="mail2folder-subfolder-mode" name="subfolder_mode">
                <option value="date" <?php if ($_['subfolder_mode'] === 'date') echo 'selected'; ?>>Nach Datum (2025/2025-06-15)</option>
                <option value="month" <?php if ($_['subfolder_mode'] === 'month') echo 'selected'; ?>>Nach Monat (2025/06)</option>
                <option value="sender" <?php if ($_['subfolder_mode'] === 'sender') echo 'selected'; ?>>Nach Absender</option>
                <option value="subject" <?php if ($_['subfolder_mode'] === 'subject') echo 'selected'; ?>>Nach Betreff (ohne Benutzertag)</option>
                <option value="flat" <?php if ($_['subfolder_mode'] === 'flat') echo 'selected'; ?>>Flach (keine Unterordner)</option>
            </select>
        </div>

        <div class="mail2folder-field">
            <input type="checkbox" id="mail2folder-delete-after" name="delete_after_processing"
                   class="checkbox" <?php if ($_['delete_after_processing'] === 'yes') echo 'checked'; ?> />
            <label for="mail2folder-delete-after">E-Mails nach Verarbeitung löschen</label>
        </div>

        <div class="mail2folder-field">
            <input type="checkbox" id="mail2folder-save-body" name="save_body"
                   class="checkbox" <?php if ($_['save_body'] === 'yes') echo 'checked'; ?> />
            <label for="mail2folder-save-body">E-Mail-Text als .txt-Datei mitspeichern</label>
            <p class="mail2folder-hint">
                Speichert den E-Mail-Text als Textdatei im selben Ordner wie die Anhänge.
                Der Dateiname entspricht dem Betreff (z.B. <code>Rechnung Mai 2025.txt</code>).
            </p>
        </div>
    </div>

    <div class="mail2folder-actions">
        <button id="mail2folder-test" class="button">Verbindung testen</button>
        <button id="mail2folder-save" class="button primary">Speichern</button>
        <span id="mail2folder-status" class="mail2folder-status"></span>
    </div>
</div>
