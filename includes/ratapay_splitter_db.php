<?php
global $ratapay_db_version;
$ratapay_db_version = '1.0';

function ratapay_install_splitter_table() {
    global $wpdb;
    global $ratapay_db_version;

    $table_name = $wpdb->prefix . 'ratapay_splitter';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(64) NOT NULL,
        note text,
        data text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'ratapay_db_version', $ratapay_db_version );
}