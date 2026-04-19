# DoorWindowState
Ermittelt den Zustand von Fenstern und Türen anhand von mehreren Sensoren und optional einer Griffposition.

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
  * Ignorieren
  * Überschreiben
  * Verfeinern (empfohlen)
* Automatische Erstellung der benötigten Variablen und Profile
* Debug-Ausgaben zur Analyse und Fehlerbehebung

---

### 2. Voraussetzungen

- Symcon ab Version 7.1

---

### 3. Software-Installation

* Über den Module Store das 'DoorWindowState'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen:
