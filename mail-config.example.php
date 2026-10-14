<?php
/**
 * Vorlage für die Mail-Konfiguration des Kontaktformulars.
 *
 * 1. Diese Datei auf dem Server als  mail-config.php  kopieren (gleiches Verzeichnis).
 * 2. Zugangsdaten eines Postfachs der Domain eintragen (hPanel -> E-Mails -> Postfächer).
 * 3. mail-config.php NIE ins Git-Repository legen – sie steht in .gitignore.
 *
 * Ohne mail-config.php fällt contact-handler.php auf die PHP-Funktion mail() zurück,
 * deren Mails bei Hostinger häufig nicht zugestellt werden.
 */
return [
    // SMTP-Server von Hostinger: Port 465 mit 'ssl' oder Port 587 mit 'tls'
    'smtp_host'       => 'smtp.hostinger.com',
    'smtp_port'       => 465,
    'smtp_encryption' => 'ssl',

    // Postfach, über das versendet wird (Benutzername = komplette E-Mail-Adresse)
    'smtp_user'       => 'info@igs-bodenwerk.de',
    'smtp_pass'       => 'HIER-DAS-POSTFACH-PASSWORT',

    // Absender: muss bei Hostinger dem angemeldeten Postfach entsprechen
    'from_email'      => 'info@igs-bodenwerk.de',
    'from_name'       => 'Website Kontaktformular',

    // Empfänger der Anfragen
    'recipient'       => 'info@igs-bodenwerk.de',

    // Geheimer Schlüssel für die Testseite: mail-test.php?key=...
    // Beliebige lange Zeichenfolge wählen, z. B. 30 zufällige Buchstaben und Zahlen.
    'test_key'        => '',
];
