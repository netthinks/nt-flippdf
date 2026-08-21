(function () {
            'use strict';
            /*
             * Fortschritt beim Bauen.
             *
             * Der Bau selbst laeuft als ganz normaler Aufruf im Hintergrund des
             * Browsers. Waehrenddessen fragt diese Anzeige eine kleine Datei im
             * Ablageort ab - eine statische Datei, kein zweiter PHP-Aufruf: Der
             * muesste auf die Sitzung warten, die der laufende Bau haelt, und
             * kaeme erst nach dessen Ende zum Zug.
             *
             * Ohne JavaScript bleibt alles beim Alten: Das Formular wird
             * abgeschickt, die Seite laedt nach dem Bau neu.
             */
            var anzeigeVorab = document.getElementById('flippdfFortschritt');
            var ablage = (anzeigeVorab && anzeigeVorab.dataset.ablage) || '/blaetterbar/';
            var anzeige = document.getElementById('flippdfFortschritt');
            var balken = document.getElementById('flippdfFortschrittBalken');
            var text = document.getElementById('flippdfFortschrittText');
            var dauer = document.getElementById('flippdfFortschrittDauer');
            if (!anzeige || !window.fetch) { return; }

            var timer = null;

            function zeige(prozent, satz, seit) {
                anzeige.hidden = false;
                balken.style.width = prozent + '%';
                balken.setAttribute('aria-valuenow', String(prozent));
                balken.textContent = prozent + ' %';
                if (satz) { text.textContent = satz; }
                if (seit) {
                    var s = Math.max(0, Math.round(Date.now() / 1000 - seit));
                    dauer.textContent = 'läuft seit ' + (s < 60 ? s + ' Sekunden' : Math.round(s / 60) + ' Minuten');
                }
            }

            function beobachte(kennung) {
                zeige(0, 'Der Bau beginnt …', 0);
                timer = window.setInterval(function () {
                    fetch(ablage + 'fortschritt-' + kennung + '.json?t=' + Date.now(), { cache: 'no-store' })
                        .then(function (a) { return a.ok ? a.json() : null; })
                        .then(function (d) { if (d) { zeige(d.prozent || 0, d.text, d.beginn); } })
                        .catch(function () { /* Datei noch nicht da oder schon weg */ });
                }, 1000);
            }

            function beenden() {
                window.clearInterval(timer);
                zeige(100, 'Fertig, die Übersicht wird neu geladen …', 0);
                /* Der Bau hat seine Meldung in die Warteschlange gelegt; sie
                   erscheint mit dem folgenden Aufbau der Seite. */
                window.location.reload();
            }

            var formular = document.querySelector('form[data-flippdf-bauen]');
            if (formular) {
                formular.addEventListener('submit', function (e) {
                    var kennung = (formular.querySelector('[name=kennung]') || {}).value || '';
                    if (!kennung) { return; }
                    e.preventDefault();
                    formular.querySelector('button[type=submit]').disabled = true;
                    beobachte(kennung.replace(/[^a-zA-Z0-9_-]/g, ''));
                    fetch(formular.getAttribute('action'), {
                        method: 'POST',
                        body: new FormData(formular),
                        credentials: 'same-origin',
                        headers: { 'X-Flippdf-Still': '1' }
                    })
                        .then(beenden).catch(beenden);
                });
            }

            document.querySelectorAll('a[data-flippdf-neubau]').forEach(function (verweis) {
                verweis.addEventListener('click', function (e) {
                    e.preventDefault();
                    beobachte(verweis.getAttribute('data-flippdf-neubau'));
                    anzeige.scrollIntoView({ block: 'center' });
                    fetch(verweis.href, { credentials: 'same-origin', headers: { 'X-Flippdf-Still': '1' } })
                        .then(beenden).catch(beenden);
                });
            });
        })();
