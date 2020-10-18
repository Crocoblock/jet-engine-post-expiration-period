<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

class Jet_Engine_Post_PE
{
    /**
     * Instance.
     *
     * Holds the plugin instance.
     *
     * @since 1.0.0
     * @access public
     * @static
     *
     * @var Plugin
     */
    public static $instance = null;

    public $can_init = false;

    public $is_valid_period = false;

    public $action_name = 'jet-engine-pep/on_expiration';

    public function __construct() {
        $this->can_init();
        $this->init();
    }

    public function can_init() {
        $this->can_init = function_exists( 'jet_engine' );
    }

    public function init() {
        if ( ! $this->can_init) return;

        $this->hooks();
    }

    public function hooks() {
        add_action(
            'jet-engine/forms/booking/notifications/fields-after',
            array( $this, 'add_fields_to_insert_post' ), 1, 1
        );
        add_filter(
            'jet-engine/forms/notifications/after-post-insert',
            array( $this, 'handle_post_action' ),
            1, 2
        );
        add_filter(
            'jet-engine/forms/notifications/after-post-update',
            array( $this, 'handle_post_action' ),
            1, 2
        );
        add_action(
            $this->action_name,
            [ $this, 'on_expiration' ], 1, 2
        );
    }

    public function add_fields_to_insert_post() {
        $this->require_template( 'form-notification-insert-post-fields' );
    }

    public function handle_post_action( $notification, $form ) {
        if ( isset( $notification[ 'enable_expiration_period' ] ) && $notification[ 'enable_expiration_period' ] ){
            $period = $this->filter_period( $notification[ 'expiration_period' ] );

            if ( ! $period ) return;

            $this->set_period(
                $period,
                $form->data[ 'inserted_post_id' ],
                $notification[ 'expiration_action' ]
            );
        }
    }

    public function filter_period( $period ) {
        $period = absint( $period );
        $period_timestamp = strtotime( sprintf( '+%b day', $period ) );

        return $period_timestamp > time() ? $period_timestamp : false;
    }

    public function set_period( $expiration_period, $post_id, $expiration_action ) {
        wp_schedule_single_event( $expiration_period, $this->action_name,
            [ $post_id, $expiration_action ]
        );

        //$result === null ? error_log( 'Success added cron!' ) : null;
    }

    public function on_expiration( $post_id, $expiration_action ) {
        if ( ! get_post_status( $post_id )
            || get_post_status( $post_id ) === $expiration_action ) return;

        $update_post = [
            'ID'            => $post_id,
            'post_status'   => $expiration_action
        ];

        wp_update_post( $update_post );
    }

    public function require_template( $template ) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        require ($path . $template . '.php');
    }

    /**
     * Instance.
     *
     * Ensures only one instance of the plugin class is loaded or can be loaded.
     *
     * @since 1.0.0
     * @access public
     * @static
     *
     * @return Plugin An instance of the class.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}

Jet_Engine_Post_PE::instance();

