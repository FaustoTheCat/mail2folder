(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var baseUrl = OC.generateUrl('/apps/mail2folder');

        function showStatus(message, isError) {
            var el = document.getElementById('mail2folder-personal-status');
            el.textContent = message;
            el.className = 'mail2folder-status ' + (isError ? 'error' : 'success');
            setTimeout(function() {
                el.textContent = '';
                el.className = 'mail2folder-status';
            }, 5000);
        }

        // Save target folder
        var saveBtn = document.getElementById('mail2folder-personal-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;

                var targetFolder = document.getElementById('mail2folder-target-folder').value;

                fetch(baseUrl + '/personal/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'requesttoken': OC.requestToken,
                    },
                    body: JSON.stringify({ target_folder: targetFolder }),
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
        }
    });
})();
