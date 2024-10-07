<?php

include_once ('class.db.php');

class Menu {

    /**
     * Get Telegram menu data (text and inline keyboard) for current
     * user and nesting level.
     * No arguments, all data taken from global bot responce.
     */
    static function get_menu() {

        global $update;

        // Detect the source of json data: callback if is present (telegram
        // keyboard button pressed) or update data json if its not
        if (isset($update->callback_query)) {
            $callback_args = explode('-', $update->callback_query->data);
            $menu_area = $callback_args[0];
            $menu_id = $callback_args[1];
        } else {
            $menu_area = 'user';
            $menu_id = 'index';
        }

        $text = '';
        $menu_data = [];



        if ($menu_area == 'user') {

            // Info (static)
            if ($menu_id == 'info') {
                $text = DB::_s('bot-message-info') . ":";
                $menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'user-index']);
            }



            // Products list
            if ($menu_id == 'products') {
                $products_in_stock = DB::get_products();
                $text = DB::_s('bot-message-choose-product') . ":";
                foreach ($products_in_stock as $product) {
                    $menu_data[] = array(['text' => $product['product_title'] . ", " . $product['product_quantity_available_delivery_2'] . DB::_s('units'), 'callback_data' => 'user-product-' . $product['product_id']]);
                }
                
                $menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'user-index']);
            }



            // Single product
            if ($menu_id == 'product') {
                $product_id = $callback_args[2];
                $product_action = $callback_args[3];
                $product_action_arg = $callback_args[4];

                $product = DB::get_product($product_id);

                $text = json_encode($callback_args);

                if ($product_action == 'add' && is_numeric($product_action_arg)) {
                    DB::add_to_cart($GLOBALS['user']['id'], $product_id, $product_action_arg);

                    $product = DB::get_product($product_id);
                }

                if ($product['product_quantity_available_delivery_2'] > 0) {

                    $text = 
                        DB::_s($product['product_title']). " " . date("Y-m-d H:i:s") . "\r\n" . 
                        DB::_s('bot-message-available-buy')     . ": " . $product['product_quantity_available'] . DB::_s('units') . "\r\n" . 
                        DB::_s('bot-message-available-order-1') . ": " . $product['product_quantity_available_delivery_1'] . DB::_s('units') . "\r\n" . 
                        DB::_s('bot-message-available-order-2') . ": " . $product['product_quantity_available_delivery_2'] . DB::_s('units') . "\r\n" . 
                        "\r\n" . 
                        DB::_s('add-to-cart') . ": ";

                    if ($product['product_quantity_available_delivery_2'] > 0)
                        $menu_data[] = array(['text' => '+1' . DB::_s('units'), 'callback_data' => 'user-product-' . $product['product_id'] . '-add-1']);
                    if ($product['product_quantity_available_delivery_2'] > 1)
                        $menu_data[] = array(['text' => '+2' . DB::_s('units'), 'callback_data' => 'user-product-' . $product['product_id'] . '-add-2']);
                    if ($product['product_quantity_available_delivery_2'] > 2)
                        $menu_data[] = array(['text' => '+3' . DB::_s('units'), 'callback_data' => 'user-product-' . $product['product_id'] . '-add-3']);
                } else {
                    $text = DB::_s($product['product_title']). "\r\n" . DB::_s('bot-message-nothing-in-stock');
                }
                $menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'user-products']);
            }


            // Cart
            if ($menu_id == 'cart') {
                $cart_action = $callback_args[2];

                if ($cart_action == 'confirm') {
                    $new_order = [];
                    $new_order['user'] = $GLOBALS['user'];
                    DB::create_order($new_order);
                }

                if ($cart_action == 'clear') {
                    DB::clear_cart($GLOBALS['user']['id']);
                }

                $cart = DB::get_cart($GLOBALS['user']['id']);

                if ($cart['total_quantity'] > 0) {
                    $text = DB::_s('bot-message-currently-in-cart') . ":\r\n";
                    foreach ($cart['items'] as $cart_item) {
                        if ($cart_item['product_quantity'] > 0) {
                            $text .= $cart_item['product']['product_title'] . ", " . $cart_item['product_quantity'] . DB::_s('units') . "\r\n";
                        }
                    }
                    $menu_data = [
                        [
                            ['text' => DB::_s('bot-btn-confirm-order'), 'callback_data' => 'user-cart-confirm'],
                            ['text' => DB::_s('bot-btn-clear-cart'), 'callback_data' => 'user-cart-clear']
                        ]
                    ];
                } else {
                    $text = DB::_s('bot-message-nothing-in-cart') . "\r\n\r\n";
                    $menu_id = 'index'; // to main menu
                    //$menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'user-index']);
                }

            }


            // Main menu
            if ($menu_id == 'index') {
                $cart = DB::get_cart($GLOBALS['user']['id']);

                $text .= DB::_s('bot-message-hello');
                $menu_data = array([
                        ['text' => DB::_s('bot-btn-user-products'), 'callback_data' => 'user-products'],
                        ['text' => DB::_s('bot-btn-user-info'), 'callback_data' => 'user-info'],
                        ['text' => ($cart['total_quantity'] > 0) ? DB::_s('bot-btn-user-cart') . " [". $cart['total_quantity'] ."]" : DB::_s('bot-btn-user-cart'), 'callback_data' => 'user-cart']
                ]);

                if ( in_array($GLOBALS['user']['id'], TG_ADMIN_ID) ) {
                    $menu_data[] = array(['text' => DB::_s('bot-menu-0-admin'), 'callback_data' => 'admin-index']);
                }
            }
        }


        // Admin area
        if ($menu_area == 'admin') {

            $admin_product_id = $callback_args[2];
            $admin_subaction = $callback_args[3];
            $admin_subaction_arg = $callback_args[4];

            if ($menu_id == 'products') {
                $products_list = DB::get_products();
                if (count($products_list) > 0) {
                    $text = ">> PRODUCTS >>                                                           ‎\r\n";                    
                    foreach ($products_list as $product) {
                        $menu_data[] = array(['text' => $product['product_title'] . ", " . $product['product_quantity_available'] . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id']]);
                    }
                }
                $menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'admin-index']);

            } elseif ($menu_id == 'product') {
                
                if ($admin_subaction == 'add' && is_numeric($admin_subaction_arg)) {
                    $product = DB::get_product($admin_product_id);
                    $product['product_quantity'] += $admin_subaction_arg;
                    DB::update_product($product);
                
                } elseif ($admin_subaction == 'delete' && is_numeric($admin_subaction_arg)) {
                    $product = DB::get_product($admin_product_id);
                    $product['product_quantity'] -= $admin_subaction_arg;
                    DB::update_product($product);
                }

                $product = DB::get_product($admin_product_id);
                $text .= ">> PRODUCTS >> ". $product['product_title'] . " [" . $product['product_quantity_available'] . "/". $product['product_quantity'] . "] ‎\r\n";// . $admin_subaction ."-" . $admin_subaction_arg . "\r\n";
                $menu_data = [
                    [
                        ['text' => '+1' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-add-1'],
                        ['text' => '+2' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-add-2'],
                        ['text' => '+3' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-add-3'],
                        ['text' => '+5' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-add-5']
                    ],
                    [
                        ['text' => '-1' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-delete-1'],
                        ['text' => '-2' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-delete-2'],
                        ['text' => '-3' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-delete-3'],
                        ['text' => '-5' . DB::_s('units'), 'callback_data' => 'admin-product-' . $product['product_id'] . '-delete-5']
                    ],
                    [
                        ['text' => DB::_s('bot-btn-back'), 'callback_data' => 'admin-products']
                    ]
                ];
 
            } elseif ($menu_id == 'billets') {
                $billets_list = DB::get_products();
                if (count($billets_list) > 0) {
                    $text = ">> BILLETS >>                                                           ‎\r\n";                    
                    foreach ($billets_list as $product) {
                        $menu_data[] = array(['text' => $product['product_title'] . ", " . $product['product_billets_quantity'] . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id']]);
                    }
                }
                $menu_data[] = array(['text' => DB::_s('bot-btn-back'), 'callback_data' => 'admin-index']);

            } elseif ($menu_id == 'billet') {
                if ($admin_subaction == 'add' && is_numeric($admin_subaction_arg)) {
                    $product = DB::get_product($admin_product_id);
                    $product['product_billets_quantity'] += $admin_subaction_arg;
                    DB::update_product($product);
                
                } elseif ($admin_subaction == 'delete' && is_numeric($admin_subaction_arg)) {
                    $product = DB::get_product($admin_product_id);
                    $product['product_billets_quantity'] -= $admin_subaction_arg;
                    DB::update_product($product);
                }

                $product = DB::get_product($admin_product_id);
                $text .= ">> BILLETS >> ". $product['product_title'] . " [" . $product['product_billets_quantity'] . "] ‎\r\n";
                $menu_data = [
                    [
                        ['text' => '+1' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-add-1'],
                        ['text' => '+2' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-add-2'],
                        ['text' => '+3' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-add-3'],
                        ['text' => '+5' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-add-5']
                    ],
                    [
                        ['text' => '-1' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-delete-1'],
                        ['text' => '-2' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-delete-2'],
                        ['text' => '-3' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-delete-3'],
                        ['text' => '-5' . DB::_s('units'), 'callback_data' => 'admin-billet-' . $product['product_id'] . '-delete-5']
                    ],
                    [
                        ['text' => DB::_s('bot-btn-back'), 'callback_data' => 'admin-billets']
                    ]
                ];

            } else {
                $text = ">>                                                              ‎\r\n";
                $menu_data = [
                    [
                        ['text' => DB::_s('bot-btn-admin-products'), 'callback_data' => 'admin-products'],
                        ['text' => DB::_s('bot-btn-admin-billets'), 'callback_data' => 'admin-billets'],
                        ['text' => DB::_s('bot-btn-admin-orders'), 'callback_data' => 'admin-orders']                        
                    ],
                    [
                        ['text' => DB::_s('bot-btn-back'), 'callback_data' => 'user-index']
                    ]
                ];
            }  
        }



        $keyboard = [
            'inline_keyboard' => $menu_data
        ];


        $parameters = 
            array(
                'chat_id' => $GLOBALS['chat_id'], 
                'text' => $text, 
                'reply_markup' => json_encode($keyboard)
            );

        return $parameters;
    }

}
?>