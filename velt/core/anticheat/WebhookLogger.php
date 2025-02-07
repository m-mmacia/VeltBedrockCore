<?php

namespace velt\core\anticheat;

use pocketmine\Server;

class WebhookLogger {
    private static $webhookUrl = "webhookHere";

    public static function sendAlert(string $message): void {
        $data = json_encode(["content" => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::$webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    }
}
