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

	const CACHE_PREFIX   = 'nera_prize_risk_fig_';
	const CACHE_TTL      = 600;
	const OPTION_FEE_PCT = 'nera_prize_risk_fee_pct';

	/**
	 * Order statuses whose lines count.
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
		// Any order line created or saved with a changed total, subtotal, quantity or product (Add
		// item(s), save items, Recalculate, coupons, REST, code). Not on the generic order-update
		// action: that fires on every order save (checkout, gateways, cron). Detected before the save,
		// flushed after it, so a report load during the save can't cache the old values.
		add_action( 'woocommerce_before_order_item_object_save', array( __CLASS__, 'mark_item_change' ), 10, 1 );
		add_action( 'woocommerce_after_order_item_object_save', array( __CLASS__, 'flush_item_change' ), 10, 1 );
		add_action( 'woocommerce_before_delete_order_item', array( __CLASS__, 'flush_order_item' ), 10, 1 );
		// CPT storage: trashing from the posts list goes through wp_trash_post, not the order data store.
		add_action( 'wp_trash_post', array( __CLASS__, 'flush_order_post' ), 10, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'flush_order_post' ), 10, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'flush_order_post' ), 10, 1 );
	}

	/**
	 * Payment fee percent from the settings screen, clamped to 0–100 (1.4 means 1.4%).
	 *
	 * @return float
	 */
	public static function fee_pct() {
		$pct = get_option( self::OPTION_FEE_PCT, 0 );
		return is_numeric( $pct ) ? max( 0, min( 100, (float) $pct ) ) : 0.0;
	}

	/**
	 * Ticket price: the price LTY charges (sale price when the product is on sale, else regular).
	 *
	 * @param WC_Product $product Lottery product.
	 * @return float
	 */
	public static function ticket_price( $product ) {
		if ( ! is_callable( array( $product, 'get_lty_regular_price' ) ) ) {
			return (float) $product->get_price( 'edit' );
		}
		if ( $product->is_on_sale( 'edit' ) && is_callable( array( $product, 'get_lty_sale_price' ) ) ) {
			return (float) $product->get_lty_sale_price( 'edit' );
		}
		return (float) $product->get_lty_regular_price( 'edit' );
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
	 * Products to clear once the order line being saved is stored, keyed by spl_object_id().
	 *
	 * @var array<int,int[]>
	 */
	private static $item_changes = array();

	/**
	 * Order line about to be saved: remember its products (old and new) when it is new or its
	 * total, subtotal, quantity or product changed.
	 *
	 * @param WC_Order_Item $item Order item.
	 * @return void
	 */
	public static function mark_item_change( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$changed = array_intersect_key( $item->get_changes(), array_flip( array( 'total', 'subtotal', 'quantity', 'product_id', 'variation_id' ) ) );
		if ( $item->get_id() && ! $changed ) {
			return;
		}
		$data = $item->get_data();
		$ids  = array( (int) $item->get_product_id( 'edit' ), isset( $data['product_id'] ) ? (int) $data['product_id'] : 0 );

		self::$item_changes[ spl_object_id( $item ) ] = array_unique( array_filter( $ids ) );
	}

	/**
	 * Order line saved: clear the products remembered by mark_item_change().
	 *
	 * @param WC_Order_Item $item Order item.
	 * @return void
	 */
	public static function flush_item_change( $item ) {
		$key = spl_object_id( $item );
		if ( ! isset( self::$item_changes[ $key ] ) ) {
			return;
		}
		foreach ( self::$item_changes[ $key ] as $product_id ) {
			self::flush_product( $product_id );
		}
		unset( self::$item_changes[ $key ] );
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
	 * Order line about to be deleted: clear its product (the order's save no longer lists it).
	 *
	 * @param int $item_id Order item id.
	 * @return void
	 */
	public static function flush_order_item( $item_id ) {
		$product_id = wc_get_order_item_meta( $item_id, '_product_id', true );
		self::flush_product( (int) $product_id );
	}

	/**
	 * CPT order trashed, restored or deleted through the posts API.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function flush_order_post( $post_id ) {
		if ( in_array( get_post_type( $post_id ), array( 'shop_order', 'shop_order_refund' ), true ) ) {
			self::flush_order( $post_id );
		}
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
		$fee  = self::fee_pct() / 100;
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
		$figures = self::get_figures( $product );

		$prize_cost  = (float) $product->get_meta( '_nera_prize_cost', true, 'edit' );
		$other_costs = (float) $product->get_meta( '_nera_other_costs', true, 'edit' );
		$total_cost  = Nera_Prize_Risk_Calc::total_cost( $prize_cost, $other_costs );
		$price      = self::ticket_price( $product );
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

		$category = self::primary_category( $id );

		return array(
			'id'             => $id,
			'title'          => $product->get_name( 'edit' ),
			'category'       => $category ? html_entity_decode( $category->name, ENT_QUOTES, 'UTF-8' ) : '',
			'category_id'    => $category ? (int) $category->term_id : 0,
			'status'         => (string) $product->get_lty_lottery_status(),
			'end_date'       => (string) $product->get_lty_end_date(),
			'prize_cost'     => $prize_cost,
			'other_costs'    => $other_costs,
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
	 * Group rows into a rollup: Comps, Prize cost, Revenue (net), Avg sell-through, Margin, Margin %.
	 *
	 * Avg sell-through is the mean of each row's sell-through (rows without a ticket total are left out of the mean).
	 * Margin is the sum of the rows' positions; Margin % = margin / revenue, null when revenue is 0.
	 * Runs on the admin screen only (month grouping uses Nera_Prize_Risk_Export::row_month()).
	 *
	 * @param array[] $rows Rows (already filtered).
	 * @param string  $key  category|month|title.
	 * @return array{groups:array[],total:array} Groups sorted by label (months by date), plus a totals group.
	 */
	public static function rollup( $rows, $key ) {
		$groups = array();
		$total  = self::rollup_group( '', '' );
		foreach ( $rows as $row ) {
			if ( 'month' === $key ) {
				$id    = Nera_Prize_Risk_Export::row_month( $row );
				$label = '' !== $id ? date_i18n( 'F Y', strtotime( $id . '-01 00:00:00' ) ) : __( 'No close date', 'nera-prize-risk' );
			} elseif ( 'title' === $key ) {
				$id    = (string) $row['title'];
				$label = $id;
			} else {
				$id    = (string) (int) $row['category_id'];
				$label = '' !== $row['category'] ? $row['category'] : __( 'Uncategorised', 'nera-prize-risk' );
			}
			if ( ! isset( $groups[ $id ] ) ) {
				$groups[ $id ] = self::rollup_group( $id, $label );
			}
			self::rollup_add( $groups[ $id ], $row );
			self::rollup_add( $total, $row );
		}

		if ( 'month' === $key ) {
			ksort( $groups, SORT_STRING );
		} else {
			uasort(
				$groups,
				static function ( $a, $b ) {
					return strnatcasecmp( $a['label'], $b['label'] );
				}
			);
		}

		return array(
			'groups' => array_map( array( __CLASS__, 'rollup_finish' ), array_values( $groups ) ),
			'total'  => self::rollup_finish( $total ),
		);
	}

	/**
	 * Live exposure: sum of |position| over Live rows that are exposed (position < 0).
	 *
	 * @param array[] $rows Report rows (filtered).
	 * @return float
	 */
	public static function live_exposure( $rows ) {
		$sum = 0.0;
		foreach ( $rows as $row ) {
			if ( 'lty_lottery_started' === $row['status'] && null !== $row['position'] && (float) $row['position'] < 0 ) {
				$sum += abs( (float) $row['position'] );
			}
		}
		return $sum;
	}

	/**
	 * Empty rollup group.
	 *
	 * @param string $id    Group key.
	 * @param string $label Group label.
	 * @return array
	 */
	private static function rollup_group( $id, $label ) {
		return array(
			'key'        => $id,
			'label'      => $label,
			'comps'      => 0,
			'prize_cost' => 0.0,
			'revenue'    => 0.0,
			'margin'     => 0.0,
			'st_sum'     => 0.0,
			'st_n'       => 0,
		);
	}

	/**
	 * Add a row to a rollup group.
	 *
	 * @param array $group Group (by reference).
	 * @param array $row   Report row.
	 * @return void
	 */
	private static function rollup_add( &$group, $row ) {
		++$group['comps'];
		$group['prize_cost'] += (float) $row['total_cost'];
		$group['revenue']    += (float) $row['revenue_net'];
		$group['margin']     += (float) $row['position'];
		if ( null !== $row['sell_through'] ) {
			$group['st_sum'] += (float) $row['sell_through'];
			++$group['st_n'];
		}
	}

	/**
	 * Derived rollup figures: avg sell-through and margin %.
	 *
	 * @param array $group Group.
	 * @return array
	 */
	private static function rollup_finish( $group ) {
		$group['avg_sell_through'] = $group['st_n'] > 0 ? $group['st_sum'] / $group['st_n'] : null;
		$group['margin_pct']       = 0.0 !== round( $group['revenue'], 2 ) ? $group['margin'] / $group['revenue'] : null;
		unset( $group['st_sum'], $group['st_n'] );
		return $group;
	}

	/**
	 * Primary category: Yoast's primary term when set and still assigned, else the first product_cat term.
	 *
	 * @param int $product_id Product id.
	 * @return WP_Term|null
	 */
	public static function primary_category( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return null;
		}
		$primary = (int) get_post_meta( $product_id, '_yoast_wpseo_primary_product_cat', true );
		foreach ( $terms as $term ) {
			if ( $primary && (int) $term->term_id === $primary ) {
				return $term;
			}
		}
		return $terms[0];
	}

	/**
	 * Primary category name.
	 *
	 * @param int $product_id Product id.
	 * @return string Term name (raw, escape on output).
	 */
	public static function category_name( $product_id ) {
		$term = self::primary_category( $product_id );
		return $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '';
	}

	/**
	 * The current run's order window, matching LTY's purchased ticket count: from the current
	 * start (or relist) date, up to the end date except for unlimited scheduled lotteries.
	 *
	 * @param WC_Product $product Lottery product.
	 * @return array{from:string,to:string} GMT dates, '' when unbounded.
	 */
	public static function run_window( $product ) {
		$from      = is_callable( array( $product, 'get_current_start_date_gmt' ) ) ? (string) $product->get_current_start_date_gmt() : '';
		$to        = '';
		$unlimited = is_callable( array( $product, 'is_unlimited_scheduled_lottery' ) ) && $product->is_unlimited_scheduled_lottery();
		if ( ! $unlimited && is_callable( array( $product, 'get_lty_end_date_gmt' ) ) ) {
			$to = (string) $product->get_lty_end_date_gmt();
		}
		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Order-derived figures for the current run, cached for 10 minutes: gross line totals minus refunds, and free entries.
	 *
	 * @param WC_Product $product Lottery product.
	 * @return array{gross:float,free:int}
	 */
	public static function get_figures( $product ) {
		$product_id = $product->get_id();
		$window     = self::run_window( $product );
		$key        = self::CACHE_PREFIX . absint( $product_id );
		$cached     = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['gross'], $cached['free'], $cached['window'] ) && $cached['window'] === $window ) {
			return $cached;
		}

		$lines   = self::paid_status_lines( $product_id, $window );
		$gross   = 0.0;
		$item_id = array();
		foreach ( $lines as $line ) {
			// D-1: lines on orders tagged as postal entries bring no revenue (nor refunds), even with a price.
			if ( self::is_postal_line( $line ) ) {
				continue;
			}
			$gross    += (float) $line['line_total'];
			$item_id[] = (int) $line['order_item_id'];
		}
		$gross -= self::refunded_for_items( $item_id );

		$figures = array(
			'gross'  => $gross,
			'free'   => self::count_free_entries( $lines ),
			'window' => $window,
		);
		set_transient( $key, $figures, self::CACHE_TTL );

		return $figures;
	}

	/**
	 * Whether a line sits on an order tagged "Postal entry" (order meta `_nera_postal_entry` = yes).
	 *
	 * @param array $line Line from paid_status_lines().
	 * @return bool
	 */
	private static function is_postal_line( $line ) {
		return isset( $line['postal'] ) && 'yes' === $line['postal'];
	}

	/**
	 * Free (postal) entries, D-1 (updated by CHG-6): tickets on lines in processing/completed orders
	 * that are tagged "Postal entry" OR have a £0 line total (the second layer; a 100% coupon also
	 * gives a £0 line and counts here). Change the rule only here and in get_figures()' revenue skip.
	 *
	 * @param array $lines Lines from paid_status_lines() for the product's run.
	 * @return int
	 */
	public static function count_free_entries( array $lines ) {
		$free = 0;
		foreach ( $lines as $line ) {
			if ( ! self::is_postal_line( $line ) && abs( (float) $line['line_total'] ) >= 0.005 ) {
				continue;
			}
			$tickets = maybe_unserialize( $line['tickets'] );
			$free   += is_array( $tickets ) && ! empty( $tickets ) ? count( $tickets ) : (int) $line['qty'];
		}
		return $free;
	}

	/**
	 * The product's line items in processing/completed orders created within the run window.
	 *
	 * @param int   $product_id Product id.
	 * @param array $window     From run_window(): GMT 'from' / 'to', '' when unbounded.
	 * @return array[] order_item_id, line_total, qty, tickets, postal ('yes' when the order is tagged a postal entry).
	 */
	private static function paid_status_lines( $product_id, $window ) {
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
			$date   = 'o.date_created_gmt';
			$postal = 'SELECT MAX(pm.meta_value) FROM ' . OrderUtil::get_table_for_order_meta() . " pm WHERE pm.order_id = oi.order_id AND pm.meta_key = '_nera_postal_entry'";
		} else {
			$join   = "INNER JOIN {$wpdb->posts} o ON o.ID = oi.order_id AND o.post_type = 'shop_order' AND o.post_status IN ({$in})";
			$date   = 'o.post_date_gmt';
			$postal = "SELECT MAX(pm.meta_value) FROM {$wpdb->postmeta} pm WHERE pm.post_id = oi.order_id AND pm.meta_key = '_nera_postal_entry'";
		}
		$args = array_merge( array( absint( $product_id ) ), $statuses );
		if ( '' !== $window['from'] ) {
			$join  .= " AND {$date} >= %s";
			$args[] = $window['from'];
		}
		if ( '' !== $window['to'] ) {
			$join  .= " AND {$date} <= %s";
			$args[] = $window['to'];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table names and placeholders built above.
		$sql = $wpdb->prepare(
			"SELECT oi.order_item_id, lt.meta_value AS line_total, q.meta_value AS qty, tk.meta_value AS tickets, ({$postal}) AS postal
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id' AND pid.meta_value = %d
			{$join}
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta lt ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta q ON q.order_item_id = oi.order_item_id AND q.meta_key = '_qty'
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta tk ON tk.order_item_id = oi.order_item_id AND tk.meta_key = '_lty_lottery_tickets'
			WHERE oi.order_item_type = 'line_item'",
			$args
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
