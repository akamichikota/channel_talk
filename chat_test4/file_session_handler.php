<?php
function startFileSession($session_id, $userId, $userChatId) {
    $sessionData = [
        'user_id' => $userId,
        'start_time' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
    file_put_contents(__DIR__ . "/sessions/{$session_id}.json", json_encode($sessionData));
    notifyChannelTalk($session_id, $userChatId, "アンケートが開始されました。");
    return ['success' => true, 'session_id' => $session_id];
}

function updateFileSession($sessionId, $step) {
    $sessionFile = __DIR__ . "/sessions/{$sessionId}.json";
    if (!file_exists($sessionFile)) {
        return ['success' => false, 'message' => 'Session does not exist'];
    }
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    $sessionData['last_step'] = $step;
    $sessionData['last_update'] = date('Y-m-d H:i:s');

    file_put_contents($sessionFile, json_encode($sessionData));
    return ['success' => true];
}

function endFileSession($sessionId, $userChatId) {
    $sessionFile = __DIR__ . "/sessions/{$sessionId}.json";
    if (!file_exists($sessionFile)) {
        return ['success' => false, 'message' => 'Session does not exist'];
    }
    $sessionData = json_decode(file_get_contents($sessionFile), true);
    $sessionData['status'] = 'completed';
    $sessionData['end_time'] = date('Y-m-d H:i:s');

    file_put_contents($sessionFile, json_encode($sessionData));
    
    // セッションファイルの削除は行わない
    $notifyResult = notifyChannelTalk($sessionId, $userChatId, "アンケートが終了しました。");
    if (!$notifyResult['success']) {
        error_log("Failed to notify Channel Talk: " . $notifyResult['message']);
    }
    return ['success' => true];
}

function notifyChannelTalk($session_id, $userChatId, $message) {
    $url = "https://api.channel.io/open/v5/user-chats/{$userChatId}/messages";
    $headers = [
        'Content-Type: application/json',
        'X-Access-Key: 661b221782915c8bc762',
        'X-Access-Secret: 3580f8e89e85879c260ab394d1779035'
    ];

    $fullMessage = "セッションID {$session_id}: {$message}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["blocks" => [["type" => "text", "value" => $fullMessage]], "options" => ["actAsManager", "private"]]));
    $response = curl_exec($ch);
    if (!$response) {
        return ['success' => false, 'message' => 'cURL Error: ' . curl_error($ch)];
    }
    $responseDecoded = json_decode($response, true);
    if (isset($responseDecoded['error'])) {
        return ['success' => false, 'message' => 'API Error: ' . $responseDecoded['message']];
    }
    curl_close($ch);
    return ['success' => true];
}
?>