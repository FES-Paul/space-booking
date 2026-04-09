<?php
declare(strict_types=1);

namespace SpaceBooking\Controllers;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\EmailService;

/**
 * POST /space-booking/v1/customer/lookup   → email → send magic link
 * GET  /space-booking/v1/customer/bookings → token → return bookings
 */
final class CustomerController extends WP_REST_Controller {

	protected $namespace = 'space-booking/v1';

	private BookingRepository $repo;
	private EmailService      $email;

	public function __construct() {
		$this->repo  = new BookingRepository();
		$this->email = new EmailService();
	}

	public function register_routes(): void {
		// Trigger magic link
		register_rest_route( $this->namespace, '/customer/lookup', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_magic_link' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => static fn( $v ) => is_email( $v ),
					],
				],
			],
		] );

		// Retrieve bookings by token
		register_rest_route( $this->namespace, '/customer/bookings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_customer_bookings' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	// ── Send Magic Link ───────────────────────────────────────────────────────

	public function send_magic_link( WP_REST_Request $request ): WP_REST_Response {
		$email = $request->get_param( 'email' );

		// Always respond with 200 – never reveal if email exists or not
		$bookings = $this->repo->find_by_email( $email );
		if ( empty( $bookings ) ) {
			return new WP_REST_Response( [ 'message' => 'If bookings exist, a link has been sent.' ], 200 );
		}

		// Generate a cryptographically random 64-char token
		$token   = bin2hex( random_bytes( 32 ) ); // 64 hex chars
		$ttl_min = (int) get_option( 'sb_magic_link_ttl_minutes', 30 );
		$expires = gmdate( 'Y-m-d H:i:s', time() + $ttl_min * 60 );

		$this->repo->set_lookup_token( $email, $token, $expires );
		$this->email->send_magic_link( $email, $token );

		return new WP_REST_Response( [ 'message' => 'If bookings exist, a link has been sent.' ], 200 );
	}

	// ── Get Customer Bookings ────────────────────────────────────────────────

	public function get_customer_bookings( WP_REST_Request $request ): WP_REST_Response {
		$token = $request->get_param( 'token' );

		// Validate token exists + is not expired
		$row = $this->repo->find_by_token( $token );

		if ( ! $row ) {
			return new WP_REST_Response( [ 'message' => 'Invalid or expired link.' ], 401 );
		}

		$email    = $row['customer_email'];
		$bookings = $this->repo->find_by_email( $email );

		// Enrich with space name and extras
		$enriched = array_map( function ( array $booking ): array {
			$booking['space_name']    = get_the_title( (int) $booking['space_id'] );
			$booking['thumbnail']     = get_the_post_thumbnail_url( (int) $booking['space_id'], 'thumbnail' );
			$booking['extras']        = $this->repo->get_extras( (int) $booking['id'] );
			// Strip internal fields
			unset( $booking['lookup_token'], $booking['token_expires'] );
			return $booking;
		}, $bookings );

		return rest_ensure_response( [
			'email'    => $email,
			'bookings' => $enriched,
		] );
	}
}
