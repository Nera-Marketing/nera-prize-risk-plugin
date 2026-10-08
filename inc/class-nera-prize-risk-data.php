<?php
/**
 * Prize Risk report data: one row of live figures per lottery product with a prize cost.
 *
 * Loaded on every request (not only wp-admin) so the cache is cleared when an order changes
 * at checkout, by cron or over REST. It never outputs anything.
 *
 * Revenue is read with SQL on the order item tables (the same in HPOS and CPT storage) joined
 * to the order table WooCommerce uses, so a product with thousands of orders doesn't load
 * thousands of order objects. Refunds come from refund line items (`_refunded_item_id`), the
 * same source as WC_Order::get_total_refunded_for_item(), counted only while the refund order exists.
 *
 * @package nera-prize-risk
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Figures for the Prize Risk table.
 */
class Nera_Prize_Risk_Data {

	const CACHE_PREFIX = 'nera_prize_risk_fig_';
	const CACHE_TTL    = 600;

	/**
	 * Order statuses whose lines count (task.md §5).
	 *
	 * @var string[]
	 */
	const PAID_STATUSES = array( 'processing', 'completed' );

	/**
	 * Hook in: cache invalidation only.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush_order' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'flush_order' ), 10, 1 );
		add_action( 'woocommerce_refund_deleted', array( __CLASS__, 'flush_refund_parent' ), 10, 2 );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'flush_order' ), 10, 1 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'flush_order' ), 10, 1 );
		add_action( 'woocommerce_untrash_order', array( __CLASS__, 'flush_order' ), 10, 1 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'flush_product' ), 10, 1 );
	}

	/**
	 * Clear the cached figures of every product in an order (or refund).
	 *
	 * @param int|WC_Abstract_Order $order_id Order or refund id.
	 * @return void
	 */
	public static function flush_order( $order_id ) {
		$order = $order_id instanceof WC_Abstract_Order ? $order_id : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				self::flush_product( $item->get_product_id() );
			}
		}
		if ( $order instanceof WC_Order_Refund && $order->get_parent_id() ) {
			self::flush_order( $order->get_parent_id() );
		}
	}

	/**
	 * Refund deleted from the edit-order screen: clear the parent order's products.
	 *
	 * @param int $refund_id Refund id.
	 * @param int $order_id  Parent order id.
	 * @return void
	 */
	public static function flush_refund_parent( $refund_id, $order_id ) {
		self::flush_order( $order_id );
	}

	/**
	 * Clear one product's cached figures.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public static function flush_product( $product_id ) {
		if ( $product_id ) {
			delete_transient( self::CACHE_PREFIX . absint( $product_id ) );
		}
	}

	/**
	 * Status label for an LTY status (D-2).
	 *
	 * @param string $status LTY `_lty_lottery_status` value.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'lty_lottery_started'     => __( 'Live', 'nera-prize-risk' ),
			'lty_lottery_not_started' => __( 'Scheduled', 'nera-prize-risk' ),
			'lty_lottery_closed'      => __( 'Ended', 'nera-prize-risk' ),
			'lty_lottery_finished'    => __( 'Drawn', 'nera-prize-risk' ),
			'lty_lottery_failed'      => __( 'Failed', 'nera-prize-risk' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * Lottery product ids with a prize cost set.
	 *
	 * @return int[]
	 */
	public static function product_ids() {
		return get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'lottery',
					),
				),
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_nera_prize_cost',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
	}

	/**
	 * Report rows, default order: Live first, then the rest; each group by position, most exposed first.
	 *
	 * @return array[]
	 */
	public static function get_rows() {
		$pct  = get_option( 'nera_prize_risk_fee_pct', 0 );
		$fee  = is_numeric( $pct ) ? max( 0, min( 100, (float) $pct ) ) / 100 : 0.0;
		$rows = array();
		foreach ( self::product_ids() as $id ) {
			$product = wc_get_product( $id );
			if ( $product && is_callable( array( $product, 'get_lty_lottery_status' ) ) ) {
				$rows[] = self::build_row( $product, $fee );
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$live_a = 'lty_lottery_started' === $a['status'] ? 0 : 1;
				$live_b = 'lty_lottery_started' === $b['status'] ? 0 : 1;
				if ( $live_a !== $live_b ) {
					return $live_a - $live_b;
				}
				if ( $a['position'] !== $b['position'] ) {
					return $a['position'] < $b['position'] ? -1 : 1;
				}
				return strnatcasecmp( $a['title'], $b['title'] );
			}
		);

		return $rows;
	}

	/**
	 * One row of figures.
	 *
	 * @param WC_Product $product Lottery product.
	 * @param float      $fee     Fee fraction.
	 * @return array
	 */
	public static function build_row( $product, $fee ) {
		$id      = $product->get_id();
		$figures = self::get_figures( $id );

		$total_cost = Nera_Prize_Risk_Calc::total_cost(
			(float) $product->get_meta( '_nera_prize_cost', true, 'edit' ),
			(float) $product->get_meta( '_nera_other_costs', true, 'edit' )
		);
		$price      = (float) $product->get_price( 'edit' );
		$max        = (int) $product->get_lty_maximum_tickets();
		$free       = (int) $figures['free'];
		$paid       = max( 0, (int) $product->get_purchased_ticket_count() - $free );
		$revenue    = Nera_Prize_Risk_Calc::revenue_net( $figures['gross'], $fee );
		$position   = Nera_Prize_Risk_Calc::position( $revenue, $total_cost );
		$be_tix     = Nera_Prize_Risk_Calc::break_even_tix( $total_cost, Nera_Prize_Risk_Calc::net_price( $price, $fee ) );

		if ( $max > 0 && $paid + $free >= $max ) {
			$risk = 'sold_out';
		} elseif ( $position >= 0 ) {
			$risk = 'covered';
		} else {
			$risk = 'exposed';
		}

		return array(
			'id'             => $id,
			'title'          => $product->get_name( 'edit' ),
			'category'       => self::category_name( $id ),
			'status'         => (string) $product->get_lty_lottery_status(),
			'end_date'       => (string) $product->get_lty_end_date(),
			'total_cost'     => $total_cost,
			'ticket_price'   => $price,
			'max'            => $max,
			'paid'           => $paid,
			'free'           => $free,
			'sell_through'   => Nera_Prize_Risk_Calc::sell_through( $paid, $free, $max ),
			'revenue_net'    => $revenue,
			'break_even_tix' => $be_tix,
			'break_even_pct' => Nera_Prize_Risk_Calc::break_even_pct( $be_tix, $max ),
			'position'       => $position,
			'risk'           => $risk,
		);
	}

	/**
	 * Primary category: Yoast's primary term when set and still assigned, else the first product_cat term.
	 *
	 * @param int $product_id Product id.
	 * @return string Term name (raw, escape on output).
	 */
	public static function category_name( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}
		$primary = (int) get_post_meta( $product_id, '_yoast_wpseo_primary_product_cat', true );
		foreach ( $terms as $term ) {
			if ( $primary && (int) $term->term_id === $primary ) {
				return html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}
		return html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Order-derived figures, cached for 10 minutes: gross line totals minus refunds, and free entries.
	 *
	 * @param int $product_id Product id.
	 * @return array{gross:float,free:int}
	 */
	public static function get_figures( $product_id ) {
		$key    = self::CACHE_PREFIX . absint( $product_id );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['gross'], $cached['free'] ) ) {
			return $cached;
		}

		$lines   = self::paid_status_lines( $product_id );
		$gross   = 0.0;
		$item_id = array();
		foreach ( $lines as $line ) {
			$gross    += (float) $line['line_total'];
			$item_id[] = (int) $line['order_item_id'];
		}
		$gross -= self::refunded_for_items( $item_id );

		$figures = array(
			'gross' => $gross,
			'free'  => self::count_free_entries( $product_id, $lines ),
		);
		set_transient( $key, $figures, self::CACHE_TTL );

		return $figures;
	}

	/**
	 * Free (postal) entries, D-1 (provisional): tickets on £0-total lines in processing/completed
	 * orders. A 100% coupon also gives a £0 line and counts here. Change the rule only in this method.
	 *
	 * @param int        $product_id Product id.
	 * @param array|null $lines      Lines from paid_status_lines(), to avoid a second query.
	 * @return int
	 */
	public static function count_free_entries( $product_id, $lines = null ) {
		if ( null === $lines ) {
			$lines = self::paid_status_lines( $product_id );
		}
		$free = 0;
		foreach ( $lines as $line ) {
			if ( abs( (float) $line['line_total'] ) >= 0.005 ) {
				continue;
			}
			$tickets = maybe_unserialize( $line['tickets'] );
			$free   += is_array( $tickets ) && ! empty( $tickets ) ? count( $tickets ) : (int) $line['qty'];
		}
		return $free;
	}

	/**
	 * The product's line items in processing/completed orders.
	 *
	 * @param int $product_id Product id.
	 * @return array[] order_item_id, line_total, qty, tickets.
	 */
	private static function paid_status_lines( $product_id ) {
		global $wpdb;

		$statuses = array_map(
			static function ( $s ) {
				return 'wc-' . $s;
			},
			self::PAID_STATUSES
		);
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders = OrderUtil::get_table_for_orders();
			$join   = "INNER JOIN {$orders} o ON o.id = oi.order_id AND o.type = 'shop_order' AND o.status IN ({$in})";
		} else {
			$join = "INNER JOIN {$wpdb->posts} o ON o.ID = oi.order_id AND o.post_type = 'shop_order' AND o.post_status IN ({$in})";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table names and placeholders built above.
		$sql = $wpdb->prepare(
			"SELECT oi.order_item_id, lt.meta_value AS line_total, q.meta_value AS qty, tk.meta_value AS tickets
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id' AND pid.meta_value = %d
			{$join}
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta lt ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta q ON q.order_item_id = oi.order_item_id AND q.meta_key = '_qty'
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta tk ON tk.order_item_id = oi.order_item_id AND tk.meta_key = '_lty_lottery_tickets'
			WHERE oi.order_item_type = 'line_item'",
			array_merge( array( absint( $product_id ) ), $statuses )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Amount refunded for the given order items (refund lines carry a negative `_line_total`).
	 *
	 * @param int[] $item_ids Order item ids.
	 * @return float Positive amount.
	 */
	private static function refunded_for_items( $item_ids ) {
		global $wpdb;
		if ( empty( $item_ids ) ) {
			return 0.0;
		}
		// Join the refund order itself: deleting a refund under HPOS can leave its line items behind.
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$refunds = 'INNER JOIN ' . OrderUtil::get_table_for_orders() . " r ON r.id = oi.order_id AND r.type = 'shop_order_refund'";
		} else {
			$refunds = "INNER JOIN {$wpdb->posts} r ON r.ID = oi.order_id AND r.post_type = 'shop_order_refund'";
		}
		$total = 0.0;
		foreach ( array_chunk( array_map( 'absint', $item_ids ), 500 ) as $chunk ) {
			$in = implode( ',', $chunk );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are absint, table names fixed.
			$sum    = $wpdb->get_var(
				"SELECT SUM(lt.meta_value)
				FROM {$wpdb->prefix}woocommerce_order_itemmeta ri
				INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = ri.order_item_id
				{$refunds}
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta lt ON lt.order_item_id = ri.order_item_id AND lt.meta_key = '_line_total'
				WHERE ri.meta_key = '_refunded_item_id' AND ri.meta_value IN ({$in})"
			);
			$total += abs( (float) $sum );
		}
		return $total;
	}
}
