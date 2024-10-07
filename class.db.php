<?php

include_once ('config.php');

class DB {

    /**
     * Check passed as paramater language code.
     * If not set try to get language code from Telegram settings.
     * If not available as well set default 'es'.
     * 
     * $language    string  Language code ('es', 'en', 'ru', 'de' etc)
     * @result      string  Resulting language code after all checks
     */
    static function get_language($language) {
        if ($language === null) {
            global $update;
            $language = $update->message->from->language_code;
            
            if ($language === null) {
                $language = 'es';
            }
        }
        return $language;
    }


    /**
     * Log full data from Telegram update to database
     * 
     * $update  array   Telegram update data from webhook
     */
    static function update_log($update) {
        if ($update === null)
            return;

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'INSERT INTO log_query(chat_id,user_id,message_text,message_date,raw_data_telegram) VALUES(:chat_id,:user_id,:message_text,:message_date,:raw_data_telegram)';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':chat_id' => $GLOBALS['chat_id'],
                    ':user_id' => $GLOBALS['user_id'],
                    ':message_text' => $GLOBALS['message_text'],
                    ':message_date' => date("Y-m-d H:i:s", $GLOBALS['message_date']),
                    ':raw_data_telegram' => json_encode($update)
                ]);
                
                $res = $pdo->lastInsertId();
                
                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }


    /**
     * Get string from 'strings' table for a specified language code
     * ('es', 'en', 'ru', 'de' etc)
     * 
     * $string_slug string  String slug in a database
     * @language    int     Language code (optional, see self::get_language)
     * @return      string  String for a specified language code
     */
    static function get_string($string_slug, $language = null) {

        if (trim($string_slug) === "")
            return;
        
        $language = self::get_language($language);

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {
                //echo "Connected to the $db database successfully!";

                $sql = 'SELECT * FROM strings WHERE string_slug=:string_slug;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':string_slug' => $string_slug
                ]);

                while ($row = $statement->fetch()) {
                    $res[$row['language']] = $row['string'];
                }
                
                $statement = null;
                $pdo = null;

                if ($res[$language] !== null)
                    return $res[$language];
                elseif ($res['en'] !== null)
                    return $res['en'];
                elseif ($res[array_key_first($res)] !== null)
                    return $res[array_key_first($res)];
                else
                    return $string_slug;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }


    /**
     * Shorter alias for get_string()
     */
    static function _s($string_slug, $language = null) {
        return self::get_string($string_slug, $language);
    }



    //=============================================================================================
    // PRODUCTS
    //=============================================================================================
    
    /**
     * Gets product specified by id from database
     * 
     * @product_id          int Id of a product that should be added to cart
     * @language    int     Language code (optional, see self::get_language)
     * @return      array   Single product associative array
     */
    static function get_product($product_id, $language = null) {
        
        $language = self::get_language($language);

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'SELECT products.*, carts.product_quantity as `product_quantity_reserved` FROM products LEFT JOIN carts ON carts.product_id=products.id WHERE products.id=:product_id;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':product_id' => $product_id
                ]);

                while ($row = $statement->fetch()) {
                    $res['id'] = $row['id'];
                    $res['product_id'] = $row['id'];
                    $res['product_slug'] = $row['product_slug'];
                    $res['product_title'] = DB::_s($row['product_slug']);
                    $res['product_description'] = $row['product_description'];
                    $res['product_category'] = $row['product_category'];
                    $res['product_quantity'] = $row['product_quantity'];
                    $res['product_billets_quantity'] = $row['product_billets_quantity'];
                    $res['product_max_units_per_owen'] = $row['product_max_units_per_owen'];
                    $res['product_quantity_reserved'] = $row['product_quantity_reserved'];
                    
                    // Additional calculated fields
                    self::process_product($res);
                }

                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }

    
    /**
     * Updates product in a database
     * 
     * @product     array  Product array instance
     */
    static function update_product($product) {
        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'UPDATE products SET product_quantity=:product_quantity,product_billets_quantity=:product_billets_quantity WHERE id=:product_id LIMIT 1;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':product_id' => $product['id'],
                    ':product_quantity' => $product['product_quantity'],
                    ':product_billets_quantity' => $product['product_billets_quantity']
                ]);

                $statement = null;
                $pdo = null;

            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
        }
    }

    
    /**
     * Gets a full list of products from database
     * 
     * @language    int     Language code (optional, see self::get_language)
     * @return      array   List of all products
     */
    static function get_products($language = null) {
        
        $language = self::get_language($language);

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'SELECT products.*, carts.product_quantity as `product_quantity_reserved` FROM products LEFT JOIN carts ON carts.product_id=products.id;';

                $statement = $pdo->query($sql);

                while ($row = $statement->fetch()) {
                    $res[$row['id']]['id'] = intval($row['id']);
                    $res[$row['id']]['product_id'] = intval($row['id']);
                    $res[$row['id']]['product_slug'] = $row['product_slug'];
                    $res[$row['id']]['product_title'] = DB::_s($row['product_slug']);
                    $res[$row['id']]['product_description'] = $row['product_description'];
                    $res[$row['id']]['product_category'] = $row['product_category'];
                    $res[$row['id']]['product_quantity'] = intval($row['product_quantity']);
                    $res[$row['id']]['product_billets_quantity'] = intval($row['product_billets_quantity']);
                    $res[$row['id']]['product_max_units_per_owen'] = intval($row['product_max_units_per_owen']);
                    $res[$row['id']]['product_quantity_reserved'] = intval($row['product_quantity_reserved']);
                    
                    // Additional calculated fields
                    self::process_product($res[$row['id']]);
                }

                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }


    /**
     * Calculate additional fields for a single product after fetching data from database
     * 
     * @product     &array  Product array instance reference
     */
    static function process_product(&$product)
    {
        $product['product_quantity_available'] = $product['product_quantity'] - $product['product_quantity_reserved'];

        // It is earlier than final moment to make first order of the day (first delivery time minus full lenght of baking process)
        if (time() < $GLOBALS['order_1_time']) {
            $product['product_quantity_available_delivery_1'] = max(0, $product['product_quantity'] - $product['product_quantity_reserved']);$product['product_quantity_available'] + min($product['product_max_units_per_owen'], $product['product_billets_quantity']);
            $product['product_quantity_available_delivery_2'] = max(0, $product['product_quantity_available'] + min($product['product_max_units_per_owen'] * 2, $product['product_billets_quantity']));
            
        } elseif (time() < $GLOBALS['order_2_time']) {

            if (time() < $GLOBALS['delivery_1_time'])
                $product['product_quantity_available_delivery_1'] = max(0, $product['product_quantity_available']);
            else
                $product['product_quantity_available_delivery_1'] = 0;

            $product['product_quantity_available_delivery_2'] = max(0, $product['product_quantity_available'] + min($product['product_max_units_per_owen'], $product['product_billets_quantity']));
        } else {
            $product['product_quantity_available_delivery_1'] = 0;

            if (time() < $GLOBALS['delivery_2_time'])
                $product['product_quantity_available_delivery_2'] = max(0, $product['product_quantity_available']);
            else
                $product['product_quantity_available_delivery_2'] = 0;
        }

        // Limit amount of product items available right now after all other calculations
        $product['product_quantity_available'] = max(0, $product['product_quantity_available']);
    }



    //=============================================================================================
    // BILLETS
    //=============================================================================================

    /**
     * Gets a full list of billets from database
     * 
     * @language    int     Language code (optional, see self::get_language)
     * @return      array   List of billets
     */
    static function get_billets($language = null) {
        
        $language = self::get_language($language);

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'SELECT b.id,b.product_id,b.billet_quantity,b.billet_quantity_reserved,p.product_title FROM billets as b, products as p WHERE b.product_id=p.id;';

                $statement = $pdo->query($sql);

                while ($row = $statement->fetch()) {
                    $res[$row['id']]['id'] = $row['id'];
                    $res[$row['id']]['billet_id'] = $row['id'];
                    $res[$row['id']]['billet_quantity'] = $row['billet_quantity'];
                    $res[$row['id']]['billet_quantity_reserved'] = $row['billet_quantity_reserved'];
                    $res[$row['id']]['product'] = DB::get_product($row['product_id']);
                    
                    // Additional calculated fields
                    $res[$row['id']]['billet_quantity_available'] = max(0, $row['billet_quantity'] - $row['billet_quantity_reserved']);
                }

                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }



    //=============================================================================================
    // CART
    //=============================================================================================

    /**
     * Gets a full list of products from database
     * 
     * @user_id     int     Telegarm user id
     * @language    int     Language code (optional, see self::get_language)
     * @return      array   List of products (associative arrays) in user's cart
     */
    static function get_cart($user_id, $language = null) {
        
        $language = self::get_language($language);

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'SELECT * FROM carts WHERE user_id=:user_id;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':user_id' => $user_id
                ]);

                while ($row = $statement->fetch()) {
                    $res['items'][$row['product_id']]['product'] = DB::get_product($row['product_id']);
                    $res['items'][$row['product_id']]['product_quantity'] = $row['product_quantity'];

                    $res['total_quantity'] += $row['product_quantity'];
                }

                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }


    /**
     * Updates user's cart in a database
     * 
     * @user_id             int Telegarm user id
     * @product_id          int Id of a product that should be added to cart
     * @product_quantity    int Quantity of a product to add
     */
    static function add_to_cart($user_id, $product_id, $product_quantity) {
        
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 
                    'INSERT INTO carts(user_id,product_id,product_quantity) ' .
                    'VALUES(:user_id,:product_id,:product_quantity) ' . 
                    'ON DUPLICATE KEY UPDATE product_quantity=product_quantity+:product_quantity;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':user_id' => $user_id,
                    ':product_id' => $product_id,
                    ':product_quantity' => $product_quantity
                ]);
                
                $res = $pdo->lastInsertId();

                $statement = null;
                $pdo = null;

            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
        }
    }


    /**
     * Clear al items in user's cart
     * 
     * @user_id  int    Telegarm user id
     */
    function clear_cart($user_id) {
        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                //$sql = 'DELETE FROM carts WHERE user_id=:user_id;';
                $sql = 'UPDATE carts SET product_quantity=0 WHERE user_id=:user_id;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':user_id' => $user_id
                ]);

                $statement = null;
                $pdo = null;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
        }
    }



    //=============================================================================================
    // USERS
    //=============================================================================================
    /**
     * Updates user in a database or create new one
     * 
     * @user    object  User associative array
     * @return  string  New/updated user id in database
     */
    static function update_user($user) {
        
        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 
                    'INSERT INTO users(id,username,first_name,last_name) ' .
                    'VALUES(:id,:username,:first_name,:last_name) ' . 
                    'ON DUPLICATE KEY UPDATE username=:username,first_name=:first_name,last_name=:last_name,dt_last_update=CURRENT_TIMESTAMP;';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':id' => $user['id'],
                    ':username' => $user['username'],
                    ':first_name' => $user['first_name'],
                    ':last_name' => $user['last_name']
                ]);
                
                $res = $pdo->lastInsertId();

                $statement = null;
                $pdo = null;

                return $res;

            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }



    //=============================================================================================
    // ORDERS
    //=============================================================================================
    /**
     * Create new order in a database
     * 
     * @order   object  Order associative array (result of get_order())
     * @return  string  New order id in database
     */
    static function create_order($order){
        if ($order === null)
            return;

        try {

            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DB . ";charset=UTF8", DB_USER, DB_PASS);

            if ($pdo) {

                $sql = 'INSERT INTO orders(user_id,order_datetime_execution) VALUES(:user_id,:order_datetime_execution)';

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    ':user_id' => $order['user']['id'],
                    ':order_datetime_execution' => date("Y-m-d H:i:s", $order['order_datetime_execution'])
                ]);
                
                $res = $pdo->lastInsertId();
                
                $statement = null;
                $pdo = null;

                return $res;
            }
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return null;
        }
    }
}

?>