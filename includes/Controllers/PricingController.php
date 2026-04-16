<?php declare(strict_types=1);

namespace SpaceBooking\Controllers;

use SpaceBooking\Services\PricingService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST endpoint: POST /space-booking/v1/pricing
 * Live price calculation for frontend preview (no persistence).
 */
final class PricingController extends WP_REST_Controller
{
	protected $namespace = 'space-booking/v1';
	protected $rest_base = 'pricing';

	private PricingService $pricing;

	public function __construct()
	{
		$this->pricing = new PricingService();
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, '/' . $this->rest_base, [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'calculate_pricing'],
				'permission_callback' => '__return_true',
				'args' => $this->get_args(),
			],
		]);
	}

	public function calculate_pricing(WP_REST_Request $request): WP_REST_Response
	{
		$space_id = (int) $request->get_param('space_id');
		$package_id = $request->get_param('package_id') ? (int) $request->get_param('package_id') : null;
		$date = (string) $request->get_param('date');
		$start_time = (string) $request->get_param('start_time');
		$end_time = (string) $request->get_param('end_time');
		$extras = (array) ($request->get_param('extras') ?? []);

		// Guard: space exists
		$post = get_post($space_id);
		if (!$post || $post->post_type !== 'sb_space' || $post->post_status !== 'publish') {
			return new WP_REST_Response(['message' => 'Invalid space.'], 422);
		}

		$price = $this->pricing->calculate(
			$space_id, $date, $start_time, $end_time, $extras, $package_id
		);

		return new WP_REST_Response([
			'total_price' => $price['total_price'],
			'breakdown' => $price['breakdown'],
			'duration_hours' => $price['duration_hours'],
		], 200);
	}

	private function get_args(): array
	{
		return [
			'space_id' => ['required' => true, 'sanitize_callback' => 'absint'],
			'package_id' => ['required' => false, 'sanitize_callback' => 'absint'],
			'date' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'start_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'end_time' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'extras' => ['required' => false, 'default' => []],
		];
	}
}