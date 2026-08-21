# Für Entwickler

## Aufbau einer Ausgabe

```
<kennung>/index.html      der Betrachter
<kennung>/book.json       alles, was der Betrachter über die Ausgabe weiß
<kennung>/search.json     der Text jeder Seite
<kennung>/pages/…         die Seitenbilder, mit Zufallskennung im Namen
<kennung>/thumbs/…        die Vorschaubilder
<kennung>/assets/…        Betrachter-Dateien, hineinkopiert
<kennung>/<name>.pdf      die Datei zum Herunterladen
<kennung>/teaser.json     die gekürzte Beschreibung für den Vorschaumodus
```

Nichts darin zeigt nach TYPO3 zurück. Das Verzeichnis auf einen anderen Server
kopieren, und es läuft dort — darauf ist die ganze Konstruktion gebaut.

In `book.json` stehen Titel, Kennung, Sprache, Seitenzahl, Beschriftungen,
Inhaltsverzeichnis, Farben, Blätterverhalten, die Liste der Bedienelemente und
die Seiten selbst. Ein Zusatzpaket kann mehr hineinschreiben; der Betrachter
übergeht, was er nicht kennt.

## Erweiterungspunkte

Der Bau meldet sich an vier Stellen, das Backend-Modul an drei. Sie sind die
Naht, an der `nt_flippdf_pro` hängt — und an der alles andere ebenfalls hängen
kann.

| Ereignis | Wann | Wofür |
|---|---|---|
| `BeforeBuildEvent` | vor dem Lauf | Angaben ändern, auch die Kennung |
| `AfterPagesRenderedEvent` | Seiten gerendert, noch nichts abgeleitet | Seitenbilder oder ihre Zahl ändern |
| `BeforeBookWrittenEvent` | bevor `book.json` geschrieben wird | ergänzen, was der Betrachter wissen soll |
| `AfterBuildEvent` | die Ausgabe steht | etwas daneben ablegen |
| `ModuleFormEvent` | das Modul rendert ein Formular | Abschnitte ergänzen |
| `ModuleSaveEvent` | das Modul baut oder speichert | Einstellungen ergänzen |
| `ModuleColumnsEvent` | das Modul listet die Ausgaben | Spalten ergänzen |

`AfterPagesRenderedEvent` trägt eine Liste mit, aus welcher Seite des
Quell-PDFs eine Buchseite stammt. Ohne sie liefen Text und Verweise
auseinander, sobald sich die Zahl der Seiten ändert — genau das geschieht beim
Teilen von Doppelseiten.

### Ein Beispiel

```php
final class MeinListener
{
    public function __invoke(BeforeBookWrittenEvent $ereignis): void
    {
        $buch = $ereignis->getBuch();
        $buch['theme']['accent'] = '#ec6602';
        $ereignis->setBuch($buch);
    }
}
```

```yaml
services:
  Vendor\Ext\EventListener\MeinListener:
    tags:
      - name: event.listener
        event: Netthinks\NtFlippdf\Event\BeforeBookWrittenEvent
```

### Bausteine

Für ein Zusatzpaket, das eine zweite Ausgabe daneben anlegen will, sind einige
Methoden des `FlipbookBuilder` öffentlich: `writeViewer()`, `swapDirectory()`,
`clearDir()`, `directoryBytes()` und `sanitizeSlug()`.

## Zählung

Das Basispaket zählt nicht. `nt_flippdf_pro` schreibt eine Adresse in
`book.json`, der Betrachter meldet dort einen Aufruf, einen Download oder einen
Klick auf einen Verweis — ohne Cookie und ohne Kennung des Lesers, tageweise
zusammengefasst.
