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
    public $daily_event = 'jet-engine-pep/daily_check_expirations';
    public $period_meta_key = '_jet_pep_period';
    public $action_meta_key = '_jet_pep_action';

    public $post_id;
    public $expiration_period;
    public $expiration_action;

    public $expired_posts_with_period;
    public $expired_posts_with_action;

    public $schedule_posts;

    const HOUR_IN_SECONDS = 60 * 60;

    public function __construct() {
        $this->can_init();
        $this->init();
    }

    public function can_init() {
        $this->can_init = function_exists( 'jet_engine' );
    }

    public function init() {
        if ( ! $this->can_init ) return;

        $this->hooks();
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
            $this->action_name,
            array( $this, 'on_expiration' ),
            1, 2
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
            '+%b day',
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
        $ve = $offset > 0 ? '-' : '+';

        $timestamp = strtotime( '00:00 tomorrow ' . $ve . absint( $offset ) . ' HOURS' );

        wp_unschedule_hook( $this->daily_event );
        wp_schedule_single_event( $timestamp, $this->daily_event );
    }

    public function find_expired_posts_with_period() {
        global $wpdb;

        $start_from = strtotime( 'today' );
        $end_by = strtotime('tomorrow -1 SECOND');
        $table = $wpdb->postmeta;
        $meta_key = $this->period_meta_key;

        $sql = "SELECT post_id, meta_value as expiration_period FROM $table
                WHERE meta_key = $meta_key 
                AND ( meta_value BETWEEN $start_from AND $end_by );";

        $this->expired_posts_with_period = $wpdb->get_results( $sql, ARRAY_A );
    }

    public function find_expired_posts_with_action() {
        global $wpdb;

        $post_ids = [];

        foreach ( $this->expired_posts_with_period as $post ) {
            $post_ids[] = $post_ids['post_id'];
        }

        $str_ids = implode( ', ', $post_ids );
        $table = $wpdb->postmeta;
        $meta_key = $this->action_meta_key;

        $sql = "SELECT post_id, meta_value as expiration_action FROM $table
                WHERE meta_key = $meta_key 
                AND post_id IN ($str_ids);";

        $this->expired_posts_with_action = $wpdb->get_results( $sql, ARRAY_A );
    }

    public function on_daily_check_expirations() {

        $this->find_expired_posts_with_period();
        $this->find_expired_posts_with_action();

        if ( ! empty( $this->expired_posts_with_period )
            && sizeof( $this->expired_posts_with_period ) === sizeof( $this->expired_posts_with_action ) ) {

            $this->schedule_posts();
        }
    }

    public function combine_posts() {
        $this->schedule_posts = array();

        foreach ( $this->expired_posts_with_period as $find_post ) {
            $this->schedule_posts[ $find_post['post_id'] ]['timestamp'] = $find_post['expiration_period'];
        }

        foreach ( $this->expired_posts_with_action as $post_action ) {
            if ( ! $this->schedule_posts[ $post_action['post_id'] ]['timestamp'] ) {
                return;
            }

            $this->schedule_posts[ $post_action['post_id'] ]['action'] = $post_action['expiration_action'];
        }
    }

    public function schedule_posts() {
        $this->combine_posts();

        if ( empty( $this->schedule_posts ) ) {
            return;
        }

        foreach ( $this->schedule_posts as $post_id => $post ) {
            wp_schedule_single_event(
                $post['timestamp'],
                $this->action_name,
                array( $post_id, $post['action'] )
            );

            $this->delete_meta_expiration( $post_id );
        }
    }

    public function on_expiration( $post_id, $expiration_action ) {
        if ( ! get_post_status( $post_id )
            || get_post_status( $post_id ) === $expiration_action ) {
            return;
        }

        $func_name = 'expiration_' . $expiration_action;

        if ( is_callable( [ $this, $func_name ] ) ) {
            $this->$func_name();
        }
    }

    public function expiration_draft( $id ) {
        $update_post = array(
            'ID'            => $id,
            'post_status'   => 'draft'
        );

        wp_update_post( $update_post );
    }

    public function expiration_trash( $id ) {
        wp_trash_post( $id );
    }

    public function require_template( $template ) {
        $path = JET_ENGINE_POST_EP_PATH . 'templates' . DIRECTORY_SEPARATOR;
        require ( $path . $template . '.php' );
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

