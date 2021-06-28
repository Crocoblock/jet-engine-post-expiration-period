<?php

// If this file is called directly, abort.
use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Jet_Engine_Post_PE {
	/**
	 * Instance.
	 *
	 * Holds the plugin instance.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @var Jet_Engine_Post_PE
	 */
	public static $instance = null;

	public $can_init = false;

	public $is_valid_period = false;

	public $daily_event = 'jet-engine-pep/daily_check_expirations';
	public $period_meta_key = '_jet_pep_period';
	public $action_meta_key = '_jet_pep_action';

	public $expired_posts;

	public function __construct() {
		$this->can_init = $this->can_init();
		$this->init();
	}

	public function can_init() {
		return function_exists( 'jet_engine' ) || function_exists( 'jet_form_builder' );
	}

	public function init() {
		if ( ! $this->can_init ) {
			return;
		}

		$this->hooks();
		
		add_action( 'init', function() {

		    $pathinfo = pathinfo( JET_ENGINE_POST_EP_PLUGIN_BASE );

		    jet_engine()->modules->updater->register_plugin( array(
			'slug'    => $pathinfo['filename'],
			'file'    => JET_ENGINE_POST_EP_PLUGIN_BASE,
			'version' => JET_ENGINE_POST_EP_VERSION
		    ) );

		}, 12 );
	}

	public function hooks() {
		add_action(
			'jet-engine/forms/booking/notifications/fields-after',
			array( $this, 'add_fields_to_insert_post' )
		);
		add_action(
			'jet-engine/forms/notifications/after-post-insert',
			array( $this, 'handle_post_action' ),
			1, 2
		);
		add_action(
			$this->daily_event,
			array( $this, 'on_daily_check_expirations' )
		);
		add_action(
			'jet-form-builder/editor-assets/before',
			array( $this, 'jet_form_builder_assets' )
		);

		add_action(
			'jet-form-builder/action/after-post-insert',
			array( $this, 'handle_post_action_form_builder' ), 10, 2
		);
	}

	/**
	 * @param Base $action_instance
	 * @param Action_Handler $action_handler
	 */
	public function handle_post_action_form_builder( $action_instance, $action_handler ) {
		if ( ! isset( $action_instance->settings['enable_expiration_period'] )
		     || ! $action_instance->settings['enable_expiration_period']
		) {
			return;
		}

		$period = $this->filter_period( $action_instance->settings['expiration_period'] );

		if ( ! $period ) {
			return;
		}

		$this->init_expiration(
			$period,
			$action_handler->response_data['inserted_post_id'],
			$action_instance->settings['expiration_action']
		);
	}

	public function jet_form_builder_assets() {
		wp_enqueue_script(
			'jet-engine-pep',
			$this->plugin_url( 'assets/js/builder.editor.js' ),
			array(),
			JET_ENGINE_POST_EP_VERSION,
			true
		);
	}

	public function add_fields_to_insert_post() {
		$this->require_template( 'form-notification-insert-post-fields' );
	}

	public function handle_post_action( $notification, $form ) {
		if ( isset( $notification['enable_expiration_period'] )
		     && $notification['enable_expiration_period'] ) {

			$period = $this->filter_period( $notification['expiration_period'] );

			if ( ! $period ) {
				return;
			}

			$this->init_expiration(
				$period,
				$form->data['inserted_post_id'],
				$notification['expiration_action']
			);
		}
	}

	public function filter_period( $period ) {
		$period = absint( $period );

		$str_period = sprintf(
			'+%d day',
			$period
		);

		$period_timestamp = strtotime( $str_period );

		if ( time() < $period_timestamp ) {
			return $period_timestamp;
		} else {
			return false;
		}
	}

	public function init_expiration( $expiration_period, $post_id, $expiration_action ) {

		$this->set_meta_expiration(
			$post_id,
			$expiration_period,
			$expiration_action
		);

		$this->set_daily_cron();
	}

	public function set_meta_expiration( $post_id, $expiration_period, $expiration_action ) {
		update_post_meta(
			$post_id,
			$this->period_meta_key,
			$expiration_period
		);

		update_post_meta(
			$post_id,
			$this->action_meta_key,
			$expiration_action
		);
	}

	public function delete_meta_expiration( $id ) {
		delete_post_meta(
			$id,
			$this->action_meta_key
		);
		delete_post_meta(
			$id,
			$this->period_meta_key
		);
	}

	public function set_daily_cron() {
		if ( wp_next_scheduled( $this->daily_event ) ) {
			return;
		}

		$offset = get_option( 'gmt_offset' );
		$ve     = $offset > 0 ? '-' : '+';

		$timestamp = strtotime( '00:00 tomorrow ' . $ve . absint( $offset ) . ' HOURS' );

		wp_unschedule_hook( $this->daily_event );
		wp_schedule_single_event( $timestamp, $this->daily_event );
	}

	public function find_expired_posts() {
		global $wpdb;

		$start_from = strtotime( 'today' );
		$end_by     = strtotime( 'tomorrow -1 SECOND' );

		$sql = "SELECT postmeta_1.post_id, postmeta_1.meta_value as expiration_action 
                FROM $wpdb->postmeta as postmeta_1
                JOIN $wpdb->postmeta as postmeta_2 ON postmeta_1.post_id = postmeta_2.post_id 
                WHERE postmeta_1.meta_key = '$this->action_meta_key'
                AND  postmeta_2.meta_key = '$this->period_meta_key'
                AND ( postmeta_2.meta_value BETWEEN $start_from AND $end_by );";

		$this->expired_posts = $wpdb->get_results( $sql, ARRAY_A );
	}


	public function on_daily_check_expirations() {

		$this->find_expired_posts();

		if ( ! empty( $this->expired_posts ) ) {
			$this->schedule_posts();
		}
		$this->set_daily_cron();
	}


	public function schedule_posts() {
		if ( empty( $this->expired_posts ) ) {
			return;
		}

		foreach ( $this->expired_posts as $post ) {

			$this->on_expiration( $post['post_id'], $post['expiration_action'] );
			$this->delete_meta_expiration( $post['post_id'] );
		}
	}

	public function on_expiration( $post_id, $expiration_action ) {
		if ( ! get_post_status( $post_id )
		     || get_post_status( $post_id ) === $expiration_action ) {
			return;
		}

		$func_name = 'expiration_' . $expiration_action;
		if ( is_callable( [ $this, $func_name ] ) ) {
			$this->$func_name( $post_id );
		}
	}

	public function expiration_draft( $id ) {
		$update_post = array(
			'ID'          => $id,
			'post_status' => 'draft'
		);

		wp_update_post( $update_post );
	}

	public function expiration_trash( $id ) {
		wp_trash_post( $id );
	}

	public function require_template( $template ) {
		$path = JET_ENGINE_POST_EP_PATH . 'templates' . DIRECTORY_SEPARATOR;
		require( $path . $template . '.php' );
	}

	public function plugin_url( $path ) {
		return JET_ENGINE_POST_EP_URL . $path;
	}

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @return Jet_Engine_Post_PE instance of the class.
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

Jet_Engine_Post_PE::instance();

