<?php
/**
 * Plugin Name: QR Code Generator
 * Plugin URI: https://www.dhirenpatel.me/
 * Description: A simple QR code generator with customization and shortcode support.
 * Version: 1.4
 * Author: Dhiren Patel
 * Author URI: https://www.dhirenpatel.me/
 * License: GPL2
 * Text Domain: qr-code-generator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the QR code library.
require_once plugin_dir_path( __FILE__ ) . 'phpqrcode/qrlib.php';

/**
 * Add text overlay to a QR code image.
 *
 * @param string $image_path Path to the QR code image.
 * @param string $text Text to overlay.
 * @param int $size Size of the QR code.
 * @param int $background_color Background color in hex format.
 * @return bool True on success, false on failure.
 */
function qr_add_text_overlay( $image_path, $text, $size, $background_color = 0xFFFFFF ) {
	$image = imagecreatefrompng( $image_path );
	if ( ! $image ) {
		return false;
	}

	// Use smaller built-in font
	$font_size = 3; // Smaller font size (1-5, where 1 is smallest)
	
	// Determine text color based on background color brightness
	$text_color = qr_get_contrasting_color( $image, $background_color );
	
	// Calculate text position to center it at the bottom
	$image_width = imagesx( $image );
	$image_height = imagesy( $image );
	
	// Get text dimensions using built-in font
	$text_width = strlen( $text ) * imagefontwidth( $font_size );
	$text_height = imagefontheight( $font_size );
	
	// Position text at the bottom center with some padding
	$x = ( $image_width - $text_width ) / 2;
	$y = $image_height - $text_height - 1; // 1px from bottom (moved closer to bottom)
	
	// Add text to the image
	imagestring( $image, $font_size, $x, $y, $text, $text_color );
	
	// Save the image with the overlay
	imagepng( $image, $image_path );
	imagedestroy( $image );
	
	return true;
}

/**
 * Get contrasting color (black or white) based on background color brightness.
 *
 * @param resource $image Image resource.
 * @param int $background_color Background color in hex format.
 * @return int Color resource for contrasting text color.
 */
function qr_get_contrasting_color( $image, $background_color ) {
	// Extract RGB components
	$r = ( $background_color >> 16 ) & 0xFF;
	$g = ( $background_color >> 8 ) & 0xFF;
	$b = $background_color & 0xFF;
	
	// Calculate brightness using luminance formula
	$brightness = ( $r * 0.299 ) + ( $g * 0.587 ) + ( $b * 0.114 );
	
	// Return black for light backgrounds, white for dark backgrounds
	if ( $brightness > 128 ) {
		return imagecolorallocate( $image, 0, 0, 0 ); // Black
	} else {
		return imagecolorallocate( $image, 255, 255, 255 ); // White
	}
}

/**
 * Handle QR code download and deletion.
 *
 * @since 1.2
 */
function qr_generator_handle_download() {
	if ( isset( $_GET['qr_download'] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'qr_download_nonce_action' ) ) {
		$filename  = sanitize_file_name( wp_unslash( $_GET['qr_download'] ) );
		$file_path = plugin_dir_path( __FILE__ ) . 'generated/' . $filename;

		if ( file_exists( $file_path ) ) {
			header( 'Content-Type: image/png' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			readfile( $file_path );
			unlink( $file_path ); // Delete after serving.
			exit;
		}
	}
}
add_action( 'init', 'qr_generator_handle_download' );

/**
 * QR code generator shortcode callback.
 *
 * Enqueues required styles only when the shortcode is rendered.
 *
 * @since 1.2
 * @return string The shortcode output.
 */
function qr_generator_shortcode() {
	// Enqueue styles only when shortcode is rendered
	wp_enqueue_style( 'qr-generator-style', plugin_dir_url( __FILE__ ) . 'style.css', array(), '1.2' );

	ob_start();

	$generation_error = '';

	// Cleanup old files (older than 5 minutes).
	$generated_dir = plugin_dir_path( __FILE__ ) . 'generated/';
	if ( file_exists( $generated_dir ) ) {
		foreach ( glob( $generated_dir . 'qr-*.png' ) as $file ) {
			if ( time() - filemtime( $file ) > 300 ) { // 5 minutes.
				unlink( $file );
			}
		}
	}

	// Handle form submission.
	if ( isset( $_POST['generate_qr'] ) && isset( $_POST['qr_generator_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qr_generator_nonce'] ) ), 'qr_generator_nonce_action' ) ) {
		$data            = sanitize_text_field( wp_unslash( $_POST['qr_data'] ) );
		$type            = sanitize_text_field( wp_unslash( $_POST['qr_type'] ) );
		$fore_color_hex  = sanitize_hex_color( wp_unslash( $_POST['qr_color'] ) );
		$back_color_hex  = sanitize_hex_color( wp_unslash( $_POST['qr_bg_color'] ) );
		$size            = intval( wp_unslash( $_POST['qr_size'] ) );

		// Convert hex to integer for library (e.g., 0xRRGGBB).
		$fore_color = hexdec( substr( $fore_color_hex, 1 ) );
		$back_color = hexdec( substr( $back_color_hex, 1 ) );

		// Prepare data based on type.
		switch ( $type ) {
			case 'url':
				$qr_content = $data;
				break;
			case 'vcard':
				$qr_content = "BEGIN:VCARD\nVERSION:3.0\nFN:$data\nEND:VCARD";
				break;
			case 'text':
			default:
				$qr_content = $data;
				break;
		}

		// Generate QR code with colors.
		$overlay_text = '';
		$unique_hash = md5( $qr_content . $fore_color_hex . $back_color_hex . $size . $overlay_text );
		$filename    = 'qr-' . $unique_hash . '.png';
		$file_path   = $generated_dir . $filename;

		if ( ! file_exists( $generated_dir ) ) {
			if ( ! wp_mkdir_p( $generated_dir ) ) {
				$generation_error = __( 'Failed to create generated directory. Check server permissions.', 'qr-code-generator' );
			}
		}

		if ( empty( $generation_error ) ) {
			$module_size = max( 4, intval( $size / 25 ) ); // Ensure a minimum module size for visibility.
			QRcode::png( $qr_content, $file_path, QR_ECLEVEL_L, $module_size, 2, false, $back_color, $fore_color );
			
			// Add fixed text overlay to every QR code
			qr_add_text_overlay( $file_path, $overlay_text, $size, $back_color );
		}

		if ( empty( $generation_error ) && file_exists( $file_path ) ) {
			// Output preview and download with cache-busting and nonce.
			$image_url    = plugin_dir_url( __FILE__ ) . 'generated/' . $filename . '?v=' . time();
			$download_url = add_query_arg(
				array(
					'qr_download' => $filename,
					'nonce'       => wp_create_nonce( 'qr_download_nonce_action' ),
				),
				home_url()
			);
			$qr_output = '
			<div class="qr-output">
				<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'Generated QR Code', 'qr-code-generator' ) . '" class="qr-preview">
				<a href="' . esc_url( $download_url ) . '" class="qr-download">' . esc_html__( 'Download QR Code', 'qr-code-generator' ) . '</a>
			</div>';
		} else {
			$generation_error = $generation_error ?: __( 'Failed to generate QR code. Ensure GD library is enabled and the library is correctly installed.', 'qr-code-generator' );
			$qr_output = '<p class="qr-error">' . esc_html( $generation_error ) . '</p>';
		}
	}

	?>
	<div class="qr-generator-container">
		<div class="qr-column qr-form-column">
			<form method="post" action="#qr-result" class="qr-generator-form">
				<?php wp_nonce_field( 'qr_generator_nonce_action', 'qr_generator_nonce' ); ?>
				<div class="qr-form-fields">
					<div>
						<label for="qr_data"><?php esc_html_e( 'Enter Data', 'qr-code-generator' ); ?></label>
						<input type="text" id="qr_data" name="qr_data" required placeholder="<?php esc_attr_e( 'e.g., https://example.com', 'qr-code-generator' ); ?>">
					</div>

					<div>
						<label for="qr_type"><?php esc_html_e( 'QR Type', 'qr-code-generator' ); ?></label>
						<select id="qr_type" name="qr_type">
							<option value="url"><?php esc_html_e( 'URL', 'qr-code-generator' ); ?></option>
							<option value="text"><?php esc_html_e( 'Text', 'qr-code-generator' ); ?></option>
							<option value="vcard"><?php esc_html_e( 'vCard', 'qr-code-generator' ); ?></option>
						</select>
					</div>

					<div>
						<label for="qr_color"><?php esc_html_e( 'Foreground Color', 'qr-code-generator' ); ?></label>
						<input type="color" id="qr_color" name="qr_color" value="#000000">
					</div>

					<div>
						<label for="qr_bg_color"><?php esc_html_e( 'Background Color', 'qr-code-generator' ); ?></label>
						<input type="color" id="qr_bg_color" name="qr_bg_color" value="#FFFFFF">
					</div>

					<div>
						<label for="qr_size"><?php esc_html_e( 'Size (pixels)', 'qr-code-generator' ); ?></label>
						<input type="number" id="qr_size" name="qr_size" min="100" max="1200" value="400">
					</div>

					<input type="submit" name="generate_qr" value="<?php esc_attr_e( 'Generate QR Code', 'qr-code-generator' ); ?>">
				</div>
			</form>
		</div>
		
		<div class="qr-column qr-output-column">
			<?php // After a submit the page reloads; focus moves to the result so keyboard and screen-reader users land on it. ?>
			<div id="qr-result" class="qr-output-area"<?php echo isset( $qr_output ) ? ' tabindex="-1" autofocus aria-label="' . esc_attr__( 'QR code result', 'qr-code-generator' ) . '" role="region"' : ''; ?>>
				<?php if ( isset( $qr_output ) ) : ?>
					<?php echo $qr_output; ?>
				<?php else : ?>
					<p class="qr-placeholder"><?php esc_html_e( 'Fill out the form and click "Generate QR Code" to create your QR code.', 'qr-code-generator' ); ?></p>
				<?php endif; ?>
			</div>
			
			<?php if ( shortcode_exists( 'wpsr_share_icons' ) ) : ?>
			<div class="qr-share-icons">
				<h5>Found this useful? Share it with someone who needs it!</h5>
				<?php echo do_shortcode( '[wpsr_share_icons icons="whatsapp, sms, facebook, x, linkedin, shortlink"]' ); ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
add_shortcode( 'qr_generator', 'qr_generator_shortcode' );
