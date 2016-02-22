<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Plugin Name: CF7 Doar Tracking
Description: Enables package delivery tracking using Israel Post & Contact Form 7
Version: 1.0.0
Author: Ido Friedlander
Author URI: https://profiles.wordpress.org/idofri/
*/

class CF7_Doar_Tracking {
	
	public function __construct() {
		
		add_action( 'wpcf7_init', array( &$this, 'doar_add_shortcode' ) );
		
		add_filter( 'wpcf7_validate_doar', array( &$this, 'doar_filter' ), 20, 2 );
		
		add_filter( 'wpcf7_validate_doar*', array( &$this, 'doar_filter' ), 20, 2 );
		
		add_action( 'wpcf7_admin_init', array( &$this, 'doar_form_tag' ) );
	}
	
	public function doar_add_shortcode() {
	
		wpcf7_add_shortcode( array( 'doar', 'doar*' ), array( &$this, 'doar_shortcode_handler' ), true );
	}
	
	public function doar_shortcode_handler( $tag ) {
	
		$tag = new WPCF7_Shortcode( $tag );

		if ( empty( $tag->name ) )
			return '';

		$validation_error = wpcf7_get_validation_error( $tag->name );

		$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-text' );

		if ( in_array( $tag->basetype, array( 'email', 'url', 'tel' ) ) )
			$class .= ' wpcf7-validates-as-' . $tag->basetype;

		if ( $validation_error )
			$class .= ' wpcf7-not-valid';

		$atts = array();

		$atts['size'] = $tag->get_size_option( '40' );
		$atts['maxlength'] = $tag->get_maxlength_option();
		$atts['minlength'] = $tag->get_minlength_option();

		if ( $atts['maxlength'] && $atts['minlength'] && $atts['maxlength'] < $atts['minlength'] ) {
			unset( $atts['maxlength'], $atts['minlength'] );
		}

		$atts['class'] = $tag->get_class_option( $class );
		$atts['id'] = $tag->get_id_option();
		$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );

		if ( $tag->has_option( 'readonly' ) )
			$atts['readonly'] = 'readonly';

		if ( $tag->is_required() )
			$atts['aria-required'] = 'true';

		$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

		$value = (string) reset( $tag->values );

		if ( $tag->has_option( 'placeholder' ) || $tag->has_option( 'watermark' ) ) {
			$atts['placeholder'] = $value;
			$value = '';
		}

		$value = $tag->get_default_option( $value );

		$value = wpcf7_get_hangover( $tag->name, $value );

		$atts['value'] = $value;

		$atts['type'] = 'text';

		$atts['name'] = $tag->name;

		$atts = wpcf7_format_atts( $atts );

		$html = sprintf(
			'<span class="wpcf7-form-control-wrap %1$s"><input %2$s />%3$s</span>',
			sanitize_html_class( $tag->name ), $atts, $validation_error );

		return $html;
	}
	
	public function doar_validate( $value ) {
		
		if ( is_wp_error( $response = wp_safe_remote_get( $this->doar_JSON( $value ) ) ) ) {
			
			return __( $response->get_error_message(), 'contact-form-7' );
			
		} else {
		
			$json_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			
			if ( json_last_error() == JSON_ERROR_NONE ) {
			
				return strip_tags( preg_replace('#<script(.*?)>(.*?)</script>#is', '', $json_decoded['itemcodeinfo'] ) );
			}
		}
		
		return __( 'JSON Error', 'contact-form-7' );
	}
	
	public function doar_filter( $result, $tag ) {
		
		$tag = new WPCF7_Shortcode( $tag );
		
		if ( isset( $_POST[ $tag->name ] ) && !empty( $_POST[ $tag->name ] ) ) {
			
			$result->invalidate( $tag, $this->doar_validate( $_POST[ $tag->name ] ) );
		}
		
		$result->invalidate( $tag, __( 'Please fill in the required field.', 'contact-form-7' ) );
		
		return $result;
	}
	
	public function doar_form_tag() {
		
		$tag_generator = WPCF7_TagGenerator::get_instance();
		
		$tag_generator->add( 'doar', __( 'doar tracking', 'contact-form-7' ), array( &$this, 'doar_tag_generator' ) );
	}
	
	private function doar_JSON( $value ) {
		
		$locale = ( substr( get_locale(), 0, 2 ) == 'he' ) ? 'he' : 'en';
		
		return sprintf( 'http://www.israelpost.co.il/itemtrace.nsf/trackandtraceJSON?openagent&lang=%s&itemcode=%s', $locale, $value );
	}
	
	public function doar_tag_generator( $contact_form, $args = '' ) {
	
		$args = wp_parse_args( $args, array() );
		
		$type = $args['id'];
		?>
		<div class="control-box">
			<fieldset>
				<table class="form-table">
					<tbody>
						<tr>
						<th scope="row"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></th>
						<td>
							<fieldset>
							<legend class="screen-reader-text"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></legend>
							<label><input type="checkbox" name="required" /> <?php echo esc_html( __( 'Required field', 'contact-form-7' ) ); ?></label>
							</fieldset>
						</td>
						</tr>
						<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
						<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
						</tr>
						<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Default value', 'contact-form-7' ) ); ?></label></th>
						<td><input type="text" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" /><br />
						<label><input type="checkbox" name="placeholder" class="option" /> <?php echo esc_html( __( 'Use this text as the placeholder of the field', 'contact-form-7' ) ); ?></label></td>
						</tr>
						<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'Id attribute', 'contact-form-7' ) ); ?></label></th>
						<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" /></td>
						</tr>
						<tr>
						<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class attribute', 'contact-form-7' ) ); ?></label></th>
						<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" /></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
		</div>
		<div class="insert-box">
			<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" />
			<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
			</div>
			<br class="clear" />
			<p class="description mail-tag"><label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>"><?php echo sprintf( esc_html( __( "To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7' ) ), '<strong><span class="mail-tag"></span></strong>' ); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" /></label></p>
		</div>
		<?php
	}
}

function init_cf7_doar_tracking() {
	
	if ( !class_exists( 'WPCF7_Shortcode' ) ) return;
	
	$doar = new CF7_Doar_Tracking();
}
add_action( 'plugins_loaded', 'init_cf7_doar_tracking' );