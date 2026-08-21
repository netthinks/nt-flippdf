# nt_flippdf

Macht aus einem PDF eine **blätterbare Ausgabe in einem eigenständigen
Verzeichnis**: Seitenbilder, Vorschaubilder, einen Volltextindex, einen
Betrachter und eine Datei zum Herunterladen.

Ist sie einmal gebaut, besteht die Ausgabe nur noch aus statischen Dateien. Sie
läuft weiter, was auch immer mit der Website drumherum geschieht — nach einem
Relaunch, nach einem TYPO3-Upgrade, selbst wenn die Extension deinstalliert
wird.

![Der Betrachter mit einer aufgeschlagenen Ausgabe](images/betrachter-doppelseite.webp)

## Warum nicht einfach das PDF verlinken

Ein PDF ist schnell verlinkt. Nur: Wer darauf klickt, verlässt Ihre Website —
und was dann geschieht, sehen Sie nicht mehr. Kein Aufruf, keine Verweildauer,
kein Anlass zurückzukommen.

Eine blätterbare Ausgabe hält den Leser auf Ihrer Seite. Sie liest sich wie ein
Heft, hat eine Volltextsuche über alle Seiten, ein Inhaltsverzeichnis und eine
Seitenübersicht — und sie liegt auf Ihrem Server, nicht bei einem Dienst, den es
in drei Jahren vielleicht nicht mehr gibt.

<div class="grid cards" markdown>

-   **Volltextsuche**

    ![Volltextsuche mit Trefferliste](images/betrachter-suche.webp)

    Über die ganze Ausgabe, mit Sprung auf die Seite, auf der der Treffer steht.

-   **Seitenübersicht**

    ![Seitenübersicht](images/betrachter-seiten.webp)

    Jede Seite als Kachel — für Leser, die ungefähr wissen, wo sie waren.

</div>

## Was der Betrachter anbietet

Blättern mit den Pfeiltasten, den Schaltflächen, durch Ziehen an der Seitenecke
oder Wischen; Seitenregler mit Kapitelmarken; Volltextsuche;
Inhaltsverzeichnis; Seitenübersicht; Vergrößern; Drucken; Download; Vollbild;
Öffnen im eigenen Fenster; ein Blättergeräusch; und einen Umschalter zwischen
verknüpften Sprachfassungen.

**Jedes davon lässt sich abschalten** — als Vorgabe in der
Erweiterungskonfiguration, je Ausgabe im Backend-Modul und je Inhaltselement.
Die Schaltflächen der Kopfleiste gibt es ausgeschrieben, nur als Symbole oder
als beides.

## Voraussetzungen

* TYPO3 12.4, 13.4 oder 14.x, PHP 8.2 oder neuer
* **Ghostscript** (`gs`) rendert die Seiten
* **ImageMagick** (`magick` oder `convert`) erzeugt die Vorschaubilder
* `pdfinfo` und `pdftotext` aus dem Poppler-Paket sind optional; sie
  beschleunigen das Zählen der Seiten und verbessern die Volltextsuche

Das Backend-Modul prüft das und sagt, was fehlt.

## Das Zusatzpaket

[`nt_flippdf_pro`](https://www.netthinks.com) bringt mit, was ein geschütztes
Whitepaper braucht: eine Vorschau-Ausgabe neben der vollständigen, eine nicht
erratbare Adresse für die Vollversion, das Teilen von Doppelseiten,
Wasserzeichen, Logo und Hintergrundbilder, französische und chinesische
Beschriftungen, verknüpfte Sprachfassungen und die Zählung von Aufrufen und
Downloads.

Nichts davon wird hier gebraucht — dieses Paket ist für sich vollständig.

## Im Einsatz

Die [ystral gmbh maschinenbau + processtechnik](https://ystral.com/wissen/wissensportal/whitepaper/die-vielseitige-welt-der-pulverdispergierung/)
veröffentlicht ihre Whitepaper auf diesem Weg: auf der Landingpage die Vorschau
der ersten Seiten, nach der Anmeldung die vollständige Ausgabe.
