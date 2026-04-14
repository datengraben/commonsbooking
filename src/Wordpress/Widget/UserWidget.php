<?php

namespace CommonsBooking\Wordpress\Widget;

use CommonsBooking\Settings\Settings;
use WP_Widget;

/**
 * Class provides the commonsbooking user widget
 */
class UserWidget extends WP_Widget {

	function __construct() {

		parent::__construct(
			'commonsbooking-user-widget',  // Base ID
			'CommonsBooking User Widget',   // Name
			array( 'description' => esc_html__( 'Shows links to My Bookings, Login, Logout. Please set the Bookings Page in CommonsBooking Settings (General-Tab)', 'commonsbooking' ) )
		);

		add_action(
			'widgets_init',
			function () {
				register_widget( '\CommonsBooking\Wordpress\Widget\UserWidget' );
			}
		);
	}

	public $args = array(
		'before_title'  => '<h4 class="widgettitle">',
		'after_title'   => '</h4>',
		'before_widget' => '<div class="widget-wrap">',
		'after_widget'  => '</div></div>',
	);

	public function widget( $args, $instance ) {

		echo commonsbooking_sanitizeHTML( $args['before_widget'] );

		if ( ! empty( $instance['title'] ) ) {
			$unfilteredTitle = $instance['title'];
			/**
			 * Default widget title
			 *
			 * @since 2.10.0 uses commonsbooking prefix
			 * @since 2.4.0
			 *
			 * @param string $unfilteredTitle of the widget
			 */
			$title = apply_filters( 'commonsbooking_widget_title', $unfilteredTitle );
			echo commonsbooking_sanitizeHTML( $args['before_title'] . $title . $args['after_title'] );
		}

		echo '<div class="textwidget">';

		echo commonsbooking_sanitizeHTML( $this->renderWidgetContent() );

		echo '</div>';

		echo commonsbooking_sanitizeHTML( $args['after_widget'] );
	}

	public function renderWidgetContent() {

		$content = '';

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();

			$bookings_page_url = get_permalink( Settings::getOption( COMMONSBOOKING_PLUGIN_SLUG . '_options_general', 'bookings_page' ) );
			if ( empty( $bookings_page_url ) ) {
				$bookings_page_url = get_home_url();
			}

			// user name or email
			if ( ! empty( $current_user->first_name ) ) {
				$loginname = $current_user->first_name;
			} else {
				$loginname = $current_user->user_email;
			}

			// translators: %s = user first name or email
			$content .= sprintf( __( 'Welcome %s', 'commonsbooking' ), esc_html( $loginname ) );
			$content .= '<ul>';
			$content .= '<li><a href="' . esc_url( $bookings_page_url ) . '">' . esc_html__( 'My Bookings', 'commonsbooking' ) . '</a></li>';
			$content .= '<li><a href="' . esc_url( get_edit_profile_url() ) . '">' . esc_html__( 'My Profile', 'commonsbooking' ) . '</a></li>';
			$content .= '<li><a href="' . esc_url( wp_logout_url() ) . '">' . esc_html__( 'Log out', 'commonsbooking' ) . '</a></li>';
			$content .= '</ul>';
		} else {
			$content  = esc_html__( 'You are not logged in.', 'commonsbooking' );
			$content .= '<ul>';
			$content .= '<li><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Login', 'commonsbooking' ) . '</a></li>';
			$content .= '<li><a href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Register', 'commonsbooking' ) . '</a></li>';
			$content .= '</ul>';
		}

		return $content;
	}

	/**
	 * @param $instance
	 *
	 * @return string
	 */
	public function form( $instance ) {

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$text  = ! empty( $instance['text'] ) ? $instance['text'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo esc_html__( 'Title:', 'commonsbooking' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'Text' ) ); ?>"><?php echo esc_html__( 'Text:', 'commonsbooking' ); ?></label>
			<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" cols="30"
						rows="10"><?php echo esc_attr( $text ); ?></textarea>
		</p>
		<?php

		return ''; // Parent class returns string, not used
	}

	public function update( $new_instance, $old_instance ) {

		$instance = array();

		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['text']  = ( ! empty( $new_instance['text'] ) ) ? $new_instance['text'] : '';

		return $instance;
	}
}

$my_widget = new UserWidget();
