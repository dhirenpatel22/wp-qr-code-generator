<?php
/**
 * Plugin Name: QR Code Generator
 * Plugin URI: https://www.dhirenpatel.me/
 * Description: A simple QR code generator with customization and shortcode support.
 * Version: 2.0
 * Requires PHP: 8.2
 * Author: Dhiren Patel
 * Author URI: https://www.dhirenpatel.me/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qr-code-generator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// QR code library: chillerlan/php-qrcode, installed with Composer into vendor/.
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Generate a QR code PNG in memory and return it as a data URI. Nothing is written to disk.
 *
 * @since 2.0
 * @param string $content Text to encode.
 * @param int[]  $fore    Foreground colour as [ r, g, b ].
 * @param int[]  $back    Background colour as [ r, g, b ].
 * @param int    $size    Requested size in pixels; the image is capped at 1024 pixels.
 * @return string data:image/png;base64,... URI.
 * @throws \chillerlan\QRCode\QRCodeException If the content is too long to encode.
 */
function qr_generator_png_data_uri( $content, $fore, $back, $size ) {
	$module_values = array();
	foreach ( QROutputInterface::DEFAULT_MODULE_VALUES as $type => $is_dark ) {
		$module_values[ $type ] = $is_dark ? $fore : $back;
	}

	$options = new QROptions(
		array(
			'eccLevel'        => EccLevel::L,
			'quietzoneSize'   => 2,
			'outputInterface' => QRGdImagePNG::class,
			'outputBase64'    => true,
			'bgColor'         => $back,
			'moduleValues'    => $module_values,
		)
	);

	$qrcode = new QRCode( $options );
	$qrcode->addByteSegment( $content );
	$matrix = $qrcode->getQRMatrix();

	// Pixels per module: size / 25 with a minimum of 4, reduced so the image stays within 1024 pixels.
	$options->scale = min( max( 4, intdiv( $size, 25 ) ), max( 1, intdiv( 1024, $matrix->getSize() ) ) );

	return $qrcode->renderMatrix( $matrix );
}

/**
 * Convert a #rrggbb colour to [ r, g, b ], with a fallback for invalid input.
 *
 * @since 2.0
 * @param string|null $hex      Colour from sanitize_hex_color().
 * @param int[]       $fallback Colour to use when $hex is empty.
 * @return int[]
 */
function qr_generator_hex_to_rgb( $hex, $fallback ) {
	if ( ! is_string( $hex ) || 7 !== strlen( $hex ) ) {
		return $fallback;
	}
	return array_map( 'hexdec', str_split( substr( $hex, 1 ), 2 ) );
}

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
	wp_enqueue_style( 'qr-generator-style', plugin_dir_url( __FILE__ ) . 'style.css', array(), '2.0' );

	ob_start();

	// Handle form submission.
	if ( isset( $_POST['generate_qr'], $_POST['qr_generator_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qr_generator_nonce'] ) ), 'qr_generator_nonce_action' ) ) {
		$data = isset( $_POST['qr_data'] ) ? sanitize_text_field( wp_unslash( $_POST['qr_data'] ) ) : '';
		$type = isset( $_POST['qr_type'] ) ? sanitize_text_field( wp_unslash( $_POST['qr_type'] ) ) : 'text';
		$fore = qr_generator_hex_to_rgb( isset( $_POST['qr_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['qr_color'] ) ) : null, array( 0, 0, 0 ) );
		$back = qr_generator_hex_to_rgb( isset( $_POST['qr_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['qr_bg_color'] ) ) : null, array( 255, 255, 255 ) );
		$size = isset( $_POST['qr_size'] ) ? min( 1200, max( 100, intval( wp_unslash( $_POST['qr_size'] ) ) ) ) : 400;

		// Prepare data based on type.
		switch ( $type ) {
			case 'vcard':
				$qr_content = "BEGIN:VCARD\nVERSION:3.0\nFN:$data\nEND:VCARD";
				break;
			case 'url':
			case 'text':
			default:
				$qr_content = $data;
				break;
		}

		$image_uri = '';
		if ( '' !== $data ) {
			try {
				$image_uri = qr_generator_png_data_uri( $qr_content, $fore, $back, $size );
			} catch ( \Throwable $e ) {
				$image_uri = '';
			}
		}

		if ( 0 === strpos( $image_uri, 'data:image/png;base64,' ) ) {
			// The image is a data URI built above (base64 only), so it is escaped as an attribute; esc_url() would drop the data: scheme.
			$qr_output = '
			<div class="qr-output">
				<img src="' . esc_attr( $image_uri ) . '" alt="' . esc_attr__( 'Generated QR Code', 'qr-code-generator' ) . '" class="qr-preview">
				<a href="' . esc_attr( $image_uri ) . '" download="qr-code.png" class="qr-download">' . esc_html__( 'Download QR Code', 'qr-code-generator' ) . '</a>
			</div>';
		} else {
			$generation_error = '' === $data
				? __( 'Enter the data to encode.', 'qr-code-generator' )
				: __( 'Could not generate the QR code. The data may be too long: try shorter text.', 'qr-code-generator' );
			$qr_output        = '<p class="qr-error">' . esc_html( $generation_error ) . '</p>';
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
