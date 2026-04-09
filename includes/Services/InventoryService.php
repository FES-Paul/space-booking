<?php
declare(strict_types=1);

namespace SpaceBooking\Services;

/**
 * Checks whether Extras (shared assets) have available inventory
 * for a given time window, preventing double-booking of shared items.
 */
final class InventoryService {

	/**
	 * Returns Extras available for a given space/date/time window.
	 *
	 * @param int    $space_id
	 * @param string $date       Y-m-d
	 * @param string $start_time H:i
	 * @param string $end_time   H:i
	 * @return array  Each item: { id, title, price, inventory, available_qty, is_available }
	 */
	public function get_available_extras(
		int $space_id,
		string $date,
		string $start_time,
		string $end_time
	): array {
		// Fetch all published extras allowed for this space (or global)
		$args = [
			'post_type'      => 'sb_extra',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$extras = get_posts( $args );
		$result = [];

		foreach ( $extras as $extra ) {
			// Check if this extra is restricted to specific spaces
			$allowed_spaces = get_post_meta( $extra->ID, '_sb_allowed_spaces', true );
			if ( is_array( $allowed_spaces ) && ! empty( $allowed_spaces ) ) {
				if ( ! in_array( $space_id, array_map( 'intval', $allowed_spaces ), true ) ) {
					continue; // Not available for this space
				}
			}

			$inventory     = (int) get_post_meta( $extra->ID, '_sb_inventory', true );
			$inventory     = max( 1, $inventory ); // default 1
			$booked_qty    = $this->get_booked_quantity( $extra->ID, $date, $start_time, $end_time );
			$available_qty = max( 0, $inventory - $booked_qty );

			$result[] = [
				'id'            => $extra->ID,
				'title'         => $extra->post_title,
				'description'   => $extra->post_excerpt ?: wp_trim_words( $extra->post_content, 20 ),
				'price'         => (float) get_post_meta( $extra->ID, '_sb_extra_price', true ),
				'inventory'     => $inventory,
				'booked_qty'    => $booked_qty,
				'available_qty' => $available_qty,
				'is_available'  => $available_qty > 0,
				'thumbnail'     => get_the_post_thumbnail_url( $extra->ID, 'thumbnail' ) ?: null,
			];
		}

		return $result;
	}

	/**
	 * Returns how many units of an extra are already confirmed for the time window.
	 */
	public function get_booked_quantity(
		int $extra_id,
		string $date,
		string $start_time,
		string $end_time
	): int {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE( SUM(be.quantity), 0 )
			 FROM {$wpdb->prefix}sb_booking_extras be
			 INNER JOIN {$wpdb->prefix}sb_bookings b ON b.id = be.booking_id
			 WHERE be.extra_id     = %d
			   AND b.booking_date  = %s
			   AND b.status        IN ('pending', 'confirmed')
			   AND b.start_time    < %s
			   AND b.end_time      > %s",
			$extra_id,
			$date,
			$end_time,
			$start_time
		) );

		return (int) $result;
	}

	/**
	 * Validates that all requested extras are available before confirming booking.
	 *
	 * @param array  $extras     [ ['extra_id' => int, 'quantity' => int], ... ]
	 * @param string $date
	 * @param string $start_time
	 * @param string $end_time
	 * @param int    $exclude_booking_id  Exclude this booking from the count (for re-checks)
	 * @return array  [ 'valid' => bool, 'conflicts' => [ extra_title, ... ] ]
	 */
	public function validate_extras(
		array $extras,
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_booking_id = 0
	): array {
		$conflicts = [];

		foreach ( $extras as $item ) {
			$extra_id = (int) $item['extra_id'];
			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			$inventory  = max( 1, (int) get_post_meta( $extra_id, '_sb_inventory', true ) );
			$booked_qty = $this->get_booked_quantity_excluding(
				$extra_id, $date, $start_time, $end_time, $exclude_booking_id
			);

			if ( ( $booked_qty + $quantity ) > $inventory ) {
				$conflicts[] = get_the_title( $extra_id ) ?: "Extra #{$extra_id}";
			}
		}

		return [
			'valid'     => empty( $conflicts ),
			'conflicts' => $conflicts,
		];
	}

	private function get_booked_quantity_excluding(
		int $extra_id,
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_booking_id
	): int {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE( SUM(be.quantity), 0 )
			 FROM {$wpdb->prefix}sb_booking_extras be
			 INNER JOIN {$wpdb->prefix}sb_bookings b ON b.id = be.booking_id
			 WHERE be.extra_id     = %d
			   AND b.booking_date  = %s
			   AND b.id           != %d
			   AND b.status        IN ('pending', 'confirmed')
			   AND b.start_time    < %s
			   AND b.end_time      > %s",
			$extra_id,
			$date,
			$exclude_booking_id,
			$end_time,
			$start_time
		) );

		return (int) $result;
	}
}
