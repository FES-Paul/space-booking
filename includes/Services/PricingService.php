<?php
declare(strict_types=1);

namespace SpaceBooking\Services;

use DateTime;

/**
 * Calculates booking prices using the priority hierarchy:
 *   1. Package flat price  (overrides everything)
 *   2. Base rate × duration
 *   3. Temporal modifiers (holiday > specific_date > weekend > night)
 *   4. Extras
 */
final class PricingService {

	/**
	 * @param int    $space_id
	 * @param string $date        Y-m-d
	 * @param string $start_time  H:i
	 * @param string $end_time    H:i
	 * @param array  $extras      [ ['extra_id' => int, 'quantity' => int], ... ]
	 * @param int|null $package_id
	 * @return array {
	 *   base_price: float,
	 *   modifier_price: float,
	 *   extras_price: float,
	 *   total_price: float,
	 *   breakdown: array,
	 * }
	 */
	public function calculate(
		int $space_id,
		string $date,
		string $start_time,
		string $end_time,
		array $extras = [],
		?int $package_id = null
	): array {

		$duration_hours = $this->hours_between( $start_time, $end_time );

		// ── Package shortcut ──────────────────────────────────────────────────
		if ( $package_id ) {
			$package_price = (float) get_post_meta( $package_id, '_sb_package_price', true );
			$extras_price  = $this->calculate_extras( $extras );
			$total         = $package_price + $extras_price;

			return [
				'base_price'     => $package_price,
				'modifier_price' => 0.0,
				'extras_price'   => $extras_price,
				'total_price'    => $total,
				'duration_hours' => $duration_hours,
				'breakdown'      => [
					[ 'label' => 'Package price', 'amount' => $package_price ],
					[ 'label' => 'Extras',        'amount' => $extras_price ],
				],
			];
		}

		// ── Standard pricing ──────────────────────────────────────────────────
		$hourly_rate = (float) get_post_meta( $space_id, '_sb_hourly_rate', true );
		$base_price  = round( $hourly_rate * $duration_hours, 2 );

		// Apply temporal modifiers
		[ $modifier_price, $breakdown_modifiers ] = $this->apply_modifiers(
			$space_id, $date, $start_time, $end_time, $base_price
		);

		$extras_price = $this->calculate_extras( $extras );
		$total        = $base_price + $modifier_price + $extras_price;

		$breakdown = array_merge(
			[ [ 'label' => "Base rate ({$duration_hours}h × \${$hourly_rate})", 'amount' => $base_price ] ],
			$breakdown_modifiers,
			( $extras_price > 0 ? [ [ 'label' => 'Extras', 'amount' => $extras_price ] ] : [] )
		);

		return [
			'base_price'     => $base_price,
			'modifier_price' => $modifier_price,
			'extras_price'   => $extras_price,
			'total_price'    => round( $total, 2 ),
			'duration_hours' => $duration_hours,
			'breakdown'      => $breakdown,
		];
	}

	// ── Temporal modifiers ───────────────────────────────────────────────────

	/**
	 * Returns [ total_modifier_amount, breakdown_array ]
	 */
	private function apply_modifiers(
		int $space_id,
		string $date,
		string $start_time,
		string $end_time,
		float $base_price
	): array {
		global $wpdb;

		// Fetch applicable rules ordered by priority DESC (higher = applied first)
		$rules = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_pricing_rules
			 WHERE is_active = 1
			   AND (space_id IS NULL OR space_id = %d)
			 ORDER BY priority DESC",
			$space_id
		), ARRAY_A );

		$dt          = new DateTime( $date );
		$day_of_week = (int) $dt->format( 'w' ); // 0=Sun … 6=Sat
		$date_str    = $dt->format( 'Y-m-d' );

		// Priority rank: holiday=40, date_specific=30, weekend=20, night/day=10
		$priority_map = [
			'holiday'       => 40,
			'date_specific' => 30,
			'date_range'    => 25,
			'weekend'       => 20,
			'weekday'       => 15,
			'night'         => 10,
			'day'           => 10,
		];

		$applied      = [];
		$applied_rank = 0;
		$modifier_sum = 0.0;
		$breakdown    = [];

		foreach ( $rules as $rule ) {
			$rank = $priority_map[ $rule['rule_type'] ] ?? 0;

			// Don't apply lower-priority rules if we already have a higher one
			if ( $rank < $applied_rank && ! empty( $applied ) ) {
				continue;
			}

			if ( ! $this->rule_matches( $rule, $date_str, $day_of_week, $start_time, $end_time ) ) {
				continue;
			}

			$amount = $rule['modifier'] === 'percent'
				? round( $base_price * ( (float) $rule['value'] / 100 ), 2 )
				: (float) $rule['value'];

			$modifier_sum += $amount;
			$applied_rank  = $rank;
			$breakdown[]   = [
				'label'  => $rule['label'] ?? ucfirst( str_replace( '_', ' ', $rule['rule_type'] ) ),
				'amount' => $amount,
			];
			$applied[] = $rule['id'];
		}

		return [ $modifier_sum, $breakdown ];
	}

	private function rule_matches(
		array $rule,
		string $date,
		int $day_of_week,
		string $start_time,
		string $end_time
	): bool {
		switch ( $rule['rule_type'] ) {
			case 'holiday':
			case 'date_specific':
				return $rule['start_date'] === $date;

			case 'date_range':
				return ( $rule['start_date'] <= $date && $date <= $rule['end_date'] );

			case 'weekend':
				return in_array( $day_of_week, [ 0, 6 ], true ); // Sun or Sat

			case 'weekday':
				return ! in_array( $day_of_week, [ 0, 6 ], true );

			case 'night':
				// Night: booking start overlaps the rule's night window
				return ( $rule['start_time'] && $start_time >= $rule['start_time'] )
					|| ( $rule['end_time'] && $end_time <= $rule['end_time'] );

			case 'day':
				return ( ! $rule['start_time'] || $start_time >= $rule['start_time'] )
					&& ( ! $rule['end_time'] || $end_time <= $rule['end_time'] );

			default:
				// days_of_week CSV check
				if ( $rule['days_of_week'] ) {
					$days = array_map( 'intval', explode( ',', $rule['days_of_week'] ) );
					return in_array( $day_of_week, $days, true );
				}
		}
		return false;
	}

	// ── Extras ───────────────────────────────────────────────────────────────

	private function calculate_extras( array $extras ): float {
		$total = 0.0;
		foreach ( $extras as $item ) {
			$price     = (float) get_post_meta( (int) $item['extra_id'], '_sb_extra_price', true );
			$quantity  = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$total    += $price * $quantity;
		}
		return round( $total, 2 );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function hours_between( string $start, string $end ): float {
		$s = new DateTime( "1970-01-01 {$start}" );
		$e = new DateTime( "1970-01-01 {$end}" );
		return round( ( $e->getTimestamp() - $s->getTimestamp() ) / 3600, 2 );
	}
}
