<?php
if (!file_exists("config/config.php")) {
    error_log("File config/config.php dont exist");
    exit;
}

if (!file_exists("modules/service/CurlService.php")) {
    error_log("File modules/service/CurlService.php dont exist");
    exit;
}

if (!file_exists("modules/user/UserService.php")) {
    error_log("File modules/user/UserService.php dont exist");
    exit;
}

require "config/config.php";
require "modules/service/CurlService.php";
require "modules/user/UserService.php";

$update = json_decode(file_get_contents('php://input'), true);
$json_response = json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (!isset($update['message']['from']['id']) || !isset($update['message']['chat']['id'])) {
    error_log("Ошибка: нет нужных полей. " . $update);
    exit;
}

define("USER_ID", $update['message']['from']['id']);
define("CHAT_ID", $update['message']['chat']['id']);
define("FIRST_NAME", $update['message']['from']['first_name'] ?? "пользователь");
define("SEND_MESSAGE_URL", "https://api.telegram.org/bot" . API_BOT_TOKEN . "/sendMessage");
define("SEND_PHOTO_URL", "https://api.telegram.org/bot" . API_BOT_TOKEN . "/sendPhoto");

file_put_contents("log.log", $json_response);

$userRepository = [
    'id' => USER_ID,
    'first_name' => $update['message']['from']['first_name'] ?? null,
    'last_name' => $update['message']['from']['last_name'] ?? null
];

$preparedData = [
    'chat_id' => CHAT_ID,
    'parse_mode' => 'HTML'
];

$userService = new UserService($userRepository, $pdo);

if (!$userService->ensureUserExists()) {
    $preparedData['text'] = "Ошибка сервера. Попробуйте позже";
    $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
    $curlService->send();
    exit;
}

$getStage = $userService->getStage();

if (!$getStage['success']) {
    $preparedData['text'] = "Ошибка сервера. Попробуйте позже";
    $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
    $curlService->send();
    exit;
}

$userStage = $getStage['fields']['stage'];
$userInput = $update['message']['text'];

if (
    isset($userInput)
    && str_starts_with($userStage, 'input:')
    && !str_starts_with($userInput, '/')
) {
    $parts = explode(":", $userStage);
    $recipientTelegramId = $parts[1];

    if (strlen($userInput) > 1500) {
        $userInput = mb_substr($userInput, 0, 1500, "UTF-8");
    }

    $setNewStage = $userService->setUserStage("type:" . $recipientTelegramId . ":" . $userInput);
    if (!$setNewStage) {
        $preparedData['text'] = "Ошибка сервера. Попробуйте позже." . $setNewStage;
        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
        $curlService->send();
        exit;
    }

    $preparedData['text'] = "<b><i>🏮 Как отправить поздравление?</i></b>";
    $preparedData['reply_markup'] = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '🥷 Анонимно', 'callback_data' => 'anonymouse']
            ],
            [
                
                ['text' => '👀 С моим именем', 'callback_data' => 'visible']
            ]
        ]
    ]);
    $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
    $curlService->send();
    exit;
}

if (isset($userInput) && str_starts_with($userInput, '/')) {
    $parts = explode(" ", $userInput);
    $command = $parts[0];
    $queryParam = isset($parts[1]) ? substr($parts[1], 0, 50) : null;

    switch ($command) {
        case '/start':
            if ($queryParam != null) {
                $recipientRepository = $userService->getRecipientRepository($queryParam);

                if ($recipientRepository['success']) {
                    $recipientFirstName = isset($recipientRepository['fields']['first_name']) ? $recipientRepository['fields']['first_name'] : $recipientRepository['fields']['telegram_id'];
                    $preparedData['text'] = "<b><i>🌸 Отправь " . $recipientFirstName . " поздравление на 8 марта</i>\n\n<blockquote>🌹 Введи свой текст ниже:</blockquote></b>";
                    $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
                    $curlService->send();

                    $setNewStage = $userService->setUserStage("input:" . $recipientRepository['fields']['telegram_id']);
                    if (!$setNewStage) {
                        $preparedData['text'] = "Ошибка сервера. Попробуйте позже." . $setNewStage;
                        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
                        $curlService->send();
                    }
                    exit;
                }
            }

            $caption = "<b>💐 Привет, " . FIRST_NAME . "!\n\n<blockquote>🌸 В этом боте можно поздравить кого угодно с 8 марта!</blockquote>\n<blockquote>🔥 Заходи по ссылке, которую тебе отправили или отправь свою ссылку!</blockquote>\n\n<i>💋 Твоя ссылка:</i> <a href='https://t.me/march_v_bot?start=" . USER_ID . "'>https://t.me/march_v_bot?start=" . USER_ID . "</a></b>";
            $curlService = new CurlService($preparedData, SEND_PHOTO_URL);
            $curlService->sendPhoto(__DIR__ . "/source/images/start.png", $caption);

            if (!$curlService) {
                $preparedData['text'] = "Произошла ошибка";
                $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
                $curlService->send();
            }
    }
}

$preparedData['text'] = "Привет!";
$curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
$curlService->send();

exit;
