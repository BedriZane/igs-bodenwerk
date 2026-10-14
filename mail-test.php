<?php
declare(strict_types=1);
/**
 * Testseite für den Mailversand: zeigt, ob SMTP konfiguriert ist, und verschickt
 * auf Wunsch eine Testmail. Nur mit dem Schlüssel aus mail-config.php nutzbar.
 * Aufruf:  https://www.igs-bodenwerk.de/mail-test.php?key=DEIN-SCHLUESSEL
 */
header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex');

$configFile = __DIR__ . '/mail-config.php';
if (!is_file($configFile)) {
    http_response_code(404);
    exit("mail-config.php fehlt – siehe mail-config.example.php.\n");
}
$config = require $configFile;
$key    = (string) ($config['test_key'] ?? '');
if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Kein Zugriff.\n");
}

echo "Mail-Konfiguration\n==================\n";
echo 'SMTP-Server:  ' . ($config['smtp_host'] ?? '–') . ':' . ($config['smtp_port'] ?? '–') . ' (' . ($config['smtp_encryption'] ?? '–') . ")\n";
echo 'Benutzer:     ' . ($config['smtp_user'] ?? '–') . "\n";
echo 'Passwort:     ' . (!empty($config['smtp_pass']) ? 'gesetzt' : 'FEHLT') . "\n";
echo 'Absender:     ' . ($config['from_email'] ?? '–') . "\n";
echo 'Empfänger:    ' . ($config['recipient'] ?? '–') . "\n\n";

if (empty($config['smtp_user']) || empty($config['smtp_pass'])) {
    exit("SMTP ist nicht vollständig konfiguriert – es wird mail() verwendet.\n");
}

require __DIR__ . '/smtp-mailer.php';
$mailer = new SmtpMailer(
    (string) ($config['smtp_host'] ?? 'smtp.hostinger.com'),
    (int)    ($config['smtp_port'] ?? 465),
    (string) $config['smtp_user'],
    (string) $config['smtp_pass'],
    (string) ($config['smtp_encryption'] ?? 'ssl')
);

$ok = $mailer->send(
    (string) ($config['from_email'] ?? $config['smtp_user']),
    (string) ($config['from_name'] ?? 'Website Kontaktformular'),
    (string) ($config['recipient'] ?? $config['smtp_user']),
    'Testmail vom Kontaktformular igs-bodenwerk.de',
    "Diese Testmail wurde über mail-test.php verschickt.\nWenn sie ankommt, funktioniert der SMTP-Versand.\n\nZeit: " . date('d.m.Y H:i:s')
);

echo $ok
    ? "Testmail wurde verschickt. Bitte Posteingang (und Spam-Ordner) von " . ($config['recipient'] ?? '') . " prüfen.\n"
    : "FEHLER beim Versand:\n" . $mailer->lastError() . "\n";
