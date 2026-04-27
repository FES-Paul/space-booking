<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use SpaceBooking\Services\Interfaces\PricingServiceInterface;
use DateTime;

final class PricingService implements PricingServiceInterface
{
	private DatabaseService $db;

	public function __construct(DatabaseService $db = null)
	{
		$this->db = $db ?: new DatabaseService();
	}

	public function calculate(
		int $space_id,
		string $date,
		string $start_time,
		string $end_time,
		array $extras = [],
		?int $package_id = null
	): array {
		$duration_hours = $this->hours_between($start_time, $end_time);

		if ($package_id) {
			$package_price = (float) get_post_meta($package_id, '_sb_package_price', true);
			$extras_price = $this->calculate_extras($extras);
			$total = $package_price + $extras_price;

			return [
				'base_price' => $package_price,
				'modifier_price' => 0.0,
				'extras_price' => $extras_price,
				'total_price' => $total,
				'duration_hours' => $duration_hours,
				'display_duration' => round($duration_hours, 1),
				'breakdown' => [
					['label' => 'Package price', 'amount' => $package_price],
					['label' => 'Extras', 'amount' => $extras_price],
					['label' => 'Total booking time', 'amount' => 0, 'info' => sprintf('%.1fh', round($duration_hours, 1))],
				],
			];
		}

		$segments = $this->get_price_segments($space_id, $date, $start_time, $end_time);

		$base_price = 0.0;
		$base_breakdown = [];
		foreach ($segments as $seg) {
			$seg_hours = ($seg['end_min'] - $seg['start_min']) / 60.0;
			$seg_price = round($seg['rate'] * $seg_hours, 2);
			$base_price += $seg_price;
			$symbol = \SpaceBooking\Services\CurrencyService::get_symbol();
			$base_breakdown[] = [
				'label' => sprintf('%s–%s (%.1fh × %s%.2f)',
					substr($seg['start_time'], 0, 5),
					substr($seg['end_time'], 0, 5),
					$seg_hours, $symbol, $seg['rate']),
				'amount' => $seg_price
			];
		}

		[$modifier_price, $breakdown_modifiers] = $this->apply_modifiers($space_id, $date, $start_time, $end_time, $base_price);

		$extras_price = $this->calculate_extras($extras);
		$total = $base_price + $modifier_price + $extras_price;

		$breakdown = array_merge(
			$base_breakdown,
			$breakdown_modifiers,
			$extras_price > 0 ? [['label' => 'Extras', 'amount' => $extras_price]] : []
		);

		return [
			'base_price' => $base_price,
			'modifier_price' => $modifier_price,
			'extras_price' => $extras_price,
			'total_price' => round($total, 2),
			'duration_hours' => $duration_hours,
			'display_duration' => round($duration_hours, 1),
			'breakdown' => $breakdown,
		];
	}

	private function apply_modifiers(
		int $space_id,
		string $date,
		string $start_time,
		string $end_time,
		float $base_price
	): array {
		$prefix = $this->db->getPrefix();
		$rules = $this->db->select("
            SELECT * FROM {$prefix}sb_pricing_rules
            WHERE is_active = 1 AND (space_id IS NULL OR space_id = %d)
            ORDER BY priority DESC
        ", [$space_id]);

		$dt = new DateTime($date);
		$day_of_week = (int) $dt->format('w');
		$date_str = $dt->format('Y-m-d');

		$priority_map = [
			'holiday' => 40,
			'date_specific' => 30,
			'date_range' => 25,
			'weekend' => 20,
			'weekday' => 15,
			'night' => 10,
			'day' => 10,
		];

		$applied_rank = 0;
		$modifier_sum = 0.0;
		$breakdown = [];

		foreach ($rules as $rule) {
			$rank = $priority_map[$rule['rule_type']] ?? 0;
			if ($rank < $applied_rank)
				continue;

			if (!$this->rule_matches($rule, $date_str, $day_of_week, $start_time, $end_time))
				continue;

			$amount = $rule['modifier'] === 'percent'
				? round($base_price * ((float) $rule['value'] / 100), 2)
				: (float) $rule['value'];

			$modifier_sum += $amount;
			$applied_rank = $rank;
			$breakdown[] = [
				'label' => $rule['label'] ?? ucfirst(str_replace('_', ' ', $rule['rule_type'])),
				'amount' => $amount,
			];
		}

		return [$modifier_sum, $breakdown];
	}

	private function rule_matches(array $rule, string $date, int $day_of_week, string $start_time, string $end_time): bool
	{
		switch ($rule['rule_type']) {
			case 'holiday':
			case 'date_specific':
				return $rule['start_date'] === $date;
			case 'date_range':
				return ($rule['start_date'] <= $date && $date <= $rule['end_date']);
			case 'weekend':
				return in_array($day_of_week, [0, 6]);
			case 'weekday':
				return !in_array($day_of_week, [0, 6]);
			case 'night':
				return ($rule['start_time'] && $start_time >= $rule['start_time']) ||
					($rule['end_time'] && $end_time <= $rule['end_time']);
			case 'day':
				return (!$rule['start_time'] || $start_time >= $rule['start_time']) &&
					(!$rule['end_time'] || $end_time <= $rule['end_time']);
			default:
				if ($rule['days_of_week']) {
					$days = array_map('intval', explode(',', $rule['days_of_week']));
					return in_array($day_of_week, $days);
				}
		}
		return false;
	}

	private function calculate_extras(array $extras): float
	{
		$total = 0.0;
		foreach ($extras as $item) {
			$price = (float) get_post_meta((int) $item['extra_id'], '_sb_extra_price', true);
			$quantity = max(1, (int) ($item['quantity'] ?? 1));
			$total += $price * $quantity;
		}
		return round($total, 2);
	}

	private function get_price_segments(int $space_id, string $date, string $start_time, string $end_time): array
	{
		$base_rate = (float) get_post_meta($space_id, '_sb_hourly_rate', true);
		$overrides = get_post_meta($space_id, '_sb_price_overrides', true) ?: [];

		$booking_start_min = $this->time_to_minutes($start_time);
		$booking_end_min = $this->time_to_minutes($end_time);

		$segments = [[
			'start_min' => $booking_start_min,
			'end_min' => $booking_end_min,
			'start_time' => $start_time,
			'end_time' => $end_time,
			'rate' => $base_rate
		]];

		foreach ($overrides as $ov) {
			$ov_days = $ov['days'] ?? [];
			$date_wday = (new DateTime($date))->format('w');
			if (!in_array((int) $date_wday, $ov_days))
				continue;

			$ov_start_min = $this->time_to_minutes($ov['start_time']);
			$ov_end_min = $this->time_to_minutes($ov['end_time']);

			$new_segments = [];
			foreach ($segments as $seg) {
				if ($seg['end_min'] <= $ov_start_min || $seg['start_min'] >= $ov_end_min) {
					$new_segments[] = $seg;
					continue;
				}

				if ($seg['start_min'] < $ov_start_min) {
					$new_segments[] = [
						'start_min' => $seg['start_min'],
						'end_min' => $ov_start_min,
						'start_time' => $seg['start_time'],
						'end_time' => $this->minutes_to_time($ov_start_min),
						'rate' => $seg['rate']
					];
				}

				$overlap_start = max($seg['start_min'], $ov_start_min);
				$overlap_end = min($seg['end_min'], $ov_end_min);
				$new_segments[] = [
					'start_min' => $overlap_start,
					'end_min' => $overlap_end,
					'start_time' => $this->minutes_to_time($overlap_start),
					'end_time' => $this->minutes_to_time($overlap_end),
					'rate' => (float) $ov['hourly_rate']
				];

				if ($seg['end_min'] > $ov_end_min) {
					$new_segments[] = [
						'start_min' => $ov_end_min,
						'end_min' => $seg['end_min'],
						'start_time' => $this->minutes_to_time($ov_end_min),
						'end_time' => $seg['end_time'],
						'rate' => $seg['rate']
					];
				}
			}
			$segments = $new_segments;
		}

		return $this->merge_segments($segments);
	}

	private function time_to_minutes(string $time): int
	{
		[$h, $m] = explode(':', $time);
		return (int) $h * 60 + (int) $m;
	}

	private function minutes_to_time(int $minutes): string
	{
		$h = floor($minutes / 60);
		$m = $minutes % 60;
		return sprintf('%02d:%02d', $h, $m);
	}

	private function merge_segments(array $segments): array
	{
		if (empty($segments))
			return [];

		usort($segments, fn($a, $b) => $a['start_min'] <=> $b['start_min']);

		$merged = [$segments[0]];
		foreach ($segments as $curr) {
			$last = &$merged[count($merged) - 1];

			if ($last['rate'] === $curr['rate'] && $last['end_min'] >= $curr['start_min']) {
				$last['end_min'] = max($last['end_min'], $curr['end_min']);
				$last['end_time'] = $this->minutes_to_time($last['end_min']);
			} else {
				$merged[] = $curr;
			}
		}
		return $merged;
	}

	private function hours_between(string $start, string $end): float
	{
		$s = new DateTime("1970-01-01 {$start}");
		$e = new DateTime("1970-01-01 {$end}");
		return round(($e->getTimestamp() - $s->getTimestamp()) / 3600, 2);
	}
}
