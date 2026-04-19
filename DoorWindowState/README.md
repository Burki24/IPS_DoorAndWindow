# DoorWindowState

Symcon Modul zur intelligenten Ermittlung des Fenster- und Türstatus basierend auf mehreren Sensoren und optionaler Griffposition.

[![Symcon PHP SDK](https://img.shields.io/badge/Symcon-PHP%20Modul-orange)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/version-1.0-blue.svg)
![Symcon](https://img.shields.io/badge/Symcon-7.1+-green.svg)
![Status](https://img.shields.io/badge/status-stable-brightgreen.svg)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
---

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in Symcon](#4-einrichten-der-instanzen-in-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

---

### 1. Funktionsumfang

* Ermittlung des Fenster-/Türstatus anhand von zwei Sensoren (oben / unten)
* Unterstützung folgender Zustände:
  * Geschlossen
  * Gekippt
  * Offen
* Optional: Integration einer Griffposition (z. B. Zigbee2MQTT)
* Automatische Umwandlung von String-Werten (Enum) in Integer-Zustände
* Unterstützung von Sensoren mit Profil:
  * `~Window`
  * `~Window.Reversed`
* Konfigurierbares Verhalten der Griffauswertung:
  * **Ignorieren** – Die Griffposition wird nicht berücksichtigt, der Zustand wird ausschließlich über die Sensoren ermittelt
  * **Überschreiben** – Die Griffposition bestimmt den Zustand vollständig, unabhängig von den Sensorwerten
  * **Verfeinern (empfohlen)** – Die Sensoren bestimmen den Grundzustand, die Griffposition kann diesen sinnvoll erweitern (z. B. von gekippt zu offen).
  * Die Einstellung "Verfeinern" bietet in den meisten Fällen die realistischste Abbildung des tatsächlichen Fensterzustands.
* Automatische Erstellung der benötigten Variablen und Profile
* Debug-Ausgaben zur Analyse und Fehlerbehebung

---

### 2. Voraussetzungen

- Symcon ab Version 7.1

---

### 3. Software-Installation

* Über den Module Store das 'DoorWindowState'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen:
  https://github.com/Burki24/IPS_DoorAndWindow
  
---

### 4. Einrichten der Instanzen in Symcon

Unter 'Instanz hinzufügen' kann das 'DoorWindowState'-Modul mithilfe des Schnellfilters gefunden werden.

- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

---

__Konfigurationsseite__:

Name            | Beschreibung
--------------- | ------------------
Betriebsmodus   | Auswahl zwischen "AUF/ZU" und "AUF/GEKIPPT/ZU"
Sensor Oben     | Sensor für oberen Fensterkontakt
Sensor Unten    | Sensor für unteren Fensterkontakt
Griffposition   | Optionaler Sensor für die Griffstellung (String/Enum)
Griff-Verhalten | Steuerung der Griffauswertung (Ignorieren, Überschreiben, Verfeinern)

---

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner Variablen kann zu Fehlfunktionen führen.

---

#### Statusvariablen

Name             | Typ     | Beschreibung
---------------- | ------- | ------------
Open             | Boolean | Zeigt an, ob das Fenster geschlossen ist (`false`) oder geöffnet (`true`) – verwendet das Profil `~Window.Reversed`
State            | Integer | Fensterstatus (nur im erweiterten Modus aktiv)
HandlePosition   | Integer | Erkannte Griffposition (optional)

---

#### Profile

Name                 | Typ
-------------------- | -------
DWS.State            | Integer (0 = Geschlossen, 1 = Gekippt, 2 = Offen)
DWS.HandlePosition   | Integer (0 = Unten, 1 = Oben, 2 = Links, 3 = Rechts)

---

### 6. Visualisierung

Das Modul stellt folgende Werte für die Visualisierung bereit:

* Boolean-Anzeige für offenen/geschlossenen Zustand
* Farbliche Darstellung des Zustands über das Profil `DWS.State`
* Optionale Anzeige der Griffposition

Die Darstellung kann direkt im WebFront genutzt oder in eigene Visualisierungen integriert werden.

---

### 7. PHP-Befehlsreferenz

Aktuell stellt das Modul keine direkten PHP-Funktionen zur Verfügung.

Die Nutzung erfolgt vollständig über die Instanzkonfiguration und die automatisch erzeugten Variablen.

---
