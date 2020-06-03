<?php
/**
 * MainWP Child Staging.
 *
 * MainWP Staging Extension handler.
 *
 * @link https://mainwp.com/extension/staging/
 *
 * Credits
 *
 * Plugin-Name: WP Staging
 * Plugin URI: https://wordpress.org/plugins/wp-staging
 * Author: WP-Staging
 * Author URI: https://wp-staging.com
 * Contributors: ReneHermi, ilgityildirim
 *
 * The code is used for the MainWP Staging Extension
 * Extension URL: https://mainwp.com/extension/staging/
 */

use MainWP\Child\MainWP_Child_Callable;
use MainWP\Child\MainWP_Helper;

// phpcs:disable PSR1.Classes.ClassDeclaration, WordPress.WP.AlternativeFunctions --  to use external code, third party credit.

/**
 * Class MainWP_Child_Staging
 *
 * @package MainWP\Child
 */
class MainWP_Child_Staging {

	/**
	 * @static
	 * @var null Holds the Public static instance of MainWP_Child_Staging.
	 */
	public static $instance = null;

	/**
	 * @var bool Whether or not the WP Staging Plugin is installed or not. Default: false.
	 */
	public $is_plugin_installed = false;

	/**
	 * Create a public static instance of MainWP_Child_Staging.
	 *
	 * @return MainWP_Child_Staging|null
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * MainWP_Child_Staging constructor.
	 */
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'wp-staging/wp-staging.php' ) && defined( 'WPSTG_PLUGIN_DIR' ) ) {
			$this->is_plugin_installed = true;
		} elseif ( is_plugin_active( 'wp-staging-pro/wp-staging-pro.php' ) ) {
			$this->is_plugin_installed = true;
		}

		if ( ! $this->is_plugin_installed ) {
			return;
		}

		add_filter( 'mainwp_site_sync_others_data', array( $this, 'sync_others_data' ), 10, 2 );
	}


	/**
	 * Initiate actions & filters.
	 */
	public function init() {
		if ( 'Y' !== get_option( 'mainwp_wp_staging_ext_enabled' ) ) {
			return;
		}

		if ( ! $this->is_plugin_installed ) {
			return;
		}

		if ( 'hide' === get_option( 'mainwp_wp_staging_hide_plugin' ) ) {
			add_filter( 'all_plugins', array( $this, 'all_plugins' ) );
			add_action( 'admin_menu', array( $this, 'remove_menu' ) );
			add_filter( 'site_transient_update_plugins', array( &$this, 'remove_update_nag' ) );
			add_filter( 'mainwp_child_hide_update_notice', array( &$this, 'hide_update_notice' ) );
		}
	}

	/**
	 * Sync others data.
	 *
	 * Get an array of available clones of this Child Sites.
	 *
	 * @param array $information Holder for available clones.
	 * @param array $data Array of existing clones.
	 *
	 * @uses MainWP_Child_Staging::get_sync_data()
	 *
	 * @return array $information An array of available clones.
	 */
	public function sync_others_data( $information, $data = array() ) {
		if ( isset( $data['syncWPStaging'] ) && $data['syncWPStaging'] ) {
			try {
				$information['syncWPStaging'] = $this->get_sync_data();
			} catch ( \Exception $e ) {
				// ok!
			}
		}
		return $information;
	}

	/**
	 * Fires off MainWP_Child_Staging::get_overview().
	 *
	 * @uses MainWP_Child_Staging::get_overview()
	 * @return array An array of available clones.
	 */
	public function get_sync_data() {
		return $this->get_overview();
	}

	/**
	 * Fires of certain WP Staging plugin actions.
	 *
	 * @uses \WPStaging\WPStaging::getInstance()
	 * @uses MainWP_Helper::update_option
	 * @uses MainWP_Child_Staging::set_showhide()
	 * @uses MainWP_Child_Staging::save_settings()
	 * @uses MainWP_Child_Staging::get_overview()
	 * @uses MainWP_Child_Staging::get_scan()
	 * @uses MainWP_Child_Staging::ajax_check_free_space()
	 * @uses MainWP_Child_Staging::ajax_check_clone_name()
	 * @uses MainWP_Child_Staging::ajax_start_clone()
	 * @uses MainWP_Child_Staging::ajax_clone_database()
	 * @uses MainWP_Child_Staging::ajax_prepare_directories()
	 * @uses MainWP_Child_Staging::ajax_copy_files()
	 * @uses MainWP_Child_Staging::ajax_replace_data()
	 * @uses MainWP_Child_Staging::ajax_finish()
	 * @uses MainWP_Child_Staging::ajax_delete_confirmation()
	 * @uses MainWP_Child_Staging::ajax_delete_clone()
	 * @uses MainWP_Child_Staging::ajax_cancel_clone()
	 * @uses MainWP_Child_Staging::ajax_update_process()
	 * @uses MainWP_Child_Staging::ajax_cancel_update()
	 * @uses MainWP_Child_Staging::MainWP_Helper::write()
	 */
    public function action() { // phpcs:ignore -- ignore complex method notice.
		if ( ! $this->is_plugin_installed ) {
			MainWP_Helper::write( array( 'error' => __( 'Please install WP Staging plugin on child website', 'mainwp-child' ) ) );
		}

		if ( ! class_exists( 'WPStaging\WPStaging' ) ) {
			if ( file_exists( WPSTG_PLUGIN_DIR . 'app/Core/WPStaging.php' ) ) {
				require_once WPSTG_PLUGIN_DIR . 'app/Core/WPStaging.php';
			} elseif ( file_exists( WPSTG_PLUGIN_DIR . 'Core/WPStaging.php' ) ) {
				require_once WPSTG_PLUGIN_DIR . 'Core/WPStaging.php';
			}
		}

		\WPStaging\WPStaging::getInstance();
		$information = array();

		if ( 'Y' !== get_option( 'mainwp_wp_staging_ext_enabled' ) ) {
			MainWP_Helper::update_option( 'mainwp_wp_staging_ext_enabled', 'Y', 'yes' );
		}

		if ( isset( $_POST['mwp_action'] ) ) {
			switch ( $_POST['mwp_action'] ) {
				case 'set_showhide':
					$information = $this->set_showhide();
					break;
				case 'save_settings':
					$information = $this->save_settings();
					break;
				case 'get_overview':
					$information = $this->get_overview();
					break;
				case 'get_scan':
					$information = $this->get_scan();
					break;
				case 'check_disk_space':
					$information = $this->ajax_check_free_space();
					break;
				case 'check_clone':
					$information = $this->ajax_check_clone_name();
					break;
				case 'start_clone':
					$information = $this->ajax_start_clone();
					break;
				case 'clone_database':
					$information = $this->ajax_clone_database();
					break;
				case 'prepare_directories':
					$information = $this->ajax_prepare_directories();
					break;
				case 'copy_files':
					$information = $this->ajax_copy_files();
					break;
				case 'replace_data':
					$information = $this->ajax_replace_data();
					break;
				case 'clone_finish':
					$information = $this->ajax_finish();
					break;
				case 'delete_confirmation':
					$information = $this->ajax_delete_confirmation();
					break;
				case 'delete_clone':
					$information = $this->ajax_delete_clone();
					break;
				case 'cancel_clone':
					$information = $this->ajax_cancel_clone();
					break;
				case 'staging_update':
					$information = $this->ajax_update_process();
					break;
				case 'cancel_update':
					$information = $this->ajax_cancel_update();
					break;
			}
		}
			MainWP_Helper::write( $information );
	}

	/**
	 * Sets whether or not to hide the WP Staging Plugin.
	 *
	 * @return array $information Action result.
	 */
	public function set_showhide() {
		$hide = isset( $_POST['showhide'] ) && ( 'hide' === $_POST['showhide'] ) ? 'hide' : '';
		MainWP_Helper::update_option( 'mainwp_wp_staging_hide_plugin', $hide, 'yes' );
		$information['result'] = 'SUCCESS';
		return $information;
	}

	/**
	 * Save WP Staging settings.
	 *
	 * @return string[] Return 'Success'.
	 */
	public function save_settings() {
		$settings = $_POST['settings'];
		$filters  = array(
			'queryLimit',
			'fileLimit',
			'batchSize',
			'cpuLoad',
			'delayRequests',
			'disableAdminLogin',
			'querySRLimit',
			'maxFileSize',
			'debugMode',
			'unInstallOnDelete',
			'checkDirectorySize',
			'optimizer',
		);

		$save_fields = array();
		foreach ( $filters as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$save_fields[ $field ] = $settings[ $field ];
			}
		}
		update_option( 'wpstg_settings', $save_fields );
		return array( 'result' => 'success' );
	}

	/**
	 * Get array of available clones.
	 *
	 * @return array $return Action result.
	 */
	public function get_overview() {
		$return = array(
			'availableClones' => get_option( 'wpstg_existing_clones_beta', array() ),
		);
		return $return;
	}

	/**
	 * Get WP Staging Jobs.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Scan::start()
	 * @uses WPStaging\Backend\Modules\Jobs\Scan::getOptions()
	 *
	 * @return array $return Action result.
	 */
	public function get_scan() {
		$scan = new WPStaging\Backend\Modules\Jobs\Scan();
		$scan->start();

		$options = $scan->getOptions();

		$return = array(
			'options'          => serialize( $options ), // phpcs:ignore -- to compatible http encoding.
			'directoryListing' => $scan->directoryListing(),
			'prefix'           => WPStaging\WPStaging::getTablePrefix(),
		);
		return $return;
	}


	/**
	 * Check if clone name already exists & it's length.
	 *
	 * @return array|string[] Action result array[status, message] or return 'success'.
	 */
	public function ajax_check_clone_name() {
		$cloneName       = sanitize_key( $_POST['cloneID'] );
		$cloneNameLength = strlen( $cloneName );
		$clones          = get_option( 'wpstg_existing_clones_beta', array() );

		if ( $cloneNameLength < 1 || $cloneNameLength > 16 ) {
			echo array(
				'status'  => 'failed',
				'message' => 'Clone name must be between 1 - 16 characters',
			);
		} elseif ( array_key_exists( $cloneName, $clones ) ) {
			return array(
				'status'  => 'failed',
				'message' => 'Clone name is already in use, please choose an another clone name',
			);
		}

		return array( 'status' => 'success' );
	}

	/**
	 * Start clone via ajax.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::save()
	 *
	 * @return false|string|void Return FALSE on failure, ajax response string on success, ELSE returns VOID.
	 */
	public function ajax_start_clone() {

		$this->url = '';
		$cloning   = new WPStaging\Backend\Modules\Jobs\Cloning();

		if ( ! $cloning->save() ) {
			return;
		}

		ob_start();
		if ( file_exists( WPSTG_PLUGIN_DIR . 'app/Backend/views/clone/ajax/start.php' ) ) {
			require_once WPSTG_PLUGIN_DIR . 'app/Backend/views/clone/ajax/start.php';
		} elseif ( file_exists( WPSTG_PLUGIN_DIR . 'Backend/views/clone/ajax/start.php' ) ) {
			require_once WPSTG_PLUGIN_DIR . 'Backend/views/clone/ajax/start.php';
		}
		$result = ob_get_clean();
		return $result;
	}

	/**
	 * Clone database via ajax.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_clone_database() {
		$cloning = new WPStaging\Backend\Modules\Jobs\Cloning();
		return $cloning->start();
	}

	/**
	 * Ajax Prepare Directories (get listing of files).
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_prepare_directories() {
		$cloning = new WPStaging\Backend\Modules\Jobs\Cloning();
		return $cloning->start();
	}

	/**
	 * Ajax Clone Files.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_copy_files() {
		$cloning = new WPStaging\Backend\Modules\Jobs\Cloning();
		return $cloning->start();
	}

	/**
	 * Ajax Replace Data.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_replace_data() {
		$cloning = new WPStaging\Backend\Modules\Jobs\Cloning();
		return $cloning->start();
	}

	/**
	 * Ajax Finish
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cloning::start()
	 *
	 * @return mixed $return Action result.
	 */
	public function ajax_finish() {
		$cloning              = new WPStaging\Backend\Modules\Jobs\Cloning();
		$this->url            = '';
		$return               = $cloning->start();
		$return->blogInfoName = get_bloginfo( 'name' );

		return $return;
	}

	/**
	 * Ajax Delete Confirmation.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Delete::getClone()
	 * @uses WPStaging\Backend\Modules\Jobs\Delete::getClone()
	 *
	 * @return array $result Action result.
	 */
	public function ajax_delete_confirmation() {
		$delete = new WPStaging\Backend\Modules\Jobs\Delete();
		$delete->setData();
		$clone  = $delete->getClone();
		$result = array(
			'clone'        => $clone,
			'deleteTables' => $delete->getTables(),
		);

		return $result;
	}

	/**
	 * Ajax Delete clone.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Delete::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_delete_clone() {
		$delete = new WPStaging\Backend\Modules\Jobs\Delete();

		return $delete->start();
	}

	/**
	 * Ajax Cancel clone.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Cancel::start()
	 */
	public function ajax_cancel_clone() {
		$cancel = new WPStaging\Backend\Modules\Jobs\Cancel();

		return $cancel->start();
	}

	/**
	 * Ajax Cancel Update.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\CancelUpdate::start()
	 *
	 * @return mixed Action result.
	 */
	public function ajax_cancel_update() {
		$cancel = new WPStaging\Backend\Modules\Jobs\CancelUpdate();

		return $cancel->start();
	}

	/**
	 * Ajax Update Process.
	 *
	 * @uses WPStaging\Backend\Modules\Jobs\Updating::save()
	 *
	 * @return false|string|void Return FALSE on failure, ajax response string on success, ELSE returns VOID.
	 */
	public function ajax_update_process() {
		$cloning = new WPStaging\Backend\Modules\Jobs\Updating();

		if ( ! $cloning->save() ) {
			return;
		}

		ob_start();
		if ( file_exists( WPSTG_PLUGIN_DIR . 'app/Backend/views/clone/ajax/update.php' ) ) {
			require_once WPSTG_PLUGIN_DIR . 'app/Backend/views/clone/ajax/update.php';
		} elseif ( file_exists( WPSTG_PLUGIN_DIR . 'Backend/views/clone/ajax/update.php' ) ) {
			require_once WPSTG_PLUGIN_DIR . 'Backend/views/clone/ajax/update.php';
		}
		$result = ob_get_clean();
		return $result;
	}

	/**
	 * Ajax check for free disk space.
	 *
	 * @uses MainWP_Child_Staging::has_free_disk_space()
	 *
	 * @return array|null Action result or null
	 */
	public function ajax_check_free_space() {
		return $this->has_free_disk_space();
	}

	/**
	 * Ajax check for free disk space.
	 *
	 * @uses MainWP_Child_Staging::format_size()
	 * @uses MainWP_Child_Staging::get_directory_size_incl_subdirs()
	 *
	 * @return array|null Action result or null
	 */
	public function has_free_disk_space() {
		if ( ! function_exists( 'disk_free_space' ) ) {
			return null;
		}
		$freeSpace = disk_free_space( ABSPATH );
		if ( false === $freeSpace ) {
			$data = array(
				'freespace' => false,
				'usedspace' => $this->format_size( $this->get_directory_size_incl_subdirs( ABSPATH ) ),
			);
			return $data;
		}
		$data = array(
			'freespace' => $this->format_size( $freeSpace ),
			'usedspace' => $this->format_size( $this->get_directory_size_incl_subdirs( ABSPATH ) ),
		);
		return $data;
	}

	/**
	 * Get size of directory & subdirectories.
	 *
	 * @param $dir Directory to size.
	 *
	 * @return false|int FALSE on failure, int $size Directory size,
	 */
	public function get_directory_size_incl_subdirs( $dir ) {
		$size = 0;
		foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
			$size += is_file( $each ) ? filesize( $each ) : $this->get_directory_size_incl_subdirs( $each );
		}
		return $size;
	}

	/**
	 * Format file size into human readable string.
	 *
	 * @param string $bytes Original size of file.
	 * @param int    $precision Number of digits after the decimal point.
	 * @return string Returned Size.
	 */
	public function format_size( $bytes, $precision = 2 ) {
		if ( (float) $bytes < 1 ) {
			return '';
		}

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = (float) $bytes;
		$base  = log( $bytes ) / log( 1000 );
		$pow   = pow( 1000, $base - floor( $base ) );

		return round( $pow, $precision ) . ' ' . $units[ (int) floor( $base ) ];
	}


	/**
	 * Get list of all plugins except WPStaging.
	 *
	 * @param array $plugins All installed plugins.
	 * @return mixed Returned array of plugins without WPStaging included.
	 */
	public function all_plugins( $plugins ) {
		foreach ( $plugins as $key => $value ) {
			$plugin_slug = basename( $key, '.php' );
			if ( 'wp-staging' === $plugin_slug ) {
				unset( $plugins[ $key ] );
			}
		}

		return $plugins;
	}

	/**
	 * Remove WPStaging WordPress Menu.
	 */
	public function remove_menu() {
		remove_menu_page( 'wpstg_clone' );
		$pos = stripos( $_SERVER['REQUEST_URI'], 'admin.php?page=wpstg_clone' );
		if ( false !== $pos ) {
			wp_safe_redirect( get_option( 'siteurl' ) . '/wp-admin/index.php' );
			exit();
		}
	}

	/**
	 * Hide all admin update notices.
	 *
	 * @param array $slugs WPStaging plugin slug.
	 * @return mixed Returned $slugs.
	 */
	public function hide_update_notice( $slugs ) {
		$slugs[] = 'wp-staging/wp-staging.php';

		return $slugs;
	}

	/**
	 * Remove WPStaging update Nag message.
	 *
	 * @uses MainWP_Helper::is_updates_screen()
	 *
	 * @param array $value WPStaging slug.
	 * @return mixed $value Response array.
	 */
	public function remove_update_nag( $value ) {
		if ( isset( $_POST['mainwpsignature'] ) ) {
			return $value;
		}

		if ( ! MainWP_Helper::is_updates_screen() ) {
			return $value;
		}

		if ( isset( $value->response['wp-staging/wp-staging.php'] ) ) {
			unset( $value->response['wp-staging/wp-staging.php'] );
		}

		if ( isset( $value->response['wp-staging-pro/wp-staging-pro.php'] ) ) {
			unset( $value->response['wp-staging-pro/wp-staging-pro.php'] );
		}

		return $value;
	}
}
