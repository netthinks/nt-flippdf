/* Farbwähler und Textfeld halten sich gegenseitig auf Stand. Das
           Textfeld bleibt massgeblich: Nur dort laesst sich "leer" eintragen,
           und leer heisst "Vorgabe verwenden". */
        document.querySelectorAll('.farbwahl').forEach(function (wahl) {
            var feld = document.getElementById(wahl.dataset.fuer);
            if (!feld) { return; }
            wahl.addEventListener('input', function () { feld.value = wahl.value; });
            feld.addEventListener('input', function () {
                /* Kein Wiederholungsmuster in geschweiften Klammern: Fluid liest sie als
                   Platzhalter und wuerde sie aus dem Skript entfernen. */
                if (feld.value.length === 7 && /^#[0-9a-fA-F]+$/.test(feld.value)) { wahl.value = feld.value; }
            });
        });
