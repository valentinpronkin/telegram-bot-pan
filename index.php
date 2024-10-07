<?php

include_once ('config.php');
include_once ('class.db.php');
include_once ('class.telegram.php');
include_once ('class.menu.php');

//=============================================================================================
// 
//=============================================================================================

try {
    $update = TelegramUpdate::parse(file_get_contents('php://input'));

    if ($update === null)
        return;

    parseGlobals($update);

    DB::update_log($update);
    DB::update_user($GLOBALS['user']);

    TelegramUpdate::send('sendMessage', Menu::get_menu());

} catch (Exception $e) {
    echo "Update parse error: " . $e->getMessage();
}


function parseGlobals($update) {
        // Detect the source of json data: callback if is present (telegram
    // keyboard button pressed) or update data json if its not
    if (isset($update->callback_query)) {
        $GLOBALS['chat_id'] = $update->callback_query->from->id;
        $GLOBALS['user_id'] = $update->callback_query->from->id;   
        $GLOBALS['user'] = [
            'id' => $update->callback_query->from->id,
            'is_bot' => $update->callback_query->from->is_bot,
            'username' => $update->callback_query->from->username,
            'last_name' => $update->callback_query->from->last_name,
            'first_name' => $update->callback_query->from->first_name,
            'language_code' => $update->callback_query->from->language_code
        ];
        $GLOBALS['message_text'] = $update->callback_query->message->text;
        $GLOBALS['message_date'] = $update->callback_query->message->date;
    } else {
        $GLOBALS['chat_id'] = $update->message->chat->id;
        $GLOBALS['user_id'] = $update->message->from->id;
        $GLOBALS['user'] = [
            'id' => $update->message->from->id,
            'is_bot' => $update->message->from->is_bot,
            'username' => $update->message->from->username,
            'last_name' => $update->message->from->last_name,
            'first_name' => $update->message->from->first_name,
            'language_code' => $update->message->from->language_code
        ];
        $GLOBALS['message_text'] = $update->message->text;
        $GLOBALS['message_date'] = $update->message->date;
    }

    // Additional calculated fields
    $GLOBALS['delivery_1_time'] = mktime(TP_DELIVERY_1_TOD[0], TP_DELIVERY_1_TOD[1], 0, date('m'), date('d'), date('Y'));
    $GLOBALS['delivery_2_time'] = mktime(TP_DELIVERY_2_TOD[0], TP_DELIVERY_2_TOD[1], 0, date('m'), date('d'), date('Y'));
    $GLOBALS['order_1_time'] = mktime(TP_DELIVERY_1_TOD[0] - TP_FULL_PROCESS_TIME[0], TP_DELIVERY_1_TOD[1] - TP_FULL_PROCESS_TIME[1], 0, date('m'), date('d'), date('Y'));
    $GLOBALS['order_2_time'] = mktime(TP_DELIVERY_2_TOD[0] - TP_FULL_PROCESS_TIME[0], TP_DELIVERY_2_TOD[1] - TP_FULL_PROCESS_TIME[1], 0, date('m'), date('d'), date('Y'));
}

?>