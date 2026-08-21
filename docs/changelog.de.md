# Änderungen

Die vollständige Liste der Änderungen. Führende Fassung ist die englische; hier steht die deutsche Übersetzung.

Die Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).
Führende Fassung ist die englische in **[CHANGELOG.md](https://github.com/netthinks/nt-flippdf/blob/main/CHANGELOG.md)**; hier
steht die deutsche Übersetzung.

## [1.8.3] – 2026-08-21

### Behoben

- **Wieder herauszoomen.** In der Vergrößerung verlangte das Rad weiterhin die
  Strg-Taste – vorher war das dort der einzige Weg hinein. Wer die
  Vergrößerung mit dem Rad allein geöffnet hatte, kam damit näher heran, aber
  nicht zurück. Das Rad wirkt jetzt in der Vergrößerung in beide Richtungen,
  ohne Taste; wer über die kleinste Stufe hinaus zurückdreht, schließt sie und
  hat das Buch wieder vor sich.

---

## [1.8.2] – 2026-08-21

### Geändert

- **Das Mausrad allein vergrößert dort, wo es nichts zu scrollen gibt.** Steht
  der Betrachter für sich – im eigenen Fenster oder im Vollbild –, genügt eine
  Radumdrehung zum Vergrößern. Eingebettet auf einer Seite nicht: Dort scrollt
  das Rad die Seite, und ein Betrachter, der es verschluckt, hält den Besucher
  fest – dieselbe Falle, wegen der auch eingebettete Karten die Strg-Taste
  verlangen. Wer es dort ohne Taste versucht, bekommt kurz den Hinweis darauf,
  und die Seite scrollt weiter.

---

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
