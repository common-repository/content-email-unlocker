<?php
/**
* Plugin Name: Content E-mail Unlock
* Version: 1.0
* Plugin URI: http://potrebka.pl/
* Description: Display content after past e-mail. Shortcode name [lock_email].
* Author: Piotr Potrebka
* Author URI:  http://potrebka.pl/
* License:     GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Domain Path: /languages
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Instantiate the main WordPress Term Images class
 *
 * @since 0.1.0
 */
if( !function_exists( 'content_email_unlocker' ) ):
function content_email_unlocker() {

	// Setup the main file
	$file = __FILE__;
	
	// Load localization file
	load_plugin_textdomain( 'ceutext', false, basename( dirname( $file ) ) . '/languages' );
	
	// Include the main class
	include dirname( $file ) . '/includes/admin_settings.php';
	include dirname( $file ) . '/includes/core.php';
	
	// Instantiate settings
	if( is_admin() ) new Content_Email_Unlocker_Settings();
	// Instantiate the main class
	
	$ceu_core = new Content_Email_Unlocker_Core( $file );
	
}
endif;
add_action( 'init', 'content_email_unlocker', 99 );

