# Grfldt KI Tools für IPSView (Beta)

Symcon-Modul, mit dem IPSView-Ansichten per JSON-Befehl gelesen und geändert werden können, ohne den
IPSView Designer zu öffnen. Gedacht vor allem für die Zusammenarbeit mit einem KI-Assistenten über den
Symcon-MCP-Server, lässt sich aber genauso aus eigenen PHP-Skripten nutzen.

- **Autor:** Cornelius Neufeldt (im Symcon-Forum: Garfieldt)
- **Mit Unterstützung von Claude** (KI-Assistent von Anthropic): Das Dateiformat wurde gemeinsam durch Analyse
  von Ansichten und des IPSView-Clients erschlossen, der Code des Moduls wurde von Claude geschrieben.
- **Dateiformat:** Seit Version 1.3 richtet sich das Modul nach der Datenmodell-Referenz, die Andreas Brauneis
  (Entwickler von IPSView) zur Verfügung gestellt hat. Vielen Dank dafür! Die Referenz liegt nicht bei; die für
  die Arbeit nötigen Informationen stehen in `KI-ANLEITUNG.md`.
- **Lizenz:** MIT, siehe `LICENSE`
- **Version:** 1.4

> **Inoffizielles Modul.** Es wird weder vom Entwickler von IPSView noch von Symcon unterstützt. Es arbeitet mit
> dem internen Dateiformat von IPSView. Getestet mit IPSView 6.5 (6.5.20) und Symcon 9.1 Rust Edition (Beta).
> Ändert sich das Format in einer späteren IPSView-Version, kann das Modul Ansichten falsch lesen oder speichern.
> Vor jeder Änderung wird deshalb automatisch eine Sicherung angelegt (siehe unten).

## Voraussetzungen

- IP-Symcon **9.1 Rust Edition (Beta)** oder neuer
- IPSView 6.5 (IPSViewConnect)
- Für die Arbeit mit einer KI: der Symcon-MCP-Server, verbunden mit deinem KI-Assistenten
- Die KI braucht Schreibrechte, um `RequestAction` auf die Variable „Befehl“ auszuführen

## Installation

1. Die ZIP-Datei im Ordner `modules` des Symcon-Ordners entpacken, sodass `modules/GrfldtKITools/library.json`
   entsteht (nicht doppelt verschachtelt, also nicht `modules/GrfldtKITools/GrfldtKITools/library.json`):
   - Linux / SymBox: `/var/lib/symcon/modules/GrfldtKITools`
   - Windows: `C:\ProgramData\Symcon\modules\GrfldtKITools`
   - macOS: `/Library/Application Support/Symcon/modules/GrfldtKITools`
2. Symcon-Dienst neu starten, damit die Bibliothek geladen wird.
3. In der Verwaltungskonsole eine Instanz „Grfldt KI Tools für IPSView“ anlegen (Ort egal, z. B. Kategorie „Automation“).

## Mit einer KI arbeiten

1. Modul installieren und Instanz anlegen (siehe oben).
2. Deinem KI-Assistenten die Datei **`KI-ANLEITUNG.md`** geben (als Anhang oder in die Projekt-/Systemanweisungen
   kopieren). Darin steht alles, was die KI über das Modul und das IPSView-Format wissen muss.
3. Der KI sagen, welche Ansicht sie bearbeiten soll, und beschreiben, was du dir wünschst.
4. Den IPSView Designer währenddessen geschlossen halten und die Ergebnisse in der App prüfen.

Tipp: Lass die KI zuerst eine Testseite oder eine Kopie deiner Ansicht bearbeiten, bis du dem Ablauf vertraust.

## Bedienung ohne KI

- Befehl als JSON in die versteckte Variable **Befehl** schreiben (RequestAction) oder per Skript:
  `echo GKT_Execute($InstanzID, '{"cmd":"listViews"}');`
- Das Ergebnis steht als JSON in der versteckten Variable **Ergebnis**: `{"ok":true,"result":...}`
  oder `{"ok":false,"error":"..."}`.
- `{"cmd":"describe"}` listet alle Befehle und Operationen.

### Befehle

| Befehl | Zweck |
|---|---|
| `ping`, `describe` | Version abfragen, Befehle auflisten |
| `listViews` | alle IPSView-Ansichten |
| `outline {view, page?}` | Seiten und Elemente als kompakte Tabelle |
| `getControls {view, page, items}` | Elemente vollständig lesen |
| `getPage`, `getView` | Seite oder Ansichts-Einstellungen lesen (ohne Passwort und Lizenzdaten) |
| `apply {view, ops, dryRun?}` | Änderungen anwenden, siehe Operationen |
| `createView {name, template}` | neue Ansicht anlegen (nur nach Freigabe, siehe Sicherheit) |
| `backups {view}`, `restore {view, backup}` | Sicherungen auflisten und wiederherstellen |
| `resetCache` | Zwischenspeicher von IPSViewConnect leeren |
| `icons` | interne Icons von IPSView (Nummer und Name) |

### Operationen für `apply`

`setView`, `addPage`, `setPage`, `renamePage`, `deletePage`, `addControls`, `updateControls`, `deleteControls`,
`moveControls`, `transferControls`, `addImage`, `setBackground`. Die Parameter liefert `describe`.

`addPage` kennt die Seitenarten `standard` (Seite für einen Container), `popup` und `alternateMain`
(bedingte alternative Hauptseite, z. B. für Smartphones; Bedingungen über `ConditionWidth`, `ConditionHeight`,
`ConditionConfig`, `ConditionOrientation` in `values`).

## Sicherheit

- **Wer die Variable „Befehl“ beschreiben darf, kann jede IPSView-Ansicht ändern.** Zugriff entsprechend
  beschränken.
- Vor jeder Änderung wird der bisherige Stand der Ansicht als verstecktes Dokument-Medienobjekt unterhalb der
  Instanz gesichert (Anzahl pro Ansicht einstellbar, Standard 20).
- `{"cmd":"backups","view":ID}` listet die Sicherungen, `{"cmd":"restore","view":ID,"backup":ID}` stellt eine
  wieder her. Der aktuelle Stand wird vorher ebenfalls gesichert.
- Jede Änderung wird vor dem Speichern geprüft: Unbekannte Elementtypen oder Eigenschaften, fehlende
  Symcon-Objekte, fehlende Zielseiten, fehlende Bilder und Seiten ohne Größe führen zum Abbruch, ohne dass etwas
  gespeichert wird.
- Nach dem Speichern wird der Zwischenspeicher von IPSViewConnect geleert, damit die Apps den neuen Stand laden.
- Eine Ansicht nicht gleichzeitig im Designer offen haben und per Modul ändern. Sonst überschreibt das nächste
  Speichern im Designer die Änderungen.
- Das Modul liest Ansichts-Passwort und Lizenzdaten nicht aus und lässt sie nicht ändern.
- **Neue Ansichten** liegen außerhalb der Instanz. `createView` arbeitet deshalb nur, wenn du vorher die Variable
  „Neue Ansicht anlegen erlauben“ der Instanz einschaltest. Nach dem Anlegen schaltet sie sich wieder aus.

## Beispiele

Die Nummern sind Platzhalter: `<ANSICHT>` ist die Objekt-ID der IPSView-Ansicht, `<BILD>` die eines
Bild-Medienobjekts.

```json
{"cmd":"outline","view":<ANSICHT>}

{"cmd":"apply","view":<ANSICHT>,"ops":[
  {"op":"addPage","name":"EG","title":"Erdgeschoss","width":1280,"height":724},
  {"op":"addImage","media":<BILD>,"ref":"grundriss"},
  {"op":"addControls","page":"Hauptseite","controls":[
    {"Type":"IPSInlinePage","container":"content","page":"EG","LocationX":0,"LocationY":76,"Width":1280,"Height":724},
    {"Type":"IPSInlineButton","target":"content","page":"EG","Text1":"EG","Text2":"EG",
     "LocationX":300,"LocationY":14,"Width":80,"Height":46,"BackColor1":"#f5b453","ForeColor1":"#1d2420"}
  ]}
]}
```

Bilder: `addImage` nimmt ein Bild-Medienobjekt (`media`), eine Datei relativ zum Symcon-Ordner (`file`) oder
Base64-Daten (`data`, PNG oder JPG, höchstens 1 MB). Das Ergebnis enthält die Bildkennung (`refs`), die dann in
`Image1` eines `IPSImage` eingetragen wird. Mit `"ref":"name"` beim `addImage` lässt sich das Bild im selben
`apply` direkt als `"Image1":"ref:name"` verwenden (ab 1.4, auch in `Associations`). Interne Icons von IPSView
brauchen kein eigenes Bild: `"Image1":"icon:Thermostat"` oder `"Image1":3008`.

`Widget`-Einstellungen werden automatisch in die von IPSView erwartete Form gebracht (Schlüssel klein, Werte als
Text).

Farben: `ForeColor1` (Text bzw. Text „an“), `ForeColor2` (Text „aus“), `BackColor1` (Hintergrund bzw. „an“),
`BackColor2` (Hintergrund „aus“), `BorderColor1`, jeweils als `#RRGGBB` oder `#AARRGGBB`.
Eckenradien `CR1` bis `CR4`, Rahmenstärke `BW`.

## Rückmeldungen

Dies ist eine Beta-Version. Fehler, Wünsche und Erfahrungen bitte im Beta-Forum melden, am besten mit dem Befehl,
der Antwort aus „Ergebnis“ und der Symcon- und IPSView-Version.

## Versionen

- **1.4 (Beta):** Verweise `"ref:Name"` auf Bilder und Elemente innerhalb eines `apply`; Prüfung der Bilder in
  Zuordnungen (`Associations`) vor dem Speichern; KI-Anleitung um bewährte Muster ergänzt (Symbole für An/Aus und
  Zustände, Popups aus Karten, ein Schalter für mehrere Symbole) und Darstellungsart 6 für Werte mit Profil empfohlen.
- **1.3 (Beta):** erste öffentliche Version.
