# Konfiguration

Die Vorgaben stehen in der Erweiterungskonfiguration unter *Einstellungen → Erweiterungskonfiguration → nt_flippdf*. Sie gelten für neue Ausgaben; was eine Ausgabe daraus macht, steht danach in ihr selbst und lässt sich im Backend-Modul ändern.

## Ablage und Grundsätzliches

Wo die Ausgaben liegen und wie sie erreichbar sind.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `basePath` | `public/blaetterbar` | Ablageort |
| `baseUrl` | `/blaetterbar/` | Adresse |
| `protectFromIndexing` | `1` | Vor Suchmaschinen schützen |
| `pdfFolders` | `—` | Ordner mit den PDF-Dateien |

## Darstellung

Wie die Seiten gerendert werden und wie die Ausgabe aussieht.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `pageWidth` | `1240` | Seitenbreite in Pixeln |
| `jpegQuality` | `80` | JPEG-Qualität der Seitenbilder (1-100) |
| `thumbWidth` | `200` | Breite der Vorschaubilder in der Seitenübersicht |
| `zoom` | `1` | Vergrößern anbieten |
| `zoomMax` | `3` | Höchste Vergrößerungsstufe |
| `extractLinks` | `1` | Verweise aus dem PDF anklickbar machen |

## Download

Die Fassung zum Herunterladen.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `buildDownloadVersion` | `1` | Verkleinerte Download-Fassung erzeugen |
| `downloadResolution` | `120` | Auflösung der Download-Fassung in dpi |

## Vorschau

Der Umfang, den das Inhaltselement höchstens zeigen darf.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `teaserPages` | `5` | Seiten in der Vorschau |

## Umblättern

Bewegung und Verhalten beim Blättern.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `flipDuration` | `700` | Dauer einer Blätterbewegung in Millisekunden (1 = praktisch ohne Bewegung) |
| `flipShadows` | `1` | Schatten beim Blättern zeichnen |
| `flipCover` | `1` | Titelseite einzeln zeigen |
| `flipDrag` | `1` | Ziehen mit der Maus erlauben |

## Bedienelemente

Was der Betrachter anbietet. Jede dieser Vorgaben lässt sich je Ausgabe und je Inhaltselement überschreiben.

| Schlüssel | Vorgabe | Bedeutung |
|---|---|---|
| `uiButtonStyle` | `text` | Schaltflächen im Betrachter |
| `uiSearch` | `1` | Suche anbieten |
| `uiToc` | `1` | Inhaltsverzeichnis anbieten |
| `uiThumbs` | `1` | Seitenübersicht anbieten |
| `uiDownload` | `1` | Download-Schaltfläche anbieten (nur wirksam, wenn die Ausgabe eine Download-Datei hat) |
| `uiZoom` | `1` | Vergrößern-Schaltfläche anbieten |
| `uiPrint` | `1` | Drucken anbieten |
| `uiFullscreen` | `1` | Vollbild anbieten |
| `uiLanguages` | `1` | Sprachumschaltung anbieten (nur wirksam, wenn Sprachfassungen verknüpft sind) |
| `uiNav` | `1` | Pfeile links und rechts neben dem Buch anbieten |
| `uiSlider` | `1` | Seitenregler in der Fußleiste anbieten |
| `uiMarks` | `1` | Kapitelmarken unter dem Seitenregler anzeigen |
| `uiIndicator` | `1` | Seitenzahl in der Kopfleiste anzeigen |
| `uiHint` | `1` | Bedienhinweis in der Fußleiste anzeigen |
| `uiExtern` | `1` | "In eigenem Fenster öffnen" anbieten (erscheint nur, wenn der Betrachter eingebettet läuft) |
| `uiSound` | `1` | Blättergeräusch anbieten |
| `uiSoundOn` | `1` | Blättergeräusch von Anfang an eingeschaltet (der Leser kann es im Betrachter umschalten) |

## Was wohin gehört

Drei Ebenen, von grob nach fein:

1. **Erweiterungskonfiguration** — die Vorgabe für alles Neue.
2. **Die Ausgabe selbst** — im Backend-Modul unter *Bearbeiten*. Was hier steht,
   gilt überall, wo diese Ausgabe eingebunden ist.
3. **Das Inhaltselement** — gilt nur an dieser einen Stelle.

Was eine feinere Ebene abschaltet, bleibt abgeschaltet; einschalten kann sie
nichts, was weiter oben aus ist.

## Einstellungen des Zusatzpakets

Wasserzeichen, Hintergrundbilder, Logo-Ordner, der Umfang der Vorschau-Ausgabe
und die Zählung stehen in der Konfiguration von `nt_flippdf_pro`, nicht hier.
