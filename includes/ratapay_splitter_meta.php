<?php

add_action( 'add_meta_boxes', 'ratapay_splitter_metabox');
function ratapay_splitter_metabox() {
    add_meta_box(
        'ratapay_splitter_metabox', // $id
        'Ratapay Splitter', // $title
        'ratapay_choose_split_template', // $callback
        'product', // $screen
        'normal', // $context
        'high' // $priority
    );
}

function ratapay_choose_split_template()
{
    global $post, $wpdb;
    $selectedSplit = get_post_meta($post->ID, 'ratapay_splitter_id', true);
    $refundThreshold = get_post_meta($post->ID, 'refund_threshold', true);
    $splitList = $wpdb->get_results("select id, name from {$wpdb->prefix}ratapay_splitter", 'ARRAY_A');
    ?>
    <label><h4>Split Template</h4></label>
    <select name="split_template">
        <option value="0">No Split</option>
        <?php foreach($splitList as $spl): ?>
            <option value="<?php esc_html_e($spl['id']) ?>" <?php if($selectedSplit == $spl['id']): ?>selected="selected" <?php endif; ?>><?php esc_html_e($spl['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <a class="button-primary button" href="<?php echo admin_url('admin.php?page=ratapay_splitter') ?>">Create New</a>

    <label><h4>Split Refund Threshold</h4></label>
    <input type="number" name="refund_threshold" value="<?php echo !empty($refundThreshold) ? esc_html($refundThreshold) : 0 ?>" /><span> Day</span>
    <?php
}

function ratapay_save_splitter_meta( $post_id ) {
    if (isset($_POST['split_template'])) {
        // check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        // check permissions
        if( !current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        if (!is_numeric($_POST['split_template'])) return $post_id;        
        if (!is_string($_POST['refund_threshold'])) return $post_id;        
        update_post_meta( $post_id, 'ratapay_splitter_id', sanitize_text_field($_POST['split_template']) );
        update_post_meta( $post_id, 'refund_threshold', sanitize_text_field($_POST['refund_threshold']) );
        return $post_id;
    }
}

add_action('save_post', 'ratapay_save_splitter_meta');

// ADDING ratapay split template TO ADMIN PRODUCTS LIST
add_filter( 'manage_edit-product_columns', 'ratapay_custom_product_column',11);
function ratapay_custom_product_column($columns)
{
   //add columns
   $columns['split'] = __( 'Split','woocommerce'); // title
   return $columns;
}

// ADDING THE DATA FOR EACH PRODUCTS BY COLUMN
add_action( 'manage_product_posts_custom_column' , 'ratapay_custom_product_list_column_content', 10, 2 );
function ratapay_custom_product_list_column_content( $column, $product_id )
{
    global $post, $wpdb;

    $splitterId = get_post_meta($product_id, 'ratapay_splitter_id', true);
    if (!empty($splitterId)) {
        $splitRecord = $wpdb->get_results("select * from {$wpdb->prefix}ratapay_splitter where id = " . $splitterId, 'ARRAY_A')[0];
    } else {
        $splitRecord = [
            'name' => '-',
            'data' => '{}'
        ];
    }
    switch ( $column )
    {
        case 'split' :
            $splitDetails = json_decode($splitRecord['data'], true);
            if (!empty($splitDetails)) {
                $splitList = "<ol style='margin: 0 0 0 15px;'>";
                foreach ($splitDetails as $sd) {
                    $unit = (isset($sd['share_type']) && $sd['share_type'] == '%') ? '%' : '';
                    $splitList .= sprintf("<li style='margin:0'>%s</li>", esc_html($sd['email']) . ' ' . number_format($sd['share_amount'], 0, null, '.') . $unit);
                }
                $splitList .= "</ol>";
            } else {
                $splitList = '';
            }
            echo esc_html($splitRecord['name']) . '<br>' . $splitList; // display the data
            break;
    }
}