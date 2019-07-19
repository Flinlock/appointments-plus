<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Packages_API extends WP_REST_Controller{
    //protected $base = 'packages';
    protected static $instance;
    protected static $NAMESPACE = 'packages';
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    public function __construct(){
        $this->register_routes();
    }
    public function register_routes(){
        register_rest_route( self::$NAMESPACE, '/add', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_package_to_user' ),
                'permission_callback' => array( $this, 'add_package_to_user_permissions_check' )
            )
        ) );
    }
    
    /**
     * @param WP_REST_Request $request
     * @return string
     *
     * Import products to Woocommerce
     */
    public function add_package_to_user(WP_REST_Request $request){
        // Get params from request and post meta
        $user_id = $request->get_param('user_id');
        $user_name = get_userdata($user_id)->first_name . ' ' . get_userdata($user_id)->last_name;
        $package = $request->get_param('package_id');
        $quantity = intval(get_post_meta($package, '_appointment_package_quantity', true));
        $quantity_remaining = $request->get_param('quantity_remaining');
        $appointment = intval(get_post_meta($package, '_appointment_package_type', true));

        // Package data to save to a new package post
        $meta = [
            '_package_product_id'			=>	$package, // The Woocommerce package product ID
            '_appointment_product_id'		=>	$appointment, // The Woocommerce appointment product that this is a bundle of
            '_order_id'						=>	null,
            '_package_quantity'				=>	$quantity,
            '_package_quantity_remaining'	=>	$quantity_remaining,
            '_package_active'				=>	$quantity_remaining > 0 ? true : false, // sets to false when used up
            '_user_id'						=>	$user_id,
            '_user_name'					=>	$user_name,
            '_appointment_usage'			=>	[] // Will track when appointments are used
        ];
        // Args for creating the new package post
        $args = [
            'post_title'		=>	get_the_title($package),
            'post_status'		=>	'publish',
            'meta_input'		=>	$meta,
            'post_type'			=>	'package',
            'post_author'		=>	$user_id
        ];

        $new_package = wp_insert_post($args);
        if ($new_package == 0) {
            error_log('Package save failure for user ' . $user_id);
            return false;
        } else {
            return true;
        }
    }
    
    public function add_package_to_user_permissions_check(){
        // TODO verify requests come from our admin
        return true;
    }
} Packages_API::get_instance();

