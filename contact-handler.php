<?php
declare(strict_types=1);

$recipient = 'info@igs-bodenwerk.de';
$redirectBase = 'index.html';

function redirect(string $status) {
    global $redirectBase;
    header('Location: ' . $redirectBase . '?contact=' . $status . '#contact');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('error');
}

// Honeypot: hidden field that only bots fill in. Pretend success, send nothing.
if (!empty($_POST['website'] ?? '')) {
    redirect('success');
}

function cleanField(string $value): string {
    // Strip line breaks so field values can never be used for header injection.
    return trim(str_replace(["\r", "\n"], '', $value));
}

$name    = cleanField($_POST['name'] ?? '');
$email   = cleanField($_POST['email'] ?? '');
$phone   = cleanField($_POST['phone'] ?? '');
$service = cleanField($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('error');
}

$subject = mb_encode_mimeheader('Neue Anfrage über die Website von ' . $name, 'UTF-8');

$body  = "Neue Kontaktanfrage über igs-bodenwerk.de\n\n";
$body .= "Name: {$name}\n";
$body .= "E-Mail: {$email}\n";
$body .= 'Telefon: ' . ($phone !== '' ? $phone : '-') . "\n";
$body .= 'Gewünschte Leistung: ' . ($service !== '' ? $service : '-') . "\n\n";
$body .= "Nachricht:\n{$message}\n";

$headers   = [];
$headers[] = 'From: Website Kontaktformular <noreply@igs-bodenwerk.de>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = mail($recipient, $subject, $body, implode("\r\n", $headers));

redirect($sent ? 'success' : 'error');
