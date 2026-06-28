<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * ReminderSender — pluggable multi-channel dispatcher.
 *
 * Email:     PHP mail() by default. Swap $mailer for PHPMailer/SwiftMailer/SMTP.
 * SMS:       Twilio-ready stub. Set TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM in .env.
 * WhatsApp:  wa.me deep-link (browser-open). For API sends set WABA_TOKEN in .env.
 */
class ReminderSender
{
    /**
     * @param  array<array{name:string,phone:string,email:string}> $recipients
     * @return array{sent:int,failed:int,errors:string[]}
     */
    public static function dispatch(
        string  $channel,
        array   $recipients,
        string  $message,
        ?string $subject      = null,
        ?string $attachPath   = null
    ): array {
        $sent = $failed = 0;
        $errors = [];

        foreach ($recipients as $r) {
            try {
                match ($channel) {
                    'email'     => self::sendEmail($r, $subject ?? 'Rent Reminder', $message, $attachPath),
                    'sms'       => self::sendSms($r, $message),
                    'whatsapp'  => self::sendWhatsapp($r, $message),
                };
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "{$r['name']}: " . $e->getMessage();
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }

    // ─── Email ───────────────────────────────────────────────────────────────

    private static function sendEmail(array $r, string $subject, string $body, ?string $attachPath): void
    {
        if (empty($r['email'])) throw new \RuntimeException('No email address');

        $to      = $r['email'];
        $from    = $_ENV['MAIL_FROM']      ?? 'rentops@localhost';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'RentOps';
        $boundary = md5(uniqid('', true));

        if ($attachPath && file_exists($attachPath)) {
            // multipart/mixed with attachment
            $filename    = basename($attachPath);
            $fileData    = chunk_split(base64_encode(file_get_contents($attachPath)));
            $contentType = mime_content_type($attachPath) ?: 'application/octet-stream';

            $headers  = "From: {$fromName} <{$from}>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $mimeBody  = "--{$boundary}\r\n";
            $mimeBody .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $mimeBody .= $body . "\r\n\r\n";
            $mimeBody .= "--{$boundary}\r\n";
            $mimeBody .= "Content-Type: {$contentType}; name=\"{$filename}\"\r\n";
            $mimeBody .= "Content-Transfer-Encoding: base64\r\n";
            $mimeBody .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $mimeBody .= $fileData . "\r\n";
            $mimeBody .= "--{$boundary}--";

            $ok = mail($to, $subject, $mimeBody, $headers);
        } else {
            $headers  = "From: {$fromName} <{$from}>\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $ok = mail($to, $subject, $body, $headers);
        }

        if (!$ok) throw new \RuntimeException('mail() returned false');
    }

    // ─── SMS (Twilio) ────────────────────────────────────────────────────────

    private static function sendSms(array $r, string $message): void
    {
        $sid   = $_ENV['TWILIO_SID']   ?? '';
        $token = $_ENV['TWILIO_TOKEN'] ?? '';
        $from  = $_ENV['TWILIO_FROM']  ?? '';

        if (!$sid || !$token || !$from) {
            // No Twilio configured — log only (UI handles wa.me / manual copy)
            error_log("[RentOps SMS] Would send to {$r['phone']}: " . substr($message, 0, 60));
            return;
        }

        $phone = '+91' . preg_replace('/\D/', '', $r['phone']);
        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_POSTFIELDS     => http_build_query(['From' => $from, 'To' => $phone, 'Body' => $message]),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            $err = json_decode($resp, true)['message'] ?? 'Unknown error';
            throw new \RuntimeException("Twilio: {$err}");
        }
    }

    // ─── WhatsApp (wa.me link — for API use WABA_TOKEN) ──────────────────────

    private static function sendWhatsapp(array $r, string $message): void
    {
        $wabaToken = $_ENV['WABA_TOKEN'] ?? '';

        if ($wabaToken) {
            // Meta Cloud API send
            $phone = '91' . preg_replace('/\D/', '', $r['phone']);
            $wabaId = $_ENV['WABA_PHONE_ID'] ?? '';
            $ch = curl_init("https://graph.facebook.com/v19.0/{$wabaId}/messages");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$wabaToken}", "Content-Type: application/json"],
                CURLOPT_POSTFIELDS     => json_encode([
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]),
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 400) {
                $err = json_decode($resp, true)['error']['message'] ?? 'Unknown error';
                throw new \RuntimeException("WhatsApp API: {$err}");
            }
        } else {
            // Fallback: log wa.me link (opened from UI per-recipient)
            $phone = preg_replace('/\D/', '', $r['phone']);
            error_log("[RentOps WA] https://wa.me/91{$phone}?text=" . rawurlencode($message));
        }
    }

    // ─── WhatsApp deep link (UI helper) ──────────────────────────────────────

    public static function waLink(string $phone, string $message): string
    {
        $phone = '91' . preg_replace('/\D/', '', $phone);
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}
