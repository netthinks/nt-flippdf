# Installation

```bash
composer require netthinks/nt-flippdf
vendor/bin/typo3 extension:setup
```

Ohne Composer: die Extension im TYPO3 Extension Repository herunterladen und im
Extension-Manager aktivieren.

## Was auf dem Server liegen muss

| Programm | Wofür | Nötig |
|---|---|---|
| `gs` (Ghostscript) | rendert die PDF-Seiten in Bilder | ja |
| `magick` oder `convert` (ImageMagick) | Vorschaubilder, Wasserzeichen, Seiten teilen | ja |
| `pdfinfo` (Poppler) | zählt Seiten schnell | nein |
| `pdftotext` (Poppler) | Text für die Volltextsuche | nein, aber empfohlen |

Beide Pflichtprogramme müssen **für den Benutzer des Webservers** aufrufbar
sein, nicht nur in Ihrer eigenen Kommandozeile. Das Backend-Modul unter *Datei →
Blätterbare Ausgaben* zeigt, was es gefunden hat und was fehlt.

## Wo die Ausgaben landen

Standardmäßig in `public/blaetterbar`, erreichbar unter `/blaetterbar/`. Beides
lässt sich in der Erweiterungskonfiguration ändern — `basePath` und `baseUrl`.
Das Verzeichnis entsteht beim ersten Bau.

!!! tip "Die Ablage aus dem Deployment heraushalten"
    Eine Ausgabe ist Datenbestand, kein Code. Wenn Ihr Deployment bei jedem
    Release `public/` leert, legen Sie die Ablage an eine Stelle, die das
    überlebt, oder nehmen Sie das Verzeichnis aus.

## Suchmaschinen

Beim Bauen entsteht in der Ablage eine `.htaccess` mit
`X-Robots-Tag: noindex, nofollow`, und jede Startseite bekommt zusätzlich ein
eigenes `noindex`. Gefunden werden soll die Seite, die eine Ausgabe einbindet —
nicht die Ausgabe selbst, die ihr sonst um dieselben Wörter Konkurrenz macht.

Unter nginx gibt es keine `.htaccess`; dort gehört der Kopf in die
Serverkonfiguration für dieses Verzeichnis.
