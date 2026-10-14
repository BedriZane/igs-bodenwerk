<?php
declare(strict_types=1);

$recipient    = 'info@igs-bodenwerk.de';

// Zurück auf die Seite, von der das Formular kam (nur bekannte Seiten erlaubt).
$origins      = ['index' => 'index.html', 'kontakt' => 'kontakt.html'];
$origin       = is_string($_POST['origin'] ?? null) ? $_POST['origin'] : 'index';
$redirectBase = $origins[$origin] ?? 'index.html';

function redirect(string $status): void {
    global $redirectBase;
    header('Location: ' . $redirectBase . '?contact=' . $status . '#contact');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('error');
}

// Honeypot: verstecktes Feld, das nur Bots ausfüllen. Erfolg vortäuschen, nichts senden.
if (!empty($_POST['website'] ?? '')) {
    redirect('success');
}

/** Zeilenumbrüche entfernen, damit Werte nie für Header-Injection genutzt werden können. */
function cleanField($value, int $maxLen = 300): string {
    if (!is_string($value)) {
        return '';
    }
    $value = str_replace(["\r", "\n"], '', $value);
    return trim(mb_substr($value, 0, $maxLen));
}

/** Mehrfachauswahl säubern und zu einer Zeile zusammenfassen. */
function cleanList($value, int $maxItems = 12): string {
    if (!is_array($value)) {
        return '';
    }
    $items = [];
    foreach (array_slice($value, 0, $maxItems) as $item) {
        $item = cleanField($item, 80);
        if ($item !== '') {
            $items[] = $item;
        }
    }
    return implode(', ', $items);
}

// --- Kontaktdaten ---
$name  = cleanField($_POST['name']  ?? '', 120);
$email = cleanField($_POST['email'] ?? '', 180);
$phone = cleanField($_POST['phone'] ?? '', 60);

// --- Projektangaben ---
$leistung     = cleanList($_POST['leistung']          ?? null);
$objektart    = cleanField($_POST['objektart']        ?? '', 60);
$flaeche      = cleanField($_POST['flaeche']          ?? '', 60);
$istBelag     = cleanField($_POST['ist_belag']        ?? '', 60);
$wunschBelag  = cleanField($_POST['wunsch_belag']     ?? '', 60);
$zeitrahmen   = cleanField($_POST['zeitrahmen']       ?? '', 60);
$material     = cleanField($_POST['material']         ?? '', 80);
$muster       = !empty($_POST['muster']               ?? '');
$plz          = cleanField($_POST['plz']              ?? '', 10);
$ort          = cleanField($_POST['ort']              ?? '', 80);
$datenschutz  = !empty($_POST['datenschutz'] ?? '');

// Nachricht darf mehrzeilig bleiben – sie landet nur im Nachrichtentext, nie in einem Header.
$message = trim(mb_substr((string) ($_POST['message'] ?? ''), 0, 5000));

// --- Pflichtangaben prüfen ---
if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('error');
}
if ($leistung === '' || $plz === '' || !$datenschutz) {
    redirect('error');
}

$fallback = static fn (string $v): string => $v !== '' ? $v : '–';

$ortZeile = trim($plz . ' ' . $ort);

$subjectText = 'Neue Anfrage: ' . $leistung . ' (' . $ortZeile . ') – ' . $name;
$subject     = mb_encode_mimeheader(mb_substr($subjectText, 0, 160), 'UTF-8');

$body  = "Neue Kontaktanfrage über igs-bodenwerk.de\n";
$body .= str_repeat('=', 46) . "\n\n";

$body .= "KONTAKT\n";
$body .= "Name:     {$name}\n";
$body .= "E-Mail:   {$email}\n";
$body .= 'Telefon:  ' . $fallback($phone) . "\n";
$body .= "Ort:      {$ortZeile}\n\n";

$body .= "PROJEKT\n";
$body .= "Leistung:         {$leistung}\n";
$body .= 'Objektart:        ' . $fallback($objektart) . "\n";
$body .= 'Fläche:           ' . $fallback($flaeche) . "\n";
$body .= 'Aktueller Belag:  ' . $fallback($istBelag) . "\n";
$body .= 'Gewünschter Belag:' . ' ' . $fallback($wunschBelag) . "\n";
$body .= 'Zeitrahmen:       ' . $fallback($zeitrahmen) . "\n";
$body .= 'Material:         ' . $fallback($material) . "\n";
$body .= 'Muster gewünscht: ' . ($muster ? 'ja' : 'nein') . "\n\n";

$body .= "NACHRICHT\n";
$body .= ($message !== '' ? $message : '– keine Nachricht hinterlassen –') . "\n\n";

$body .= str_repeat('-', 46) . "\n";
$body .= 'Eingegangen am ' . date('d.m.Y \u\m H:i') . " Uhr\n";
$body .= "Datenschutzerklärung wurde beim Absenden bestätigt.\n";

$headers   = [];
$headers[] = 'From: Website Kontaktformular <noreply@igs-bodenwerk.de>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = mail($recipient, $subject, $body, implode("\r\n", $headers));

redirect($sent ? 'success' : 'error');
