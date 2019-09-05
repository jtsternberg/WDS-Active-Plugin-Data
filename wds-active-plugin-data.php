<?php
/*
Plugin Name: WDS Active Plugin Data
Plugin URI: http://www.webdevstudios.com
Description: Get active status of available plugins in WordPress Multisite
Version: 1.0.1
Author: WebDevStudios
Author URI: http://www.webdevstudios.com
License: GPLv2
Text Domain: wds-apd
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WDS_Active_Plugin_Data {

	/**
	 * @var array Available plugins in /wp-content/plugins/
	 *
	 * @since 1.0.0
	 */
	public $available_plugins = array();

	/**
	 * @var array Active plugins list for every site
	 *
	 * @since 1.0.1
	 */
	public $all_sites_active_plugins = array();

	/**
	 * @var array List of sites
	 *
	 * @since 1.0.1
	 */
	public $sites = array();

	/**
	 * Load our textdomain.
	 *
	 * @since 1.0.0
	 */
	public function languages() {
		load_plugin_textdomain( 'wds-apd', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Query for our available plugins
	 *
	 * @since 1.0.0
	 */
	public function get_available_plugins() {
		if ( empty( $this->available_plugins ) ) {
			$this->available_plugins = get_plugins();
		}

		return $this->available_plugins;
	}

	/**
	 * Query for our sites
	 *
	 * @since 1.0.1
	 */
	public function get_sites() {
		if ( empty( $this->sites ) ) {
			$this->sites = get_sites( array( 'deleted' => false ) );
		}

		return $this->sites;
	}

	/**
	 * Get active plugins list for every site and store to a transient
	 *
	 * @since 1.0.1
	 */
	public function get_all_sites_active_plugins() {
		global $wpdb;

		if ( ! empty( $this->all_sites_active_plugins ) ) {
			return $this->all_sites_active_plugins;
		}

		$exists = get_transient( 'all_sites_active_plugins' );
		if ( $exists && ! isset( $_GET['delete-trans'] ) ) {
			$this->all_sites_active_plugins = $exists;;
			return $this->all_sites_active_plugins;
		}

		$sites = $this->get_sites();

		if ( empty( $sites ) ) {
			return;
		}

		foreach ( $sites as $site ) {
			$blog_id = absint( $site->blog_id );
			$sql = 1 == $blog_id
				? "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'active_plugins' LIMIT 1"
				: "SELECT option_value FROM {$wpdb->prefix}{$blog_id}_options WHERE option_name = 'active_plugins' LIMIT 1";
			$row = $wpdb->get_row( $sql );

			$this->all_sites_active_plugins[ $blog_id ] = array();
			if ( isset( $row->option_value ) ) {
				$this->all_sites_active_plugins[ $blog_id ] = maybe_unserialize( $row->option_value );
			}
		}

		set_transient( 'all_sites_active_plugins', $this->all_sites_active_plugins, DAY_IN_SECONDS );

		return $this->all_sites_active_plugins;
	}

	/**
	 * Add our Network Admin menu item.
	 *
	 * @since 1.0.0
	 */
	function network_menu() {
		$hook = add_submenu_page( 'settings.php', __( 'WDS Active Plugins Data', 'wds-apd' ), __( 'WDS Active Plugins Data', 'wds-apd' ), 'manage_options', 'wds-apd', array( $this, 'display_plugin_data' ) );
		add_action( "admin_footer-$hook", array( $this, 'scripts' ) );
		add_action( "admin_head-$hook", array( $this, 'styles' ) );
	}

	/**
	 * Callback for our Network Admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_data() { ?>
		<div class="wrap">
		<h1><?php _e( 'WDS Active Plugin Data', 'wds-apd' ); ?></h1>

		<p>
			<a class="wds-sites-list wds-toggle-active" href="#"><?php _e( 'Toggle Sites List', 'wds-apd' ); ?></a> |
			<a class="wds-simple" href="#"><?php _e( 'Toggle Simple', 'wds-apd' ); ?></a> |
			<a class="wds-advanced" href="#"><?php _e( 'Toggle Advanced', 'wds-apd' ); ?></a>
		</p>

		<?php
		$this->get_sites_list();
		$this->get_simple_list();
		$this->get_advanced_list();
		?>

		</div>
		<?php
	}

	/**
	 * Output our "Simple" list.
	 *
	 * @since 1.0.0
	 */
	public function get_simple_list() { ?>
		<div id="wds-simple" class="wds-display-none">
			<h2><?php _e( 'Simple', 'wds-apd' ); ?></h2>
			<?php
			$this->get_clear_transients_link();

			$text = '';
			foreach( $this->get_available_plugins() as $plugin_file => $plugin_data ) {
				$text .= $plugin_data['Name'] . ' ';
				/* translators: [A] is meant to describe "Active" */
				$text .= ( $this->is_plugin_active_on_any_site( $plugin_file ) ) ? __( '[A]', 'wds-apd' ) : '';
				/* translators: [NA] is meant to describe "Network Active" */
				$text .= ( is_plugin_active_for_network( $plugin_file ) ) ? __( '[NA]', 'wds-apd' ) : '';
				$text .= "\n";
			}
			?>
			<textarea onclick="this.focus();this.select()" readonly="readonly"><?php echo trim( $text ); ?></textarea>
		</div>

		<?php
	}

	/**
	 * Output our "Advanced" List.
	 *
	 * @since 1.0.0
	 */
	public function get_advanced_list() { ?>
		<div id="wds-advanced" class="wds-display-none">
			<h2><?php _e( 'Advanced', 'wds-apd' ); ?></h2>

			<?php $this->get_clear_transients_link(); ?>

			<table class="wp-list-table widefat plugins striped">
				<tr>
					<th><?php _e( 'Plugin Name', 'wds-apd' ); ?></th>
					<th><?php _e( 'Active', 'wds-apd' ); ?></th>
					<th><?php _e( 'Network Active', 'wds-apd' ); ?></th>
				</tr>
				<?php
					foreach( $this->get_available_plugins() as $plugin_file => $plugin_data ) { ?>
						<tr>
							<td><?php echo $plugin_data['Name']; ?></td>
							<td><?php ( $this->is_plugin_active_on_any_site( $plugin_file ) ) ? printf( '<span style="color:green;">%s</span>', __( 'true', 'wds-apd' ) ) : printf( '<span style="color:red;">%s</span>', __( 'false', 'wds-apd' ) ); ?></td>
							<td><?php ( is_plugin_active_for_network( $plugin_file ) ) ? printf( '<span style="color:green;">%s</span>', __( 'true', 'wds-apd' ) ) : printf( '<span style="color:red;">%s</span>', __( 'false', 'wds-apd' ) ); ?></td>
						</tr>
					<?php
					}
				?>
			</table>
		</div>
	<?php
	}

	/**
	 * Output the sites list
	 *
	 * @since 1.0.0
	 */
	public function get_sites_list() {
		$sites = $this->get_sites();
		?>
		<div id="wds-sites-list">
		<h2><?php _e( 'Sites List', 'wds-apd' ); ?></h2>
		<?php $this->get_clear_transients_link(); ?>
		<table class="wp-list-table striped">

			<tr>
				<td><strong><?php _e( 'Plugin Name / Site ID', 'wds-apd' ); ?></strong></td>
				<?php
					foreach( $sites as $site ) {
						echo '<td title="' . esc_attr( $site->domain ) . '"><a href="' . get_admin_url( $site->blog_id ) . 'plugins.php">' . $site->blog_id . '</a></td>';
					}
				?>
			</tr>

			<?php
				$index = 0;
				foreach( $this->get_available_plugins() as $plugin_file => $plugin_data ) {
					echo '<tr><td>' . $plugin_data['Name'] . '</td>';

					$index = 0;
					foreach ( $this->get_all_sites_active_plugins() as $plugins ) {

						$class = 'dashicons-no-alt wds-red';
						if ( in_array( $plugin_file, (array) $plugins ) ) {
							$class = 'dashicons-yes wds-green';
						} elseif ( is_plugin_active_for_network( $plugin_file ) ) {
							$class = 'dashicons-yes wds-lt-green';
						}

						$site = $sites[ $index ];
						echo '<td title="' . esc_attr( $site->domain ) . '">
							<a href="'. esc_url( get_admin_url( $site->blog_id ) ) .'" class="dashicons ' . $class . '"></a>
						</td>';

						$index++;
					}
					echo '</tr>';
				}
			?>
		</table>
		</div>
		<?php
	}

	/**
	 * Determines if a plugin is active on any site in the network
	 *
	 * @param $plugin_file (string) plugin to check
	 *
	 * @return bool
	 */
	public function is_plugin_active_on_any_site( $plugin_file ) {
		foreach( $this->get_all_sites_active_plugins() as $plugins ) {
			if ( in_array( $plugin_file, $plugins ) ) {
				return true;
			}
		}

		return false;
	}

	public function get_clear_transients_link() {
		?>
		<p><a href="<?php echo esc_url( isset( $_GET['delete-trans'] ) ? remove_query_arg( 'delete-trans' ) : add_query_arg( 'delete-trans', true ) ); ?>"><?php _e( 'Clear Transients', 'wds-apd' ); ?></a></p>
		<?php
	}

	/**
	 * Output some jQuery goodness
	 *
	 * @since 1.0.0.
	 */
	public function scripts() {
		?>
		<script>
			(function($) {
				var $links = $('.wds-advanced,.wds-simple,.wds-sites-list');
				var $sections = $('#wds-advanced,#wds-simple,#wds-sites-list');
				$links.on( 'click', function(e){
					e.preventDefault();

					$sections.addClass('wds-display-none');
					$links.removeClass('wds-toggle-active');
					$('#'+ $(this).attr('class') ).removeClass('wds-display-none');
					$(this).addClass('wds-toggle-active');
				});
			})(jQuery);
		</script>
	<?php
	}

	/**
	 * Make it pretty-er
	 *
	 * @since 1.0.0
	 */
	public function styles() { ?>
		<style>
		.wds-display-none { display: none; }
		#wds-simple textarea { width: 500px; height: 500px; }
		.wds-green, .wds-green:hover { background-color: #6ea954; color: #fff; }
		.wds-red, .wds-red:hover { background-color: #f398a3; color: #fff; }
		.wds-lt-green, .wds-lt-green:hover { background-color: #bfdcbb; color: #fff; }
		#wds-sites-list tr td { padding: 2px 4px; }
		.wds-green, .wds-red, .wds-lt-green {
			width: 1.2em;
			height: 1.2em;
			line-height: 1.2em;
		}
		.wds-toggle-active {
			text-decoration: none;
			color: inherit;
		}
		</style>
	<?php
	}

} // end class

function wds_active_plugin_data() {
	static $object = null;
	if ( is_null( $object ) ) {
		$object = new WDS_Active_Plugin_Data();
	}

	return $object;
}

add_action( 'plugins_loaded', array( wds_active_plugin_data(), 'languages' ) );
add_action( 'network_admin_menu', array( wds_active_plugin_data(), 'network_menu') );
