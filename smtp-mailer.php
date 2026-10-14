<?php
declare(strict_types=1);

/**
 * Minimaler SMTP-Versand ohne Fremdbibliothek (AUTH LOGIN; SSL, STARTTLS oder unverschlüsselt).
 * Reicht für das Kontaktformular völlig aus – reiner Text, ein Empfänger, keine Anhänge.
 */
final class SmtpMailer
{
    /** @var resource|null */
    private $sock = null;
    private string $lastError = '';

    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $encryption;

    /**
     * @param string $encryption 'ssl' (Port 465), 'tls' (STARTTLS, Port 587) oder 'none'
     */
    public function __construct(string $host, int $port, string $user, string $pass, string $encryption = 'ssl')
    {
        $this->host       = $host;
        $this->port       = $port;
        $this->user       = $user;
        $this->pass       = $pass;
        $this->encryption = $encryption;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        string $replyToEmail = '',
        string $replyToName = ''
    ): bool {
        $this->lastError = '';

        $remote  = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'SNI_enabled'      => true,
        ]]);

        $sock = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if ($sock === false) {
            $this->lastError = "Verbindung zu {$remote} fehlgeschlagen: {$errstr} ({$errno})";
            return false;
        }
        $this->sock = $sock;
        stream_set_timeout($sock, 20);

        $helo = 'EHLO ' . $this->domainOf($fromEmail);

        try {
            $this->expect(220);
            $this->command($helo, 250);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS fehlgeschlagen');
                }
                $this->command($helo, 250);
            }

            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($this->user), 334);
            $this->command(base64_encode($this->pass), 235);

            $this->command('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', 250);
            $this->command('DATA', 354);
            $this->write($this->buildMessage($fromEmail, $fromName, $to, $subject, $body, $replyToEmail, $replyToName) . "\r\n.");
            $this->expect(250);

            // QUIT ist Höflichkeit – die Mail ist bereits angenommen, Fehler hier ignorieren.
            try { $this->command('QUIT', 221); } catch (RuntimeException $e) {}

            return true;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } finally {
            fclose($sock);
            $this->sock = null;
        }
    }

    // ------------------------------------------------------------ Protokoll

    private function command(string $line, int $expected): string
    {
        $this->write($line);
        return $this->expect($expected);
    }

    private function write(string $data): void
    {
        if ($this->sock === null || fwrite($this->sock, $data . "\r\n") === false) {
            throw new RuntimeException('Senden an den SMTP-Server fehlgeschlagen');
        }
    }

    /** Liest eine (ggf. mehrzeilige) Antwort und prüft den Statuscode. */
    private function expect(int $code): string
    {
        $response = '';
        while ($this->sock !== null && ($line = fgets($this->sock, 1024)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        if ($response === '') {
            throw new RuntimeException('Keine Antwort vom SMTP-Server (Zeitüberschreitung)');
        }
        if ((int) substr($response, 0, 3) !== $code) {
            throw new RuntimeException('Unerwartete SMTP-Antwort (erwartet ' . $code . '): ' . trim($response));
        }
        return $response;
    }

    // ------------------------------------------------------------ Nachricht

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        string $replyToEmail,
        string $replyToName
    ): string {
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->address($fromName, $fromEmail),
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->domainOf($fromEmail) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        if ($replyToEmail !== '') {
            $headers[] = 'Reply-To: ' . $this->address($replyToName, $replyToEmail);
        }

        // Einheitliche CRLF-Zeilenenden, dann Quoted-Printable (hält Zeilen kurz und 7-Bit-sicher)
        $body    = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? '';
        $encoded = quoted_printable_encode($body);
        // Zeilen, die mit einem Punkt beginnen, verdoppeln (RFC 5321 "dot stuffing")
        $encoded = preg_replace('/^\./m', '..', $encoded) ?? $encoded;

        return implode("\r\n", $headers) . "\r\n\r\n" . $encoded;
    }

    private function address(string $name, string $email): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        $display = preg_match('/[^\x20-\x7E]/', $name)
            ? mb_encode_mimeheader($name, 'UTF-8', 'B')
            : '"' . addcslashes($name, '"\\') . '"';
        return $display . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? mb_encode_mimeheader($value, 'UTF-8', 'B') : $value;
    }

    private function domainOf(string $email): string
    {
        $domain = (string) preg_replace('/^.*@/', '', $email);
        return $domain !== '' ? $domain : 'localhost';
    }
}
