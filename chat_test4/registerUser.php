<?php
// APIキーの設定
$accessKey = 'myAccessKey';
$accessSecret = 'myAccessSecret';

// リクエストボディからデータを取得
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'];

error_log("Received request with userID: " . $userId);

// チャットセッションを作成するためのAPIエンドポイント
$userChatsUrl = "https://api.channel.io/open/v5/users/{$userId}/user-chats";

// cURLセッションの初期化と実行
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userChatsUrl,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Access-Key: ' . $accessKey,
        'X-Access-Secret: ' . $accessSecret
    ],
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
if (!$response) {
    error_log('cURL Error: ' . curl_error($ch));  // cURLエラーのログ
    echo json_encode(['error' => true, 'message' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$responseDecoded = json_decode($response, true);
if (!isset($responseDecoded['userChat']['id'])) {
    error_log('Failed to create chat session or retrieve chat ID');  // チャットID取得失敗のログ
    echo json_encode(['error' => true, 'message' => 'Failed to create chat session or retrieve chat ID']);
    curl_close($ch);
    exit;
}

$userChatId = $responseDecoded['userChat']['id'];
error_log('User chat created with ID: ' . $userChatId);  // チャットセッション作成のログ


$inviteUrl = "https://api.channel.io/open/v5/user-chats/{$userChatId}/invite?botName=riku-individual&managerIds=433104";
$ch = curl_init($inviteUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Access-Key: ' . $accessKey,
        'X-Access-Secret: ' . $accessSecret
    ],
    CURLOPT_RETURNTRANSFER => true
]);

$inviteResponse = curl_exec($ch);
error_log('Invite response: ' . $inviteResponse);

$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (!$inviteResponse || $httpStatusCode != 200) {
    echo json_encode(['error' => true, 'message' => 'Failed to invite manager: HTTP Status Code: ' . $httpStatusCode]);
    curl_close($ch);
    exit;
}
$inviteResponseDecoded = json_decode($inviteResponse, true);
error_log('Manager invited successfully: ' . json_encode($inviteResponseDecoded));

if (!$inviteResponseDecoded || !isset($inviteResponseDecoded['userChat'])) {
    echo json_encode(['error' => true, 'message' => 'Failed to invite manager']);
    curl_close($ch);
    exit;
}


echo json_encode(['success' => true, 'userChatId' => $userChatId]);
curl_close($ch);
?>
