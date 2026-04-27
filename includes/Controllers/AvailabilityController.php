<?php declare(strict_types=1);

namespace SpaceBooking\Controllers;

use SpaceBooking\Services\AvailabilityService;
use SpaceBooking\Services\InventoryService;
use SpaceBooking\Services\PricingService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /space-booking/v1/availability   → slots for a space+date
 * GET /space-booking/v1/extras         → available extras for space+date+time
 * GET /space-booking/v1/pricing        → price preview
 */
final class AvailabilityController extends WP_REST_Controller
{
	protected $namespace = 'space-booking/v1';

	public function __construct(
		private AvailabilityService $availability,
		private InventoryService $inventory,
		private PricingService $pricing
	) {}

	public function register_routes(): void
	{
		// Slot availability
		register_rest_route($this->namespace, '/availability', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_availability'],
				'permission_callback' => '__return_true',
				'args' => [
					'space_id' => [
						'required' => true,
						'sanitize_callback' => 'absint',
					],
					'date' => [
						'required' => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [$this, 'validate_date'],
					],
				],
			],
		]);

		// Extras availability for a specific time window
		register_rest_route($this->namespace, '/extras', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_extras'],
				'permission_callback' => '__return_true',
				'args' => [
					'space_id' => ['required' => true, 'sanitize_callback' => 'absint'],
					'date' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
					'start_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
					'end_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
				],
			],
		]);

		// Price preview
		register_rest_route($this->namespace, '/pricing', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_pricing'],
				'permission_callback' => '__return_true',
			],
		]);
	}

	// ── Handlers ─────────────────────────────────────────────────────────────

	public function get_availability(WP_REST_Request $request): WP_REST_Response
	{
		$space_id = $request->get_param('space_id');
		$date = $request->get_param('date');
		$step_mins = (int) get_option('sb_slot_interval_minutes', 60);

		// Validate space exists
		$post = get_post($space_id);
		if (!$post || $post->post_type !== 'sb_space') {
			return new WP_REST_Response(['message' => 'Space not found.'], 404);
		}

		$slots = $this->availability->get_slots($space_id, $date, $step_mins);
		[$open, $close] = $this->availability->resolve_effective_hours($space_id, $date);

		return rest_ensure_response([
			'date' => $date,
			'space_id' => $space_id,
			'open_time' => $open,
			'close_time' => $close,
			'slots' => $slots,
		]);
	}

	public function get_extras(WP_REST_Request $request): WP_REST_Response
	{
		$space_id = $request->get_param('space_id');
		$date = $request->get_param('date');
		$start_time = $request->get_param('start_time');
		$end_time = $request->get_param('end_time');

		$extras = $this->inventory->get_available_extras(
			$space_id, $date, $start_time, $end_time
		);

		return rest_ensure_response($extras);
	}

	public function get_pricing(WP_REST_Request $request): WP_REST_Response
	{
		$space_id = (int) $request->get_param('space_id');
		$date = (string) $request->get_param('date');
		$start_time = (string) $request->get_param('start_time');
		$end_time = (string) $request->get_param('end_time');
		$package_id = $request->get_param('package_id') ? (int) $request->get_param('package_id') : null;

		$extras_raw = $request->get_param('extras');
		$extras = is_array($extras_raw) ? $extras_raw : [];

		$result = $this->pricing->calculate(
			$space_id, $date, $start_time, $end_time, $extras, $package_id
		);

		return rest_ensure_response($result);
	}

	// ── Validators ───────────────────────────────────────────────────────────

	public function validate_date($value): bool
	{
		$d = \DateTime::createFromFormat('Y-m-d', $value);
		return $d && $d->format('Y-m-d') === $value;
	}
}
