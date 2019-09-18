<?php

// Zugangsdaten zur Datenbank
$DB_HOST = "localhost"; // Host-Adresse
$DB_NAME = "amigo"; // Datenbankname
$DB_BENUTZER = "amigo"; // Benutzername
$DB_PASSWORT = "qwertz"; // Passwort

// Zeichenkodierung UTF-8 bei der Verbindung setzen,
// Infos: https://werner-zenk.de/tipps/schriftzeichen_richtig_darstellen.php
// Und eine PDOException bei einem Fehler auslösen.
$OPTION = [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8", PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
 // Verbindung zur Datenbank aufbauen
 $db = new PDO("mysql:host=" . $DB_HOST . ";dbname=" . $DB_NAME,
  $DB_BENUTZER, $DB_PASSWORT, $OPTION);
}
catch (PDOException $e) {
 // Bei einer fehlerhaften Verbindung eine Nachricht ausgeben
 exit("Verbindung fehlgeschlagen! " . $e->getMessage());
}

?>