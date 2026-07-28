<?php
require_once __DIR__ . '/config/mail.php';

$payload = json_encode([
    'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
    'to'          => [['email' => MAIL_FROM_EMAIL]],
    'subject'     => 'AlgoNest API Test',
    'textContent' => 'Brevo API is working correctly.',
]);

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json',
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "CURL error: $curlError\n";
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo "SUCCESS (HTTP $httpCode): $response\n";
} else {
    echo "FAILED (HTTP $httpCode): $response\n";
}
