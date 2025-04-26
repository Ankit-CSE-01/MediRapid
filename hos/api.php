<?php
header('Content-Type: application/json');

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['role']) || !isset($data['prompt']) || empty($data['prompt'])) {
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

// Load configuration (you should store your API key in a config file outside web root)
require_once 'config.php'; // Create this file with your API key

// Validate API key
if (!defined('GROQ_API_KEY')) {
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

// Prepare the API request
$api_url = "https://api.groq.com/openai/v1/chat/completions";
$request_data = [
    "model" => "llama3-70b-8192",
    "messages" => [
        [
            "role" => "system",
            "content" => $data['role']
        ],
        [
            "role" => "user",
            "content" => $data['prompt']
        ]
    ],
    "temperature" => 0.7,
    "max_tokens" => 1000
];

// Initialize cURL
$ch = curl_init($api_url);

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . GROQ_API_KEY
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request_data),
    CURLOPT_TIMEOUT => 30 // 30 seconds timeout
]);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Handle errors
if (curl_errno($ch)) {
    echo json_encode(['error' => 'API connection error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Process response
if ($http_code !== 200) {
    echo json_encode(['error' => 'API request failed with status code ' . $http_code]);
    exit;
}

$response_data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid API response']);
    exit;
}

// Return the successful response
echo json_encode($response_data);
?>
