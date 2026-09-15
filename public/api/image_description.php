<?php
require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

require_login();

if (!defined('HF_API_TOKEN') || !HF_API_TOKEN || HF_API_TOKEN === 'PASTE_YOUR_HF_TOKEN_HERE') {
    respond(['error' => 'AI description is not configured.'], 503);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    respond(['error' => 'No image was uploaded.'], 400);
}

$file = $_FILES['image'];

if ($file['size'] <= 0 || $file['size'] > 10 * 1024 * 1024) {
    respond(['error' => 'Image must be smaller than 10 MB.'], 400);
}

$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

$mime = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowed, true)) {
    respond(['error' => 'Photo must be a JPEG, PNG, or WEBP image.'], 400);
}

$imageData = base64_encode(file_get_contents($file['tmp_name']));
$dataUrl = 'data:' . $mime . ';base64,' . $imageData;

$payload = [
    'model' => 'google/gemma-3-4b-it:deepinfra',
    'messages' => [[
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => 'Describe only what is visibly present in this tree image. Return one concise factual sentence for a citizen hazard report. Mention visible tree condition or damage when clearly visible. Do not infer species, cause, danger level, or give advice.',
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUrl,
                ],
            ],
        ],
    ]],
    'max_tokens' => 100,
    'stream' => false,
];

$ch = curl_init('https://router.huggingface.co/v1/chat/completions');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HF_API_TOKEN,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    respond(['error' => 'Could not connect to the AI service.'], 502);
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
    $providerError = trim((string) ($data['error']['message'] ?? $data['error'] ?? ''));

    respond([
        'error' => 'AI description is currently unavailable.',
        'debug_http_code' => $httpCode,
        'debug_message' => $providerError ?: 'Unknown Hugging Face error.',
    ], 502);
}

$description = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

if (!$description) {
    respond(['error' => 'AI did not return a description.'], 502);
}

respond(['success' => true, 'description' => $description]);