<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Ratapay_Splitter_List extends WP_List_Table {

    /** Class constructor */
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Splitter', 'sp' ), //singular name of the listed records
            'plural'   => __( 'Splitters', 'sp' ), //plural name of the listed records
            'ajax'     => false //should this table support ajax?
        ] );

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
            case 'name':
              return "<span id='splitter-name-".$item['id']."'>".$item['name']."</span>";
              break;
            case 'note':
              return "<span id='splitter-note-".$item['id']."'>".$item['note']."</span>";
              break;
            case 'data':
              $showData = [];
              foreach (json_decode($item['data'], true) as $data) {
                $unit = (isset($data['share_type']) && $data['share_type'] == '%') ? '%' : '';
                $showData[] = $data['email'] . ' (' . number_format($data['share_amount'],0,',','.') . $unit .')';
              }
              return implode(', ', $showData);
              break;
            case 'id':
              return "
                <input class='button edit-split' type='button' value='Edit' data-content='".json_encode($item)."'/>
                <input class='button delete-split' type='button' value='Delete' data-splitter_id='".$item['id']."'/>
              ";
              break;
            default:
                return $item[ $column_name ];//Show the whole array for troubleshooting purposes
        }
    }

    /** Text displayed when no rating data is available */
    public function no_items() {
      _e( 'No Splitters avaliable.', 'sp' );
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
      $columns = [
          'name'  => __( 'Name', 'sp' ),
          'note'  => __( 'Note', 'sp' ),
          'data'  => __( 'Detail', 'sp' ),
          'id'    => __( 'Action', 'sp' ),
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
      global $wpdb;

      $columns = $this->get_columns();
      $hidden = array();
      $sortable = array();
      $this->_column_headers = array($columns, $hidden, $sortable);
      /** Process bulk action */
      // $this->process_bulk_action();

      $per_page     = 15;
      $current_page = $this->get_pagenum() - 1;

      $data['list'] = $wpdb->get_results("select * from {$wpdb->prefix}ratapay_splitter limit $current_page, $per_page", "ARRAY_A");
      
      $total_items = $wpdb->get_results("select count(id) as count from {$wpdb->prefix}ratapay_splitter", "ARRAY_A")[0]['count'];
      
      $this->set_pagination_args( [
        'total_items' => $total_items, //WE have to calculate the total number of items
        'per_page'    => $per_page //WE have to determine how many items to show on a page
      ] );

      $this->items = $data['list'];

    }
}