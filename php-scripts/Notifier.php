<?php
declare(strict_types=1);

class Notifier
{
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = getenv('WA_PHONE_NUMBER_ID') ?: '';
        $this->accessToken = getenv('WA_ACCESS_TOKEN') ?: '';
    }

    /**
     * Send a WhatsApp text message via Meta Cloud API.
     *
     * @param string $to Recipient phone number (e.g. 6591234567, no + prefix).
     * @param string $message The message body.
     * @return bool True on HTTP 200, false otherwise.
     */
    public function sendWhatsApp(string $to, string $message): bool
    {
        if ($this->phoneNumberId === '' || $this->accessToken === '') {
            error_log('[nirix-notify] WA_PHONE_NUMBER_ID or WA_ACCESS_TOKEN not set');
            return false;
        }

        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        if ($ch === false) {
            error_log('[nirix-notify] curl_init failed');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            error_log('[nirix-notify] curl error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[nirix-notify] WhatsApp API returned HTTP {$httpCode}: {$response}");
            return false;
        }

        return true;
    }
}
