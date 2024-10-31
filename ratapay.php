<?php
/*
Plugin Name: Ratapay
Plugin URI: https://www.ratapay.co.id
Description: This plugin provide integration with Ratapay
Text Domain: ratapay
Version: 0.1.1
Author: Ratapay Dev
*/
require_once "includes/woocommerce_ratapay_gateway.php";
require_once "includes/ratapay_splitter_meta.php";
require_once "includes/ratapay_splitter_db.php";

// add payment link on email
function ratapay_render_payment_link( $order, $sent_to_admin, $plain_text, $email ) {
    if(isset($email->id)){
        if ( $email->id == 'customer_on_hold_order' ) {
            $payment_link = get_post_meta($order->ID, 'ratapay_payment_link', true);
            if(!empty($payment_link)){
                $uri = esc_url(base64_decode($payment_link));
                echo "<a href='$uri' target='_blank' class='button'>". __('Lanjut ke Pembayaran')."</a>";
            }
        }
    }
}
add_action( 'woocommerce_email_after_order_table', 'ratapay_render_payment_link', 20, 4 );

register_activation_hook( __FILE__, 'ratapay_install_splitter_table' );

add_action('admin_head', 'ratapay_icon');

function ratapay_icon() {
  echo '
  <style>
    @font-face {
      font-family: "icomoon";
      src: url("'.plugins_url("ratapay/ratapay.woff").'") format("woff");
      font-weight: normal;
      font-style: normal;
      font-display: block;
    }
    #adminmenu #toplevel_page_ratapay_menu .menu-icon-generic div.wp-menu-image:before {
      font-family: "icomoon" !important;
        content: "\e900";
        font-size: 1em !important;
        margin-top: 0.2em;
    } 
  </style>
  ';
}

add_action( 'admin_menu', 'ratapay_admin_menu' );
function ratapay_admin_menu()
{
    add_menu_page( __("Ratapay", "ratapay"), __("Ratapay", "ratapay"), 'administrator', 'ratapay_menu', 'ratapay_menu' );
    add_submenu_page('ratapay_menu', __('PayLink', 'ratapay'), __('PayLink', 'ratapay'), 'administrator', 'ratapay_paylink', 'ratapay_paylink');
    add_submenu_page('ratapay_menu', __('Splitter', 'ratapay'), __('Splitter', 'ratapay'), 'administrator', 'ratapay_splitter', 'ratapay_splitter');
    add_submenu_page('ratapay_menu', __('Setting', 'ratapay'), __('Setting', 'ratapay'), 'administrator', 'ratapay_setting', 'ratapay_setting');
}

function ratapay_splitter()
{
    require_once('includes/ratapay_splitter_data_source.php');
    wp_enqueue_script( 'jquery-ui-dialog' ); // jquery and jquery-ui should be dependencies, didn't check though...
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    add_action('admin_footer', 'ratapay_add_modal_js_to_footer');   

    ?>
    <h1 class='wp-heading-inline'>Ratapay Splitter <button class="button button-primary" id="create-splitter">Create</button></h1>
    <?php

    if (isset($_POST['delete'])) {
        global $wpdb;

        if (!is_numeric($_POST['code'])) {
            $class = 'notice notice-error';
            $message = __( 'Failed Deleting Splitter Data', 'ratapay-message' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

        } else {
            $wpdb->query("delete from {$wpdb->prefix}ratapay_splitter where id = " . sanitize_text_field($_POST['code']));

            $class = 'notice notice-success';
            $message = __( 'Splitter Delete Successfully', 'ratapay-message' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
    }
    if (isset($_POST['save'])) {
        global $wpdb;
        $postData['split'] = true;
        $splits = [];
        foreach ($_POST['splits'] as $index => $spl) {
            if (!in_array($spl['type'], ['%', '$'])) continue;
            if (!is_numeric($spl['amount']) || intval($spl['amount']) <= 0) continue;
            if (!filter_var($spl['email'], FILTER_VALIDATE_EMAIL)) continue;
            $splits[$spl['email']] = [
                'email' => sanitize_text_field($spl['email']),
                'share_amount' => sanitize_text_field($spl['amount']),
                'rebill_share_amount' => 0,
                'share_type' => sanitize_text_field($spl['type']),
                'merchant_id' => get_option('ratapay_merchant_id'),
            ];
        }

        if (!empty($splits)) {
            $table = $wpdb->prefix.'ratapay_splitter';
            $data = array('name' => sanitize_text_field($_POST['name']), 'note' => sanitize_text_field($_POST['note']), 'data' => json_encode($splits));
            $format = array('%s','%s', '%s');
            if (empty($_POST['splitter_id'])) {
                $wpdb->insert($table,$data,$format);
                $my_id = $wpdb->insert_id;
                $message = __( 'Splitter Created Successfully', 'ratapay-message' );
            } else {
                $where = array('id' => sanitize_text_field($_POST['splitter_id']));
                $whereFormat = array('%d');
                $my_id = $wpdb->update($table,$data,$where,$format,$whereFormat);
                $message = __( 'Splitter Updated Successfully', 'ratapay-message' );
            }

            if (!$my_id) {
                $class = 'notice notice-error';
                $message = __( 'Whoops, Error happened when creating Splitter, please try again.', 'ratapay-message' );
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
            } else {
                $class = 'notice notice-success';
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );             
            }
        } else {
            $class = 'notice notice-error';
            $message = __( 'Whoops, Error happened when creating Splitter, please try again.', 'ratapay-message' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );             
        }
    }

    $splitter = new Ratapay_Splitter_List();
    $splitter->prepare_items();
    $splitter->display();
    
    ?>

    <div id="view-splitter-dialog" class="hidden" style="max-width:800px">
        <form method='post'>
            <input type="hidden" name="splitter_id" id="splitter-id">
            <table class='form-table'>
                <tbody>
                    <tr>
                        <th><label>Splitter Name</label></th>
                        <td><input id="view-splitter-name" type='text' name='name'/></td>
                    </tr>
                    <tr>
                        <th><label>Splitter Note</label></th>
                        <td><textarea id="view-splitter-note" name="note"></textarea></td>
                    </tr>
                </tbody>
            </table>
            <ol id="view-beneficiaries"></ol>
            <button id="add-benef-btn" class="button button-primary">Add Beneficiaries</button>
            <input type='hidden' name='save' value='1'/>
            <p class='submit'><input name='submit' id='submit' class='button button-primary' value='Submit' type='submit'></p>
        </form>
    </div>

    <div id="delete-splitter-dialog" class="hidden" style="max-width:800px">
        <form method='post'>
            <h4>Really Delete Splitter Data?</h4>
            <input type="hidden" name="code" id="delete-splitter-id">
            <input type='hidden' name='delete' value='1'/>
            <p class='submit'><input name='submit' id='submit' class='button button-primary' value='Delete' type='submit'></p>
        </form>
    </div>

    <?php
}


function ratapay_save_paylink($postData)
{
    unset($postData['save']);
    unset($postData['submit']);
    $postData['linkType'] = 'open';
    $err = false;
    if(isset($postData['split'])){
        $postData['split'] = 1;
        $splits = [];
        foreach ($postData['splits'] as $index => $spl) {
            if (count($splits) < 3) {
                if (!is_numeric($postData['splits'][$index]['amount']) || intval($postData['splits'][$index]['amount']) <= 0) {
                    $err = true;
                    continue;
                }
                $splits[$postData['splits'][$index]['email']] = [
                    'email' => sanitize_text_field($postData['splits'][$index]['email']),
                    'share_amount' => sanitize_text_field($postData['splits'][$index]['amount']),
                    'rebill_share_amount' => 0,
                    'merchant_id' => get_option('ratapay_merchant_id'),
                ];
            }
        }
        $postData['vendor_share'] = $splits;
        unset($postData['splits']);
    }

    if(isset($postData['refundable']))$postData['refundable'] = 1;
    if(isset($postData['affable']))$postData['affable'] = 1;
    if (!isset($postData['amount']) || !is_numeric($postData['amount']) || intval($postData['amount']) <= 0) {
        $err = true;
    }
    if($postData['paytype'] == 2){
        if (!isset($postData['second_amount']) || !is_numeric($postData['second_amount']) || intval($postData['second_amount']) <= 0) {
            $err = true;
        } else {
            $postData['second_amount'] = sanitize_text_field($postData['recurring_amount']);
            $postData['first_period'] = sanitize_text_field($postData['recurring_interval_value']) . sanitize_text_field($postData['recurring_interval_unit']);
            $postData['second_period'] = sanitize_text_field($postData['first_period']);
        }
    };

    $encodedReqData = json_encode($postData);

    if ($err) {
        $class = 'notice notice-error';
        $message = __( 'Whoops, Error happened when creating Paylink, please try again.', 'ratapay-message' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
        return;
    }

    try {
        $time = ratapay_generate_isotime();
        $url = 'POST:/transaction/paylink';

        foreach ($postData as $key => $value) {
            if (empty($value)) {
                unset($postData[$key]);
            }
        }
        $resultData = wp_remote_post(get_option( 'ratapay_api_uri' ).'/transaction/paylink',array(
            'body'=> $postData,
            'headers' => array(
                'Authorization' => 'Bearer ' . get_option('ratapay_token'),
                'X-RATAPAY-SIGN' => ratapay_generate_sign($url, get_option('ratapay_token'), get_option('ratapay_api_secret'), $time, $postData),
                'X-RATAPAY-TS' => $time,
                'X-RATAPAY-KEY' => get_option('ratapay_api_key')
            ),
        ));
        $class = 'notice notice-success';
        $message = __( 'Paylink Saved Successfully', 'ratapay-message' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

        // $resData = json_decode(wp_remote_retrieve_body($resultData), true);
        // return $resData;
    }
    catch (Exception $e) {
        $class = 'notice notice-error';
        $message = __( 'Whoops, Error happened when creating Paylink, please try again.', 'ratapay-message' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
    }
}

function ratapay_paylink()
{
    require_once('includes/ratapay_paylink_data_source.php');
    wp_enqueue_script( 'jquery-ui-dialog' ); // jquery and jquery-ui should be dependencies, didn't check though...
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    add_action('admin_footer', 'ratapay_add_modal_js_to_footer');   

    ?>
    <h1 class='wp-heading-inline'>Ratapay PayLink <button class="button button-primary" id="create-paylink">Create</button></h1>
    <?php

    if (isset($_POST['save'])) {
        $result = ratapay_save_paylink($_POST);
    }

    $payLinks = new Ratapay_PayLink_List();
    $payLinks->prepare_items();
    $payLinks->display();
    
    ?>

    <div id="view-paylink-dialog" class="hidden" style="max-width:800px">
        <form method='post'>
            <input type="hidden" name="code" id="payment-code">
            <table class='form-table'>
                <tbody>
                    <tr>
                        <th><label>Payment Note</label></th>
                        <td><input id="view-payment-note" type='text' name='note'/></td>
                    </tr>
                    <tr>
                        <th><label>Payment Type</label></th>
                        <td>
                            <select id="view-payment-type" name="paytype">
                                <option value="1">One Time</option>
                                <option value="2">Recurring</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Amount</label></th>
                        <td><input id="view-payment-amount" type='number' name='amount'/></td>
                    </tr>
                    <tr>
                        <th><label>Rebill Amount</label></th>
                        <td><input id="view-payment-second-amount" type='number' name='recurring_amount'/></td>
                    </tr>
                    <tr>
                        <th><label>Rebill Interval</label></th>
                        <td>
                            <input id="view-payment-recur-value" size='10' type='number' name='recurring_interval_value'/>
                            <select id="view-payment-recur-unit" name="recurring_interval_unit">
                                <option value="D">Day</option>
                                <option value="M">Month</option>
                                <option value="Y">Year</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Refundable?</label></th>
                        <td>
                            <input id="view-payment-refundable" type='checkbox' name='refundable'/>
                            <div>
                                <label>Threshold</label>
                                <input id="view-refund-threshold-value" size='10' type='number' name='refund_threshold_value'/>
                                <select id="view-refund-threshold-unit" name="refund_threshold_unit">
                                    <option value="D">Day</option>
                                    <option value="M">Month</option>
                                    <option value="Y">Year</option>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Split Payment?</label></th>
                        <td>
                            <input id="view-payment-split" type='checkbox' name='split'/>
                            <ol id="view-beneficiaries"></ol>
                            <button id="add-benef-btn" class="button button-primary">Add Beneficiaries</button>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Payment Success URL</label></th>
                        <td>
                            <input id="view-payment-success-url" type='text' name='url_success'/>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type='hidden' name='save' value='1'/>
            <p class='submit'><input name='submit' id='submit' class='button button-primary' value='Submit' type='submit'></p>
        </form>
    </div>

    <?php
}

function ratapay_base_split_js_footer() {
    ?>
    <script type="text/javascript">
    var benefCount = 0;
    (function ($) {
        $(document).ready(function (){
          $('#add-benef-btn').on('click', function(e){
            e.preventDefault();
            var dt = Date.now();
            if (benefCount < 2) {
                $('#view-beneficiaries').first().append('<li><input required="required" type="email" placeholder="Beneficiary Email" name="splits['+dt+'][email]"/><input required="required" type="number" name="splits['+dt+'][amount]" placeholder="Split Amount"/><select required="required" name="splits['+dt+'][type]"><option value="">Split Type</option><option value="$">Fixed</option><option value="%">Percent</option></select><button class="button remove-beneficiary">Remove</button></li>');
            }
            benefCount++;
            if (benefCount >= 2) {
                $('#add-benef-btn').hide();
            }
          });

          $('#view-beneficiaries').on('click', '.remove-beneficiary',function(e){
            e.preventDefault();
            $(this).parent().remove();
            benefCount --;
            if (benefCount < 2) {
                $('#add-benef-btn').show();
            }
          });

          $('#view-payment-split').on('click', function() {
            if ($(this).prop('checked')) {
                $('#view-beneficiaries').show();
                if (benefCount > 1) {
                    $('#add-benef-btn').hide();
                } else {
                    $('#add-benef-btn').show();
                }
            } else {
                $('#view-beneficiaries').hide();
                $('#add-benef-btn').hide();                
            }
          });

        });
    })(jQuery);
    </script>
    <?php
}

function ratapay_add_modal_js_to_footer() {
    ratapay_base_split_js_footer();
    $slug = sanitize_text_field($_GET['page']);
    ?>
    <script>
    function copyStringToClipboard (str) {
       // Create new element
       var el = document.createElement('textarea');
       // Set value (string to be copied)
       el.value = str;
       // Set non-editable to avoid focus and move outside of view
       el.setAttribute('readonly', '');
       el.style = {position: 'absolute', left: '-9999px'};
       document.body.appendChild(el);
       // Select text inside element
       el.select();
       // Copy text to clipboard
       document.execCommand('copy');
       // Remove temporary element
       document.body.removeChild(el);
    }

    (function ($) {
      $(document).ready(function (){
        $('.delete-split').click(function(){
            $('#delete-splitter-dialog').dialog('open');
            $('#delete-splitter-id').val($(this).data('splitter_id'));
        })

        $('.edit-split').click(function(e){
            e.preventDefault();
            var contents = $(this).data('content');
            $('#splitter-id').val(contents.id);
            $('#view-splitter-note').val(contents.note);
            $('#view-splitter-name').val(contents.name);

            $('#view-beneficiaries').empty();
            benefCount = 0;
            var list = '';
            var splitBenefs = JSON.parse(contents['data']);
            for (var s in splitBenefs) {
                if (splitBenefs.hasOwnProperty(s)) {
                    var listItem = '<li><input type="email" value="'+splitBenefs[s].email+'" name="splits['+s+'][email]"/><input type="number" value="'+splitBenefs[s].share_amount+'" name="splits['+s+'][amount]"/><select required="required" name="splits['+s+'][type]">';
                    if (splitBenefs[s].share_type == '%') {
                        listItem += '<option value="">Split Type</option><option value="$">Fixed</option><option value="%" selected="selected">Percent</option>';
                    } else {
                        listItem += '<option value="">Split Type</option><option selected="selected" value="$">Fixed</option><option value="%">Percent</option>';
                    }
                    listItem += '</select><button class="button remove-beneficiary">Remove</button></li>'
                    list += listItem;
                }
                benefCount ++;
            }
            if (benefCount == 2) {
                $('#add-benef-btn').hide();
            } else {
                $('#add-benef-btn').show();
            }
            $('#view-beneficiaries').append(list);
            $('#view-splitter-dialog').dialog('open');
        })

          $('.link-copier').on('click', function(e){
            e.preventDefault();
            copyStringToClipboard($(this).data('link'));
            alert('Link Copied');
          });

          $('#view-payment-type').on('change', function(){
            if ($(this).val() == 1) {
                $('#view-payment-second-amount').parent().parent().hide();
                $('#view-payment-recur-value').parent().parent().hide();
                $('#view-payment-recur-unit').parent().parent().hide();
            } else {
                $('#view-payment-second-amount').parent().parent().show();
                $('#view-payment-recur-value').parent().parent().show();
                $('#view-payment-recur-unit').parent().parent().show();
            }
          });

          $('#view-payment-refundable').on('click', function() {
            if ($(this).prop('checked')) {
                $('#view-refund-threshold-value').parent().show();
            } else {
                $('#view-refund-threshold-value').parent().hide();
            }
          });

          $('#view-payment-affable').on('click', function() {
            if ($(this).prop('checked')) {
                $('#view-aff-comm').parent().show();
            } else {
                $('#view-aff-comm').parent().hide();
            }
          });

          // initalise the dialog
          $('#delete-splitter-dialog').dialog({
            title: 'Delete Splitter',
            dialogClass: 'wp-dialog',
            autoOpen: false,
            draggable: false,
            width: 'auto',
            modal: true,
            resizable: false,
            closeOnEscape: true,
            position: {
              my: "center",
              at: "center",
              of: window
            },
            open: function () {
              // close dialog by clicking the overlay behind it
              $('.ui-widget-overlay').bind('click', function(){
                $('#delete-splitter-dialog').dialog('close');
              })
            },
            create: function () {
              // style fix for WordPress admin
              $('.ui-dialog-titlebar-close').addClass('ui-button');
            },
          });

          $('#view-paylink-dialog').dialog({
            title: 'PayLink Details',
            dialogClass: 'wp-dialog',
            autoOpen: false,
            draggable: false,
            width: 'auto',
            modal: true,
            resizable: false,
            closeOnEscape: true,
            position: {
              my: "center",
              at: "center",
              of: window
            },
            open: function () {
              // close dialog by clicking the overlay behind it
              $('.ui-widget-overlay').bind('click', function(){
                $('#view-paylink-dialog').dialog('close');
              })
            },
            create: function () {
              // style fix for WordPress admin
              $('.ui-dialog-titlebar-close').addClass('ui-button');
            },
          });

          $('#view-splitter-dialog').dialog({
            title: 'Splitter Details',
            dialogClass: 'wp-dialog',
            autoOpen: false,
            draggable: false,
            width: 'auto',
            modal: true,
            resizable: false,
            closeOnEscape: true,
            position: {
              my: "center",
              at: "center",
              of: window
            },
            open: function () {
              // close dialog by clicking the overlay behind it
              $('.ui-widget-overlay').bind('click', function(){
                $('#view-splitter-dialog').dialog('close');
              })
            },
            create: function () {
              // style fix for WordPress admin
              $('.ui-dialog-titlebar-close').addClass('ui-button');
            },
          });
          // bind a button or a link to open the dialog
          $('#create-splitter').on('click', function(e) {
            e.preventDefault();
            benefCount = 0;
            $('#splitter-id').val('');
            $('#view-splitter-note').val('');
            $('#view-splitter-name').val('');
            $('#view-splitter-dialog').dialog('open');
            $('#view-beneficiaries').empty();
            $('#view-beneficiaries').show();
            $('#add-benef-btn').show();
          });

          $('#create-paylink').on('click', function(e) {
            e.preventDefault();
            benefCount = 0;
            $('#payment-code').val(null);
            $('#view-payment-note').val('');
            $('#view-payment-type').val(1);
            $('#view-payment-amount').val(0);
            $('#view-payment-refundable').removeAttr('checked');
            $('#view-refund-threshold-value').parent().hide();
            $('#view-payment-affable').removeAttr('checked');
            $('#view-aff-comm').parent().hide();
            $('#view-payment-second-amount').parent().parent().hide();
            $('#view-payment-recur-value').parent().parent().hide();
            $('#view-payment-recur-unit').parent().parent().hide();
            $('#view-payment-split').removeAttr('checked');
            $('#view-beneficiaries').empty();
            $('#view-beneficiaries').hide();
            $('#add-benef-btn').hide();
            $('#view-payment-success-url').val('');
            $('#view-paylink-dialog').dialog('open');
          });

          $('input.view-paylink').on('click', function(e) {
            e.preventDefault();
            var contents = $(this).data('content');
            var code = $(this).data('code');
            $('#view-splitter-note').val($('#splitter-note-'+code).html());
            $('#view-splitter-name').val($('#splitter-name-'+code).html());
            $('#payment-code').val($(this).data('code'));
            $('#view-payment-note').val(contents['note']);
            $('#view-payment-type').val(contents['paytype']);
            $('#view-payment-amount').val(contents['amount']);
            $('#view-payment-success-url').val(contents['url_success']);
            if (contents['refundable'] == 1) {
                $('#view-payment-refundable').attr('checked', 'checked');
                $('#view-refund-threshold-value').val(contents['refund_threshold_value']);
                $('#view-refund-threshold-unit').val(contents['refund_threshold_unit']);
                $('#view-refund-threshold-value').parent().show();
            }
            else {
                $('#view-payment-refundable').removeAttr('checked');
                $('#view-refund-threshold-value').parent().hide();
            }

            if (contents['affable'] == 1) {
                $('#view-payment-affable').attr('checked', 'checked');
                $('#view-aff-comm').val(contents['aff_comm']);
                $('#view-aff-comm').parent().show();
            }
            else {
                $('#view-payment-affable').removeAttr('checked');
                $('#view-aff-comm').parent().hide();
            }

            if (contents['paytype'] == 1) {
                $('#view-payment-second-amount').parent().parent().hide();
                $('#view-payment-recur-value').parent().parent().hide();
                $('#view-payment-recur-unit').parent().parent().hide();
            } else {
                $('#view-payment-second-amount').parent().parent().show();
                $('#view-payment-second-amount').val(contents['recurring_amount']);
                $('#view-payment-recur-value').parent().parent().show();
                $('#view-payment-recur-value').val(contents['recurring_interval_value']);
                $('#view-payment-recur-unit').parent().parent().show();
                $('#view-payment-recur-unit').val(contents['recurring_interval_unit']);
            }

            $('#view-beneficiaries').empty();
            benefCount = 0;
            var list = '';
            for (var s in contents['vendor_share']) {
                if (contents['vendor_share'].hasOwnProperty(s)) {
                    list += '<li><input type="email" value="'+contents['vendor_share'][s].email+'" name="splits[][email]"/><input type="number" value="'+contents['vendor_share'][s].share_amount+'" name="splits[][amount]"/><button class="button remove-beneficiary">Remove</button>' + '</li>';
                }
                benefCount ++;
            }
            $('#view-beneficiaries').append(list);

            <?php if($slug != 'ratapay_splitter'): ?>
            if (contents['split']) {
                $('#view-payment-split').attr('checked','checked');
                $('#view-beneficiaries').show();
                if (benefCount > 1) {
                    $('#add-benef-btn').hide();
                } else {
                    $('#add-benef-btn').show();
                }
            } else {
                $('#view-payment-split').removeAttr('checked');
                $('#view-beneficiaries').hide();
                $('#add-benef-btn').hide();                
            }
            <?php endif; ?>
            $('#view-paylink-dialog').dialog('open');
          });
      });
    })(jQuery);
    </script>    
    <?php
}

function ratapay_menu()
{
    $resAccount = ratapay_get_own_account();
    if(empty($resAccount) || $resAccount['success'] == 0){
        if (empty($resAccount) || $resAccount['message'] == 'Invalid Access Token') {
            ratapay_renew_token();
            $resAccount = ratapay_get_own_account();
        }
    }

    echo "<h1>Ratapay</h1>";

    $postData['account_number'] = $resAccount['account']['account_number'];
    $postData['offset'] = 0;
    $postData['limit'] = 20;
    $postData['complete'] = true;
    $encodedReqData = json_encode($postData);
    $statuses = [
        0 => 'pending',
        1 => 'success',
        3 => 'cancelled',
        4 => 'failed',
        5 => 'recurring active',
        6 => 'recurring cancelled',
        7 => 'recurring failed',
    ];
    $isSandbox = get_option('ratapay_sandbox');
    ?>
    <div class="postbox" style="width: 25%">
        <div class="inside">
            <ul>
                <li>Account Balance <?php esc_html_e($resAccount['account']['email']) ?></li>
                <li> <h1>Rp <?php esc_html_e(number_format($resAccount['account']['account_balance'], null)) ?></h1>
                </li>
                <li><a href="<?php printf('https://app%s.ratapay.co.id/topup', $isSandbox ? 'dev' : ''); ?>" target="_blank" class="button button-primary">Topup Balance</a> <a href="<?php printf('https://app%s.ratapay.co.id/withdraw', $isSandbox ? 'dev' : ''); ?>" target="_blank" class="button">Withdraw</a></li>
            </ul>
        </div>
    </div>
    <div class="postbox">
        <div class="inside">
            <h2 style="text-align: center">Account History</h2>
            <table class="widefat fixed">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Final Balance</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>
                <?php if($resAccount['success'] && isset($resAccount['activity'])): ?>
                <?php foreach($resAccount['activity'] as $l): ?>
                <tr>
                  <td><?php esc_html_e($l['date_time']) ?></td>
                  <td><?php esc_html_e($l['amount']) ?></td>
                  <td><?php esc_html_e($statuses[$l['status']]) ?></td>
                  <td><?php esc_html_e($l['acc_balance']) ?></td>
                  <td><?php esc_html_e($l['note']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td class="text-center" colspan="5">No Data</td></tr>
                <?php endif; ?>
              </tbody>
          </table>
          <p>View Complete List on <a href="<?php printf('https://app%s.ratapay.co.id/', $isSandbox ? 'dev' : ''); ?>" target="_blank" class="button button-primary">Ratapay</a></p>
        </div>
    </div>
    <?php
}

function ratapay_get_own_account()
{
    $postData = array(
        'merchant_id' => get_option('ratapay_merchant_id'),
        'ts' => time(),
    );
    $time = ratapay_generate_isotime();
    $url = 'GET:/account';
    $resultAccount = wp_remote_get(get_option( 'ratapay_api_uri' ).'/account',array(
        'headers' => array(
            'Authorization' => 'Bearer ' . get_option('ratapay_token'),
            'X-RATAPAY-SIGN' => ratapay_generate_sign($url, get_option('ratapay_token'), get_option('ratapay_api_secret'), $time, null),
            'X-RATAPAY-TS' => $time,
            'X-RATAPAY-KEY' => get_option('ratapay_api_key')
        ),
    ));

    $resAccount = json_decode(wp_remote_retrieve_body($resultAccount), true);
    return $resAccount;
}

function ratapay_setting()
{
    $resAccount = ratapay_get_own_account();
    if(isset($_POST['save'])){
        $error = false;
        try{
            if (!empty($_POST['ratapay_invoice_prefix'])) {
                if (strlen($_POST['ratapay_invoice_prefix']) > 6) {
                    throw new Exception("Invoice prefix maximum 6 characters");
                } elseif (!ctype_alnum($_POST['ratapay_invoice_prefix'])) {
                    throw new Exception("Invoice prefix should be alphanumeric");
                } elseif (!is_numeric($_POST['merchant_id'])) {
                    throw new Exception("Merchant ID should be numeric");
                }
            }

            update_option('ratapay_api_uri', empty($_POST['sandbox']) ? 'https://api.ratapay.co.id/v2' : 'https://dev.ratapay.co.id/v2');
            update_option('ratapay_app_uri', empty($_POST['sandbox']) ? 'https://app.ratapay.co.id' : 'https://appdev.ratapay.co.id');
            update_option('ratapay_merchant_id', sanitize_text_field($_POST['merchant_id']));
            update_option('ratapay_merchant_secret',sanitize_text_field($_POST['merchant_secret']));
            update_option('ratapay_api_key', sanitize_text_field($_POST['api_key']));
            update_option('ratapay_api_secret',sanitize_text_field($_POST['api_secret']));
            update_option('ratapay_refund_threshold',sanitize_text_field($_POST['ratapay_refund_threshold']));
            update_option('ratapay_sandbox',sanitize_text_field($_POST['sandbox']));
            update_option('ratapay_use_success_url',sanitize_text_field($_POST['ratapay_use_success_url']));
            update_option('ratapay_success_url',sanitize_text_field($_POST['ratapay_success_url']));
            update_option('ratapay_checkout_redirect',sanitize_text_field($_POST['ratapay_checkout_redirect']));
            update_option('ratapay_invoice_prefix',sanitize_text_field($_POST['ratapay_invoice_prefix']));
            update_option('ratapay_invoice_title',sanitize_text_field($_POST['ratapay_invoice_title']));
            if((!empty($_POST['merchant_id']) && !empty($_POST['merchant_secret'])) && (empty($resAccount) || $resAccount['success'] == 0)){
                if (empty($resAccount) || $resAccount['message'] == 'Invalid Access Token') {
                    ratapay_renew_token();
                    $resAccount = ratapay_get_own_account();
                }
            }
        }
        catch(Exception $e){
            $error = true;
            $class = 'notice notice-error';
            $msg = $e->getMessage();
            if (!empty($msg)) {
                $message = __( $msg, 'ratapay-message' );
            } else {
                $message = __( 'Whoops, Error happened when updating settings, please try again.', 'ratapay-message' );
            }
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
        }

        if (!$error) {
            $class = 'notice notice-success';
            $message = __( 'Settings Saved', 'ratapay-message' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
        }
    }

    $merchant_id = get_option('ratapay_merchant_id');
    $api_key = get_option('ratapay_api_key');
    $api_secret = get_option('ratapay_api_secret');
    $merchant_id = get_option('ratapay_merchant_id');
    $api_uri = get_option('ratapay_api_uri');
    $merchant_secret = get_option('ratapay_merchant_secret');
    $ratapay_sandbox = get_option('ratapay_sandbox');
    $ratapay_use_success_url = get_option('ratapay_use_success_url');
    $ratapay_refund_threshold = get_option('ratapay_refund_threshold');
    $ratapay_success_url = get_option('ratapay_success_url');
    $ratapay_invoice_prefix = get_option('ratapay_invoice_prefix');
    $ratapay_invoice_title = get_option('ratapay_invoice_title');
    $ratapay_checkout_redirect = get_option('ratapay_checkout_redirect');

    if (empty($ratapay_checkout_redirect)) {
        $ratapay_checkout_redirect = 'ratapay';
    }

    if (empty($ratapay_invoice_prefix)) {
        $ratapay_invoice_prefix = 'wp'.rand(0,100);
    }

    $checked = empty($ratapay_sandbox) ? '' : 'checked="checked"';
    $checkedSuccess = empty($ratapay_use_success_url) ? '' : 'checked="checked"';
    $styleSuccess = empty($ratapay_use_success_url) ? 'display:none' : '';
    if (isset($resAccount['account'])) {
        $accountDetail = "<tr>
                <th><label>Ratapay Merchant Account</label></th>
                <td><strong>". esc_html($resAccount['account']['name']) . ' (' . esc_html($resAccount['account']['email']) . ')' ."</strong></td>
            </tr>";
    } else {
        $accountDetail = '';
    }
    echo "
    <div class='wrap'>
    <h1 class='wp-heading-inline'>Ratapay Setting</h1>
    <form method='post'>
    <table class='form-table'>
        <tbody>
            $accountDetail
            <tr>
                <th><label>Ratapay Merchant ID *</label></th>
                <td><input size='10' type='text' name='merchant_id' value='$merchant_id' required='required'/></td>
            </tr>
            <tr>
                <th><label>Ratapay Merchant Secret *</label></th>
                <td><input size='50' type='text' name='merchant_secret' value='$merchant_secret' required='required'/></td>
            </tr>
            <tr>
                <th><label>Ratapay API Key *</label></th>
                <td><input size='50' type='text' name='api_key' value='$api_key' required='required'/></td>
            </tr>
            <tr>
                <th><label>Ratapay API Secret *</label></th>
                <td><input size='50' type='text' name='api_secret' value='$api_secret' required='required'/></td>
            </tr>
            <tr>
                <th><label>Default Refund Threshold (Day)</label><i style='padding:1px 5px' class='fa fa-question' title='Define refund threshold for a cart which will affect all items inside the cart which does not have its own refund threshold set'></i></th>
                <td><input size='50' type='text' name='ratapay_refund_threshold' value='$ratapay_refund_threshold'/></td>
            </tr>
            <tr>
                <th><label>Checkout Redirect</label><i style='padding:1px 5px' class='fa fa-question' title='Where to redirect customer after they choose ratapay as payment then checking out their cart'></th>
                <td>
                    <span><input id='checkout_ratapay' size='50' type='radio' name='ratapay_checkout_redirect' value='ratapay' ".($ratapay_checkout_redirect == 'ratapay' ? 'checked="checked"' : '')."/><label for='checkout_ratapay'>Redirect to Ratapay</label></span><br>
                    <span><input id='checkout_woo' size='50' type='radio' name='ratapay_checkout_redirect' value='woo' ".($ratapay_checkout_redirect == 'woo' ? 'checked="checked"' : '')."/><label for='checkout_woo'>Redirect to Default WooCommerce Order Received Page</label></span>
                </td>
            </tr>
            <tr>
                <th><label>Success URL<br><small>Link to Redirect User After Successful Payment</small></label><i style='padding:1px 5px' class='fa fa-question' title='Where to redirect customer after they successfully paid the invoice from Ratapay payment page'></th>
                <td><input type='checkbox' name='ratapay_use_success_url' $checkedSuccess value='1'/><span id='custom_success_url' style='$styleSuccess'><input size='50' type='text' name='ratapay_success_url' value='$ratapay_success_url' placeholder='https://mystore.com/thanks'/><p>if left blank, default to WooCommerce order received page</p></span></td>
            </tr>
            <tr>
                <th><label>Invoice Prefix *<br><small>Max. 6 Alphanumeric Characters</small></label><i style='padding:1px 5px' class='fa fa-question' title='Invoice Prefix to differentiate one site from the other which use the same merchant ID and merchant Secret'></th>
                <td><input size='50' type='text' name='ratapay_invoice_prefix' value='$ratapay_invoice_prefix' required='required'/></td>
            </tr>
            <tr>
                <th><label>Custom Invoice Title Format</label><i style='padding:1px 5px' class='fa fa-question' title='Invoice title format to be used on Ratapay invoice title'></th>
                <td><input size='50' type='text' name='ratapay_invoice_title' value='$ratapay_invoice_title'/><p>Available placeholders: {invoice_id}, {customer_name}, {customer_email}, {customer_phone}</p><p>e.g. INV#{invoice_id} {customer_name}</p></td>
            </tr>
            <tr>
                <th><label>Ratapay Sandbox Mode</label><a style='padding:1px 5px' href='https://ratapay.co.id/sandbox-mode' target='_blank' title='Sandbox Mode is a mode used for testing purpose, click for more information'><i class='fa fa-question'></i></a></th>
                <td><input type='checkbox' name='sandbox' $checked value='1'/></td>
            </tr>
        </tbody>
    </table>
    <input type='hidden' name='save' value='1'/>
    <p class='submit'><input name='submit' id='submit' class='button button-primary' value='Save Settings' type='submit'></p>
    </form>
    <script>
        jQuery('input[name=ratapay_use_success_url]').on('change', function () {
            if (jQuery(this).prop('checked')) {
                jQuery('#custom_success_url').show();
            } else {
                jQuery('#custom_success_url').hide();
            }
        });
    </script>
    </div>
    ";
}
// renew ratapay api token hourly

if ( ! wp_next_scheduled( 'ratapay_hourly_task' ) ) {
  wp_schedule_event( current_time('timestamp'), 'hourly', 'ratapay_hourly_task' );
}

add_action( 'ratapay_hourly_task', 'ratapay_renew_token' );

function ratapay_renew_token()
{
    $postData = [
        'grant_type' => 'client_credentials',
        'client_id' => get_option('ratapay_merchant_id'),
        'client_secret' => get_option('ratapay_merchant_secret'),
        'scope' => '*',
    ];
    $result = wp_remote_post(get_option( 'ratapay_api_uri' ).'/oauth/token',array(
        'body'=>$postData,
        'timeout' => 300
    ));

    $resBody = json_decode(wp_remote_retrieve_body($result), true);
    if (isset($resBody['access_token']) && !empty($resBody['access_token'])) {
        update_option('ratapay_token',$resBody['access_token']);
    }
}

function ratapay_sandbox_notice() {
    $isSandbox = get_option('ratapay_sandbox');
    if (!empty($isSandbox)) {
        ?>
        <div class="notice notice-warning" style="padding: 15px">
            <h3 style="display: inline;"><?php esc_html_e( 'Ratapay Plugin is In Sandbox Mode.', 'ratapay-message' ); ?></h3><p style="display: inline;font-size: 1rem"><?php esc_html_e( 'Please Switch off Sandbox Mode Before Going Live ', 'ratapay-message' ); ?></p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'ratapay_sandbox_notice' );

function ratapay_instruction_notice() {
    $merchant_id = get_option('ratapay_merchant_id');
    if (empty($merchant_id)) {
        ?>
        <div class="notice notice-info" style="padding: 15px">
            <p style="display: inline;font-weight: bold;"><?php esc_html_e( 'Ratapay plugin is not configured yet', 'ratapay-message' ); ?></p> <a href="https://ratapay.co.id/plugin-wordpress" target='_blank' style="display: inline;font-size: 1rem" class="button button-primary"> <?php esc_html_e( 'Instruction', 'ratapay-message' ); ?> </a>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'ratapay_instruction_notice' );

function ratapay_generate_sign($url, $auth_token, $secret_key, $isoTime, $bodyToHash)
{
    $hash = null;
    if (is_array($bodyToHash) && !empty($bodyToHash)) {
        ksort($bodyToHash);
        $encoderData = json_encode($bodyToHash, JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK);
        $encoderData = preg_replace('/\s/', '', $encoderData);
        $hash        = hash("sha256", $encoderData);
    } else {
        $hash = hash("sha256", "");
    }

    $stringToSign   = $url . ":" . $auth_token . ":" . $hash . ":" . $isoTime;
    $stringToSign = preg_replace('/\s/', '', $stringToSign);

    $auth_signature = hash_hmac('sha256', $stringToSign, $secret_key);

    return $auth_signature;
}

function ratapay_generate_isotime ()
{
    $fmt = date('Y-m-d\TH:i:s');
    $time = sprintf("$fmt.%s%s", substr(microtime(), 2, 3), date('P'));
    return $time;
}