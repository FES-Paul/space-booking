<?php declare(strict_types=1);

namespace SpaceBooking\Controllers;

use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\InventoryService;
use SpaceBooking\Services\PricingService;
use SpaceBooking\Services\StripeService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST /space-booking/v1/bookings   → Create pending booking + PaymentIntent
 */
final class BookingController extends WP_REST_Controller
{
	protected $namespace = 'space-booking/v1';
	protected $rest_base = 'bookings';

	private BookingRepository $repo;
	private InventoryService $inventory;
	private PricingService $pricing;
	private \SpaceBooking\Services\WooCommerceService $wc;

	public function __construct()
	{
		$this->repo = new BookingRepository();
		$this->inventory = new InventoryService();
		$this->pricing = new PricingService();
		$this->wc = new \SpaceBooking\Services\WooCommerceService();
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'create_booking'],
				'permission_callback' => '__return_true',
				'args' => $this->get_create_args(),
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_booking'],
				'permission_callback' => '__return_true',
			],
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_booking_status'],
				'permission_callback' => '__return_true',
			],
		]);
	}

	// ── Get Booking Status ────────────────────────────────────────────────────

	public function get_booking_status(WP_REST_Request $request): WP_REST_Response
	{
		$id = (int) $request->get_param('id');
		$booking = $this->repo->find($id);

		if (!$booking) {
			return new WP_REST_Response(['message' => 'Booking not found.'], 404);
		}

		return rest_ensure_response([
			'id' => $booking['id'],
			'status' => $booking['status'],
			'booking' => $booking
		]);
	}

	// ── Create Booking ────────────────────────────────────────────────────────

	public function create_booking(WP_REST_Request $request): WP_REST_Response
	{
		$space_id = (int) $request->get_param('space_id');
		$package_id = $request->get_param('package_id') ? (int) $request->get_param('package_id') : null;
		$date = (string) $request->get_param('date');
		$start_time = (string) $request->get_param('start_time');
		$end_time = (string) $request->get_param('end_time');
		$extras = (array) ($request->get_param('extras') ?? []);
		$name = sanitize_text_field($request->get_param('customer_name'));
		$email = sanitize_email($request->get_param('customer_email'));
		$phone = sanitize_text_field($request->get_param('customer_phone') ?? '');
		$notes = sanitize_textarea_field($request->get_param('notes') ?? '');
		$selected_item_ids = array_map('intval', (array) $request->get_param('selected_item_ids'));
		if (empty($selected_item_ids)) {
			return new WP_REST_Response(['message' => 'Missing selected_item_ids.'], 422);
		}
		$lead_space_id = $space_id;

		$data = [
			'space_id' => $space_id,
			'package_id' => $package_id,
			'booking_date' => $date,
			'start_time' => $start_time,
			'end_time' => $end_time,
			'customer_name' => $name,
			'customer_email' => $email,
			'customer_phone' => $phone,
			'notes' => $notes,
			'extras' => $extras,
		];

		// ── Guard: space exists ───────────────────────────────────────────────
		$post = get_post($space_id);
		if (!$post || $post->post_type !== 'sb_space' || $post->post_status !== 'publish') {
			return new WP_REST_Response(['message' => 'Invalid space.'], 422);
		}

		// ── Guard: time window still available ───────────────────────────────
		$booked = $this->repo->get_confirmed_intervals($space_id, $date);
		foreach ($booked as $b) {
			if ($start_time < $b['end'] && $end_time > $b['start']) {
				return new WP_REST_Response(['message' => 'Selected time is no longer available.'], 409);
			}
		}

		// ── Guard: extras inventory ───────────────────────────────────────────
		if (!empty($extras)) {
			$inv_check = $this->inventory->validate_extras($extras, $date, $start_time, $end_time);
			if (!$inv_check['valid']) {
				return new WP_REST_Response([
					'message' => 'Some extras are no longer available.',
					'conflicts' => $inv_check['conflicts'],
				], 409);
			}
		}

		// ── Calculate price ───────────────────────────────────────────────────
		$price = $this->pricing->calculate(
			$space_id, $date, $start_time, $end_time, $extras, $package_id
		);

		// ── Persist pending booking ───────────────────────────────────────────
		try {
			$booking_id = $this->repo->create($data);
		} catch (\RuntimeException $e) {
			return new WP_REST_Response(['message' => 'Could not save booking.'], 500);
		}

		// ── Bidirectional shadows for full selection footprint ──────────────────
		$avail = new \SpaceBooking\Services\AvailabilityService();
		$footprint = $avail->get_conflict_groups($selected_item_ids);
		$shadow_targets = array_values(array_diff($footprint, [$lead_space_id]));
		foreach ($shadow_targets as $sid) {
			try {
				$this->repo->create_shadow($booking_id, $sid, $date, $start_time, $end_time);
			} catch (\RuntimeException $e) {
				error_log('Failed to create shadow for booking #' . $booking_id . ' space ' . $sid . ': ' . $e->getMessage());
			}
		}

		// ── Add to WooCommerce cart or session ────────────────────────────────
		$checkout_url = wc_get_cart_url();  // Default to cart
		$cart_added = false;
		try {
			$checkout_url = $this->wc->add_booking_to_cart([
				'space_id' => $space_id,
				'package_id' => $package_id,
				'date' => $date,
				'start_time' => $start_time,
				'end_time' => $end_time,
				'customer_name' => $name,
				'customer_email' => $email,
				'extras' => $extras,
				'breakdown' => $price['breakdown'],
			], $price['total_price'], $booking_id);
			$cart_added = true;
			error_log('SpaceBooking: Booking #' . $booking_id . ' added to cart directly');
		} catch (\RuntimeException $e) {
			error_log('SpaceBooking: Direct cart add failed for #' . $booking_id . ': ' . $e->getMessage());
			// Fallback: Use transient + session link for checkout page
			$pending_data = [
				'booking_data' => [
					'space_id' => $space_id,
					'package_id' => $package_id,
					'date' => $date,
					'start_time' => $start_time,
					'end_time' => $end_time,
					'customer_name' => $name,
					'customer_email' => $email,
					'extras' => $extras,
					'breakdown' => $price['breakdown'],
				],
				'total_price' => $price['total_price'],
			];
			set_transient('sb_pending_checkout_' . $booking_id, $pending_data, 1800);  // 30 min
			error_log('SpaceBooking: Booking #' . $booking_id . ' stored in transient (session unavailable in REST)');
			// Session set removed - using transient fallback in populate_pending_cart()
		}

		// Always redirect to checkout, not cart
		$checkout_url = wc_get_checkout_url();

		error_log('SpaceBooking: Booking #' . $booking_id . ' → checkout_url: ' . $checkout_url . ' (cart_direct: ' . json_encode($cart_added) . ')');

		return new WP_REST_Response([
			'booking_id' => $booking_id,
			'checkout_url' => $checkout_url,
			'total_price' => $price['total_price'],
			'breakdown' => $price['breakdown'],
			'cart_added_directly' => $cart_added,
		], 201);
	}

	// ── Get single booking ────────────────────────────────────────────────────

	public function get_booking(WP_REST_Request $request): WP_REST_Response
	{
		$id = (int) $request->get_param('id');
		$booking = $this->repo->findEnriched($id);

		if (!$booking) {
			return new WP_REST_Response(['message' => 'Booking not found.'], 404);
		}

		return rest_ensure_response($booking);
	}

	// ── Arg schema ───────────────────────────────────────────────────────────

	private function get_create_args(): array
	{
		return [
			'space_id' => ['required' => true, 'sanitize_callback' => 'absint'],
			'package_id' => ['required' => false, 'sanitize_callback' => 'absint'],
			'date' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'start_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'end_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'customer_name' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'customer_email' => [
				'required' => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => static fn($v) => is_email($v),
			],
			'customer_phone' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'notes' => ['required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
			'extras' => ['required' => false, 'default' => []],
			'selected_item_ids' => [
				'required' => true,
				'type' => 'array',
				'sanitize_callback' => function ($input) {
					return array_map('absint', (array) $input);
				}
			],
		];
	}
}
