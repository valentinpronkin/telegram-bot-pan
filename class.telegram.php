<?php

class TelegramUpdate{


    /**
     * Parse responce from telegram bot webhook to associative array
     * 
     * @input   string  Telegram bot responce (JSON)
     * @return  array   Responce in form of associative array
     */
    static function parse($input) {
        return json_decode($input);
    }


    /**
     * 
     * Send request to a Telegram bot
     * 
     * @method  string  Method according to Teleram Bot API
     * @data    array   Data array
     */
    static function send($method, $data) {
        $url = "https://api.telegram.org/bot" . TG_TOKEN . "/" . $method;
    
        if (!$curld = curl_init()) {
            exit;
        }
        curl_setopt($curld, CURLOPT_POST, true);
        curl_setopt($curld, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curld, CURLOPT_URL, $url);
        curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curld);
        curl_close($curld);
        return $output;
    }
}

?>