<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use SpaceBooking\Services\BookingRepository;
use DateInterval;
use DateTime;

/**
 * Generates available time slots for a given Space + Date.
 *
 * Merges global hours with per-space day overrides, then subtracts
 * confirmed bookings to return an array of open 1-hour (or custom) slots.
 */
final class AvailabilityService
{
	private BookingRepository $repo;

	public function __construct(BookingRepository $repo = null)
	{
		if ($repo === null) {
			$this->repo = new BookingRepository();
		} else {
			$this->repo = $repo;
		}
	}

	/**
	 * Get all space IDs in the bidirectional conflict group for a space (downstream deps + upstream parents + recursion)
	 * @return array<int> Unique space IDs
	 */
	public function get_conflict_group_ids(int $space_id): array
	{
		$group = [$space_id];
		$visited = [$space_id => true];

		// Bidirectional DFS
		$this->collect_conflicts($space_id, $group, $visited);

		return array_values($group);
	}

	private function collect_conflicts(int $id, array &$group, array &$visited): void
	{
		global $wpdb;

		// Downstream: my dependencies
		$my_deps = get_post_meta($id, '_sb_resource_dependencies', true) ?: [];
		foreach ((array) $my_deps as $child_id) {
			$child_id = (int) $child_id;
			if ($child_id && !isset($visited[$child_id])) {
				$visited[$child_id] = true;
				$group[] = $child_id;
				$this->collect_conflicts($child_id, $group, $visited);
			}
		}

		// Upstream: spaces that depend on me (reverse edges)
		$parents = $wpdb->get_col($wpdb->prepare("
			SELECT pm.post_id 
			FROM {$wpdb->postmeta} pm
			JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_sb_resource_dependencies' 
		\t  AND pm.meta_value LIKE %s
		\t  AND p.post_type = 'sb_space'
		\t  AND pm.post_id != %d
		", '%i:' . $id . ';%', $id));
		foreach ($parents as $parent_id) {
			if (!isset($visited[$parent_id])) {
				$visited[$parent_id] = true;
				$group[] = $parent_id;
				$this->collect_conflicts($parent_id, $group, $visited);
			}
		}
	}

	/**
	 * Get unioned conflict groups for multiple space IDs
	 */
	public function get_conflict_groups(array $space_ids): array
	{
		$all_conflicts = [];
		$master_visited = [];

		foreach ($space_ids as $id) {
			if (!isset($master_visited[$id])) {
				$this->collect_conflicts($id, $all_conflicts, $master_visited);
			}
		}

		return array_unique($all_conflicts);
	}

	/**
	 * New fixed slots logic - load from meta, check availability against booked
	 */
	public function get_fixed_slots(array|int $space_ids, string $date): array
	{
		if (!is_array($space_ids)) {
			$space_ids = [$space_ids];
		}

		$primary_id = $space_ids[array_key_first($space_ids)] ?? $space_ids[0] ?? 0;

		if ($primary_id === 0) {
			return [];
		}

		$date_overrides = get_post_meta($primary_id, '_sb_date_overrides', true);
		if (is_array($date_overrides) && isset($date_overrides[$date])) {
			$override = $date_overrides[$date];
			if ($override['status'] === 'closed') {
				return [];
			}
			if ($override['status'] === 'custom' && !empty($override['slots'])) {
				$fixed_slots = $override['slots'];
			} else {
				return [];
			}
		} else {
			$fixed_slots = get_post_meta($primary_id, '_sb_fixed_slots', true);
			if (!is_array($fixed_slots) || empty($fixed_slots)) {
				return [];  // No fixed slots defined
			}
		}

		$booked_intervals = $this->repo->get_confirmed_intervals_for_spaces($space_ids, $date);

		$space_pre_buf = (int) get_post_meta($primary_id, '_sb_buffer_pre_minutes', true) ?: (int) get_option('sb_buffer_pre_minutes', 0);
		$space_post_buf = (int) get_post_meta($primary_id, '_sb_buffer_post_minutes', true) ?: (int) get_option('sb_buffer_post_minutes', 0);

		$slots = [];
		foreach ($fixed_slots as $slot_data) {
			$pre_buf = $slot_data['pre_buffer'] ?? $space_pre_buf;
			$post_buf = $slot_data['post_buffer'] ?? $space_post_buf;

			$slot_start = $this->add_minutes($slot_data['start_time'], -$pre_buf);
			$slot_end = $this->add_minutes($slot_data['end_time'], $post_buf);

			$is_available = !self::overlaps($slot_start, $slot_end, $booked_intervals);

			$slots[] = [
				'slot_id' => $slot_data['slot_id'],
				'start' => $slot_data['start_time'],
				'end' => $slot_data['end_time'],
				'available' => $is_available,
				'override_price' => $slot_data['override_price'],
				'pre_buffer' => $pre_buf,
				'post_buffer' => $post_buf,
				'capacity' => $slot_data['capacity'] ?? 1
			];
		}

		return $slots;
	}

	public function has_fixed_slots_defined(array|int $space_ids): bool
	{
		if (!is_array($space_ids)) {
			$space_ids = [$space_ids];
		}

		$primary_id = $space_ids[array_key_first($space_ids)] ?? $space_ids[0] ?? 0;

		if ($primary_id === 0) {
			return false;
		}

		// Check default fixed slots
		$fixed_slots = get_post_meta($primary_id, '_sb_fixed_slots', true);
		if (is_array($fixed_slots) && !empty($fixed_slots)) {
			return true;
		}

		// Check date-specific overrides
		$date_overrides = get_post_meta($primary_id, '_sb_date_overrides', true);
		if (is_array($date_overrides)) {
			foreach ($date_overrides as $override) {
				if (isset($override['status']) && $override['status'] === 'custom' && !empty($override['slots'])) {
					return true;
				}
			}
		}

		return false;
	}

	public function get_slots(int|array $space_ids, string $date, int $step_mins = 60): array
	{
		if (!is_array($space_ids))
			$space_ids = [$space_ids];

		$conflict_ids = $this->get_conflict_groups($space_ids);

		// Primary space for meta
		$primary_id = $space_ids[array_key_first($space_ids)] ?? $space_ids[0] ?? 0;

		// FIXED-PRIORITY: Check if fixed slots defined on primary (before conflicts)
		if ($this->has_fixed_slots_defined($primary_id)) {
			return $this->get_fixed_slots($conflict_ids, $date);
		}

		// Fallback to dynamic slots
		[$open, $close] = $this->resolve_effective_hours($primary_id, $date);

		if (!$open || !$close) {
			return [];  // Primary space closed
		}

		$slots = $this->generate_slots($open, $close, $step_mins);
		$booked_intervals = $this->repo->get_confirmed_intervals_for_spaces($conflict_ids, $date);
		[$pre_buf, $post_buf] = $this->resolve_buffers($primary_id);
		$inflated_intervals = array_map(function ($b) use ($pre_buf, $post_buf) {
			return [
				'start' => $this->add_minutes($b['start'], -$pre_buf),
				'end' => $this->add_minutes($b['end'], $post_buf),
			];
		}, $booked_intervals);

		return array_map(static function (array $slot) use ($inflated_intervals): array {
			$slot['available'] = !self::overlaps($slot['start'], $slot['end'], $inflated_intervals);
			return $slot;
		}, $slots);
	}

	/**
	 * Returns open/close times for the given space & date, applying overrides.
	 * Returns [ '09:00', '22:00' ] or [ null, null ] if closed.
	 */
	public function resolve_buffers(int $space_id): array
	{
		$pre = (int) get_post_meta($space_id, '_sb_buffer_pre_minutes', true);
		$post = (int) get_post_meta($space_id, '_sb_buffer_post_minutes', true);

		if ($pre === 0) {
			$pre = (int) get_option('sb_buffer_pre_minutes', 0);
		}
		if ($post === 0) {
			$post = (int) get_option('sb_buffer_post_minutes', 0);
		}

		return [$pre, $post];
	}

	public function resolve_effective_hours(int $space_id, string $date): array
	{
		[$raw_open, $raw_close] = $this->resolve_hours($space_id, $date);
		[$pre_buf, $post_buf] = $this->resolve_buffers($space_id);

		if (!$raw_open || !$raw_close) {
			return [null, null];
		}

		$effective_open = $this->add_minutes($raw_open, $pre_buf);
		$effective_close = $this->add_minutes($raw_close, -$post_buf);

		// Allow if buffers eat entire day (still generate slots in raw window)
		$raw_open_min = $this->time_to_minutes($raw_open);
		$raw_close_min = $this->time_to_minutes($raw_close);
		if ($raw_open_min >= $raw_close_min) {
			return [null, null];
		}
		return [$effective_open, $effective_close];
	}

	public function resolve_hours(int $space_id, string $date): array
	{
		$day_of_week = (int) (new DateTime($date))->format('w');  // 0=Sun … 6=Sat

		// Check per-space day overrides stored in post meta
		$overrides = get_post_meta($space_id, '_sb_day_overrides', true);
		if (is_array($overrides) && isset($overrides[$day_of_week])) {
			$override = $overrides[$day_of_week];
			if (isset($override['closed']) && $override['closed']) {
				return [null, null];
			}
			return [$override['open'] ?? null, $override['close'] ?? null];
		}

		// Fallback to global defaults
		$global_open = get_option('sb_global_open_time', '09:00');
		$global_close = get_option('sb_global_close_time', '22:00');

		return [$global_open, $global_close];
	}

	private function time_to_minutes(string $time): int
	{
		[$h, $m] = explode(':', $time);
		return (int) $h * 60 + (int) $m;
	}

	// ── Internal helpers ─────────────────────────────────────────────────────

	private function generate_slots(string $open, string $close, int $step_mins): array
	{
		$slots = [];
		$cursor = new DateTime("1970-01-01 {$open}");
		$end = new DateTime("1970-01-01 {$close}");
		$step = new DateInterval('PT' . $step_mins . 'M');

		while (true) {
			$slot_end = (clone $cursor)->add($step);
			if ($slot_end > $end) {
				break;
			}
			$slots[] = [
				'start' => $cursor->format('H:i'),
				'end' => $slot_end->format('H:i'),
			];
			$cursor->add($step);
		}

		return $slots;
	}

	private function add_minutes(string $time_str, int $minutes): string
	{
		$dt = new DateTime("1970-01-01 {$time_str}");
		$interval_str = 'PT' . abs($minutes) . 'M';
		if ($minutes < 0) {
			$dt->sub(new DateInterval($interval_str));
		} else {
			$dt->add(new DateInterval($interval_str));
		}
		$hours = (int) $dt->format('H');
		$mins = (int) $dt->format('i');
		if ($hours < 0)
			$hours = 0;
		if ($hours > 23)
			$hours = 23;
		return sprintf('%02d:%02d', $hours, $mins);
	}

	/**
	 * Returns true if [slotStart, slotEnd) overlaps any of the booked intervals.
	 */
	private static function overlaps(string $slot_start, string $slot_end, array $booked): bool
	{
		foreach ($booked as $b) {
			// Overlap condition: slot_start < b_end AND slot_end > b_start
			if ($slot_start < $b['end'] && $slot_end > $b['start']) {
				return true;
			}
		}
		return false;
	}
}
