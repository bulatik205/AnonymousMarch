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

file_put_contents("log.log", $json_response);

$isCallback = isset($update['callback_query']);
$userData = $isCallback ? $update['callback_query']['from'] : $update['message']['from'];
$chatId = $isCallback ? $userData['id'] : $update['message']['chat']['id'];

if (!isset($userData['id'])) {
    error_log("Ошибка: нет ID пользователя. " . json_encode($update));
    exit;
}

define("USER_ID", $userData['id']);
define("CHAT_ID", $chatId);
define("FIRST_NAME", $userData['first_name'] ?? "пользователь");
define("SEND_MESSAGE_URL", "https://api.telegram.org/bot" . API_BOT_TOKEN . "/sendMessage");
define("SEND_PHOTO_URL", "https://api.telegram.org/bot" . API_BOT_TOKEN . "/sendPhoto");

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

$keyboard = [
    ['🪭 Мои поздравления'],
    ['🍁 Профиль', '💫 Статистика']
];

if (isset($userInput) && $userInput == "❌ Отменить") {
    $setNewStage = $userService->setUserStage("await");

    $confirmData = [
        'chat_id' => CHAT_ID,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'input_field_placeholder' => 'Выберите действие'
        ]),
        'text' => "❌ Поздравление отменено!"
    ];
    $confirmService = new CurlService($confirmData, SEND_MESSAGE_URL);
    $confirmService->send();

    exit;
}

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
                ['text' => '🥷 Анонимно', 'callback_data' => 'anonymous']
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
                    $keyboard = [
                        ['❌ Отменить']
                    ];
                    $preparedData['reply_markup'] = json_encode([
                        'keyboard' => $keyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        'input_field_placeholder' => 'Выберите действие'
                    ]);
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
            exit;
    }
}

if (isset($update['callback_query']['data'])) {
    $callbackData = $update['callback_query']['data'];
    $callbackFromId = $update['callback_query']['from']['id'];

    $parts = explode(":", $userStage);

    if (count($parts) >= 3 && $parts[0] === 'type') {
        $recipientTelegramId = $parts[1];
        $userInput = $parts[2];

        $preparedData = [
            'chat_id' => $recipientTelegramId,
            'parse_mode' => 'HTML'
        ];

        $emoji = ["🌹", "🪻", "🌼", "🪷", "🌺", "💮", "🌷", "💐", "🌸"];
        $randomEmoji = $emoji[random_int(0, count($emoji) - 1)];

        if (strlen($userInput) > 1500) {
            $userInput = mb_substr($userInput, 0, 1500, "UTF-8");
        }

        $saveCongratulations = $userService->saveCongratulations($userInput, USER_ID, $recipientTelegramId, $callbackData);

        if (!$saveCongratulations) {
            $preparedData['chat_id'] = USER_ID;
            $preparedData['text'] = "Ошибка сервера. Попробуйте позже";

            $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
            $result = $curlService->send();
            exit;
        }

        $preparedData['text'] = "<b><i>💞 Новое поздравление!</i></b>\n\n";
        $preparedData['text'] .= "<blockquote>" . $randomEmoji . " " . htmlspecialchars($userInput) . "</blockquote>";

        switch ($callbackData) {
            case 'anonymous':
                $preparedData['text'] .= "\n\n<b>💘 Аноним</b>";
                break;
            case 'visible':
                $senderName = $update['callback_query']['from']['first_name'] ?? 'пользователь';
                $senderId = $callbackFromId;
                $preparedData['text'] .= "\n\n💘 <b><a href='tg://user?id={$senderId}'>{$senderName}</a></b>";
                break;
        }

        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
        $result = $curlService->send();

        $confirmData = [
            'chat_id' => $callbackFromId,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
                'input_field_placeholder' => 'Выберите действие'
            ]),
            'text' => "{$randomEmoji} Поздравление успешно отправлено!"
        ];
        $confirmService = new CurlService($confirmData, SEND_MESSAGE_URL);
        $confirmService->send();

        $userService->setUserStage('await');
    }

    exit;
}

if (isset($update['message']['text'])) {
    $text = $update['message']['text'];

    switch ($text) {
        case '🪭 Мои поздравления':
            $preparedData['text'] = "Ваши отправленные поздравления:";
            break;

        case '🎀 Профиль':
            $preparedData['text'] = "Ваш профиль:\nID: " . USER_ID . "\nИмя: " . FIRST_NAME;
            break;

        case '💫 Статистика':
            $preparedData['text'] = "Как пользоваться ботом...";
            break;
    }

    if (isset($preparedData['text'])) {
        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
        $curlService->send();
    }
}
exit;
?>