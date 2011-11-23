<?php
/*
Plugin Name: 404 Error Logger
Plugin URI: http://rayofsolaris.net/code/404-error-logger-for-wordpress
Description: A simple plugin to log 404 (Page Not Found) errors on your site.
Version: 0.1.1
Author: Samir Shah
Author URI: http://rayofsolaris.net/
License: GPL2
*/

if( ! defined( 'ABSPATH' ) )
	exit;

class Log_404 {
	const db_version = 1;
	const opt = '404_error_logger_options';
	private $options;
	private $table;
	private $list_table;
	
	function __construct() {
		// load options
		$this->options = get_option( self::opt, array() );
		$this->table = $GLOBALS['wpdb']->prefix . '404_log';
		
		if( !isset( $this->options['db_version'] ) || $this->options['db_version'] < self::db_version ) {
			// upgrade placeholder
			$this->install_table();
			$defaults = array( 'max_entries' => 500, 'also_record' => array( 'ip', 'ua', 'ref' ) );
			foreach( $defaults as $k => $v )
				if( !isset( $this->options[$k] ) )
					$this->options[$k] = $v;
				
			$this->options['db_version'] = self::db_version;
			update_option( self::opt, $this->options );
		}

		add_action( 'template_redirect', array( $this, 'log_404s' ) );
		
		if( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'settings_menu' ) );
			add_action( 'admin_head', array( $this, 'load_table' ) );
		}
	}
	
	private function install_table() {
		// remember, two spaces after PRIMARY KEY otherwise WP borks
		$sql = "CREATE TABLE $this->table (
			id SMALLINT NOT NULL AUTO_INCREMENT,
			date DATETIME NOT NULL,
			url VARCHAR(512) NOT NULL,
			ref VARCHAR(512) NOT NULL default '', 
			ip VARCHAR(512) NOT NULL default '',
			ua VARCHAR(512) NOT NULL default '',
			PRIMARY KEY  (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function settings_menu() {
		add_submenu_page('tools.php', '404 Error Log', '404 Error Log', 'manage_options', '404_error_log', array( $this, 'settings_page') );
	}
	
	function load_table(){
		// need to load the list table early for WP to catch it
		if( get_current_screen()->id == 'tools_page_404_error_log' ) {
			require_once( dirname( __FILE__ ) . '/includes/class-log-404-list-table.php' );
			$this->list_table = new Log_404_List_Table( $this->options['also_record'] );
		}
	}
	
	function settings_page() {
		?>
		<style>
		.log-404-links {float: left}
		.widefat tbody th.check-column {padding: 11px 0 0}
		#the-list td {vertical-align: middle}
		#the-list .column-date {font-size: 0.9em}
		.wp-list-table .column-ip {width: 15%}
		</style>
		<?php screen_icon(); ?>
		<div class="wrap">
		<h2>404 Error Log</h2>
		<?php
		if( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="error"><p><strong>You do not currently have pretty permalinks enabled on your site. This means that WordPress does not handle requests for pages that are not found on your site (your web server handles them directly), and so this plugin cannot log them. You need to be using pretty permalinks in order for this plugin to work.</strong></div>';
			echo '</div>';
			return;
		}
		if( isset( $_GET['view'] ) && $_GET['view'] == 'options' )
			$this->manage_options();
		else
			$this->show_log();
	}
	
	private function subsubsub(){
		$manage_options = isset( $_GET['view'] ) && $_GET['view'] == 'options';
		echo '<ul class="subsubsub"><li><a ' . ( $manage_options ? '' : 'class="current"' ) . ' href="?page=404_error_log">View 404 log</a> | </li><li><a ' . ( $manage_options ? 'class="current"' : '' ) . ' href="?page=404_error_log&amp;view=options">Manage plugin settings</a></li></ul>';
	}
	
	private function show_log(){
		$this->list_table->prepare_items();
		$this->subsubsub();
	?>
	<form action="" method="post" id="log-404">
	<input type="hidden" name="page" value="404_error_log" />
	<?php $this->list_table->search_box( 'Search log', 'log' ); ?>
	<?php $this->list_table->display(); ?>
	</form>
	<script>
	jQuery(document).ready(function($){
		$("#doaction").click( function(e){
			if( $("select[name='action']").val() == "-1" ) {
				e.preventDefault();
				alert("You did not select an action to perform!");
			}
			else if( ! $("#the-list :checked").length ) {
				e.preventDefault();
				alert("You did not select any items to delete!");
			}
		});
		
		$("#url-hide").parent().hide();	// can't hide this
	});
	</script>
	</div>
<?php
	}
	
	private function manage_options(){
		if( isset( $_POST['submit'] ) ) {
			$this->options['also_record'] = empty( $_POST['also_record'] ) ? array() : (array) $_POST['also_record'];
			$this->options['max_entries'] = abs( intval( $_POST['max_entries'] ) );
			update_option( self::opt, $this->options );
			echo '<div id="message" class="updated fade"><p>Options updated.</p></div>';
		}
		$this->subsubsub();
	?>
	<form action="" method="post" id="log-404-settings">
	<input type="hidden" name="page" value="404_error_log" />
	<table class="form-table"><tbody>
	<tr><th scope="row"><label for="max_entries">Maximum log entries to keep</label></th><td>
	<input type="number" name="max_entries" id="max_entries" value="<?php echo $this->options['max_entries'];?>" maxlength="4" size="4" />
	</td></tr>
	<tr><th scope="row">Additional data to record</th><td>
	<?php
	foreach( array( 'ref' => 'HTTP Referer', 'ip' => 'Client IP Address', 'ua' => 'Client User Agent' ) as $k => $v ) {
		$checked = checked( in_array( $k, $this->options['also_record'] ), true, false );
		echo "<label for='also_record_$k'><input type='checkbox' name='also_record[]' id='also_record_$k' value='$k' $checked /> $v</label><br>";
	}
	?>
	</td></tr>
	</tbody></table>
	<p class="submit"><input class="button-primary" type="submit" name="submit" value="Update settings" /></p>
	</form>
	<script>
	jQuery(document).ready(function($){
		$("#log-404-settings :input").change( function(){
			$("#message").slideUp();
		});
	});
	</script>
	</div>
<?php
	}
	
	function log_404s () {
		if( !is_404() )
			return;
		
		global $wpdb;
		
		$data = array( 
			'date' => current_time('mysql'),
			'url' => ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
		);
		
		if( in_array( 'ip', $this->options['also_record'] ) )
			$data['ip'] = $_SERVER['REMOTE_ADDR'];
		if( in_array( 'ref', $this->options['also_record'] ) && isset( $_SERVER['HTTP_REFERER'] ) )
			$data['ref'] = $_SERVER['HTTP_REFERER'];
		if( in_array( 'ua', $this->options['also_record'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) )
			$data['ua'] = $_SERVER['HTTP_USER_AGENT'];
		
		// trim stuff
		foreach( array( 'url', 'ref', 'ua' ) as $k )
			if( isset( $data[$k] ) )
				$data[$k] = substr( $data[$k], 0, 512 );
		
		$wpdb->insert( $this->table, $data );
		
		// pop old entry if we exceeded the limit
		$max = $this->options['max_entries'];
		$cutoff = $wpdb->get_var( "SELECT id FROM $this->table ORDER BY id DESC LIMIT $max,1" );
		if( $cutoff )
			$wpdb->query( "DELETE FROM $this->table WHERE id <= $cutoff" );
	}
}

new Log_404();
