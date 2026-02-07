<?php
// api/chat_logic.php
header('Content-Type: application/json');

// 1. Get the Input (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$question = $input['question'] ?? '';
$context = $input['context'] ?? '';

// 2. Simple Logic (The "Fake" AI for testing)
// later we will replace this with real Gemini/Ollama API code

$answer = "I am a simple AI. You asked: '$question'. ";

if (strpos(strtolower($question), 'hello') !== false) {
    $answer = "Hello! I am ready to help you with this lesson.";
} elseif (empty($context)) {
    $answer .= "However, I don't see any lesson text to analyze.";
} else {
    $answer .= "I see the lesson text! It is " . strlen($context) . " characters long. I can help answer questions about it.";
}

// 3. Send Back JSON
echo json_encode(['answer' => $answer]);
?>