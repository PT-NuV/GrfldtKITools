# Anleitung für KI-Assistenten: IPSView-Ansichten mit „Grfldt KI Tools für IPSView“ bauen

Diese Datei richtet sich an einen KI-Assistenten, der über den Symcon-MCP-Server mit einer Symcon-Installation
verbunden ist und für den Nutzer IPSView-Ansichten lesen, bauen oder ändern soll. Sie enthält alles, was dafür nötig
ist: wie das Modul angesprochen wird, wie man sicher arbeitet und die wichtigsten Regeln des IPSView-Dateiformats.

Grundlage des Formatteils ist die Datenmodell-Referenz von Andreas Brauneis, dem Entwickler von IPSView (Stand
Oktober 2026, IPSView 6.5). Die Referenz selbst liegt dem Modul nicht bei.

---

## 1. Verbindung zum Modul

Das Modul wird als Instanz „Grfldt KI Tools für IPSView“ angelegt (Modul `GrfldtKITools`, Präfix `GKT`). Unter der Instanz
liegen drei Variablen (die ersten beiden versteckt):

| Ident | Name | Zweck |
|---|---|---|
| `Command` | Befehl | Befehl als JSON-Text hineinschreiben, mit `RequestAction` |
| `Result` | Ergebnis | Antwort als JSON-Text, mit `GetValue` lesen |
| `AllowCreateView` | Neue Ansicht anlegen erlauben | Freigabe für `createView`; **nur der Nutzer schaltet sie ein** |

So findest du sie:

1. Instanz suchen, z. B. mit `symcon_find` (query „Grfldt KI Tools“ oder module „GrfldtKITools“).
2. Die Kinder der Instanz auflisten und die IDs der Variablen `Befehl` und `Ergebnis` merken.

So sendest du einen Befehl:

1. `RequestAction(<Befehl-ID>, '{"cmd":"ping"}')`. Den JSON-Text als normalen Text übergeben, nicht zusätzlich
   in Anführungszeichen setzen.
2. Danach `GetValue(<Ergebnis-ID>)`. Der Befehl läuft synchron, das Ergebnis steht sofort bereit.
3. Antwortform: `{"ok":true,"id":…,"time":…,"result":…}` oder `{"ok":false,"error":"…"}`.
   Ein optionales Feld `"id"` im Befehl wird in der Antwort zurückgegeben.

Wenn die Symcon-Funktion `GKT_Execute` aufrufbar ist (z. B. über einen Skript-Aufruf), geht auch
`GKT_Execute(<Instanz-ID>, '<JSON>')`; sie liefert dieselbe Antwort direkt zurück.

`{"cmd":"describe"}` liefert die aktuelle Liste aller Befehle, Operationen, Elementtypen und Hinweise.

## 2. Arbeitsregeln

1. **Erst lesen, dann ändern.** Mit `listViews` die Ansichten finden, mit `outline` den Aufbau lesen, mit
   `getControls` einzelne Elemente vollständig ansehen. Element-Nummern (`ItemID`) nie raten.
2. **Jede Änderung zuerst mit `"dryRun":true` schicken.** Erst wenn die Probe ohne Fehler durchläuft, ohne
   `dryRun` wiederholen.
3. **Der Designer muss geschlossen sein.** Ist die Ansicht im IPSView Designer offen, überschreibt dessen nächstes
   Speichern die Änderungen des Moduls. Den Nutzer vor größeren Änderungen daran erinnern.
4. **Sicherungen nutzen.** Vor jedem Speichern legt das Modul automatisch eine Sicherung an (`backups`,
   `restore`). Bei einem Fehler zurückspielen statt von Hand zu reparieren.
5. **Nur ändern, was gewünscht ist.** Lizenz-, Versions-, Passwort-, Farb- und Sondereinstellungen bestehender
   Ansichten nicht anfassen. Das Modul schützt die wichtigsten davon.
6. **Nur echte IDs verwenden.** Jede Symcon-Objekt-ID in `ID` muss existieren (vorher mit `symcon_find` prüfen).
   Seitennamen in Verweisen müssen exakt stimmen, einschließlich Groß-/Kleinschreibung.
7. **Den Nutzer prüfen lassen.** Das Modul kann nicht sehen, wie die Ansicht auf dem Gerät aussieht. Nach jeder
   sichtbaren Änderung den Nutzer bitten, die Ansicht zu prüfen. Der Zwischenspeicher von IPSViewConnect wird nach
   jedem Speichern automatisch geleert; geöffnete Clients laden die Ansicht dann meist von selbst neu. Hat die KI
   einen Browser, kann sie die Web-Ansicht von IPSViewConnect selbst ansehen; das Kennwort gibt der Nutzer ein, und
   ein Neuladen der Seite fragt es erneut ab.
8. **In kleinen Schritten bauen.** Lieber eine Seite oder Karte nach der anderen als eine ganze Ansicht auf einmal.
9. **Medien der Ansicht ablegen**, wo der Nutzer es möchte (z. B. eine eigene Kategorie für IPSView-Medien).
10. **Neue Ansichten nur mit Freigabe.** `createView` legt ein Objekt außerhalb der Instanz an und funktioniert nur,
    wenn der Nutzer die Variable „Neue Ansicht anlegen erlauben“ (Ident `AllowCreateView`) eingeschaltet hat. Den
    Nutzer darum bitten; **diesen Schalter niemals selbst setzen**. Er schaltet sich nach dem Anlegen wieder aus.

## 3. Befehle

| Befehl | Parameter | Zweck |
|---|---|---|
| `ping` | – | Modulversion |
| `describe` | – | Befehle, Operationen, Typen, Hinweise |
| `listViews` | – | alle IPSView-Ansichten (Medienobjekte vom Typ IPSView) |
| `outline` | `view`, `page?` | Seiten und Elemente als Tabelle: ItemID, Typ, Objekt-ID, X, Y, Breite, Höhe, Text {Parent/Objekt}, Menüziele |
| `getControls` | `view`, `page`, `items` | Elemente vollständig |
| `getPage` | `view`, `page` | Seite vollständig |
| `getView` | `view`, `keys?` | Einstellungen der Ansicht (ohne Seiten, Bilder, Passwort, Lizenz) |
| `apply` | `view`, `ops`, `dryRun?` | Änderungen anwenden (siehe Abschnitt 4) |
| `createView` | `name`, `template` | neue Ansicht mit den Einstellungen (Gerät, Vorgaben) einer bestehenden Ansicht und leerer Hauptseite; nur nach Freigabe durch den Nutzer (siehe Abschnitt 2) |
| `backups` / `restore` | `view` / `view`, `backup` | Sicherungen auflisten / zurückspielen |
| `resetCache` | – | Zwischenspeicher von IPSViewConnect leeren |
| `icons` | – | interne Icons von IPSView: Nummer → Name |

## 4. Operationen für `apply`

Alle Operationen eines `apply` werden zusammen geprüft und gespeichert. Schlägt eine fehl, wird nichts gespeichert.

| Operation | Parameter | Hinweise |
|---|---|---|
| `setView` | `values` | Einstellungen der Ansicht; geschützte Felder werden abgelehnt |
| `addPage` | `name`, `title?`, `kind?`, `width?`, `height?`, `values?` | `kind`: `standard` (Seite für einen Container, Standard), `popup`, `alternateMain` |
| `setPage` | `page`, `values` | Seiteneinstellungen |
| `renamePage` | `page`, `to` | Verweise werden mit umbenannt |
| `deletePage` | `page` | Hauptseite nicht löschbar |
| `addControls` | `page`, `controls:[…]` | jedes Element mit `Type` und Feldern; optional `ref` für die Rückgabe der neuen ItemID |
| `updateControls` | `page`, `items`, `values` oder `page`, `updates:[{item, values}]` | Wert `null` entfernt ein Feld |
| `deleteControls` | `page`, `items` | |
| `moveControls` | `page`, `items` oder `"all"`, `dx`, `dy` | verschieben |
| `transferControls` | `from`, `to`, `items`, `dx?`, `dy?` | auf eine andere Seite verschieben |
| `addImage` | `media` oder `file` oder `data`, `ref?` | Bild in die Ansicht aufnehmen; Ergebnis `refs` enthält die Bildkennung; mit `ref` in derselben Anweisung als `"ref:Name"` nutzbar |
| `setBackground` | `page`, `media`/`file`/`hash`, `layout?` | Seitenhintergrund (siehe Hinweis zu Handys in Abschnitt 6) |

Komfortfunktionen des Moduls:

- **Farben** als `"#RRGGBB"` oder `"#AARRGGBB"`; das Modul wandelt sie in Farbobjekte um.
- **IPSInlinePage** mit `"container":"content"` (Containerrolle) und `"page":"Seitenname"` (Startseite).
- **IPSInlineButton/IPSInlineImage** mit `"target":"content"` und `"page":"Seitenname"`.
- **Interne Icons** in `Image1`, `Image2`, `PageImage` und `Associations[].Image` als `"icon:Thermostat"`,
  `"icon:3008"` oder `3008`.
- **Verweise `"ref:Name"`** (ab 1.4): Ein Text `"ref:Name"` wird durch das Ergebnis einer früheren Operation derselben
  Anweisung ersetzt, die `"ref":"Name"` hatte: Bildkennung bei `addImage`, ItemID bei `addControls`. So lässt sich ein
  Bild aufnehmen und im selben `apply` verwenden, ohne die Kennung abzuschreiben:
  `{"op":"addImage","data":"…","ref":"lampe_an"}`, danach `"Image1":"ref:lampe_an"`. Ein unbekannter Verweis bricht
  die Anweisung ab.
- **`Widget`** darf mit beliebiger Schreibweise und Zahlen/Wahrheitswerten übergeben werden; das Modul schreibt die
  Schlüssel klein und die Werte als Text.
- Das Modul vergibt `ItemID`, `ItemName` und `ItemIdx` selbst.

Beispiel:

```json
{"cmd":"apply","view":12345,"dryRun":true,"ops":[
  {"op":"addControls","page":"Wohnzimmer","controls":[
    {"Type":"IPSToggleButton","ID":23456,"LocationX":40,"LocationY":40,"Width":160,"Height":48,
     "Text1":"Licht an","Text2":"Licht aus","AutoSize":false,"TextAlign":5,
     "ForeColor1":"#1d2420","BackColor1":"#f5b453","ForeColor2":"#eef2ec","BackColor2":"#262e29",
     "CR1":10,"CR2":10,"CR3":10,"CR4":10,"Font":{"Size":16,"isBold":true},"ref":"licht"}
  ]}
]}
```

## 5. Aufbau einer Ansicht

Eine Ansicht ist ein JSON-Objekt mit Einstellungen, der Liste `Pages` (alle Seiten, nicht verschachtelt) und der Liste
`Images` (eingebettete Bilder). Jede Seite hat eine Liste `Controls`.

### Ansicht

- `Width`/`Height`: Koordinatenraum der Ansicht (z. B. 1280 × 800 für ein Tablet quer).
- `Hardware`, `Orientation` (`Landscape`/`Portrait`): Zielgerät.
- `ItemID`: nächste freie Element-Nummer (pflegt das Modul).
- Lizenz-, Passwort- und Versionsfelder nie ändern.

### Seitenarten

Eine Seite hat kein Typfeld; die Art ergibt sich aus Kennzeichen. Pro Seite nur eines setzen.

| Art | Kennzeichen | Zweck |
|---|---|---|
| Hauptseite | `IsMainPage: true` | Grundgerüst, meist mit Kopfzeile und Containern; genau eine |
| Container-Seite | `IsInline: true` | Inhalt, der in einem Container (IPSInlinePage) angezeigt wird |
| Popup | `IsPopup: true` | wird über IPSDetailButton/IPSDetailImage geöffnet |
| Registerseite | keine Kennzeichen | Tab-Layout (mindestens zwei solcher Seiten) |
| Bedingte alternative Hauptseite | `IsAlternateMain: true` | ersetzt die Hauptseite auf passenden Geräten |
| Ordner | `IsFolder: true` | nur zur Ordnung im Designer |
| Eigenes Element (Definition) | `IsCustomControl: true` | wiederverwendbares Element (siehe Abschnitt 10) |

**Seiten in Containern, Popups und alternative Hauptseiten brauchen eine Größe in `PopupWidth`/`PopupHeight`.**
Fehlt sie (0), bleibt der Container leer. `addPage` setzt die Größe automatisch (Standard: Größe der Ansicht).

### Bewährtes Grundgerüst: Kopfzeile mit Menü und Inhaltsbereich

1. Hauptseite: Kopfzeile als `IPSShapeRect` (z. B. 0/0, 1280 × 76), Titel als `IPSTxtLabel`, Menüknöpfe als
   `IPSInlineButton`.
2. Darunter ein `IPSInlinePage` als Container: `"container":"content"`, Startseite in `"page"`, z. B. 0/76,
   1280 × 724.
3. Jede Inhaltsseite mit `addPage` (`kind: standard`, Größe = Größe des Containers, hier 1280 × 724).
4. Jeder Menüknopf mit `"target":"content","page":"<Seite>"`. Beim Menüknopf gilt `ForeColor1`/`BackColor1` für
   die aktive Seite, `ForeColor2`/`BackColor2` für die übrigen.

Containerrollen (`Digits` eines IPSInlinePage): 1 Main, 2 Header, 3 Menu, 4 Navigation, 5 Detail, 6 Content,
7 Info, 8 Popup, 9 Detailmenü, 10 Fuß. Scrollrichtung in `TextAlign`: 0 senkrecht, 1 waagerecht, 2 beides.

### Größenanpassung (`ResizeOption` der Seite)

| Wert | Wirkung | Erlaubt für |
|---|---|---|
| 0 | keine Anpassung (Standard) | alle |
| 1 | an das Gerät anpassen | Haupt-, Alternativ-, Popup-Seiten |
| 2 | an Containergröße anpassen | Container-Seiten |
| 3 / 4 | nur Breite / nur Höhe an Container | Container-Seiten |
| 5 | an Gerät anpassen, Seitenverhältnis halten | Popups |
| 6 | an Containerbreite skalieren (Höhe proportional) | Container-Seiten |
| 11 / 61 | wie 0 / 6, zusätzlich zoombar | Container-Seiten |

Die Elemente werden dabei je nach ihren Ankern mit angepasst. Das Modul prüft, ob Wert und Seitenart zusammenpassen.

## 6. Elemente

Jedes Element braucht `Type`, `LocationX`, `LocationY`, `Width`, `Height`; `ID` nur bei Bindung an ein
Symcon-Objekt (sonst 0). Optionale Felder weglassen statt mit 0 zu füllen: fehlende Werte erben Vorgaben der Ansicht.
Nur die Typen aus der Liste verwenden; das Modul lehnt andere ab.

### Häufige Typen

| Zweck | Type | Wichtige Felder |
|---|---|---|
| fester Text | `IPSTxtLabel` | `Text1`, `TextAlign`, `ForeColor1`, `BackColor1`, `Font` |
| Variablenwert anzeigen | `IPSVarLabel` | `ID`, `Step` (Darstellungsart), `Digits`, `Prefix`, `Suffix`, `TextAlign`, `ForeColor1`, `Font` |
| Ein/Aus schalten | `IPSToggleButton`, `IPSSwitch`, `IPSCheckBox` | `ID` (boolesche Variable), Text/Farben für an (1) und aus (2) |
| festen Wert setzen | `IPSValueButton` | `ID`, `ValueSelected` (Typ passend zur Variable), Text/Farben aktiv/inaktiv |
| Auswahl aus Zuordnungen | `IPSAssociationButton` | `ID`, `Associations` |
| Wert schrittweise ändern | `IPSRangeButton` | `ID`, `Min`, `Max`, `Step` |
| Schieberegler | `IPSSlider` (`IPSSliderVertical`) | `ID`, `Widget.minvalue`, `Widget.maxvalue`, `Widget.step` |
| Seite im Container laden | `IPSInlineButton` | `Inline…`-Feld mit Seitenname (Komfort: `target`/`page`) |
| Popup öffnen | `IPSDetailButton` | `Text1` Beschriftung, `Text2` Popup-Seitenname, `Digits` Popup-Position |
| Bild / Icon | `IPSImage` | `Image1`, `AutoSize` (Seitenverhältnis halten) |
| Fläche, Kreis, Linie | `IPSShapeRect`, `IPSShapeCircle`, `IPSShapeLineH`, `IPSShapeLineV` | `BackColor1`, `BorderColor1`, `BW`, `CR1`–`CR4` |
| Schatten | `IPSShadow` | `BackColor1`, `Widget.shadowcolor`, `Widget.shadowblurradius`, … |
| HTML aus Stringvariable | `IPSHTMLBox` | `ID` (Stringvariable mit HTML) |
| Diagramm | `IPSWidgetIPSViewChart` | `ID`, `Widget.objectid` (Archiv-/Diagrammobjekt), `Widget.timeperiod` |
| Container | `IPSInlinePage` | siehe Abschnitt 5 |

Text-Bild-Varianten (`IPSImageButton`, `IPSToggleImage`, `IPSValueImage`, `IPSInlineImage`, `IPSDetailImage` usw.)
verhalten sich wie die Button-Typen, zeigen aber `Image1`/`Image2`.

### Felder mit typabhängiger Bedeutung

| Feld | Bedeutung |
|---|---|
| `Text1` / `Text2` | Text bzw. Text „an“ / Text „aus“; bei IPSInlinePage Startseite; bei IPSDetailButton `Text2` = Popup-Seite |
| `Step` | bei IPSVarLabel Darstellungsart (siehe unten), bei Range-Buttons Schrittweite |
| `Digits` | Nachkommastellen (Standard 0); bei IPSInlinePage Containerrolle; bei Popup-Auslösern Popup-Position |
| `AutoSize` | bei Bildern Seitenverhältnis halten; bei Texten automatische Größe (für feste Layouts `false`) |
| `TextAlign` | 1–9: oben/mitte/unten × links/mittig/rechts (z. B. 4 mitte links, 5 mittig, 6 mitte rechts); 100–108 Bild-Text-Anordnung bei Knöpfen (103 Bild vor Text, mittig) |

### Wertanzeige (`IPSVarLabel`): Darstellungsart in `Step`

| Wert | Darstellung |
|---|---|
| 0 | Zahl mit `Digits`, `Prefix`, `Suffix` (**Einheit aus dem Symcon-Profil erscheint hier nicht**, `Suffix` z. B. `" °C"` setzen) |
| 1 | Zuordnungen (`Associations`) |
| 2 | Prozent (mit `Min`/`Max`) |
| 3 / 4 / 5 | Zeitstempel / Datum / Uhrzeit |
| 6 | Formatierung aus Symcon übernehmen (Einheit, Nachkommastellen, Zuordnungstexte aus Profil bzw. Darstellung) – **die beste Wahl für Variablen mit Profil** |
| 100 | Text: **nur bei `IPSFlowText`**; bei `IPSVarLabel` bleibt das Feld sonst leer (das Modul lehnt es ab) |

### Farben, Schrift, Ebenen

- Farben auf Element-, Seiten- und Ansichtsebene sind Objekte `{"A":255,"R":…,"G":…,"B":…}`. Verlauf über
  `A2`/`R2`/`G2`/`B2`, `Type` (0 waagerecht, 1 senkrecht, 2 radial, 3 ohne Verlauf) und `Pattern` (`"12"`,
  `"121"`, `"1221"`). Mit dem Modul einfach `"#RRGGBB"` übergeben.
- Farben **innerhalb von `Widget`** sind Text: `"#2878DC"`, `"40;120;220"`, `"transparent"`.
- `ForeColor1` Text/„an“, `ForeColor2` Text „aus“, `BackColor1` Hintergrund/„an“, `BackColor2` Hintergrund „aus“,
  `BorderColor1` Rahmen; `BW` Rahmenbreite; `CR1`–`CR4` Eckenradien (oben links, oben rechts, unten rechts, unten links).
- `Font`: `{"FontFamily":"…","Size":16,"isBold":true,"isItalic":false}` (kleines `is…`).
- `ItemLayer`: 1 Hintergrund, 2 Mitte (Standard), 3 Vordergrund, 4 Overlay. Für Karten: Fläche auf 1, Inhalt auf 2.

### Zuordnungen (`Associations`)

```json
[{"Idx":0,"Value":false,"Text":"Zu"},
 {"Idx":1,"Value":true,"Text":"Offen","ForeColor":{"A":255,"R":255,"G":255,"B":255},"BackColor":{"A":255,"R":200,"G":60,"B":60}}]
```

`Value` muss zum Typ der Variable passen (boolesch, Zahl oder Text); `Begin`/`End` für Bereiche. Bei
`IPSAssociationImage` und `IPSAssociationButton` gehört zu jeder Zuordnung ein Bild in `Image` (Bildkennung, Icon
oder `"ref:Name"`). Das Modul prüft vor dem Speichern, ob diese Bilder in der Ansicht vorhanden sind.

### Dynamik (`Remote`)

Über das Objekt `Remote` lassen sich Eigenschaften an Variablen binden, z. B. `VariableVisible`/`VariableHidden`
(Element nur unter einer Bedingung zeigen), `VariableText`, `VariableForeground`, `VariableBackground`,
`VariableReadonly`. Werte sind Symcon-Variablen-IDs (0 = keine Bindung).

### `Widget`

Typabhängige Zusatzeinstellungen, z. B. `minvalue`/`maxvalue`/`step` bei Schiebereglern, `htmlrenderer` bei
HTML-Boxen, `hidden`/`visible`, Rasterwerte (`grididx`, `gridcolspan`, `gridrowspan`). Schlüssel immer klein, Werte
als Text (das Modul wandelt um). Nicht mit `WidgetVars` verwechseln (Bindung eines Bedienelements an ein anderes
Element, z. B. Legende eines Diagramms schalten).

## 7. Bilder und Icons

- **Eigene Bilder** (PNG, JPEG) mit `addImage` aufnehmen: aus einem Bild-Medienobjekt (`media`), einer Datei im
  Symcon-Ordner (`file`) oder direkt als Base64 (`data`, höchstens 1 MB). Die zurückgegebene Kennung in `Image1`
  eines `IPSImage` eintragen. Gleiche Bilder werden nur einmal gespeichert.
- **Interne Icons** brauchen kein eigenes Bild: Nummer oder `"icon:Name"` direkt in `Image1`. Die vollständige
  Liste liefert `{"cmd":"icons"}`. Häufig gebraucht:

| Nr. | Icon | Nr. | Icon | Nr. | Icon |
|---|---|---|---|---|---|
| 1001 | Home | 3005 | Bulb | 3039 | Solar Power |
| 1002 | Power | 3006 | Lamp | 3044 | Charging Station |
| 1004 | Settings | 3007 | Light | 3056 | Humidity |
| 1015/1016 | Lock Closed/Open | 3003 | Shutter | 3061 | Sprinkler |
| 2002 | Warning | 3008 | Thermostat | 4003 | Kitchen |
| 2009 | Info | 3009 | Door | 4006 | Couch |
| 2013 | Battery | 3010 | Window | 4014 | Garage |
| 2015 | Security | 3033 | Sun | 4027 | Moon |

- **Eigene Symbole per Base64:** klein halten (z. B. 24–48 px, als PNG mit wenigen Farben, möglichst unter
  1,2 KB Base64), damit beim Übertragen keine Zeichen verloren gehen. Am sichersten mit `"ref:Name"` arbeiten; wer
  Kennungen von Hand einträgt, prüft sie vorher mit einem `dryRun` des `addImage`.
- **Wichtig für Smartphones:** Seitenhintergründe (`setBackground`) werden auf kleineren Geräten nicht mitskaliert.
  Grundrisse und andere Bilder, die mitskalieren sollen, als `IPSImage`-Element anlegen.

### Bewährte Muster

- **Grundriss mit Bedienelementen:** Grundriss als `IPSImage` auf Ebene 1, Bedienelemente auf Ebene 3. Rollos und
  Raffstores außen vor das jeweilige Fenster setzen, damit der Grundriss frei bleibt. Ein Rollo-Feld: `IPSShapeRect`
  als Fläche, zwei `IPSValueButton` (↑ mit `ValueSelected` 0, ↓ mit 100) und dazwischen ein `IPSVarLabel` mit `Step` 6.
- **An/Aus als Symbol** (Licht, Steckdose): `IPSToggleImage` mit `Image1` = Bild „an“ (z. B. leuchtend),
  `Image2` = Bild „aus“ (grau), `BackColor1`/`BackColor2` `"#00000000"` (durchsichtig), ohne Text.
- **Zustand als Bild** (Fenster zu/gekippt/offen): `IPSAssociationImage` mit `Readonly: true` und `Associations`
  mit `Image` je Wert.
- **Mehrere Symbole für einen Schalter** (z. B. acht Deckenspots an einem Kreis): jedes Symbol als `IPSToggleImage`
  auf dieselbe Variable und darüber ein durchsichtiger `IPSToggleButton` (Ebene 4, Farben `"#00000000"`, `BW` 0, ohne
  Text) über die ganze Fläche, damit ein Tippen irgendwo alle schaltet.
- **Popup aus einer Karte:** Karte aus Fläche und Texten, darüber ein durchsichtiger `IPSDetailButton` auf Ebene 4
  in Kartengröße mit `Text2` = Popup-Seite. Das Popup schließt beim Tippen außerhalb.
- **Spalte mit Gerätedaten:** platzsparend untereinander, Gerätename als Überschrift, darunter je Zeile Bezeichnung
  (`IPSTxtLabel`) und Wert (`IPSVarLabel`, `Step` 6).

## 8. HTML-Boxen

`IPSHTMLBox` zeigt das HTML einer Stringvariable. Für Inhalte, die IPSView-Elemente nicht abbilden (z. B.
Verlaufslinien), ein Symcon-Skript das HTML erzeugen lassen. Damit es auf allen Geräten passt: Größen in
`min(<x>vw, <y>vh)` angeben, bezogen auf die Boxgröße (z. B. bei einer Box 580 × 150: 15 px →
`min(2.586vw, 10vh)`), `overflow:hidden`, Hintergrund passend zur Karte.

## 9. Smartphones

Möglichkeiten für eine Smartphone-Darstellung:

1. Eigene Ansicht im Hochformat (einfach, unabhängig).
2. **Bedingte alternative Hauptseite** in derselben Ansicht: `addPage` mit `kind: alternateMain` und Bedingungen in
   `values`, z. B. `{"ConditionWidth":"<700"}`. Mindestens `ConditionWidth`, `ConditionHeight` oder
   `ConditionConfig` muss gesetzt sein; `ConditionOrientation` allein reicht nicht. Bei mehreren Kandidaten gewinnt
   der letzte passende (nach `Position`).
3. `ResizeOption` 6 bei Container-Seiten, damit Inhalte auf die Containerbreite skaliert werden.

## 10. Eigene wiederverwendbare Elemente (CustomControls)

Für gleichartige Karten (z. B. eine Raumkarte pro Raum): eine Definitionsseite mit `IsCustomControl: true` und
den inneren Elementen; öffentliche Eigenschaften über `CustomProperties` an den inneren Elementen
(Property-Ident → öffentlicher Name, z. B. `{"ID":"WertID","Suffix":"Einheit"}`); Instanzen als
`IPSCustomControl` mit `Text1` = Name der Definitionsseite und Werten in `Widget` (öffentliche Namen klein, Werte
als Text). Das Modul legt Definitionsseiten derzeit nicht über `addPage` an; dafür eine im Designer angelegte
Definition verwenden oder den Nutzer bitten, eine anzulegen.

## 11. Bekannte Stolperfallen

| Beobachtung | Ursache | Lösung |
|---|---|---|
| Container bleibt leer | Seite ohne `PopupWidth`/`PopupHeight` | Größe setzen (`setPage`) |
| Einheit fehlt oder Zahl statt Text (z. B. „4“ statt „geparkt“) | `Step` 0 nutzt das Profil nicht | `Step` 6 setzen (oder `Suffix`/`Digits` von Hand) |
| Zahl ohne Nachkommastellen („8 °C“ statt „8,4 °C“) | bei `Step` 0 ist `Digits` standardmäßig 0 | `Digits` setzen, z. B. 1 |
| Wertanzeige leer | `Step` 100 bei einem `IPSVarLabel` | `Step` weglassen (0) oder 6 |
| Text wird abgeschnitten, Einheit fehlt | Feld zu schmal, Text bricht in eine unsichtbare zweite Zeile um | breiter machen oder Schrift verkleinern |
| Hintergrundbild auf dem Handy abgeschnitten | Hintergründe werden nicht skaliert | Bild als `IPSImage` anlegen |
| Änderungen weg | Designer hat danach gespeichert | Designer schließen, Sicherung prüfen |
| App zeigt alten Stand | Zwischenspeicher | `resetCache`, Ansicht in der App neu laden |
| „Position ist verschlüsselt“ | Demo-Lizenz von IPSView | Positionen nur im Designer ändern |
| Symbol fehlt, Prüfung meldet „Bild … fehlt“ | Bildkennung falsch abgeschrieben oder Bild beim Übertragen beschädigt | `"ref:Name"` verwenden; Bild kleiner machen und neu aufnehmen |

## 12. Grenzen

- Das Modul ist inoffiziell, setzt Symcon 9.1 Rust Edition (Beta) oder neuer voraus und wurde mit IPSView 6.5 getestet.
- Es prüft Aufbau und Verweise, aber nicht das Aussehen. Das muss der Nutzer auf dem Gerät beurteilen.
- Für Element- und Widget-Einstellungen, die hier nicht beschrieben sind: ein im Designer angelegtes Beispiel mit
  `getControls` lesen und dessen Felder übernehmen, statt Felder zu erfinden.
