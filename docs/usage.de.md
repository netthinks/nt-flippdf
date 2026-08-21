# Ausgaben bauen

![Das Backend-Modul](images/modul-uebersicht.webp)

## Im Backend

**Datei → Blätterbare Ausgaben.** PDF auswählen, Kennung vergeben, Sprache
wählen, *Ausgabe bauen*. Das Rendern dauert je nach Umfang eine halbe bis
mehrere Minuten, ein Verlaufsbalken zeigt, was gerade geschieht — das Fenster
muss dabei offen bleiben.

In der Liste steht die Ausgabe danach mit Seitenzahl, Inhaltsverzeichnis, Größe,
Baudatum und den Aktionen:

| Aktion | Was sie tut |
|---|---|
| **Ansehen** | öffnet die Ausgabe in einem neuen Tab |
| **Bearbeiten** | Titel, Sprache, Farben, Umfang der Vorschau, Download, Bedienung |
| **Inhalt** | das Inhaltsverzeichnis, eine Zeile je Kapitel |
| **Erneuern** | bringt die Ausgabe auf einen neueren Betrachter, ohne neu zu rendern |
| **Neu bauen** | baut aus derselben Quelldatei, mit allen Einstellungen |
| **Löschen** | entfernt das Verzeichnis |

Eine Ausgabe, deren Quelldatei sich seit dem Bau geändert hat, ist als
*veraltet* gekennzeichnet.

Aus welchen Ordnern PDF-Dateien angeboten werden, begrenzt `pdfFolders` in der
Erweiterungskonfiguration. Ohne die Angabe wird alles angeboten, was im
Dateibereich liegt — in einer gewachsenen Installation also auch die
Bewerbungen aus dem Benutzer-Upload.

## Auf der Kommandozeile

```bash
vendor/bin/typo3 flippdf:build <pfad/zur.pdf> <kennung> \
    --titel "Titel über dem Betrachter" \
    --sprache de
```

| Option | Bedeutung |
|---|---|
| `--titel` | Titel über dem Betrachter und im Browserfenster |
| `--sprache` | `de`, `en`, `fr` oder `zh` — die Beschriftungen im Betrachter |
| `--download` | verweist den Download auf eine vorhandene Adresse, statt eine Datei abzulegen |
| `--ohne-download` | bietet gar keinen Download an |
| `--farbe-leiste`, `--farbe-akzent` | Farbe der Leisten und der Akzente |
| `--vorschau` | wie viele Seiten das Inhaltselement höchstens zeigen darf |
| `--blaetterdauer`, `--ohne-schatten` | Dauer und Schatten beim Umblättern |
| `--ohne-zoom`, `--ohne-verweise` | ohne Vergrößern, ohne Verweise aus dem PDF |
| `--schwester` | verknüpft eine Sprachfassung, `en:kennung`, mehrere mit Komma |

Das Zusatzpaket ergänzt denselben Befehl um `--vorschau-ausgabe`,
`--vorschau-kennung`, `--zufallskennung` und `--doppelseiten`.

## Den Betrachter erneuern

Nach einem Update der Extension laufen bestehende Ausgaben mit dem Betrachter
weiter, mit dem sie gebaut wurden — er liegt in ihnen drin. Ein Befehl bringt
sie auf den neuen Stand, ohne eine einzige Seite neu zu rendern:

```bash
vendor/bin/typo3 flippdf:refresh             # alle Ausgaben
vendor/bin/typo3 flippdf:refresh <kennung>   # nur diese
```

## Eine Ausgabe bearbeiten

![Eine Ausgabe bearbeiten](images/modul-bearbeiten.webp)

Titel, Sprache, Farben, Umfang der Vorschau, Download und jedes einzelne
Bedienelement des Betrachters — alles, ohne die Seiten neu zu rendern. Was hier
abgeschaltet ist, wird aus dem Betrachter entfernt statt versteckt; sonst wäre
es über die Tastatur weiter erreichbar.

## Während des Neubaus

Eine Ausgabe bleibt beim Neubau erreichbar. Die neue entsteht in einem
Verzeichnis daneben, und erst der letzte Schritt tauscht beide — ein Augenblick,
nicht die mehreren Minuten, die ein Neubau dauert.
