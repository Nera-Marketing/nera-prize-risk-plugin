<?php
/**
 * "Postal entry" tag on orders (D-1, CHG-6): edit-order checkbox, auto-tag for admin-created £0 orders,
 * and a "Postal" label in the orders list. HPOS and CPT storage.
 *
 * @package Nera_Prize_Risk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Postal entry tag.
 */
class Nera_Prize_Risk_Postal {

	/**
	 * Order meta key: 'yes' / 'no'; absent = never set (the auto rule may still tag it).
	 */
	const META = '_nera_postal_entry';

	/**
	 * Nonce action and field.
	 */
	const NONCE = 'nera_postal_entry_save';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		// Priority 60: after WooCommerce saves items (10), order data (40) and actions (50).
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ), 60, 1 );

		// Orders list: HPOS and CPT.
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_column_cpt' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Order screen ids: HPOS page screen and the CPT post type screen.
	 *
	 * @return string[]
	 */
	private static function screens() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		return array_unique( $screens );
	}

	/**
	 * Whether the order is tagged as a postal entry.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_tagged( $order ) {
		return 'yes' === $order->get_meta( self::META );
	}

	/**
	 * Register the sidebar box on the edit-order screens.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		foreach ( self::screens() as $screen ) {
			add_meta_box( 'nera-postal-entry-box', __( 'Postal entry', 'nera-prize-risk' ), array( __CLASS__, 'render_meta_box' ), $screen, 'side', 'default' );
		}
	}

	/**
	 * Box markup. `nera_postal_entry_was` carries the state shown, so an unchanged unticked box
	 * is not read as a manual untick (which would block the auto-tag).
	 *
	 * @param WP_Post|WC_Order $post_or_order CPT post or HPOS order.
	 * @return void
	 */
	public static function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$tagged = self::is_tagged( $order );
		wp_nonce_field( self::NONCE, 'nera_postal_entry_nonce' );
		?>
		<input type="hidden" name="nera_postal_entry_was" value="<?php echo $tagged ? '1' : '0'; ?>" />
		<p>
			<label for="nera-postal-entry">
				<input type="checkbox" id="nera-postal-entry" name="nera_postal_entry" value="1" <?php checked( $tagged ); ?> />
				<?php esc_html_e( 'Postal entry', 'nera-prize-risk' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Tickets on this order count as free entries in the Prize Risk report, with no revenue.', 'nera-prize-risk' ); ?></p>
		<?php
	}

	/**
	 * Save the tag: a changed box is a manual choice (yes/no); an unchanged box on a never-set order
	 * applies the auto rule (created in wp-admin with a £0 total); otherwise the stored value stays.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function save( $order_id ) {
		if ( ! isset( $_POST['nera_postal_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nera_postal_entry_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$posted  = ! empty( $_POST['nera_postal_entry'] );
		$was     = isset( $_POST['nera_postal_entry_was'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['nera_postal_entry_was'] ) );
		$current = $order->get_meta( self::META );

		if ( $posted !== $was ) {
			$value = $posted ? 'yes' : 'no';
		} elseif ( '' === $current && 'admin' === $order->get_created_via() && abs( (float) $order->get_total() ) < 0.005 ) {
			$value = 'yes';
		} else {
			return;
		}
		if ( $value === $current ) {
			return;
		}

		$order->update_meta_data( self::META, $value );
		$order->save();
		if ( class_exists( 'Nera_Prize_Risk_Data' ) ) {
			Nera_Prize_Risk_Data::flush_order( $order->get_id() );
		}
	}

	/**
	 * Add the column before Actions (or at the end).
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$label = __( 'Postal entry', 'nera-prize-risk' );
		if ( ! isset( $columns['wc_actions'] ) ) {
			$columns['nera_postal'] = $label;
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $name ) {
			if ( 'wc_actions' === $key ) {
				$out['nera_postal'] = $label;
			}
			$out[ $key ] = $name;
		}
		return $out;
	}

	/**
	 * HPOS list cell.
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Order.
	 * @return void
	 */
	public static function render_column_hpos( $column, $order ) {
		if ( 'nera_postal' === $column && $order instanceof WC_Order ) {
			self::render_mark( $order );
		}
	}

	/**
	 * CPT list cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order id.
	 * @return void
	 */
	public static function render_column_cpt( $column, $post_id ) {
		if ( 'nera_postal' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			self::render_mark( $order );
		}
	}

	/**
	 * The "Postal" mark for tagged orders; nothing otherwise.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private static function render_mark( $order ) {
		if ( self::is_tagged( $order ) ) {
			echo '<mark class="nera-prize-risk-postal-mark">' . esc_html__( 'Postal', 'nera-prize-risk' ) . '</mark>';
		}
	}

	/**
	 * Admin CSS on the order screens (list and edit).
	 *
	 * @return void
	 */
	public static function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array_merge( self::screens(), array( 'edit-shop_order' ) ), true ) ) {
			return;
		}
		wp_enqueue_style( 'nera-prize-risk-admin', NERA_PRIZE_RISK_PLUGIN_URL . 'assets/css/admin.css', array(), NERA_PRIZE_RISK_VERSION );
	}
}
