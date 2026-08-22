# Änderungen

Die vollständige Liste der Änderungen. Führende Fassung ist die englische; hier steht die deutsche Übersetzung.

Die Versionsnummern folgen [Semantic Versioning](https://semver.org/lang/de/).
Führende Fassung ist die englische in **[CHANGELOG.md](https://github.com/netthinks/nt-flippdf/blob/main/CHANGELOG.md)**; hier
steht die deutsche Übersetzung.

## [1.9.4] – 2026-08-22

### Neu

- **Ein Wort an der ersten und der letzten Seite.** Wer dort weiterblättert, wo
  die Ausgabe endet, bekam bisher gar nichts zu sehen und konnte nicht
  unterscheiden, ob das Ende erreicht ist oder der Betrachter klemmt. Jetzt
  erscheint am äußeren Rand kurz ein Hinweis – auf allen Wegen: Knöpfe,
  Pfeiltasten, Klick an den Außenrand und Wischen auf dem Tablet. Er zeigt sich
  einmal und geht von selbst wieder; mehrfaches Blättern stapelt keine
  Meldungen.

---

## [1.9.3] – 2026-08-22

### Behoben

- **Strg und Mausrad zoomten die ganze Browserseite.** Beim Herausdrehen
  schrumpfte alles, nicht nur die Ausgabe – und sobald das Fenster unter 900
  Pixel fiel, stellte der Betrachter auf Einzelseiten um, die Nachbarseite
  schien also zu fehlen. Im Betrachter gehört die Geste jetzt dem Betrachter, in
  beide Richtungen und überall auf der Seite. (Ein bereits eingestellter
  Browser-Zoom bleibt bestehen; **Strg + 0** setzt ihn zurück, die Doppelseite
  kommt dann von selbst wieder.)

### Geändert

- **Der Zoom-Knopf öffnet eine Doppelseite ganz**, statt die zweite Seite aus
  dem Bild zu schieben. Bei einer einzelnen Seite bleibt es beim gewohnten
  Sprung.
- **Über die volle Breite hinaus bleibt die Mitte im Bild** – dort steht der
  Bund –, und die Ansicht lässt sich mit der Maus schieben: Das Rad vergrößert
  hier, also nimmt man die Seite in die Hand.
- **Die Knöpfe + und – wirken anteilig** wie das Rad. Ein festes Maß war bei
  einer Doppelseite, die schon bei einer halben Stufe ganz zu sehen ist, ein
  Satz.

---

## [1.9.2] – 2026-08-22

### Behoben

- **Ein Klick oder Tipp auf eine Doppelseite machte sie kleiner statt
  größer.** Die Einstiegsgröße wurde am ersten Blatt gemessen, das die
  Blätterbibliothek vorhält – bei einer Doppelseite liegt das abgelegt und ohne
  Breite da. Die Vergrößerung ging deshalb auf ihrer Untergrenze auf, etwa einem
  Fünftel des Bildschirms. Gemessen wird jetzt das Blatt, das tatsächlich auf
  der Bühne steht.
- **Eine Doppelseite lief in der Vergrößerung nach rechts aus dem Bild.** Die
  Ebene weiß nun, wie viele Seiten sie zeigt, und gibt ihnen den Platz dafür:
  Die Doppelseite bleibt mittig, und jede Seite ist so groß wie eine einzelne
  wäre.
- **Klick und Tipp öffnen jetzt in voller Breite** statt in der Größe, die die
  Seite auf der Bühne ohnehin schon hatte – zum Lesen greift man schließlich
  hin. Rad und zwei Finger setzen weiterhin dort an, wo das Buch steht, damit
  ihr erster Schritt ein Schritt bleibt.

---

## [1.9.1] – 2026-08-22

### Behoben

- **Auf dem iPad lief das ganze Fenster aus dem Bild.** Safari deutet zwei
  Finger als eigenen Seitenzoom; weil der Betrachter dasselbe gleichzeitig tat,
  war am Ende die Seite vergrößert und ließ sich hin und her schieben. Die
  Geste des Browsers wird jetzt abgewiesen — vergrößert wird die Seite im
  Betrachter. Waagerechtes Wischen nimmt der Betrachter ebenfalls für sich,
  senkrechtes bleibt bei der Seite darunter, und seitlich verrutschen kann
  nichts mehr.

---

## [1.9.0] – 2026-08-22

### Hinzugefügt

- **Ein Klick mitten auf die Seite öffnet die Vergrößerung.** Das ist der Reflex
  der meisten Leser, und es war das Einzige, worauf der Betrachter keine Antwort
  hatte. Klicks nahe an den Außenrändern blättern weiter — dort greift man hin,
  um umzublättern.
- **Wischen funktioniert auf dem Tablet.** Die Blätter-Bibliothek erkennt ein
  Wischen nur, wenn es in 250 Millisekunden vorbei ist; ein bequemes Wischen auf
  dem iPad dauert länger, und die Seite blieb einfach stehen. Die Berührungen
  nimmt jetzt der Betrachter selbst entgegen: Wischen blättert, Tippen in die
  Mitte vergrößert, Tippen am Rand blättert. Senkrechtes Wischen bleibt
  unangetastet, damit die Seite darunter weiter scrollt.
- **Zwei Finger ziehen die Seite auf** und verkleinern sie wieder — auf der
  Seite wie in der Vergrößerung.

---

## [1.8.5] – 2026-08-21

### Behoben

- **Die Vergrößerung springt beim ersten Dreh nicht mehr.** Sie begann auf einer
  Stufe, auf der die Seite die volle Breite füllt — von einem Buch aus, das ein
  Drittel davon einnimmt, ist das ein Satz, und die Ecke der Seite war
  angeschnitten. Das Rad öffnet die Vergrößerung jetzt genau in der Größe, die
  das Buch auf der Bühne hat, und wächst von dort aus weiter, jede Raste etwa
  ein Zehntel mehr als die vorige. Anteilige Schritte statt fester, damit es
  nah wie fern gleich ruhig wirkt. Solange die Seite schmaler ist als die
  Fläche, steht sie mittig.

---

## [1.8.4] – 2026-08-21

### Hinzugefügt

- **TYPO3 14.** Auf 14.3 durchgeprüft: Bauen auf der Kommandozeile und im Modul,
  Betrachter, Inhaltselement, Vorschau im Seitenmodul, Backend-Modul in beiden
  Sprachen. Drei Stellen mussten nachgeben — `StandaloneView` gibt es in 14
  nicht mehr, das Seitenmodul übergibt ein Datensatz-Objekt statt eines Feldes,
  und das FlexForm eines Elements kommt bereits aufgelöst an. Alle drei sind für
  12.4, 13.4 und 14 gleichermaßen abgedeckt.

### Geändert

- **Die Vergrößerung wächst schrittweise.** Eine Raste am Mausrad sprang bisher
  auf 140 %. Sie beginnt jetzt dort, wo das Buch aufhört, und wächst mit jeder
  weiteren Raste; wie weit eine Raste trägt, leitet sich aus dem ab, was das
  Gerät meldet — Mausrad und Trackpad laufen damit gleich ruhig.

---

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
