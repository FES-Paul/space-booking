<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use SpaceBooking\Services\Interfaces\BookingRepositoryInterface;
use RuntimeException;

/**
 * Refactored BookingRepository using DatabaseService abstraction.
 * Implements interface for DI.
 */
final class BookingRepository implements BookingRepositoryInterface
{
	private DatabaseService $db;

	public function __construct(DatabaseService $db = null)
	{
		$this->db = $db ?? new DatabaseService();
	}

	public function get_confirmed_intervals(int $space_id, string $date, bool $for_update = false): array
	{
		$lock = $for_update ? ' FOR UPDATE' : '';
		$rows = $this->db->select("
            SELECT start_time, end_time
            FROM {$this->db->getPrefix()}sb_bookings
            WHERE space_id = %d AND booking_date = %s
              AND (status IN ('confirmed', 'in_review') OR (status = 'pending' AND expired_at > NOW())) {$lock}
        ", [$space_id, $date]);

		return array_map(static function (array $row): array {
			return [
				'start' => substr($row['start_time'], 0, 5),
				'end' => substr($row['end_time'], 0, 5),
			];
		}, $rows);
	}

	public function find(int $id): ?array
	{
		return $this->db->selectOne("SELECT * FROM {$this->db->getPrefix()}sb_bookings WHERE id = %d", [$id]);
	}

	public function findEnriched(int $id): ?array
	{
		$booking = $this->find($id);
		if (!$booking) {
			return null;
		}

		$booking['space_title'] = $this->db->scalar(
			"SELECT post_title FROM {$this->db->getPrefix()}posts WHERE ID = %d",
			[$booking['space_id']]
		) ?: 'Space #' . $booking['space_id'];

		$booking['extras'] = $this->get_extras($id);

		return $booking;
	}

	public function getDashboardStats(): array
	{
		$prefix = $this->db->getPrefix();

		return [
			'total_confirmed' => (int) $this->db->scalar("SELECT COUNT(*) FROM {$prefix}sb_bookings WHERE status = %s", ['confirmed']),
			'total_pending' => (int) $this->db->scalar("SELECT COUNT(*) FROM {$prefix}sb_bookings WHERE status = %s", ['pending']),
			'total_revenue' => (float) $this->db->scalar("SELECT COALESCE(SUM(total_price), 0) FROM {$prefix}sb_bookings WHERE status = %s", ['confirmed']),
			'recent_bookings' => $this->db->select("
                SELECT b.*, p.post_title AS space_name
                FROM {$prefix}sb_bookings b
                LEFT JOIN {$prefix}posts p ON p.ID = b.space_id
                ORDER BY b.created_at DESC LIMIT 10
            "),
		];
	}

	public function getAdminBookings(array $filters = []): array
	{
		$where = ['1=1'];
		$params = [];

		if (!empty($filters['status'])) {
			$where[] = 'b.status = %s';
			$params[] = $filters['status'];
		}
		if (!empty($filters['space_id'])) {
			$where[] = 'b.space_id = %d';
			$params[] = $filters['space_id'];
		}
		if (!empty($filters['date_from'])) {
			$where[] = 'b.booking_date >= %s';
			$params[] = $filters['date_from'];
		}
		if (!empty($filters['date_to'])) {
			$where[] = 'b.booking_date <= %s';
			$params[] = $filters['date_to'];
		}
		$where[] = '(b.status != %s OR b.expired_at > NOW())';
		$params[] = 'pending';

		$prefix = $this->db->getPrefix();
		return $this->db->select("
            SELECT b.*, p.post_title AS space_name
            FROM {$prefix}sb_bookings b
            LEFT JOIN {$prefix}posts p ON p.ID = b.space_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY b.booking_date, b.start_time
        ', $params);
	}

	public function find_by_email(string $email): array
	{
		$prefix = $this->db->getPrefix();
		return $this->db->select("
            SELECT b.*, p.post_title AS space_name
            FROM {$prefix}sb_bookings b
            LEFT JOIN {$prefix}posts p ON p.ID = b.space_id
            WHERE b.customer_email = %s
            ORDER BY b.booking_date DESC, b.start_time DESC
        ", [$email]);
	}

	public function find_by_token(string $token): ?array
	{
		return $this->db->selectOne("
            SELECT * FROM {$this->db->getPrefix()}sb_bookings
            WHERE lookup_token = %s AND token_expires > NOW()
            LIMIT 1
        ", [$token]);
	}

	public function find_by_stripe_pi(string $pi_id): ?array
	{
		return $this->db->selectOne("SELECT * FROM {$this->db->getPrefix()}sb_bookings WHERE stripe_pi_id = %s LIMIT 1", [$pi_id]);
	}

	public function get_extras(int $booking_id): array
	{
		$prefix = $this->db->getPrefix();
		return $this->db->select("
            SELECT be.*, p.post_title AS extra_name
            FROM {$prefix}sb_booking_extras be
            LEFT JOIN {$prefix}posts p ON p.ID = be.extra_id
            WHERE be.booking_id = %d
        ", [$booking_id]);
	}

	public function cleanup_expired(): int
	{
		$prefix = $this->db->getPrefix();
		$deleted = $this->db->query("
            DELETE FROM {$prefix}sb_bookings
            WHERE status = 'pending' AND expired_at < NOW()
        ");

		if ($deleted > 0) {
			$this->db->query("
                DELETE be FROM {$prefix}sb_booking_extras be
                JOIN {$prefix}sb_bookings b ON be.booking_id = b.id
                WHERE b.status = 'pending' AND b.expired_at < NOW()
            ");
		}

		return (int) $deleted;
	}

	public function create(array $data): int
	{
		$table = $this->db->getPrefix() . 'sb_bookings';
		$booking_data = [
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
		];
		$format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s'];

		$inserted = $this->db->insert($table, $booking_data, $format);
		if ($inserted === false) {
			throw new RuntimeException('Failed to insert booking: ' . $this->db->lastError());
		}

		$id = $this->db->getLastInsertId();

		if (!empty($data['extras'])) {
			$this->save_extras($id, $data['extras']);
		}

		return $id;
	}

	public function createWithTransaction(array $data, array $booked_intervals): int
	{
		return $this->db->transaction(function () use ($data) {
			$current_intervals = $this->get_confirmed_intervals($data['space_id'], $data['booking_date'], true);

			$start = substr($data['start_time'], 0, 5);
			$end = substr($data['end_time'], 0, 5);

			foreach ($current_intervals as $interval) {
				if ($start < $interval['end'] && $end > $interval['start']) {
					throw new RuntimeException('Slot no longer available (concurrent booking)');
				}
			}

			$id = $this->create($data);
			return $id;
		});
	}

	public function save_extras(int $booking_id, array $extras): void
	{
		$table = $this->db->getPrefix() . 'sb_booking_extras';
		foreach ($extras as $item) {
			$extra_id = (int) $item['extra_id'];
			$quantity = max(1, (int) ($item['quantity'] ?? 1));
			$unit_price = (float) get_post_meta($extra_id, '_sb_extra_price', true);

			$this->db->insert($table, [
				'booking_id' => $booking_id,
				'extra_id' => $extra_id,
				'quantity' => $quantity,
				'unit_price' => $unit_price,
			], ['%d', '%d', '%d', '%f']);
		}
	}

	public function update_status(int $id, string $status, array $extra_data = []): bool
	{
		$table = $this->db->getPrefix() . 'sb_bookings';
		$data = array_merge(['status' => $status], $extra_data);
		$format = array_fill(0, count($data), '%s');

		return $this->db->update($table, $data, ['id' => $id], $format, ['%d']) !== false;
	}

	public function set_lookup_token(string $email, string $token, string $expires): void
	{
		$prefix = $this->db->getPrefix();
		$this->db->query("
            UPDATE {$prefix}sb_bookings
            SET lookup_token = %s, token_expires = %s
            WHERE customer_email = %s
        ", [$token, $expires, $email]);
	}
}
