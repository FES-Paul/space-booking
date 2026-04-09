<?php
declare(strict_types=1);

namespace SpaceBooking\Services;

use DateInterval;
use DateTime;

/**
 * Generates available time slots for a given Space + Date.
 *
 * Merges global hours with per-space day overrides, then subtracts
 * confirmed bookings to return an array of open 1-hour (or custom) slots.
 */
final class AvailabilityService {

	private BookingRepository $repo;

	public function __construct() {
		$this->repo = new BookingRepository();
	}

	/**
	 * Returns available slots as [ ['start' => '14:00', 'end' => '15:00', 'available' => true], ... ]
	 *
	 * @param int    $space_id
	 * @param string $date       Y-m-d
	 * @param int    $step_mins  Slot step in minutes (default 60)
	 */
	public function get_slots( int $space_id, string $date, int $step_mins = 60 ): array {
		[ $open, $close ] = $this->resolve_hours( $space_id, $date );

		if ( ! $open || ! $close ) {
			return []; // Space is closed on this day
		}

		$slots            = $this->generate_slots( $open, $close, $step_mins );
		$booked_intervals = $this->repo->get_confirmed_intervals( $space_id, $date );

		return array_map( static function ( array $slot ) use ( $booked_intervals ): array {
			$slot['available'] = ! self::overlaps( $slot['start'], $slot['end'], $booked_intervals );
			return $slot;
		}, $slots );
	}

	/**
	 * Returns open/close times for the given space & date, applying overrides.
	 * Returns [ '09:00', '22:00' ] or [ null, null ] if closed.
	 */
	public function resolve_hours( int $space_id, string $date ): array {
		$day_of_week = (int) ( new DateTime( $date ) )->format( 'w' ); // 0=Sun … 6=Sat

		// Check per-space day overrides stored in post meta
		$overrides = get_post_meta( $space_id, '_sb_day_overrides', true );
		if ( is_array( $overrides ) && isset( $overrides[ $day_of_week ] ) ) {
			$override = $overrides[ $day_of_week ];
			if ( isset( $override['closed'] ) && $override['closed'] ) {
				return [ null, null ];
			}
			return [ $override['open'] ?? null, $override['close'] ?? null ];
		}

		// Fallback to global defaults
		$global_open  = get_option( 'sb_global_open_time',  '09:00' );
		$global_close = get_option( 'sb_global_close_time', '22:00' );

		return [ $global_open, $global_close ];
	}

	// ── Internal helpers ─────────────────────────────────────────────────────

	private function generate_slots( string $open, string $close, int $step_mins ): array {
		$slots  = [];
		$cursor = new DateTime( "1970-01-01 {$open}" );
		$end    = new DateTime( "1970-01-01 {$close}" );
		$step   = new DateInterval( "PT{$step_mins}M" );

		while ( true ) {
			$slot_end = ( clone $cursor )->add( $step );
			if ( $slot_end > $end ) {
				break;
			}
			$slots[] = [
				'start' => $cursor->format( 'H:i' ),
				'end'   => $slot_end->format( 'H:i' ),
			];
			$cursor->add( $step );
		}

		return $slots;
	}

	/**
	 * Returns true if [slotStart, slotEnd) overlaps any of the booked intervals.
	 */
	private static function overlaps( string $slot_start, string $slot_end, array $booked ): bool {
		foreach ( $booked as $b ) {
			// Overlap condition: slot_start < b_end AND slot_end > b_start
			if ( $slot_start < $b['end'] && $slot_end > $b['start'] ) {
				return true;
			}
		}
		return false;
	}
}
