# Eine Ausgabe auf einer Seite

Das Inhaltselement **Blätterbare Ausgabe** bringt eine Ausgabe auf jede Seite —
eingebettet im Textfluss oder als Schaltfläche, die sie in einem eigenen Fenster
öffnet.

Es kommt ohne eigene Spalte in `tt_content` aus: Die Einstellungen liegen im
FlexForm-Feld, das ohnehin da ist. In einer gewachsenen Installation zählt das —
`tt_content` läuft irgendwann in das Zeilenlimit von InnoDB, und jede weitere
Spalte rückt diesen Tag näher.

## Die Einstellungen

| Einstellung | Was sie bewirkt |
|---|---|
| **Ausgabe** | welche der gebauten Ausgaben gezeigt wird |
| **Darstellung** | eingebettet auf der Seite oder als Schaltfläche mit eigenem Fenster |
| **Höhe** | des eingebetteten Betrachters, 300 bis 2000 Pixel |
| **Beschriftung der Schaltfläche** | der Text auf dem Knopf |
| **Nur eine Vorschau zeigen** | zeigt nur die ersten Seiten und bietet keinen Download |
| **Seiten in der Vorschau** | höchstens so viele, wie die Ausgabe zulässt |
| **Beschriftung der Schaltflächen** | ausgeschrieben, nur Symbole oder beides — für dieses Element |
| **Hier ausblenden** | schaltet einzelne Bedienelemente nur für dieses Element ab |

Das Seitenmodul zeigt eine Vorschau des Elements mit dem Titelbild der Ausgabe,
ihrem Umfang, der Sprache, dem Baudatum, der Größe und den Einstellungen dieses
Elements.

## Vorschau: was sie tut und was nicht

Bei *nur eine Vorschau zeigen* lädt der Betrachter eine gekürzte Beschreibung
der Ausgabe und zeigt die ersten Seiten, ohne Download. Die übrigen Seitenbilder
tragen eine Zufallskennung im Namen, sind also nicht durch Weiterzählen der
Adresse zu erreichen.

!!! warning "Kein Schutz für geschützte Inhalte"
    Die vollständige Beschreibungsdatei liegt daneben im selben Verzeichnis, und
    darin stehen alle Seiten. Wer die Adresse der Ausgabe kennt, hat das ganze
    Dokument. Für ein Whitepaper, das erst nach einer Anmeldung herausgegeben
    wird, muss die Vorschau eine **eigene Ausgabe** sein — genau die baut das
    Zusatzpaket.

## Mehrere Elemente, eine Ausgabe

Dieselbe Ausgabe kann auf mehreren Seiten stehen, jedes Mal mit eigenen
Einstellungen: hier eingebettet und vollständig, dort als Schaltfläche, an
dritter Stelle als Vorschau mit drei Seiten. Die Einstellungen reisen in der
Adresse des Betrachters mit; die gebaute Ausgabe bleibt unangetastet.
