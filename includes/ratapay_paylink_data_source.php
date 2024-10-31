<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Ratapay_PayLink_List extends WP_List_Table {

    /** Class constructor */
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'PayLink', 'sp' ), //singular name of the listed records
            'plural'   => __( 'PayLinks', 'sp' ), //plural name of the listed records
            'ajax'     => false //should this table support ajax?
        ] );

    }

    /**
     * Retrieve data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_data( $per_page = 15, $page_number = 1 ) {
      $time = ratapay_generate_isotime();
      $url = '/transaction/paylink/'.($page_number-1)* $per_page;

      $resultData = wp_remote_get(get_option( 'ratapay_api_uri' ) . $url, array(
          'headers' => array(
              'Authorization' => 'Bearer ' . get_option('ratapay_token'),
              'X-RATAPAY-SIGN' => ratapay_generate_sign('GET:'.$url, get_option('ratapay_token'), get_option('ratapay_api_secret'), $time, null),
              'X-RATAPAY-TS' => $time,
              'X-RATAPAY-KEY' => get_option('ratapay_api_key')
          ),
      ));
      $resData = json_decode(wp_remote_retrieve_body($resultData), true);
      if(empty($resData) || $resData['success'] == 0){
        if (empty($resData) || $resData['message'] == 'Invalid Access Token') {
            ratapay_renew_token();
            $resultData = wp_remote_get(get_option( 'ratapay_api_uri' ) . $url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . get_option('ratapay_token'),
                    'X-RATAPAY-SIGN' => ratapay_generate_sign('GET:'.$url, get_option('ratapay_token'), get_option('ratapay_api_secret'), $time, null),
                    'X-RATAPAY-TS' => $time,
                    'X-RATAPAY-KEY' => get_option('ratapay_api_key')
                ),
            ));
            $resData = json_decode(wp_remote_retrieve_body($resultData), true);
        }
      }
      return $resData;
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'note':
              return json_decode($item['data'])->note;
              break;
            case 'code':
              return $item[ $column_name ] . ' <input type="button" class="button button-primary link-copier" data-link="'.get_option('ratapay_app_uri') . '/pay/' . $item[ $column_name ] .'" value="Copy"/>';
              break;
            case 'id':
              return "
                <input class='button button-primary view-paylink' type='button' value='View' data-content='".$item['data']."' data-code='".$item['code']."'/>
              ";
              break;
            default:
                return $item[ $column_name ];//Show the whole array for troubleshooting purposes
        }
    }

    /** Text displayed when no rating data is available */
    public function no_items() {
      _e( 'No PayLinks avaliable.', 'sp' );
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
      $columns = [
          'code'  => __( 'PayLink Code', 'sp' ),
          'note'  => __( 'Note', 'sp' ),
          'created_at'    => __( 'Created At', 'sp' ),
          'updated_at'    => __( 'Updated At', 'sp' ),
          'id'    => __( 'View', 'sp' ),
        ];

      return $columns;
    }

        /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array();

        return $sortable_columns;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items($byDate = false, $dateStart = null, $dateEnd = null) {

      $columns = $this->get_columns();
      $hidden = array();
      $sortable = array();
      $this->_column_headers = array($columns, $hidden, $sortable);
      /** Process bulk action */
      // $this->process_bulk_action();

      $per_page     = $this->get_items_per_page( 'certificates_per_page', 15 );
      $current_page = $this->get_pagenum();

      $data = self::get_data( $per_page, $current_page );
      
      $total_items = $data['count'];
      
      $this->set_pagination_args( [
        'total_items' => $total_items, //WE have to calculate the total number of items
        'per_page'    => $per_page //WE have to determine how many items to show on a page
      ] );

      $this->items = $data['list'];

    }
}