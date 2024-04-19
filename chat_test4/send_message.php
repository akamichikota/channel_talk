<?php
// エラーレポーティングを有効に
ini_set('display_errors', 1);
error_reporting(E_ALL);

$accessKey = 'myAccessKey';
$accessSecret = 'myAccessSecret';

// POSTリクエストからデータを受け取る
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'] ?? '';
$userChatId = $data['userChatId'] ?? '';
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$firstMessage = $data['firstMessage'] ?? '';

// 必要なデータがすべて存在するか確認
if (empty($userId) || empty($userChatId) || empty($firstMessage)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// ユーザー情報更新のためのAPIエンドポイント
$updateUserUrl = "https://api.channel.io/open/v5/users/{$userId}";

// ユーザー情報を更新するcURLセッションの初期化と実行
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $updateUserUrl,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Access-Key: ' . $accessKey,
        'X-Access-Secret: ' . $accessSecret
    ],
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => json_encode([
        'profile' => [
            'name' => $name,
            'email' => $email,
            'firstMessage' => $firstMessage
        ]
    ]),
    CURLOPT_RETURNTRANSFER => true
]);

$updateResponse = curl_exec($ch);
if (!$updateResponse) {
    echo json_encode(['error' => true, 'message' => 'Failed to update user information - cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// ユーザー情報更新のレスポンスを確認
$updateResponseDecoded = json_decode($updateResponse, true);
if (isset($updateResponseDecoded['error'])) {
    echo json_encode(['error' => true, 'message' => 'Failed to update user information - API response: ' . json_encode($updateResponseDecoded)]);
    curl_close($ch);
    exit;
}


// APIエンドポイントの設定
$messageUrl = "https://api.channel.io/open/v5/user-chats/{$userChatId}/messages";

// ヘッダーとデータの設定
$headers = [
    'Content-Type: application/json',
    'X-Access-Key: ' . $accessKey,
    'X-Access-Secret: ' . $accessSecret,
];
$postData = json_encode([
    'blocks' => [
        [
            'type' => 'text',
            'value' => $firstMessage,
        ],
    ],
]);

// cURLセッションの初期化
$ch = curl_init($messageUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// リクエストの実行
$response = curl_exec($ch);

// エラーチェック
if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'cURL Error: ' . curl_error($ch)]);
    exit;
}

// レスポンスのデコード
$responseData = json_decode($response, true);
if (isset($responseData['error'])) {
    echo json_encode(['success' => false, 'message' => 'API Error: ' . $responseData['message']]);
    exit;
}

// 成功レスポンス
echo json_encode(['success' => true, 'message' => 'Message sent successfully']);

// cURLセッションの終了
curl_close($ch);
