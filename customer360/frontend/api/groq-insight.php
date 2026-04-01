<?php
/**
 * Groq insight proxy for advertiser-style segment profiling.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$segment = $payload['segment'] ?? null;
if (!$segment || !is_array($segment)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing segment payload']);
    exit;
}

$cooldownSeconds = 20;
$lastInsightAt = $_SESSION['groq_last_insight_at'] ?? 0;
if (time() - (int) $lastInsightAt < $cooldownSeconds) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Please wait before requesting another AI profile.',
        'retry_after' => $cooldownSeconds - (time() - (int) $lastInsightAt),
    ]);
    exit;
}

$groqKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '') ?: loadEnvValue('GROQ_API_KEY');
if (!$groqKey) {
    echo json_encode([
        'success' => true,
        'source' => 'fallback',
        'profile' => heuristicProfile($segment),
    ]);
    exit;
}

$prompt = buildPrompt($segment);
$body = [
    'model' => 'llama3-8b-8192',
    'temperature' => 0.4,
    'messages' => [
        ['role' => 'system', 'content' => 'You infer advertiser-style customer profiles from segment statistics. Return strict JSON only.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $groqKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Groq request failed: ' . $curlError]);
    exit;
}

$decoded = json_decode($response, true);
$content = $decoded['choices'][0]['message']['content'] ?? null;
$profile = json_decode($content ?? '', true);

if ($httpCode < 200 || $httpCode >= 300 || !is_array($profile)) {
    echo json_encode([
        'success' => true,
        'source' => 'fallback',
        'profile' => heuristicProfile($segment),
    ]);
    exit;
}

$_SESSION['groq_last_insight_at'] = time();

echo json_encode([
    'success' => true,
    'source' => 'groq',
    'profile' => $profile,
]);
exit;

function loadEnvValue(string $key): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = dirname(__DIR__, 2) . '/backend/.env';
        if (is_file($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$envKey, $envValue] = explode('=', $line, 2);
                $env[trim($envKey)] = trim($envValue);
            }
        }
    }
    return $env[$key] ?? '';
}

function buildPrompt(array $segment): string {
    $json = json_encode($segment, JSON_PRETTY_PRINT);
    return <<<PROMPT
Given this customer segment payload:

$json

Infer an advertiser-style profile and return strict JSON with this shape:
{
  "headline": "short title",
  "lifestyle": "1 paragraph",
  "buying_personality": "1 paragraph",
  "churn_risk": {"level": "Low|Medium|High", "reason": "1 sentence"},
  "messaging_angles": ["angle 1", "angle 2", "angle 3"],
  "channels": ["channel 1", "channel 2", "channel 3"],
  "offers": ["offer 1", "offer 2", "offer 3"]
}
PROMPT;
}

function heuristicProfile(array $segment): array {
    $name = $segment['name'] ?? 'Customer Segment';
    $pct = $segment['customer_pct'] ?? 0;
    $avgFrequency = $segment['avg_frequency'] ?? 0;
    $avgMonetary = $segment['avg_monetary'] ?? 0;
    $risk = stripos($name, 'risk') !== false || stripos($name, 'hibernat') !== false || stripos($name, 'lost') !== false ? 'High' : ($avgFrequency >= 5 ? 'Low' : 'Medium');

    return [
        'headline' => $name . ' Audience Snapshot',
        'lifestyle' => "This group makes up about {$pct}% of the customer base and shows a spending level around " . number_format((float) $avgMonetary, 2) . ". Their pattern suggests a recognizable behavioural cluster rather than one-off buyers.",
        'buying_personality' => "They tend to respond to practical value signals, repeat purchase triggers, and category familiarity. Their purchase frequency of {$avgFrequency} suggests " . ($avgFrequency >= 5 ? 'habit-driven buying behaviour.' : 'a segment that still needs stronger reactivation or retention prompts.'),
        'churn_risk' => [
            'level' => $risk,
            'reason' => $risk === 'High' ? 'The segment label and engagement pattern suggest weaker recent activity and higher drop-off risk.' : 'The observed frequency and value profile indicates a segment that still has recoverable engagement.',
        ],
        'messaging_angles' => [
            'Lead with relevance to their most likely category preferences.',
            'Use offer framing that matches their observed spend level.',
            'Reinforce habit, exclusivity, or urgency depending on segment maturity.',
        ],
        'channels' => ['Email', 'WhatsApp', 'Retargeting ads'],
        'offers' => [
            'Personalized bundle recommendations',
            'Time-bound loyalty or comeback offer',
            'Segment-specific product spotlight',
        ],
    ];
}
