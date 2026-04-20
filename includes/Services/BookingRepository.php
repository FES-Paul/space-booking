<?php declare(strict_types=1);

namespace SpaceBooking\Services;

/**
 * All database reads/writes for the bookings tables.
 */
final class BookingRepository
{
	// ── Reads ─────────────────────────────────────────────────────────────────

	/**
	 * Returns confirmed time intervals for a space on a given date.
	 * Used for slot-blocking by AvailabilityService.
	 *
	 * @return array  [ ['start' => 'H:i', 'end' => 'H:i'], ... ]
	 */
	public function get_confirmed_intervals(int $space_id, string $date): array
	{
		global $wpdb;

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT start_time, end_time
		\t FROM {$wpdb->prefix}sb_bookings
		\t WHERE space_id    = %d
		\t   AND booking_date = %s
		\t   AND (status = 'confirmed' OR (status = 'pending' AND expired_at > NOW()))",
			$space_id,
			$date
		), ARRAY_A);

		return array_map(static function (array $row): array {
			return [
				'start' => substr($row['start_time'], 0, 5),
				'end' => substr($row['end_time'], 0, 5),
			];
		}, $rows ?: []);
	}

	/**
	 * Fetch a single booking by ID.
	 */
	public function find(int $id): ?array
	{
		global $wpdb;

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_bookings WHERE id = %d",
			$id
		), ARRAY_A);

		return $row ?: null;
	}

	/**
	 * Fetch all bookings for an email address.
	 */
	public function find_by_email(string $email): array
	{
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT b.*, p.post_title AS space_name
		\t FROM {$wpdb->prefix}sb_bookings b
		\t LEFT JOIN {$wpdb->posts} p ON p.ID = b.space_id
		\t WHERE b.customer_email = %s
		\t ORDER BY b.booking_date DESC, b.start_time DESC",
			$email
		), ARRAY_A) ?: [];
	}

	/**
	 * Fetch booking by lookup token (not expired).
	 */
	public function find_by_token(string $token): ?array
	{
		global $wpdb;

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_bookings
		\t WHERE lookup_token  = %s
		\t   AND token_expires > NOW()
		\t LIMIT 1",
			$token
		), ARRAY_A);

		return $row ?: null;
	}

	/**
	 * Find booking by Stripe PaymentIntent ID.
	 */
	public function find_by_stripe_pi(string $pi_id): ?array
	{
		global $wpdb;

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_bookings WHERE stripe_pi_id = %s LIMIT 1",
			$pi_id
		), ARRAY_A);

		return $row ?: null;
	}

	/**
	 * Get extras for a booking.
	 */
	public function get_extras(int $booking_id): array
	{
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT be.*, p.post_title AS extra_name
		\t FROM {$wpdb->prefix}sb_booking_extras be
		\t LEFT JOIN {$wpdb->posts} p ON p.ID = be.extra_id
		\t WHERE be.booking_id = %d",
			$booking_id
		), ARRAY_A) ?: [];
	}

	/**
	 * Cleanup expired pending bookings.
	 *
	 * @return int Number of rows deleted
	 */
	public function cleanup_expired(): int
	{
		global $wpdb;

		$deleted = $wpdb->query("
			DELETE FROM {$wpdb->prefix}sb_bookings 
			WHERE status = 'pending' AND expired_at < NOW()
		");

		// Also cleanup extras
		if ($deleted > 0) {
			$wpdb->query("
				DELETE be FROM {$wpdb->prefix}sb_booking_extras be
				JOIN {$wpdb->prefix}sb_bookings b ON be.booking_id = b.id
				WHERE b.status = 'pending' AND b.expired_at < NOW()
			");
		}

		return (int) $deleted;
	}

	// ── Writes ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new booking. Returns the new row ID or throws on failure.
	 *
	 * @throws \RuntimeException
	 */
	public function create(array $data): int
	{
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'sb_bookings',
			[
				'space_id' => $data['space_id'],
				'package_id' => $data['package_id'] ?? null,
				'customer_name' => $data['customer_name'],
				'customer_email' => $data['customer_email'],
				'customer_phone' => $data['customer_phone'] ?? null,
				'booking_date' => $data['booking_date'],
				'start_time' => $data['start_time'],
				'end_time' => $data['end_time'],
				'duration_hours' => $data['duration_hours'],
				'base_price' => $data['base_price'],
				'extras_price' => $data['extras_price'],
				'modifier_price' => $data['modifier_price'],
				'total_price' => $data['total_price'],
				'status' => 'pending',
				'expired_at' => 'DATE_ADD(NOW(), INTERVAL 30 MINUTE)',
				'stripe_pi_id' => $data['stripe_pi_id'] ?? null,
				'notes' => $data['notes'] ?? null,
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
		);

		if (false === $inserted) {
			throw new \RuntimeException('Failed to insert booking: ' . $wpdb->last_error);
		}

		$id = (int) $wpdb->insert_id;

		// Save extras
		if (!empty($data['extras'])) {
			$this->save_extras($id, $data['extras']);
		}

		return $id;
	}

	/**
	 * Attach extras to a booking.
	 */
	public function save_extras(int $booking_id, array $extras): void
	{
		global $wpdb;

		foreach ($extras as $item) {
			$extra_id = (int) $item['extra_id'];
			$quantity = max(1, (int) ($item['quantity'] ?? 1));
			$unit_price = (float) get_post_meta($extra_id, '_sb_extra_price', true);

			$wpdb->insert(
				$wpdb->prefix . 'sb_booking_extras',
				[
					'booking_id' => $booking_id,
					'extra_id' => $extra_id,
					'quantity' => $quantity,
					'unit_price' => $unit_price,
				],
				['%d', '%d', '%d', '%f']
			);
		}
	}

	/**
	 * Update booking status (and optionally more fields).
	 */
	public function update_status(int $id, string $status, array $extra_data = []): bool
	{
		global $wpdb;

		$data = array_merge(['status' => $status], $extra_data);
		$format = array_fill(0, count($data), '%s');

		return false !== $wpdb->update(
			$wpdb->prefix . 'sb_bookings',
			$data,
			['id' => $id],
			$format,
			['%d']
		);
	}

	/**
	 * Store a lookup token on all bookings for a given email.
	 */
	public function set_lookup_token(string $email, string $token, string $expires): void
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->prefix}sb_bookings
		\t SET lookup_token = %s, token_expires = %s
		\t WHERE customer_email = %s",
			$token,
			$expires,
			$email
		));
	}
}