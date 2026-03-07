<?php
class CurlService
{
    public array $preparedData;
    private string $telegramApiLink;

    public function __construct(array $preparedData, string $telegramApiLink)
    {
        $this->preparedData = $preparedData;
        $this->telegramApiLink = $telegramApiLink;
    }

    public function send(): void
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->telegramApiLink);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->preparedData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);

            if (curl_error($ch)) {
                error_log(curl_error($ch) . " - " . curl_errno($ch));
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function sendPhoto($photoPath, $caption = '')
    {
        try {
            if (!file_exists($photoPath)) {
                error_log("Фото не найдено: " . $photoPath);
                return false;
            }

            $photo = new CURLFile($photoPath);

            $keyboard = [
                ['🪭 Мои поздравления'],
                ['🍁 Профиль', '💫 Статистика']
            ];

            $postFields = [
                'chat_id' => $this->preparedData['chat_id'],
                'photo' => $photo,
                'caption' => $caption,
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                    'input_field_placeholder' => 'Выберите действие'
                ]),
                'parse_mode' => 'HTML'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->telegramApiLink);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            if (curl_error($ch)) {
                error_log("Curl error: " . curl_error($ch));
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("Error sending photo: " . $e->getMessage());
            return false;
        }
    }
}
