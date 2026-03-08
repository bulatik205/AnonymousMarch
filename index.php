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
    ['🍁 Профиль', '💫 Статистика'],
    ['🪻 Поделиться']
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

if (isset($update['callback_query']['data']) && (str_starts_with($update['callback_query']['data'], "visible") || str_starts_with($update['callback_query']['data'], "anonymous"))) {
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

        $emoji = ["🌹", "🪻", "🪷", "🌺", "💮", "🌷", "💐", "🌸"];
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

        $congratulationId = $pdo->lastInsertId();

        $reactionEmojis = [["❤️‍🩹", "❤️‍🔥", "🩷"][random_int(0, 2)], ["💜", "🧡", "❤️"][random_int(0, 2)], "👎"];
        $reactionButtons = [];

        foreach ($reactionEmojis as $e) {
            $reactionButtons[] = ['text' => $e, 'callback_data' => "reaction:{$congratulationId}:{$e}"];
        }

        $preparedData['reply_markup'] = json_encode([
            'inline_keyboard' => array_chunk($reactionButtons, 3)
        ]);

        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
        $result = $curlService->send();

        $confirmEmoji = ["❤️‍🩹", "❤️‍🔥", "🩷", "💜", "🧡", "❤️"][random_int(0, 5)];
        $confirmData = [
            'chat_id' => $callbackFromId,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
                'input_field_placeholder' => 'Выберите действие'
            ]),
            'text' => "{$confirmEmoji} Поздравление успешно отправлено!"
        ];
        $confirmService = new CurlService($confirmData, SEND_MESSAGE_URL);
        $confirmService->send();

        $userService->setUserStage('await');
    }

    exit;
}

# обработчик реакций
if (isset($update['callback_query']['data']) && str_starts_with($update['callback_query']['data'], 'reaction:')) {
    $callbackData = $update['callback_query']['data'];
    $callbackFromId = $update['callback_query']['from']['id'];
    $callbackFromName = $update['callback_query']['from']['first_name'] ?? 'Пользователь';
    $messageId = $update['callback_query']['message']['message_id'];
    $chatId = $update['callback_query']['message']['chat']['id'];

    $parts = explode(':', $callbackData);
    $congratulationId = $parts[1] ?? 0;
    $reactionEmoji = $parts[2] ?? '❤️';

    if (!$congratulationId) {
        exit;
    }

    $congratulation = $userService->getCongratulationById($congratulationId);

    if (!$congratulation) {
        exit;
    }

    $toId = ($congratulation['recipient_id'] == $callbackFromId) ? $congratulation['from_id'] : $congratulation['recipient_id'];

    error_log("toId: " . $toId . " from callbackFromId: " . $callbackFromId);

    $existingReaction = $userService->getUserReaction($congratulationId, $callbackFromId);

    if ($existingReaction) {
        $answerData = [
            'callback_query_id' => $update['callback_query']['id'],
            'text' => '❌ Ты уже поставил реакцию!',
            'show_alert' => true
        ];
        $curlService = new CurlService($answerData, "https://api.telegram.org/bot" . API_BOT_TOKEN . "/answerCallbackQuery");
        $curlService->send();
        exit;
    }

    $saveResult = $userService->saveReaction($congratulationId, $callbackFromId, $toId, $reactionEmoji);

    if (!$saveResult['success']) {
        exit;
    }

    $newText = "💞 " . $callbackFromName . " поставил реакцию: " . $reactionEmoji;

    $editData = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $newText,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => []])
    ];

    $curlService = new CurlService($editData, "https://api.telegram.org/bot" . API_BOT_TOKEN . "/editMessageText");
    $curlService->send();

    $answerData = [
        'callback_query_id' => $update['callback_query']['id'],
        'text' => "✅ Готово",
        'show_alert' => false
    ];
    $curlService = new CurlService($answerData, "https://api.telegram.org/bot" . API_BOT_TOKEN . "/answerCallbackQuery");
    $curlService->send();

    if ($toId != $callbackFromId) {
        $notifyText = "💌 " . $callbackFromName . " поставил реакцию: " . $reactionEmoji;

        $notifyData = [
            'chat_id' => $toId,
            'text' => $notifyText,
            'parse_mode' => 'HTML'
        ];

        $curlService = new CurlService($notifyData, SEND_MESSAGE_URL);
        $curlService->send();
    }

    exit;
}

if (isset($update['message']['text'])) {
    $text = $update['message']['text'];

    switch ($text) {
        case '🪭 Мои поздравления':
            $congratulations = $userService->getCongratulations();

            if (!$congratulations['success'] || empty($congratulations['fields'])) {
                $preparedData['text'] = "💜 У тебя пока нет поздравлений";
                break;
            }

            $preparedData['text'] = "💜 <b>Твои поздравления:</b>\n\n";

            foreach ($congratulations['fields'] as $c) {
                if ($c['from_id'] == USER_ID) {
                    $arrow = "Отправлено";
                    $name = $c['recipient_name'] ?? $c['recipient_id'];
                    $action = "<b>кому:</b> {$name}";
                } else {
                    $arrow = "Получено";
                    $name = $c['from_name'] ?? $c['from_id'];
                    $action = "<b>от:</b> {$name}";
                }

                $anon = ($c['is_anonym'] == 'visible') ? "👀 [С именем]" : "🥷 [Анонимно]";
                $text = htmlspecialchars(mb_substr($c['text'], 0, 30)) . (mb_strlen($c['text']) > 30 ? '…' : '');
                $date = date("d.m H:i", strtotime($c['created_at']));

                $preparedData['text'] .= "<blockquote><code>{$anon} {$arrow} {$action}: \"{$text}\" [{$date}]</code></blockquote>\n";
            }
            break;

        case '🍁 Профиль':
            $resopitory = $userService->getUser();
            $countSendCongratulations = $userService->getCountSendCongratulations();
            $countTakedCongratulations = $userService->getCountTakedCongratulations();
            $preparedData['text'] = "<b>🥷 Профиль " . FIRST_NAME . "\n\n";
            $preparedData['text'] .= "<blockquote><i>🔑 ID:</i> " . USER_ID . "</blockquote>\n";
            $preparedData['text'] .= "<blockquote>🤍 <i>Отправлено поздравлений: " . $countSendCongratulations . "</i></blockquote>\n";
            $preparedData['text'] .= "<blockquote>💜 <i>Получено поздравлений: " . $countTakedCongratulations . "</i></blockquote></b>";
            break;

        case '🪻 Поделиться':
            $userLink = "https://t.me/march_v_bot?start=" . USER_ID;

            $preparedData['text'] = "🍀 <b>Твоя ссылка:</b>\n<code>" . $userLink . "</code>";
            $preparedData['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🍃 Поделиться', 'url' => 'https://t.me/share/url?url=' . urlencode($userLink) . "&text=\n🌹 Поздравь меня с 8 марта!"]
                    ]
                ]
            ]);
            break;

        # this case make with AI 
        case '💫 Статистика':
            $stats = $userService->getGlobalStats();

            if (!$stats['success']) {
                $preparedData['text'] = "🌸❌ Ой, статистика завяла... Попробуй позже";
                break;
            }

            $s = $stats['fields'];

            $preparedData['text'] = "🌷 <b>Статистика</b>\n\n";

            $preparedData['text'] .= "💐 <b>Букет цифр:</b>\n";
            $preparedData['text'] .= "<blockquote>";
            $preparedData['text'] .= "🌸 Поздравлений всего: <b>" . number_format($s['total_congratulations']) . "</b>\n";
            $preparedData['text'] .= "🌺 Пользователей: <b>" . number_format($s['total_users']) . "</b>\n";
            $preparedData['text'] .= "🌷 За сегодня: <b>" . $s['today_count'] . "</b>\n";
            $preparedData['text'] .= "</blockquote>\n";

            $preparedData['text'] .= "💮 <b>Как поздравляют:</b>\n";
            $preparedData['text'] .= "<blockquote>";
            $preparedData['text'] .= "🥷 Тайно (анонимно): <b>" . ($s['type_stats']['anonymous_count'] ?? 0) . "</b>\n";
            $preparedData['text'] .= "👀 Открыто (с именем): <b>" . ($s['type_stats']['named_count'] ?? 0) . "</b>\n";
            $preparedData['text'] .= "</blockquote>\n";

            if (!empty($s['top_senders'])) {
                $preparedData['text'] .= "🏵 <b>Больше поздравляют:</b>\n";
                $preparedData['text'] .= "<blockquote>";
                foreach (array_slice($s['top_senders'], 0, 5) as $index => $sender) {
                    $emoji = ["🌹", "🪻", "🌼", "🪷", "🌺"][$index] ?? "🌸";
                    $name = $sender['first_name'] ?? 'Пользователь';
                    if (!empty($sender['last_name'])) {
                        $name .= " " . mb_substr($sender['last_name'], 0, 1) . ".";
                    }
                    $preparedData['text'] .= "{$emoji} {$name} — <b>{$sender['sent_count']}</b>\n";
                }
                $preparedData['text'] .= "</blockquote>\n";
            }

            if (!empty($s['top_receivers'])) {
                $preparedData['text'] .= "🎀 <b>Больше получают:</b>\n";
                $preparedData['text'] .= "<blockquote>";
                foreach (array_slice($s['top_receivers'], 0, 5) as $index => $receiver) {
                    $emoji = ["💐", "🌺", "🪷", "🌻", "🌞"][$index] ?? "💮";
                    $name = $receiver['first_name'] ?? 'Красавица';
                    if (!empty($receiver['last_name'])) {
                        $name .= " " . mb_substr($receiver['last_name'], 0, 1) . ".";
                    }
                    $preparedData['text'] .= "{$emoji} {$name} — <b>{$receiver['received_count']}</b>\n";
                }
                $preparedData['text'] .= "</blockquote>";
            }

            $preparedData['text'] .= "\n🌷 <i>С 8 марта, дорогие!</i> 🌷";
            break;
    }

    if (isset($preparedData['text'])) {
        $curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
        $curlService->send();
    }
}
exit;
