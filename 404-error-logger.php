<?php
/*
Plugin Name: 404 Error Logger
Plugin URI: http://rayofsolaris.net/code/404-error-logger-for-wordpress
Description: A simple plugin to log 404 (Page Not Found) errors on your site.
Version: 0.3
Author: Samir Shah
Author URI: http://rayofsolaris.net/
License: GPL2
*/

if( ! defined( 'ABSPATH' ) )
	exit;

class Log_404 {
	const db_version = 3;
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
			$defaults = array( 
				'max_entries' => 500, 
				'also_record' => array( 'ip', 'ua', 'ref' ),
				'ignore_bots' => false,
				'only_w_ref' => false
			);
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
			id BIGINT NOT NULL AUTO_INCREMENT,
			date DATETIME NOT NULL,
			url VARCHAR(512) NOT NULL,
			ref VARCHAR(512) NOT NULL default '', 
			ip VARCHAR(40) NOT NULL default '',
			ua VARCHAR(512) NOT NULL default '',
			PRIMARY KEY  (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function settings_menu() {
		add_submenu_page('tools.php', '404 Error Log', '404 Error Log', 'manage_options', '404_error_log', array( $this, 'settings_page') );
		// Register a page for CSV
		add_submenu_page('tools.php', '404 Error Log CSV', '404 Error Log CSV', 'manage_options', '404_error_log_csv', array( $this, 'csv') );
		// ...But hide it
		remove_submenu_page('tools.php', '404_error_log_csv');
	}
	
	function load_table(){
		// need to load the list table early for WP to catch it
		if( get_current_screen()->id == 'tools_page_404_error_log' && empty( $_GET['view'] ) ) {
			require_once( dirname( __FILE__ ) . '/includes/class-log-404-list-table.php' );
			$this->list_table = new Log_404_List_Table( $this->options['also_record'] );
		}
	}
	
	function csv() {
		global $wpdb;
		if( isset( $_GET['csv'] ) && isset( $_GET['noheader'] ) && check_admin_referer('404_error_log_csv') ) {
			$orderby = ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'date', 'url', 'ua', 'ref', 'ip' ) ) )
						? $_GET['orderby'] : 'id';
			$order = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] == 'asc' ) ) 
						? 'ASC' : 'DESC';

			$rows = $wpdb->get_results( "SELECT date, url, ref, ip, ua FROM $this->table ORDER BY $orderby $order", ARRAY_N );
			$fp = fopen('php://output', 'w');
			$headers = array('Date', 'URL', 'Referrer', 'IP Address', 'User Agent');
			if($rows && $fp){
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="404_error_log.csv"');
				header('Cache-Control: private, max-age=0');
				fputcsv($fp, $headers);
				foreach($rows as $row){
					fputcsv($fp, $row);
				}
			}
			else { 
				header('Content-Type: text/plain');
				echo 'An error occurred when generating the CSV.';
			}
			exit;
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
		#the-list .column-ua, #the-list .column-ref {color: #777; font-size: 0.9em}
		</style>
		<?php screen_icon(); ?>
		<div class="wrap">
		<h2>404 Error Log <?php if ( !empty( $_POST['s'] ) ) echo '<span class="subtitle">Search results for &#8220;' . esc_attr( $_POST['s'] ) . '&#8221;</span>'; ?></h2>
		<?php
		if( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="error"><p><strong>You do not currently have pretty permalinks enabled on your site. This means that WordPress does not handle requests for pages that are not found on your site (your web server handles them directly), and so this plugin cannot log them. You need to be using pretty permalinks in order for this plugin to work.</strong></div>';
			echo '</div>'; //wrap
			return;
		}
		if( WP_CACHE ) :?>
		<div class="updated">
		<p><strong style="color: #900">Warning:</strong> It seems that a caching/performance plugin is active on this site. This plugin has only been tested with the following caching plugins:</p>
		<ul style="list-style: disc; margin-left: 2em">
		<li>W3 Total Cache</li>
		<li> WP Super Cache</li>
		</ul>
		<p><strong>Other caching plugins may cache responses to requests for pages that don't exist</strong>, in which case this plugin will not be able to intercept the requests and log them.</p>
		</div>
		<?php endif;
		if( isset( $_GET['view'] ) && $_GET['view'] == 'options' )
			$this->manage_options();
		else
			$this->show_log();
		// div is closed in show_log()
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
	
	<p style="text-align: right"><a class="button button-primary" target="_blank" href="<?php echo wp_nonce_url( menu_page_url('404_error_log_csv', false), '404_error_log_csv' ) . '&amp;csv=1&amp;noheader=true&amp;orderby=' . ( empty($_GET['orderby']) ? '' : $_GET['orderby'] ) . '&amp;order=' . ( empty($_GET['order']) ? '' : $_GET['order'] ); ?>">Download this table as CSV</a></p>
	<script>
	jQuery(function($){
		$("#doaction, #doaction2").click( function(e){
			if( $(this).parent().find("select").val() == "-1" ) {
				e.preventDefault();
				alert("You did not select an action to perform!");
			}
			else if( !$("#the-list :checked").length ) {
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
			check_admin_referer( '404-logger-options' );
			$this->options['also_record'] = empty( $_POST['also_record'] ) ? array() : (array) $_POST['also_record'];
			$this->options['max_entries'] = abs( intval( $_POST['max_entries'] ) );
			$this->options['ignore_bots'] = isset( $_POST['ignore_bots'] );
			$this->options['only_w_ref'] = isset( $_POST['only_w_ref'] );
			update_option( self::opt, $this->options );
			echo '<div id="message" class="updated fade"><p>Options updated.</p></div>';
		}
		$this->subsubsub();
	?>
	<form action="" method="post" id="log-404-settings">
	<input type="hidden" name="page" value="404_error_log" />
	<table class="form-table"><tbody>
		<tr>
			<th scope="row"><label for="max_entries">Maximum log entries to keep</label></th>
			<td><input type="number" name="max_entries" id="max_entries" value="<?php echo $this->options['max_entries'];?>" maxlength="4" size="4" /></td>
		</tr>
		<tr>
			<th scope="row">Additional data to record</th>
			<td><?php
				foreach( array( 'ref' => 'HTTP Referrer', 'ip' => 'Client IP Address', 'ua' => 'Client User Agent' ) as $k => $v ) {
					$checked = checked( in_array( $k, $this->options['also_record'] ), true, false );
					echo "<label><input type='checkbox' name='also_record[]' value='$k' $checked /> $v</label><br/>";
				}
				?></td>
		</tr>
		<tr>
			<th scope="row">Other options</th>
			<td>
				<label><input type='checkbox' name='ignore_bots' <?php checked( $this->options['ignore_bots'] ); ?> /> Ignore visits from robots</label><br/>
				<label><input type='checkbox' name='only_w_ref' <?php checked( $this->options['only_w_ref'] ); ?> /> Ignore visits which don't have an HTTP Referrer</label>
			</td>
		</tr>
	</tbody></table>
	<?php wp_nonce_field( '404-logger-options' ); ?>
	<p class="submit"><input class="button-primary" type="submit" name="submit" value="Update settings" /></p>
	</form>
	<script>
	jQuery(function($){
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
		
		define( 'DONOTCACHEPAGE', true );		// WP Super Cache and W3 Total Cache recognise this

		if ( $this->options['ignore_bots'] && !empty( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/(bot|spider)/', $_SERVER['HTTP_USER_AGENT'] ) )
			return;

		if ( $this->options['only_w_ref'] && empty($_SERVER['HTTP_REFERER'] ) )
			return;
			
		$data = array( 
			'date' => current_time('mysql'),
			'url' => $_SERVER['REQUEST_URI']
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
		$max = intval( $this->options['max_entries'] );
		$cutoff = $wpdb->get_var( "SELECT id FROM $this->table ORDER BY id DESC LIMIT $max,1" );
		if( $cutoff ) {
			$wpdb->delete( $this->table, array( 'id' => intval( $cutoff ) ), array( '%d' ) );
		}
	}
}

new Log_404();
