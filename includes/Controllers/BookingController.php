<?php
declare(strict_types=1);

namespace SpaceBooking\Controllers;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\InventoryService;
use SpaceBooking\Services\PricingService;
use SpaceBooking\Services\StripeService;

/**
 * POST /space-booking/v1/bookings   → Create pending booking + PaymentIntent
 */
final class BookingController extends WP_REST_Controller {

	protected $namespace = 'space-booking/v1';
	protected $rest_base = 'bookings';

	private BookingRepository $repo;
	private InventoryService  $inventory;
	private PricingService    $pricing;
	private StripeService     $stripe;

	public function __construct() {
		$this->repo      = new BookingRepository();
		$this->inventory = new InventoryService();
		$this->pricing   = new PricingService();
		$this->stripe    = new StripeService();
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_booking' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_create_args(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_booking' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	// ── Create Booking ────────────────────────────────────────────────────────

	public function create_booking( WP_REST_Request $request ): WP_REST_Response {
		$space_id   = (int)    $request->get_param( 'space_id' );
		$package_id = $request->get_param( 'package_id' ) ? (int) $request->get_param( 'package_id' ) : null;
		$date       = (string) $request->get_param( 'date' );
		$start_time = (string) $request->get_param( 'start_time' );
		$end_time   = (string) $request->get_param( 'end_time' );
		$extras     = (array)  ( $request->get_param( 'extras' ) ?? [] );
		$name       = sanitize_text_field( $request->get_param( 'customer_name' ) );
		$email      = sanitize_email( $request->get_param( 'customer_email' ) );
		$phone      = sanitize_text_field( $request->get_param( 'customer_phone' ) ?? '' );
		$notes      = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );

		// ── Guard: space exists ───────────────────────────────────────────────
		$post = get_post( $space_id );
		if ( ! $post || $post->post_type !== 'sb_space' || $post->post_status !== 'publish' ) {
			return new WP_REST_Response( [ 'message' => 'Invalid space.' ], 422 );
		}

		// ── Guard: time window still available ───────────────────────────────
		$booked = $this->repo->get_confirmed_intervals( $space_id, $date );
		foreach ( $booked as $b ) {
			if ( $start_time < $b['end'] && $end_time > $b['start'] ) {
				return new WP_REST_Response( [ 'message' => 'Selected time is no longer available.' ], 409 );
			}
		}

		// ── Guard: extras inventory ───────────────────────────────────────────
		if ( ! empty( $extras ) ) {
			$inv_check = $this->inventory->validate_extras( $extras, $date, $start_time, $end_time );
			if ( ! $inv_check['valid'] ) {
				return new WP_REST_Response( [
					'message'   => 'Some extras are no longer available.',
					'conflicts' => $inv_check['conflicts'],
				], 409 );
			}
		}

		// ── Calculate price ───────────────────────────────────────────────────
		$price = $this->pricing->calculate(
			$space_id, $date, $start_time, $end_time, $extras, $package_id
		);

		$amount_cents = (int) round( $price['total_price'] * 100 );

		// ── Create Stripe PaymentIntent ───────────────────────────────────────
		try {
			$intent = $this->stripe->create_payment_intent(
				$amount_cents,
				(string) get_option( 'sb_currency', 'usd' ),
				[
					'customer_email' => $email,
					'customer_name'  => $name,
					'space'          => get_the_title( $space_id ),
					'date'           => $date,
					'time'           => "{$start_time} – {$end_time}",
				]
			);
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [ 'message' => 'Payment setup failed: ' . $e->getMessage() ], 502 );
		}

		// ── Persist pending booking ───────────────────────────────────────────
		try {
			$booking_id = $this->repo->create( [
				'space_id'       => $space_id,
				'package_id'     => $package_id,
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'booking_date'   => $date,
				'start_time'     => $start_time,
				'end_time'       => $end_time,
				'duration_hours' => $price['duration_hours'],
				'base_price'     => $price['base_price'],
				'extras_price'   => $price['extras_price'],
				'modifier_price' => $price['modifier_price'],
				'total_price'    => $price['total_price'],
				'stripe_pi_id'   => $intent['id'],
				'notes'          => $notes,
				'extras'         => $extras,
			] );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [ 'message' => 'Could not save booking.' ], 500 );
		}

		return new WP_REST_Response( [
			'booking_id'    => $booking_id,
			'client_secret' => $intent['client_secret'],
			'total_price'   => $price['total_price'],
			'breakdown'     => $price['breakdown'],
		], 201 );
	}

	// ── Get single booking ────────────────────────────────────────────────────

	public function get_booking( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$booking = $this->repo->find( $id );

		if ( ! $booking ) {
			return new WP_REST_Response( [ 'message' => 'Booking not found.' ], 404 );
		}

		return rest_ensure_response( $booking );
	}

	// ── Arg schema ───────────────────────────────────────────────────────────

	private function get_create_args(): array {
		return [
			'space_id'       => [ 'required' => true,  'sanitize_callback' => 'absint' ],
			'package_id'     => [ 'required' => false, 'sanitize_callback' => 'absint' ],
			'date'           => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'start_time'     => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'end_time'       => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'customer_name'  => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'customer_email' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => static fn( $v ) => is_email( $v ),
			],
			'customer_phone' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
			'notes'          => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
			'extras'         => [ 'required' => false, 'default' => [] ],
		];
	}
}
