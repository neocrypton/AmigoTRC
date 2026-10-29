<?php
/**
 * Beispiel-Konfiguration – wird normalerweise von install.php als config.php erzeugt.
 * Nur nötig, wenn du ohne Installer einrichten willst.
 */
return [
    'site_name'        => 'AMIGO!',
    'timezone'         => 'Europe/Berlin',
    'debug'            => false,          // nie auf true im Live-Betrieb (zeigt Fehlerdetails)
    'force_https'      => true,           // leitet http:// auf https:// um
    'trust_proxy'      => false,          // true nur hinter einem Reverse-Proxy, der X-Forwarded-Proto setzt
    'session_lifetime' => 7200,           // Sekunden Inaktivität bis zum Auto-Logout
    'upload_max_mb'    => 10,             // max. Größe je Foto (zusätzlich PHP-Limits beachten)
    'data_dir'         => '/pfad/ausserhalb/des/webroots/amigo-data',
    'app_key'          => 'BASE64_32_BYTES', // php -r 'echo base64_encode(random_bytes(32));'
    'tgdb_api_key'     => '',             // TheGamesDB API-Key (optional)

    // Variante A: SQLite
    'db' => [
        'driver' => 'sqlite',
        'path'   => '/pfad/ausserhalb/des/webroots/amigo-data/amigo.sqlite',
    ],

    // Variante B: MySQL / MariaDB
    // 'db' => [
    //     'driver' => 'mysql',
    //     'host'   => 'localhost',
    //     'port'   => 3306,
    //     'name'   => 'datenbankname',
    //     'user'   => 'benutzer',
    //     'pass'   => 'passwort',
    //     'prefix' => 'amigo_',      // erlaubt das Teilen einer vorhandenen Datenbank
    // ],
];
