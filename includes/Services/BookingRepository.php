<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use WP_Error;

/**
 * Repository for sb_bookings table + extras.
 * Handles CRUD for bookings, time conflicts, cleanup.
 */
class BookingRepository
{
	public function find(int $id): ?array
	{
		global $wpdb;

		$booking = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_bookings WHERE id = %d",
			$id
		), ARRAY_A);

		return $booking ?: null;
	}

	public function findEnriched(int $id): ?array
	{
		$booking = $this->find($id);
		if (!$booking) {
			return null;
		}

		// Enrich with space/package info
		$space = get_post($booking['space_id']);
		$package = $booking['package_id'] ? get_post($booking['package_id']) : null;

		$booking['_space_title'] = $space->post_title ?? 'Unknown Space';
		$booking['_package_title'] = $package ? $package->post_title : null;
		$booking['_extras'] = $this->get_extras($id);

		return $booking;
	}

	public function create(array $data): int
	{
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'sb_bookings',
			[
				'space_id' => $data['space_id'],
				'package_id' => $data['package_id'] ?? null,
				'customer_name' => $data['customer_name'],
				'customer_email' => $data['customer_email'],
				'customer_phone' => $data['customer_phone'] ?? '',
				'booking_date' => $data['booking_date'],
				'start_time' => $data['start_time'],
				'end_time' => $data['end_time'],
				'duration_hours' => $data['duration_hours'],
				'base_price' => $data['base_price'],
				'extras_price' => $data['extras_price'],
				'modifier_price' => $data['modifier_price'] ?? 0.0,
				'total_price' => $data['total_price'],
				'notes' => $data['notes'] ?? '',
				'extras' => !empty($data['extras']) ? wp_json_encode($data['extras']) : '[]',
				'status' => 'pending',  // Initial status
				'expired_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),  // Auto-expire
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
		);

		if (false === $result) {
			throw new \RuntimeException('Failed to create booking: ' . $wpdb->last_error);
		}

		$booking_id = $wpdb->insert_id;

		// Insert extras if present
		if (!empty($data['extras'])) {
			$this->save_extras($booking_id, $data['extras']);
		}

		return $booking_id;
	}

	public function get_confirmed_intervals(int $space_id, string $date): array
	{
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT start_time as start, end_time as end
             FROM {$wpdb->prefix}sb_bookings 
             WHERE space_id = %d AND booking_date = %s AND status IN ('confirmed', 'in_review', 'shadow')
             ORDER BY start_time",
			$space_id, $date
		), ARRAY_A) ?: [];
	}

	public function get_confirmed_intervals_for_spaces(array $space_ids, string $date): array
	{
		if (empty($space_ids)) {
			return [];
		}

		global $wpdb;

		$space_ids_placeholder = implode(',', array_fill(0, count($space_ids), '%d'));
		$space_ids_params = $space_ids;

		return $wpdb->get_results($wpdb->prepare("
			SELECT start_time as start, end_time as end
            FROM {$wpdb->prefix}sb_bookings 
            WHERE space_id IN ({$space_ids_placeholder}) AND booking_date = %s AND status IN ('confirmed', 'in_review', 'shadow')
            ORDER BY start_time",
			...array_merge($space_ids_params, [$date])), ARRAY_A) ?: [];
	}

	public function create_shadow(int $parent_id, int $space_id, string $date, string $start_time, string $end_time): int
	{
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'sb_bookings',
			[
				'space_id' => $space_id,
				'parent_booking_id' => $parent_id,
				'booking_date' => $date,
				'start_time' => $start_time,
				'end_time' => $end_time,
				'status' => 'shadow',
				'expired_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),  // Same TTL as parent
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s']
		);

		if (false === $result) {
			throw new \RuntimeException('Failed to create shadow booking: ' . $wpdb->last_error);
		}

		return $wpdb->insert_id;
	}

	public function cleanup_expired(): int
	{
		global $wpdb;
		$table = $wpdb->prefix . 'sb_bookings';
		$extras_table = $wpdb->prefix . 'sb_booking_extras';

		// Delete main bookings with safe prepared query
		$result = $wpdb->query($wpdb->prepare(
			"DELETE FROM {$table} WHERE status = %s AND expired_at <= NOW()",
			'pending'
		));

		// Delete associated extras
		if ($result !== false && $result > 0) {
			$wpdb->query($wpdb->prepare(
				"DELETE be FROM {$extras_table} be
			\t JOIN {$table} b ON b.id = be.booking_id
			\t WHERE b.status = %s AND b.expired_at <= NOW()",
				'pending'
			));
		}

		return (int) $result;
	}

	public function get_extras(int $booking_id): array
	{
		global $wpdb;

		$extras = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_booking_extras WHERE booking_id = %d",
			$booking_id
		), ARRAY_A);

		foreach ($extras as &$extra) {
			$post = get_post($extra['extra_id']);
			$extra['title'] = $post ? $post->post_title : 'Unknown Extra';
			$extra['price'] = (float) get_post_meta($extra['extra_id'], '_sb_extra_price', true);
		}

		return $extras ?: [];
	}

	public function update_status(int $id, string $status): bool
	{
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'sb_bookings',
			['status' => $status],
			['id' => $id],
			['%s'],
			['%d']
		);

		return false !== $result;
	}

	private function save_extras(int $booking_id, array $extras): void
	{
		global $wpdb;

		foreach ($extras as $extra) {
			$wpdb->insert(
				$wpdb->prefix . 'sb_booking_extras',
				[
					'booking_id' => $booking_id,
					'extra_id' => (int) $extra['extra_id'],
					'quantity' => (int) ($extra['quantity'] ?? 1),
				],
				['%d', '%d', '%d']
			);
		}
	}
}
