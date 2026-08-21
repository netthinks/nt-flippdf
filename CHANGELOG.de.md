# Änderungen

Die Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).
Führende Fassung ist die englische in **[CHANGELOG.md](CHANGELOG.md)**; hier
steht die deutsche Übersetzung.

## [1.8.1] – 2026-08-21

### Hinzugefügt

- **Strg und Mausrad über dem Buch öffnen die Vergrößerung.** Für viele Leser
  ist das der erste Griff, und ohne uns zoomt der Browser stattdessen die ganze
  Seite – eingebettet auf einer Landingpage wachsen dann Kopf- und Fußbereich,
  während das Buch gleich groß bleibt. Die Geste öffnet jetzt die Vergrößerung
  des Betrachters; wer weiterdreht, vergrößert darin. Zwei Finger auf dem
  Trackpad melden sich als dasselbe Ereignis und sind damit abgedeckt.
  Herauszoomen verhält sich unverändert.

---

## [1.8.0] – 2026-08-21

Erste öffentliche Fassung.

Aus einem PDF entsteht eine blätterbare Ausgabe als eigenständiges Verzeichnis:
Seitenbilder, Vorschaubilder, Volltextsuche, Inhaltsverzeichnis,
Seitenübersicht, Vergrößern, Drucken, eine verkleinerte Fassung zum
Herunterladen und ein Betrachter, der nichts weiter braucht als die Dateien in
diesem Verzeichnis.

- **Backend-Modul** unter *Datei → Blätterbare Ausgaben*: Ausgaben bauen,
  ansehen, Inhaltsverzeichnis pflegen, Betrachter erneuern, neu bauen, löschen.
  Es zeigt Seitenzahl, Größe und Baudatum und kennzeichnet Ausgaben, deren
  Quelldatei neuer ist.
- **Inhaltselement „Blätterbare Ausgabe"** – eingebettet oder als Schaltfläche.
  Ohne eigene Spalte in `tt_content`; die Einstellungen liegen im FlexForm-Feld,
  und das Seitenmodul zeigt eine Vorschau mit Titelbild und Angaben.
- **Befehle** `flippdf:build` und `flippdf:refresh`; das Erneuern bringt
  vorhandene Ausgaben auf einen neuen Betrachter, ohne die Seiten neu zu
  rendern.
- **Jedes Bedienelement lässt sich abschalten** – als Vorgabe, je Ausgabe und je
  Inhaltselement. Die Schaltflächen der Kopfleiste gibt es ausgeschrieben, als
  Symbole oder beides.
- **Beschriftungen auf Deutsch und Englisch**, im Modul nach der Sprache des
  Backend-Benutzers, im Betrachter je Ausgabe eingestellt.
- **Erweiterungspunkte**: vier Ereignisse rund um den Bau, drei im Backend-Modul
  und ein paar öffentliche Handgriffe – dort hängt sich
  [nt_flippdf_pro](https://www.netthinks.com) ein.
- Ausgaben bleiben aus den Suchmaschinen heraus: eine `.htaccess` mit
  `X-Robots-Tag: noindex, nofollow` und ein `noindex` in jeder Startseite.
