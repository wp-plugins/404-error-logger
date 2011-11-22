<?php 
if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) 
	exit;

function remove_404_log_table(){
	global $wpdb;
	$table = $wpdb->prefix . '404_log';
	$wpdb->query( "DROP TABLE $table;" );
}

remove_404_log_table();
delete_option( '404_error_logger_options' );
?>
