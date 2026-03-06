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
}
