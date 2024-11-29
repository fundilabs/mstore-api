<?php

class CouponManagementHelper
{
    public function sendError($code, $message, $statusCode)
    {
        return new WP_Error($code, $message, [
            "status" => $statusCode,
        ]);
    }

    public function get_coupons($request, $user_id)
    {
        global $wpdb;
        $page = isset($request["page"]) ? sanitize_text_field($request["page"])  : 1;
        $limit = isset($request["per_page"]) ? sanitize_text_field($request["per_page"]) : 10;
        if(!is_numeric($page)){
            $page = 1;
        }
        if(!is_numeric($limit)){
            $limit = 10;
        }
        if ($page >= 1) {
            $page = ($page - 1) * $limit;
        }

        if ($user_id) {
            $user = get_userdata($user_id);
            $is_admin = $user != false ? (in_array('administrator', (array)$user->roles) || in_array('shop_manager', (array)$user->roles)) : false;
            $vendor_id = absint($user_id);
        }

        $table_name = $wpdb->prefix . "posts";
        $postmeta_table = $wpdb->prefix . "postmeta";
        $is_admin = isset($is_admin) && $is_admin == true;
        if($is_admin){
            $sql = "SELECT p.*, 
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_code') AS coupon_code,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'discount_type') AS type,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_amount') AS amount,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_limit') AS usage_limits,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'date_expires') AS expiry,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_count') AS usage_count
                FROM `$table_name` p 
                WHERE p.`post_type` = 'shop_coupon' AND p.`post_status` != 'trash'";
        }else{
            $sql = "SELECT p.*, 
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_code') AS coupon_code,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'discount_type') AS type,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_amount') AS amount,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_limit') AS usage_limits,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'date_expires') AS expiry,
                    (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_count') AS usage_count
                FROM `$table_name` p 
                WHERE p.`post_author` = %s AND p.`post_type` = 'shop_coupon' AND p.`post_status` != 'trash'";
        }

        if (isset($request["search"])) {
            $search =  sanitize_text_field($request["search"]);
            $search = "%$search%";

            if ($is_admin) {
                $sql = "SELECT DISTINCT p.ID, p.*, 
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_code') AS coupon_code,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'discount_type') AS type,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_amount') AS amount,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_limit') AS usage_limits,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'date_expires') AS expiry,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_count') AS usage_count
                    FROM `$table_name` p 
                    LEFT JOIN `$postmeta_table` pm ON p.ID = pm.post_id 
                    WHERE p.`post_type` = 'shop_coupon' AND p.`post_status` != 'trash'";
            } else {
                $sql = "SELECT DISTINCT p.ID, p.*, 
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_code') AS coupon_code,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'discount_type') AS type,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'coupon_amount') AS amount,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_limit') AS usage_limits,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'date_expires') AS expiry,
                        (SELECT meta_value FROM {$postmeta_table} WHERE post_id = p.ID AND meta_key = 'usage_count') AS usage_count
                    FROM `$table_name` p 
                    LEFT JOIN `$postmeta_table` pm ON p.ID = pm.post_id 
                    WHERE p.`post_author` = %s AND p.`post_type` = 'shop_coupon' AND p.`post_status` != 'trash'";
            }

            $sql .= " AND (p.`post_title` LIKE %s OR p.`post_excerpt` LIKE %s OR pm.`meta_value` LIKE %s)";
        }
        $sql .= " ORDER BY p.ID DESC LIMIT %d OFFSET %d";

        $args = array();
        if(!$is_admin){
            $args[] = $vendor_id;
        }
        if (isset($search)) {
            $args[] = $search;
            $args[] = $search;
            $args[] = $search;
        }
        $args[] = $limit;
        $args[] = $page;
        $sql = $wpdb->prepare($sql, $args);
        $coupons = $wpdb->get_results($sql);
        return apply_filters(
            "get_coupons",
            $coupons,
            $request,
            $user_id
        );
    }

    public function create_coupon($request, $user_id)
    {
        $user = get_userdata($user_id);
        $is_seller = false;
        $role_arr = ['wcfm_vendor', 'seller', 'administrator'];

        foreach ($user->roles as $role) {
            if (in_array($role, $role_arr)) {
                $is_seller = true;
                break;
            }
        }

        if (!$is_seller) {
            return $this->sendError(
                "invalid_role",
                "You must be a seller to create coupons",
                401
            );
        }

        $code = isset($request['code']) ? sanitize_text_field($request['code']) : '';
        $type = isset($request['type']) ? sanitize_text_field($request['type']) : 'fixed_cart';
        $amount = isset($request['amount']) ? floatval($request['amount']) : 0;
        $description = isset($request['description']) ? sanitize_text_field($request['description']) : '';
        $expiry = isset($request['expiry']) ? sanitize_text_field($request['expiry']) : '';
        $usage_limit = isset($request['usage_limit']) ? intval($request['usage_limit']) : 0;
        
        if (empty($code)) {
            return $this->sendError(
                "invalid_code",
                "Coupon code is required",
                400
            );
        }

        $coupon = array(
            'post_title' => $code,
            'post_excerpt' => $code,
            'post_name' => $code,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_type' => 'shop_coupon'
        );

        $coupon_id = wp_insert_post($coupon);

        if (!is_wp_error($coupon_id)) {
            update_post_meta($coupon_id, 'discount_type', $type);
            update_post_meta($coupon_id, 'coupon_amount', $amount);
            update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            update_post_meta($coupon_id, 'date_expires', strtotime($expiry));
            update_post_meta($coupon_id, 'individual_use', 'no');
            update_post_meta($coupon_id, 'product_ids', '');
            update_post_meta($coupon_id, 'exclude_product_ids', '');
            update_post_meta($coupon_id, 'usage_count', '0');
            update_post_meta($coupon_id, 'free_shipping', 'no');
            update_post_meta($coupon_id, 'product_categories', array());
            update_post_meta($coupon_id, 'exclude_product_categories', array());
            update_post_meta($coupon_id, 'exclude_sale_items', 'no');
            update_post_meta($coupon_id, 'minimum_amount', '');
            update_post_meta($coupon_id, 'maximum_amount', '');
            update_post_meta($coupon_id, 'customer_email', array());

            $updated_coupon = get_post($coupon_id);
            $coupon_data = array(
                'ID' => strval($coupon_id),
                'post_title' => $updated_coupon->post_title,
                'type' => get_post_meta($coupon_id, 'discount_type', true),
                'amount' => strval(get_post_meta($coupon_id, 'coupon_amount', true)),
                'description' => $updated_coupon->post_content,
                'expiry' => get_post_meta($coupon_id, 'date_expires', true),
                'usage_limit' => strval(get_post_meta($coupon_id, 'usage_limit', true)),
                'usage_count' => strval(get_post_meta($coupon_id, 'usage_count', true))
            );

            return new WP_REST_Response(array(
                'status' => 'success',
                'response' => $coupon_data
            ), 200);
        }

        return $this->sendError(
            "create_failed",
            "Could not create coupon",
            400
        );
    }

    public function update_coupon($request, $user_id)
    {
        $coupon_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$coupon_id) {
            return $this->sendError(
                "invalid_id",
                "Coupon ID is required",
                400
            );
        }

        $user = get_userdata($user_id);
        $is_seller = false;
        $role_arr = ['wcfm_vendor', 'seller', 'administrator'];

        foreach ($user->roles as $role) {
            if (in_array($role, $role_arr)) {
                $is_seller = true;
                break;
            }
        }

        if (!$is_seller) {
            return $this->sendError(
                "invalid_role",
                "You must be a seller to update coupons",
                401
            );
        }

        $code = isset($request['code']) ? sanitize_text_field($request['code']) : '';
        $type = isset($request['type']) ? sanitize_text_field($request['type']) : 'fixed_cart';
        $amount = isset($request['amount']) ? floatval($request['amount']) : 0;
        $description = isset($request['description']) ? sanitize_text_field($request['description']) : '';
        $expiry = isset($request['expiry']) ? sanitize_text_field($request['expiry']) : '';
        $usage_limit = isset($request['usage_limit']) ? intval($request['usage_limit']) : 0;

        $coupon = array(
            'ID' => $coupon_id,
        );
        
        if (!empty($code)) {
            $coupon['post_title'] = $code;
        }
        
        if (isset($request['description'])) {
            $coupon['post_content'] = $description;
        }

        $updated = wp_update_post($coupon);

        if (!is_wp_error($updated)) {
            if (isset($request['type'])) {
                update_post_meta($coupon_id, 'discount_type', $type);
            }
            if (isset($request['amount'])) {
                update_post_meta($coupon_id, 'coupon_amount', $amount);
            }
            if (isset($request['usage_limit'])) {
                update_post_meta($coupon_id, 'usage_limit', $usage_limit);
            }
            if (isset($request['expiry'])) {
                update_post_meta($coupon_id, 'date_expires', strtotime($expiry));
            }

            $updated_coupon = get_post($coupon_id);
            $coupon_data = array(
                'ID' => strval($coupon_id),
                'post_title' => $updated_coupon->post_title,
                'type' => get_post_meta($coupon_id, 'discount_type', true),
                'amount' => strval(get_post_meta($coupon_id, 'coupon_amount', true)),
                'description' => $updated_coupon->post_content,
                'expiry' => get_post_meta($coupon_id, 'date_expires', true),
                'usage_limit' => strval(get_post_meta($coupon_id, 'usage_limit', true)),
                'usage_count' => strval(get_post_meta($coupon_id, 'usage_count', true))
            );

            return new WP_REST_Response(array(
                'status' => 'success',
                'response' => $coupon_data
            ), 200);
        }

        return $this->sendError(
            "update_failed",
            "Could not update coupon",
            400
        );
    }
}