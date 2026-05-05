private function tanyaGemini(string $prompt): string|false
{
    $apiKey = env('GEMINI_API_KEY');
    $apiUrl = env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');

    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4096],
    ];

    $ch = curl_init($apiUrl . '?key=' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    \Illuminate\Support\Facades\Log::info('Gemini API', [
        'code' => $code,
        'response' => substr($response, 0, 500),
        'key_prefix' => substr($apiKey, 0, 10),
        'url' => $apiUrl,
    ]);

    if (!$response || $code !== 200) return false;

    $json = json_decode($response, true);
    return $json['candidates'][0]['content']['parts'][0]['text'] ?? false;
}
