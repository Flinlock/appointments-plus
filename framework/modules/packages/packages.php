<?php

/**
 * Packages are added as a Woocommerce product type. They are a bundle of Appointable products.
 */

// TODO: add checks to make sure Woocommerce and Appointments plugins are active

/* Add Package product type */
add_action( 'plugins_loaded', 'register_appointment_package_type' );
function register_appointment_package_type() {
	class WC_Product_Appointment_Package extends WC_Product {
	public function __construct( $product ) {
		$this->product_type = 'appointment_package';
		parent::__construct( $product );
		// add additional functions here
	}
	}
}

/* Add the new type to the selector */
add_filter( 'product_type_selector', 'add_appointment_package_type' );
function add_appointment_package_type( $type ) {
	$type[ 'appointment_package' ] = __( 'Appointment Package' );
return $type;
}

/* Add a tab to the product settings section */
add_filter( 'woocommerce_product_data_tabs', 'appointment_package_tab' );
function appointment_package_tab( $tabs ) {
	$tabs['appointment_package'] = array(
	'label'	 => __( 'Package Details', 'appointments-plus' ),
	'target' => 'appointment_package_options',
	'class'  => ('show_if_appointment_package'),
);
return $tabs;
}

/* Add settings to the new tab */
add_action( 'woocommerce_product_data_panels', 'appointment_package_options_product_tab_content' );
function appointment_package_options_product_tab_content () {
// First get all active appointable products
$args = [
	'type'		=>	'appointment'
];
$appointable_products = wc_get_products($args);

$select_options[''] = __( 'Select a value', 'woocommerce');
foreach ($appointable_products as $product) {
	$id = $product->get_id();
	$title = $product->get_title();
	$select_options[$id] = $title;
}

?><div id='appointment_package_options' class='panel woocommerce_options_panel'><?php
	?><div class='options_group'><?php
		woocommerce_wp_text_input( 
			array(
				'id'		=>	'_appointment_package_quantity',
				'label'		=>	__( 'How many in package?', 'woocommerce' ),
			)
		);
		woocommerce_wp_select( 
			array( 
				'id'      => '_appointment_package_type', 
				'label'   => __( 'Select appointment type', 'woocommerce' ),
				'options' =>  $select_options
				)
			);
	?></div>
</div><?php

}

/* Customize the tabs that display for this product type */
if (is_admin()) {
	add_filter( 'woocommerce_product_tabs', 'appointment_package_edit_product_tabs', 98 );
}
function appointment_package_edit_product_tabs ( $tabs ) {
	array_push($tabs['general']['class'], 'show_if_appointment_package');
	return $tabs;
}
add_action( 'admin_footer', 'appointment_package_custom_js' );
function appointment_package_custom_js () {
	if ( 'product' != get_post_type() ) :
		return;
  endif;
  ?><script type='text/javascript'>
		jQuery( '.options_group.pricing' ).addClass( 'show_if_appointment_package' );
  </script><?php
}

/* Save data in our new product fields */
add_action( 'woocommerce_process_product_meta', 'save_appointment_package_options_field' );
function save_appointment_package_options_field( $post_id ) {
	
	if ( isset( $_POST['_appointment_package_type'] ) ) :
		update_post_meta( $post_id, '_appointment_package_type', sanitize_text_field( $_POST['_appointment_package_type'] ) );
	endif;

	if ( isset( $_POST['_appointment_package_quantity'] ) ) :
		update_post_meta( $post_id, '_appointment_package_quantity', sanitize_text_field( $_POST['_appointment_package_quantity'] ) );
	endif;
	
	if ( isset( $_POST['_appointment_package_price'] ) ) :
		update_post_meta( $post_id, '_appointment_package_price', sanitize_text_field( $_POST['_appointment_package_price'] ) );
	endif;
}

/* Add custom menu to Wordpress Admin for package tracking */
add_action( 'admin_menu', 'register_appointment_package_admin_menu', 10);
function register_appointment_package_admin_menu () {
	add_menu_page(
		__( 'Appointments Plus Admin', 'appointments-plus' ),
		'Package Tracking',
		'manage_options',
		'appointments-plus-plugin-admin-menu',
		'render_appointments_plus_admin_menu',
		'dashicons-admin-tools',
		58
  );
}
function render_appointments_plus_admin_menu(){
	// Build data on current and previous packages for all users
	$users_objects = get_users();
	$current_packages = [];
	$previous_packages = [];
	$users = [];
	foreach ($users_objects as $user) {
		$users[] = [
			'name'		=>	get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true),
			'id'		=>	$user->ID
		];
		$user_packages = is_array(get_user_meta($user->ID, 'appointment_packages', true)) ? get_user_meta($user->ID, 'appointment_packages', true) : false;
		if ($user_packages) {
			foreach ($user_packages as $package) {
				if ($package['quantity_remaining'] > 0) {
					$current_packages[] = [
						'user'		=>	[
							'name'		=>	get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true),
							'id'		=>	$user->ID
						],
						'title'		=>	get_the_title($package['package_id']),
						'quantity'	=>	$package['quantity'],
						'remaining'	=>	$package['quantity_remaining'],
					];
				} else {
					$previous_packages[] = [
						'user'		=>	[
							'name'		=>	get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true),
							'id'		=>	$user->ID
						],
						'title'		=>	get_the_title($package['package_id']),
						'quantity'	=>	$package['quantity'],
						'remaining'	=>	$package['quantity_remaining'],
					];
				}
			}
		}
	} 

	// Populate list of Appointment Packages
	$appointment_packages = wc_get_products([
		'type'		=>	'appointment_package',
		'return' 	=> 'ids'
	]);
	$packages = [];
	foreach ($appointment_packages as $appointment_package) {
		$packages[] = [
			'title'		=>	get_the_title($appointment_package),
			'id'		=>	$appointment_package
		];
	}

	$context = [
		 'current_packages'		=>	$current_packages,
		 'previous_packages'	=>	$previous_packages,
		 'users'				=>	$users,
		 'packages'				=>	$packages
	];
	Timber::render('package-tracking.twig', $context);
}

/* Add the "Add to Cart" button on appointment package pages */
function appointment_package_add_to_cart_button() {
    wc_get_template( 'single-product/add-to-cart/simple.php' );
}
add_action( 'woocommerce_appointment_package_add_to_cart', 'appointment_package_add_to_cart_button' );

/* 
 * Customize the "Added to Cart" message for Packages
 * If an appointment package is added to the cart we want the user to schedule an appointment before checking out
 */
add_filter( 'wc_add_to_cart_message_html', 'appointment_packages_add_to_cart_function', 10, 2 ); 
function appointment_packages_add_to_cart_function( $message, $products ) {
	$purchased = intval(key($products));
	// Get a list of appointment package products
	$appointment_packages = wc_get_products([
		'type'		=>	'appointment_package',
		'return' 	=> 'ids'
	]);

	// See if the purchased item matches an appointment package product
	foreach ($appointment_packages as $package) {
		if ($package == $purchased) {
			$appointment = intval(get_post_meta($purchased, '_appointment_package_type', true));
			// Match! Customize the message accordingly
			$message = '<a href="' . get_permalink($appointment) . '" tabindex="1" class="button wc-forward">Schedule Appointment</a>' . 
			get_the_title(key($products)) . 
			' has been added to your cart. Please schedule your first appointment before checking out!';
		}
	}
	return $message; 
}

/* 
 * Hook into post-checkout Woocommerce to look for packages and add package/appt data to user
 * This is for user-purchased packages only as the "Thank You" action is not triggered for manually added packages
 */
add_action('woocommerce_thankyou', 'add_package_to_user', 10, 1);
function add_package_to_user($order_id) {
	// Get info for the order, the user, and any packages/appointments in the order
	$order = wc_get_order($order_id);
	$user = $order->get_user_id();
	$line_items = $order->get_items();
	$packages = [];
	$appointments = [];
	$current_packages = is_array(get_user_meta($user, 'appointment_packages', true)) ? get_user_meta($user, 'appointment_packages', true) : [];
	foreach ($line_items as $line_item) {
		$product = wc_get_product($line_item->get_product_id());
		$product_type = $product->get_type();
		$product_id = $product->get_id();
		if ($product_type == 'appointment_package') {
			$packages[$product_id] = intval(get_post_meta($product_id, '_appointment_package_type', true));
		} elseif ($product_type == 'appointment') {
			if (isset($appointments[$product_id])) {
				$appointments[$product_id] += 1;
			} else {
				$appointments[$product_id] = $line_item->get_quantity();
			}
		}
	}

	/*
	 * Check to see if any packages were purchased
	 * If so, apply any qualifying appointments on this order
	 */
	if (sizeof($packages) > 0) {
		foreach ($packages as $package=>$appointment) {
			$quantity = intval(get_post_meta($package, '_appointment_package_quantity', true));
			$consumed = isset($appointments[$appointment]) ? $appointments[$appointment] : 0;
			$quantity_remaining = $quantity - $consumed >= 0 ? $quantity - $consumed : 0;
			$extra_quantity = $quantity - $consumed < 0 ? $consumed - $quantity : false;
			
			$current_packages[$order_id] = [
				'order_id'				=>	$order_id,
				'package_id'			=>	$package,
				'appointment_id'		=>	$appointment,
				'quantity'				=>	$quantity,
				'quantity_remaining'	=>	$quantity - $consumed >= 0 ? $quantity - $consumed : 0,
				'created_by'			=>	'user'
			];
			/*
			 * If all appointment quantity was accounted for with the package then remove it
			 * Otherwise, simply adjust quantity remaining
			 */
			if ($extra_quantity) {
				$appointments[$appointment] = $extra_quantity;
			} else {
				unset($appointments[$appointment]);
			}
			
		}
	}

	/*
	 * Check for any remaining appointments on this order
	 * If appointments and if user selected to pay with package and if the user has current packages
	 */
	if ((sizeof($appointments) > 0) && ($order->get_payment_method() == 'cod') && (sizeof($current_packages) > 0)) {
		foreach ($current_packages as $package_key => $package) {
			if ($package['quantity_remaining'] > 0) {
				foreach ($appointments as $appointment => $quantity) {
					if ($appointment == $package['appointment_id']) {
						// Subtract this appointment 
						// TODO account for someone buying more appointments than they have remaining
						$quantity_remaining = $package['quantity_remaining'] - $quantity >= 0 ? $package['quantity_remaining'] - $quantity : 0;
						$extra_quantity = $package['quantity_remaining'] - $quantity < 0 ? $quantity - $package['quantity_remaining'] : false;
						$package['quantity_remaining'] -= $quantity;
						$current_packages[$package_key] = $package;

						if ($extra_quantity) {
							$appointments[$appointment] = $extra_quantity;
						} else {
							unset($appointments[$appointment]);
						}
					}
				}
			}
		}
		// If no more appointments are in the order, mark the appointment as paid
		if (sizeof($appointments) == 0) {
			$order->update_status('completed');
			$order->add_order_note( __('Paid for using a prepaid package.') );
		}
	}

	// Update packages user meta 
	$updated = update_user_meta($user, 'appointment_packages', $current_packages);
}

/* Add My Packages menu to Woocommerce account page */
add_filter( 'woocommerce_account_menu_items', 'packages_menu_items', 10, 1 );
function packages_menu_items ( $items ) {
    $items['packages'] = __( 'Packages', 'woocommerce' );
    return $items;
}
add_action( 'init', 'add_packages_endpoint' );
function add_packages_endpoint() {
    add_rewrite_endpoint( 'packages', EP_PAGES );
}
add_action( 'woocommerce_account_packages_endpoint', 'packages_endpoint_content' );
function packages_endpoint_content() {
	$user = get_current_user_id();
	$packages = is_array(get_user_meta($user, 'appointment_packages', true)) ? get_user_meta($user, 'appointment_packages', true) : false;
	$current_packages = [];
	$previous_packages = [];
	if ($packages) {
		foreach ($packages as $package) {
			if ($package['quantity_remaining'] == 0) {
				$previous_packages[] = $package;
			} else {
				$current_packages[] = $package;
			}
		}


	}
	// Current packages
	$html = 
	'<h3>Current Packages</h3>';
	if (sizeof($current_packages) > 0) {
		$html = $html . '<table style="width:100%">
		<tr>
		  <th>Package Title</th>
		  <th>Quantity Remaining</th> 
		  <th>Book Appointment</th>
		</tr>';
		foreach ($current_packages as $current_package) {
			$title = get_the_title($current_package['package_id']);
			$quantity_remaining = $current_package['quantity_remaining'];
			$book_appointment = get_post_permalink($current_package['appointment_id']);
			$html = $html . 
			'<tr>
			<td>' . $title . '</td>
			<td>' . $quantity_remaining . '</td>
			<td><a href="' . $book_appointment . '">Click here to book</a></td>
			</tr>';
		}
		$html = $html . '</table>';
	} else {
		$html = $html . '<p>No current packages found. You can purchase one <a href="' . get_site_url() . '/massage-appointments">here</a>.</p>';
	}
	
	// Previous packages
	$html = $html . '<h3>Previous Packages</h3>';
	if (sizeof($previous_packages) > 0) {
		$html = $html . '<table style="width:100%">
		<tr>
		  <th>Package Title</th>
		  <th>Quantity Remaining</th> 
		  <th>Purchase New Package</th>
		</tr>';
		foreach ($previous_packages as $previous_package) {
			$title = get_the_title($previous_package['package_id']);
			$quantity_remaining = $previous_package['quantity_remaining'];
			$buy_package = get_post_permalink($previous_package['package_id']);
			$html = $html . 
			'<tr>
			<td>' . $title . '</td>
			<td>' . $quantity_remaining . '</td>
			<td><a href="' . $buy_package . '">Click here to purchase</a></td>
			</tr>';
		}
		$html = $html . '</table>';
	} else {
		$html = $html . '<p>No previous packages found.</p>';
	}



	echo $html;
}

