(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var baseUrl = OC.generateUrl('/apps/mail2folder');

        // Update example when pattern changes
        var patternSelect = document.getElementById('mail2folder-subject-pattern');
        var exampleEl = document.getElementById('mail2folder-pattern-example');
        if (patternSelect && exampleEl) {
            var examples = {
                '[{username}]': '[anna] Rechnung Mai 2025',
                '@{username}': '@anna Rechnung Mai 2025',
                'user:{username}': 'user:anna Rechnung Mai 2025',
            };
            patternSelect.addEventListener('change', function() {
                exampleEl.textContent = examples[this.value] || this.value;
            });
        }

        function getFormData() {
            return {
                imap_host: document.getElementById('mail2folder-imap-host').value,
                imap_port: document.getElementById('mail2folder-imap-port').value,
                imap_username: document.getElementById('mail2folder-imap-username').value,
                imap_password: document.getElementById('mail2folder-imap-password').value,
                imap_encryption: document.getElementById('mail2folder-imap-encryption').value,
                imap_folder: document.getElementById('mail2folder-imap-folder').value,
                subject_pattern: document.getElementById('mail2folder-subject-pattern').value,
                fallback_user: document.getElementById('mail2folder-fallback-user').value,
                poll_interval: document.getElementById('mail2folder-poll-interval').value,
                subfolder_mode: document.getElementById('mail2folder-subfolder-mode').value,
                delete_after_processing: document.getElementById('mail2folder-delete-after').checked ? 'yes' : 'no',
                save_body: document.getElementById('mail2folder-save-body').checked ? 'yes' : 'no',
            };
        }

        function showStatus(message, isError) {
            var el = document.getElementById('mail2folder-status');
            el.textContent = message;
            el.className = 'mail2folder-status ' + (isError ? 'error' : 'success');
            setTimeout(function() {
                el.textContent = '';
                el.className = 'mail2folder-status';
            }, 5000);
        }

        // Save settings
        document.getElementById('mail2folder-save').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;

            fetch(baseUrl + '/admin/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken,
                },
                body: JSON.stringify(getFormData()),
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                showStatus(
                    data.status === 'ok' ? 'Einstellungen gespeichert.' : 'Fehler beim Speichern.',
                    data.status !== 'ok'
                );
            })
            .catch(function(err) {
                showStatus('Fehler: ' + err.message, true);
            })
            .finally(function() {
                btn.disabled = false;
            });
        });

        // Test connection
        document.getElementById('mail2folder-test').addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            showStatus('Teste Verbindung...', false);

            fetch(baseUrl + '/admin/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken,
                },
                body: JSON.stringify(getFormData()),
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                showStatus(data.message, data.status !== 'ok');
            })
            .catch(function(err) {
                showStatus('Fehler: ' + err.message, true);
            })
            .finally(function() {
                btn.disabled = false;
            });
        });
    });
})();
