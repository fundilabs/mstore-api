<?php
class DeliveryWCFMHelper
{
    public function sendError($code, $message, $statusCode)
    {
        return new WP_Error($code, $message, array(
            'status' => $statusCode
        ));
    }

    protected function upload_image_from_mobile($image, $count, $user_id)
    {
        require_once (ABSPATH . '/wp-load.php');
        require_once (ABSPATH . 'wp-admin' . '/includes/file.php');
        require_once (ABSPATH . 'wp-admin' . '/includes/image.php');
        $imgdata = $image;
        $imgdata = trim($imgdata);
        $imgdata = str_replace('data:image/png;base64,', '', $imgdata);
        $imgdata = str_replace('data:image/jpg;base64,', '', $imgdata);
        $imgdata = str_replace('data:image/jpeg;base64,', '', $imgdata);
        $imgdata = str_replace('data:image/gif;base64,', '', $imgdata);
        $imgdata = str_replace(' ', '+', $imgdata);
        $imgdata = base64_decode($imgdata);
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        $type_file = explode('/', $mime_type);
        $avatar = time() . '_' . $count . '.' . $type_file[1];

        $uploaddir = wp_upload_dir();
        $myDirPath = $uploaddir["path"];
        $myDirUrl = $uploaddir["url"];

        file_put_contents($uploaddir["path"] . '/' . $avatar, $imgdata);

        $filename = $myDirUrl . '/' . basename($avatar);
        $wp_filetype = wp_check_filetype(basename($filename) , null);
        $uploadfile = $uploaddir["path"] . '/' . basename($filename);

        $attachment = array(
            "post_mime_type" => $wp_filetype["type"],
            "post_title" => preg_replace("/\.[^.]+$/", "", basename($filename)) ,
            "post_content" => "",
            "post_author" => $user_id,
            "post_status" => "inherit",
            'guid' => $myDirUrl . '/' . basename($filename) ,
        );

        $attachment_id = wp_insert_attachment($attachment, $uploadfile);
        $attach_data = apply_filters('wp_generate_attachment_metadata', $attachment, $attachment_id, 'create');
        // $attach_data = wp_generate_attachment_metadata($attachment_id, $uploadfile);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        return $attachment_id;
    }

    protected function find_image_id($image)
    {
        $image_id = attachment_url_to_postid(stripslashes($image));
        return $image_id;
    }

    protected function http_check($url)
    {
        if ((!(substr($url, 0, 7) == 'http://')) && (!(substr($url, 0, 8) == 'https://')))
        {
            return false;
        }
        return true;
    }


      /// GET FUNCTIONS
      public function get_delivery_profile($user_id)
      {
        $data['first_name'] = get_user_meta($user_id, 'billing_first_name', true);
        $data['last_name']  = get_user_meta($user_id, 'billing_last_name', true);
		$data['phone'] = get_user_meta($user_id, 'billing_phone', true);
		  
		  
          return new WP_REST_Response(array(
              'status' => 'success',
              'response' => $data,
          ) , 200);
      }

      public function update_vendor_profile($request, $user_id)
      {
          $data = json_decode($request, true);
          $vendor_data = get_user_meta($user_id, 'wcfmmp_profile_settings', true);
          if(is_string($vendor_data)){
              $vendor_data = array();
          }
    }


    public function get_delivery_stat($user_id){
        $results = array();
        if (is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php'))
        {
            global $wpdb;
            $sql  = "SELECT COUNT(ID) FROM `{$wpdb->prefix}wcfm_delivery_orders`";
            $sql .= " WHERE 1=1";
            $sql .= " AND delivery_boy = {$user_id}";
            $sql .= " AND is_trashed = 0";
            $total = count($wpdb->get_results( $sql ));
            
            $pending_sql = $sql . " AND delivery_status = 'pending' GROUP BY order_id";
            $delivered_sql = $sql . " AND delivery_status = 'delivered' GROUP BY order_id";

            $pending_count =count($wpdb->get_results( $pending_sql ));
            $delivered_count =count($wpdb->get_results( $delivered_sql ));

            $results = array(
                'delivered' => $delivered_count,
                'pending' => $pending_count,
                'total' => $total,
            );

        }

        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => $results,
        ) , 200);
    }
    public function get_delivery_order($user_id, $request){
        $api = new WC_REST_Orders_V1_Controller();
$order = array();
        
        if (is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php'))
        {
            global $wpdb;
            $table_name = $wpdb->prefix . "wcfm_delivery_orders";
            $sql  = "SELECT * FROM `{$table_name}`";
            $sql .= " WHERE 1=1";
            $sql .= " AND delivery_boy = {$user_id}";
            $sql .= " AND is_trashed = 0";
            $order_id = $request['id'];
            $sql .= " AND order_id = '{$order_id}'";
            $items = $wpdb->get_results($sql);
            if(!empty($items)){
                $vendor = new FlutterWCFMHelper();
                $order = wc_get_order($items[0]->order_id);
                $response = $api->prepare_item_for_response($order, $request);
                $order = $response->get_data();
                $count = count($order['line_items']);
                $order['product_count'] = $count;

                for ($i = 0;$i < $count;$i++)
                {
                    $product_id = absint($order['line_items'][$i]['product_id']);
                    $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id));
                    if (!is_null($image[0]))
                    {
                        $order['line_items'][$i]['featured_image'] = $image[0];
                    }
                    $vendor_data = $vendor->flutter_get_wcfm_stores_by_id($item[0]->vendor_id);
                    $order['wcfm_store'] = $vendor_data->data;
                }
                
                $order['delivery_status'] = $items[0]->delivery_status;
                if( apply_filters( 'wcfmmp_is_allow_checkout_user_location', true ) ) {
                    $address = get_post_meta( $order['id'], '_wcfmmp_user_location', true );
                    $lat     = get_post_meta( $order['id'], '_wcfmmp_user_location_lat', true );
                    $lng     = get_post_meta( $order['id'], '_wcfmmp_user_location_lng', true );
                    if( $address ) {
                        $order['user_location'] = [
                            "address"=>$address,
                            "lat" => $lat,
                            "lng" => $lng,
                        ];
                    }
                }
            }
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => $order,
        ) , 200);
    }
  

    public function get_delivery_stores($user_id, $request){
        global $wpdb;
        $table_name = $wpdb->prefix . "wcfm_delivery_orders";
        $sql  = "SELECT $table_name.`vendor_id` FROM `{$table_name}`";
        $sql .= " WHERE 1=1";
        $sql .= " AND delivery_boy = {$user_id}";
        $sql .= " AND is_trashed = 0";
        $sql .= " AND delivery_status = 'pending'";
        $sql .= " GROUP BY $table_name.`vendor_id`";
        $items = $wpdb->get_results($sql);

        $vendor = new FlutterWCFMHelper();
        $stores = array();
        foreach ($items as $item){
            $vendor_data = $vendor->flutter_get_wcfm_stores_by_id($item->vendor_id);
            $stores[] = $vendor_data->data;
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => $stores,
        ) , 200);

    }
    public function get_delivery_orders($user_id, $request){
        $api = new WC_REST_Orders_V1_Controller();
        $results = [];
        if (is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php'))
        {
            global $wpdb;
            $page = 1;
            $per_page = 10;
            if (isset($request['page']))
            {
                $page = $request['page'];
            }
            if (isset($request['per_page']))
            {
                $per_page = $request['per_page'];
            }
            $page = ($page - 1) * $per_page;
            $table_name = $wpdb->prefix . "wcfm_delivery_orders";
            $sql  = "SELECT * FROM `{$table_name}`";
            $sql .= " WHERE 1=1";
            $sql .= " AND delivery_boy = {$user_id}";
            $sql .= " AND is_trashed = 0";
            if(isset($request['status']) && !empty($request['status'])){
                $status = $request['status'];
                $sql .= " AND delivery_status = '{$status}'";
            }
            if(isset($request['search'])){
                $order_search = $request['search'];
                $sql .= " AND $table_name.`order_id` LIKE '%{$order_search}%'";
            }
            $sql .= " GROUP BY $table_name.`order_id` ORDER BY $table_name.`order_id` DESC LIMIT $per_page OFFSET $page";
            $items = $wpdb->get_results($sql);

            
            $vendor = new FlutterWCFMHelper();
            foreach ($items as $item)
            {
                $order = wc_get_order($item->order_id);
                if (is_bool($order))
                {
                    continue;
                }
                $response = $api->prepare_item_for_response($order, $request);
                $order = $response->get_data();
                $count = count($order['line_items']);
                $order['product_count'] = $count;

                for ($i = 0;$i < $count;$i++)
                {
                    $product_id = absint($order['line_items'][$i]['product_id']);
                    $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id));
                    if (!is_null($image[0]))
                    {
                        $order['line_items'][$i]['featured_image'] = $image[0];
                    }
                    $vendor_data = $vendor->flutter_get_wcfm_stores_by_id($item->vendor_id);
                    $order['wcfm_store'] = $vendor_data->data;
                }
                $order['delivery_status'] = $item->delivery_status;
                $results[] = $order;
            }
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => $results,
        ) , 200);
    }


    function get_notification($request, $user_id)
    {
        global $WCFM, $wpdb;
        $wcfm_messages;
        if (isset($request['per_page']) && $request['per_page'])
        {
            $limit = absint($request['per_page']);
            $offset = absint($request['page']);
            $offset = ($offset - 1) * $limit;
            $message_to = $user_id;

            $sql = 'SELECT wcfm_messages.* FROM ' . $wpdb->prefix . 'wcfm_messages AS wcfm_messages';
            $vendor_filter = " WHERE ( `author_id` = {$message_to} OR `message_to` = -1 OR `message_to` = {$message_to} ) AND message_type = 'delivery_boy_assign'";
            $sql .= $vendor_filter;
            $message_status_filter = " AND NOT EXISTS (SELECT * FROM {$wpdb->prefix}wcfm_messages_modifier as wcfm_messages_modifier_2 WHERE wcfm_messages.ID = wcfm_messages_modifier_2.message AND wcfm_messages_modifier_2.read_by={$message_to})";
            $sql .= $message_status_filter;
            $sql .= " ORDER BY wcfm_messages.`ID` DESC";
            $sql .= " LIMIT $limit";
            $sql .= " OFFSET $offset";
            $wcfm_messages = $wpdb->get_results($sql);

            foreach ($wcfm_messages as $wcfm_message)
            {
                unset($wcfm_message->author_id, $wcfm_message->reply_to, $wcfm_message->author_is_admin, $wcfm_message->author_is_vendor, $wcfm_message->author_is_customer, $wcfm_message->is_notice, $wcfm_message->is_direct_message, $wcfm_message->is_pined, $wcfm_message->message_to);
                $wcfm_message->message = strip_tags($wcfm_message->message);
            }
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => $wcfm_messages,
        ) , 200);
    }
        

    function update_delivery_profile($request, $user_id){
        $is_pw_correct = true;
        $pass = $request['password'];
        $new_pass = $request['new_password'];
        $first_name = $request['first_name'];
        $last_name = $request['last_name'];
        $phone = $request['phone'];
        $data = array('ID'=>$user_id);
		        if (isset($params->display_name)) {
            $user_update['first_name'] = $params->first_name;
        }
        if (isset($params->display_name)) {
            $user_update['last_name'] = $params->last_name;
        }
        
        if(isset($first_name)){
            $data['first_name']=$first_name;
            update_user_meta( $user_id, 'billing_first_name', $first_name,'' );
			wp_update_user(array('ID'=>$user_id,'first_name'=>$first_name));
        }
        if(isset($last_name)){
            $data['last_name']=$last_name;
            update_user_meta( $user_id, 'billing_last_name', $last_name,'' );
			wp_update_user(array('ID'=>$user_id,'last_name'=>$last_name));
        }
        if(isset($phone)){
            update_user_meta( $user_id, 'billing_phone', $phone,'' );
        }
        if(!empty($data)){
            wp_update_user($data,$user_id);
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => 1,
        ) , 200);
    }



    function update_delivery_order($order_id) {
		global $WCFM, $WCFMd, $wpdb;
		
		$delivered_not_notified = false;
        $sql  = "SELECT * FROM `{$wpdb->prefix}wcfm_delivery_orders`";
        $sql .= " WHERE 1=1";
        $sql .= " AND order_id = {$order_id}";
        $delivery_details = $wpdb->get_results( $sql );
        
        if( !empty( $delivery_details ) ) {
            foreach( $delivery_details as $delivery_detail ) {
                $delivery_id = $delivery_detail->ID;
                // Update Delivery Order Status Update
                $wpdb->update("{$wpdb->prefix}wcfm_delivery_orders", array('delivery_status' => 'delivered', 'delivery_date' => date('Y-m-d H:i:s', current_time( 'timestamp', 0 ))), array('ID' => $delivery_id), array('%s', '%s'), array('%d'));
                
                $order = wc_get_order( $delivery_detail->order_id );
                $wcfm_delivery_boy_user = get_userdata( $delivery_detail->delivery_boy );
                
                if( apply_filters( 'wcfm_is_show_marketplace_itemwise_orders', true ) ) {
                    // Admin Notification
                    $wcfm_messages = sprintf( __( 'Order <b>%s</b> item <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery' ), '#<a class="wcfm_dashboard_item_title" target="_blank" href="'.get_wcfm_view_order_url($delivery_detail->order_id) . '">' . $order->get_order_number() . '</a>', get_the_title( $delivery_detail->product_id ), '<a class="wcfm_dashboard_item_title" target="_blank" href="'.get_wcfm_delivery_boys_stats_url($delivery_detail->delivery_boy) . '">' . $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name . '</a>' );
                    $WCFM->wcfm_notification->wcfm_send_direct_message( -2, 0, 0, 0, $wcfm_messages, 'delivery_complete' );
                    
                    // Vendor Notification
                    if( $delivery_detail->vendor_id ) {
                        $WCFM->wcfm_notification->wcfm_send_direct_message( -1, $delivery_detail->vendor_id, 1, 0, $wcfm_messages, 'delivery_complete' );
                    }
                    
                    // Order Note
                    $wcfm_messages = sprintf( __( 'Order <b>%s</b> item <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery' ), '#<span class="wcfm_dashboard_item_title">' . $order->get_order_number() . '</span>', get_the_title( $delivery_detail->product_id ), $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name );
                    $comment_id = $order->add_order_note( $wcfm_messages, apply_filters( 'wcfm_is_allow_delivery_note_to_customer', '1' ) );
                    
                    do_action( 'wcfmd_after_order_item_mark_delivered', $delivery_detail->order_id, $delivery_detail->product_id, $delivery_detail );
                } elseif( !$delivered_not_notified ) {
                    // Admin Notification
                    $wcfm_messages = sprintf( __( 'Order <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery' ), '#<a class="wcfm_dashboard_item_title" target="_blank" href="'.get_wcfm_view_order_url($delivery_detail->order_id) . '">' . $order->get_order_number(). '</a>', '<a class="wcfm_dashboard_item_title" target="_blank" href="'.get_wcfm_delivery_boys_stats_url($delivery_detail->delivery_boy) . '">' . $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name . '</a>' );
                    $WCFM->wcfm_notification->wcfm_send_direct_message( -2, 0, 0, 0, $wcfm_messages, 'delivery_complete' );
                    
                    // Vendor Notification
                    if( $delivery_detail->vendor_id ) {
                        $WCFM->wcfm_notification->wcfm_send_direct_message( -1, $delivery_detail->vendor_id, 1, 0, $wcfm_messages, 'delivery_complete' );
                    }
                    
                    // Order Note
                    $wcfm_messages = sprintf( __( 'Order <b>%s</b> delivered by <b>%s</b>.', 'wc-frontend-manager-delivery' ), '#<span class="wcfm_dashboard_item_title">' . $order->get_order_number() . '</span>', $wcfm_delivery_boy_user->first_name . ' ' . $wcfm_delivery_boy_user->last_name );
                    $comment_id = $order->add_order_note( $wcfm_messages, apply_filters( 'wcfm_is_allow_delivery_note_to_customer', '1' ) );
                    
                    do_action( 'wcfmd_after_order_mark_delivered', $delivery_detail->order_id, $delivery_detail );
                    
                    $delivered_not_notified = true;
                }
            }
            
            return new WP_REST_Response(array(
                'status' => 'success',
                'response' => 1,
            ) , 200);
        }
        return new WP_REST_Response(array(
            'status' => 'success',
            'response' => -1,
        ) , 200);
		
	}
}
    
