<?php
/**
 * astra-child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package astra-child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );


/* =====================
	 * Custom Checkout Field
	 * ===================== */

	// Our hooked in function - $fields is passed via the filter!
	function custom_override_checkout_fields( $fields ) {
	     
		// Remove Fields
	    unset($fields['billing']['billing_company']);
	    unset($fields['billing']['billing_address_2']);
	    unset($fields['billing']['billing_last_name']);
	    unset($fields['billing']['billing_city']);
		unset($fields['billing']['billing_state']);
		
		unset($fields['shipping']['shipping_company']);
	    unset($fields['shipping']['shipping_address_2']);
	    unset($fields['shipping']['shipping_last_name']);
	    unset($fields['shipping']['shipping_city']);
	    unset($fields['shipping']['shipping_state']);


	    // Change Label
	    $fields['billing']['billing_first_name']['label'] = '姓名';
	    $fields['billing']['billing_email']['label'] = '聯絡信箱';
	    $fields['billing']['billing_phone']['label'] = '聯絡電話';
	    $fields['billing']['billing_address_1']['label'] = '聯絡電話';
	    $fields['billing']['billing_postcode']['label'] = '郵遞區號';
		$fields['billing']['billing_country']['label'] = '運送國家';
		
		$fields['shipping']['shipping_first_name']['label'] = '姓名';
	    $fields['shipping']['shipping_email']['label'] = '聯絡信箱';
	    $fields['shipping']['shipping_phone']['label'] = '聯絡電話';
	    $fields['shipping']['shipping_address_1']['label'] = '聯絡電話';
	    $fields['shipping']['shipping_postcode']['label'] = '郵遞區號';
	    $fields['shipping']['shipping_country']['label'] = '運送國家';

		unset($fields['order']['order_comments']['label']);

	    // Change Placeholder
	    $fields['billing']['billing_first_name']['placeholder'] = '請輸入姓名';
	    $fields['billing']['billing_email']['placeholder'] = '請輸入Email';
	    $fields['billing']['billing_phone']['placeholder'] = '請輸入聯絡電話';
	    $fields['billing']['billing_address_1']['placeholder'] = '請輸入聯絡地址';
		$fields['billing']['billing_postcode']['placeholder'] = '請輸入郵遞區號(3碼)';
		
		$fields['shipping']['shipping_first_name']['placeholder'] = '請輸入姓名';
	    $fields['shipping']['shipping_email']['placeholder'] = '請輸入Email';
	    $fields['shipping']['shipping_phone']['placeholder'] = '請輸入聯絡電話';
	    $fields['shipping']['shipping_address_1']['placeholder'] = '請輸入聯絡地址';
	    $fields['shipping']['shipping_postcode']['placeholder'] = '請輸入郵遞區號(3碼)';

		//Change Class
		$fields["billing"]["billing_first_name"]["class"] = array('form-row-wide');
		
		$fields["shipping"]["shipping_first_name"]["class"] = array('form-row-wide');

	   return $fields;
	}

	add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );


// Add custom Theme Functions here
add_action( 'woocommerce_before_order_notes', 'add_invoice_type' );
function add_invoice_type( $checkout ) {
woocommerce_form_field( 'invoice_type', array(
	'type' => 'radio',
	'class' => array( 'form-row-wide' ),
	'label' => '發票開立',
	'options' => array(
	'invoice_no' => '二聯式發票',
	'invoice_yes' => '三聯式發票',
	)
),$checkout->get_value( 'invoice_type' ));
woocommerce_form_field( 'company_name', array(
	'type' => 'text',
	'class' => array( 'form-row-wide' ),
	'label' => '公司抬頭',
),$checkout->get_value( 'company_name' ));
woocommerce_form_field( 'company_id', array(
	'type' => 'text',
	'class' => array( 'form-row-wide' ),
	'label' => '統一編號',
),$checkout->get_value( 'company_id' ));
}
add_action('woocommerce_checkout_update_order_meta', 'update_invoice_meta');
function update_invoice_meta( $order_id ) {
	if ($_POST['invoice_type']){
		update_post_meta( $order_id, 'invoice_type', esc_attr($_POST['invoice_type']));
		update_post_meta( $order_id, 'company_name', esc_attr($_POST['company_name']));
		update_post_meta( $order_id, 'company_id', esc_attr($_POST['company_id']));
	}
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'custom_order_meta_invoice', 10, 1 );
function custom_order_meta_invoice($order){
	if( get_post_meta( $order->id, 'invoice_type', true ) == 'invoice_yes' ){
		echo '<h3><strong>發票:</strong> 開立三聯式發票</h3>';
		echo '<p><strong>公司抬頭:</strong> ' . get_post_meta( $order->id, 'company_name', true );
		echo '<br><strong>統一編號:</strong> ' . get_post_meta( $order->id, 'company_id', true ).'</p>';
	} else {
		echo '<h3><strong>發票:</strong> 開立二聯式發票</h3>';
	}
}
add_filter("woocommerce_after_checkout_form", "invoice_container");
function invoice_container(){
	$output = '
	<style>label.radio{display:inline-block;margin-right:1rem;}</style>
	<script>
	var $ = jQuery.noConflict();
		$(document).ready(function(){
			$("#invoice_type_invoice_no").prop("checked", true);
			$("#company_name_field,#company_id_field").hide();
			$("input[name=invoice_type]").on("change",function(){
			if($("#invoice_type_invoice_yes").is(":checked")) {
				$("#company_name_field,#company_id_field").fadeIn();
			} else {
				$("#company_name_field,#company_id_field").fadeOut();
			}
		})
	});
	</script>
	';
echo $output;
}