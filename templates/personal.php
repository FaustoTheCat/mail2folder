<?php
/** @var array $_ */
script('mail2folder', 'personal');
style('mail2folder', 'style');
?>

<div id="mail2folder-personal" class="section">
    <h2>Mail2Folder</h2>

    <?php if ($_['imap_configured']): ?>
        <p class="settings-hint">
            Sende E-Mails mit Anhängen an <strong><?php p($_['imap_username']); ?></strong>.
            Schreibe deinen Benutzernamen in den Betreff, damit die Anhänge in deinem
            Nextcloud-Ordner landen.
        </p>

        <div class="mail2folder-info-box">
            <h4>So funktioniert es</h4>
            <table class="mail2folder-info-table">
                <tr>
                    <td><strong>An:</strong></td>
                    <td><code><?php p($_['imap_username']); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Betreff:</strong></td>
                    <td><code><?php p($_['subject_tag']); ?> Dein Betreff hier</code></td>
                </tr>
                <tr>
                    <td><strong>Anhänge:</strong></td>
                    <td>Werden automatisch in <code>/<?php p($_['target_folder']); ?>/</code> gespeichert</td>
                </tr>
            </table>
        </div>

        <div class="mail2folder-form">
            <div class="mail2folder-field">
                <label for="mail2folder-target-folder">Zielordner</label>
                <input type="text" id="mail2folder-target-folder" name="target_folder"
                       value="<?php p($_['target_folder']); ?>"
                       placeholder="Mail-Attachments" />
                <p class="mail2folder-hint">
                    Anhänge werden in diesen Ordner in deinen Dateien gespeichert.
                    Unterordner werden ggf. nach Datum oder Absender erstellt.
                </p>
            </div>
        </div>

        <div class="mail2folder-actions">
            <button id="mail2folder-personal-save" class="button primary">Speichern</button>
            <span id="mail2folder-personal-status" class="mail2folder-status"></span>
        </div>
    <?php else: ?>
        <p class="settings-hint">
            Mail2Folder ist noch nicht konfiguriert. Bitte wende dich an deinen Administrator.
        </p>
    <?php endif; ?>
</div>
