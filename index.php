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
define("SEND_MESSAGE_URL", "https://api.telegram.org/bot" . API_BOT_TOKEN . "/sendMessage");

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

$preparedData['text'] = "Привет!";
$curlService = new CurlService($preparedData, SEND_MESSAGE_URL);
$curlService->send();

exit;