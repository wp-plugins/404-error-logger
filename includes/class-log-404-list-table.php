<?php
if( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Log_404_List_Table extends WP_List_Table {
    private $log_404_table;
	private $extra_columns;
   
    function __construct( $extra_columns ){
		$this->log_404_table = $GLOBALS['wpdb']->prefix . '404_log';
		$this->extra_columns = $extra_columns;
		parent::__construct();
    }

    function get_columns(){
        $cols = array(
            'cb' => '<input type="checkbox" />',
            'date' => 'Date',
			'url' => 'URL',
			'ua' => 'User Agent',
			'ref' => 'HTTP Referer',
			'ip' => 'IP Address'
		);
		
		foreach( array( 'ua', 'ref', 'ip' ) as $c )
			if( ! in_array( $c, $this->extra_columns ) )
				unset( $cols[$c] );
		
		return $cols;
    }
    
    function get_sortable_columns() {
         return array(
            'date' => 'date',
			'url' => 'url',
            'ua' => 'ua',
			'ref' => 'ref',
			'ip' => 'ip'
        );
    }
    
    function get_bulk_actions() {
        return array(
            'delete'    => 'Delete'
        );
    }
	
	protected function column_default( $item, $column_name ){
		return esc_html( $item->$column_name );
    }
	
	protected function column_url( $item ){
		$url = esc_html( $item->url );	// don't use esc_url because it could be an invalid one that 404d
		return "<a href='$url' class='log_404_url' target='_blank'>$url</a>";
    }

	protected function column_ip( $item ){
		$ip = esc_html( $item->ip );
		return "<a href='https://duckduckgo.com/?q=%21whois%20$ip' target='_blank' title='Information about this IP address'>$ip</a>";
    }
	
	protected function column_cb( $item ){
		return "<input type='checkbox' name='delete_404[]' value='$item->id' />";
    }
	
    private function bulk_delete() {
		global $wpdb;
		if( empty( $_REQUEST['delete_404'] ) ) {
			return;
		}

		$item_ids = array_map( 'intval', (array) $_REQUEST['delete_404'] );
		$item_ids = implode(',', $item_ids);
		return $wpdb->query( "DELETE FROM $this->log_404_table WHERE id IN ($item_ids)" );
    }
    
    function prepare_items() {
		global $wpdb;
		
		// process bulk deletes
		if( 'delete' === $this->current_action() ) {
			$deleted = $this->bulk_delete();
			echo '<div id="message" class="updated"><p>' . $deleted . ' rows deleted</p></div>';
		}
        
		$page = $this->get_pagenum();
		$per_page = 50;
		$orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
		if( !in_array( $orderby, $this->get_sortable_columns() ) )
			$orderby = 'date';
		
		$order = isset( $_GET['order'] ) && strtolower( $_GET['order'] ) == 'asc' ? 'ASC' : 'DESC';
		$limit = ( ( $page - 1 ) * $per_page ) . ',' . $per_page;
		$search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '' ;
		
		if ( ! empty( $search ) ) {
			$search = like_escape( $search );
			$search = " AND ( (url LIKE '%$search%') OR (ip LIKE '%$search%') OR (ref LIKE '%$search%') ) ";
		}

		$this->items = $wpdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM $this->log_404_table WHERE 1=1 $search ORDER BY $orderby $order LIMIT $limit" );
        $total_items = $wpdb->get_var( "SELECT FOUND_ROWS()" );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items/$per_page )
        ) );
    } 
}
