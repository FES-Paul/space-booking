<?php declare(strict_types=1);

namespace SpaceBooking\Controllers;

use SpaceBooking\Services\AvailabilityService;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

final class ConflictsController extends WP_REST_Controller
{
    protected $namespace = 'space-booking/v1';
    protected $rest_base = 'conflicts';

    private AvailabilityService $availability;

    public function __construct()
    {
        $this->availability = new AvailabilityService();
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'get_conflicts'],
                'permission_callback' => '__return_true',
                'args' => [
                    'item_id' => ['required' => true, 'sanitize_callback' => 'absint'],
                    'type' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => fn($v) => in_array($v, ['space', 'package'])],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/resource-map', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_resource_map'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function get_conflicts($request): WP_REST_Response
    {
        $item_id = (int) $request->get_param('item_id');
        $type = sanitize_text_field($request->get_param('type'));

        if ($type === 'space') {
            $ids = $this->availability->get_conflict_group_ids($item_id);
        } elseif ($type === 'package') {
            $space_ids = get_post_meta($item_id, '_sb_package_space_ids', true) ?: [];
            if (empty($space_ids)) {
                $space_ids = [get_post_meta($item_id, '_sb_package_space_id', true) ?: 0];
            }
            $space_ids = array_map('intval', (array) $space_ids);
            $ids = [];
            foreach ($space_ids as $sid) {
                if ($sid)
                    $ids = array_merge($ids, $this->availability->get_conflict_group_ids($sid));
            }
            $ids = array_unique($ids);
            array_unshift($ids, $item_id);  // Include package itself
        } else {
            return new WP_REST_Response(['error' => 'Invalid type'], 400);
        }

        return rest_ensure_response([
            'item_id' => $item_id,
            'type' => $type,
            'conflict_group_ids' => $ids,
        ]);
    }

    public function get_resource_map($request): WP_REST_Response
    {
        $spaces = get_posts([
            'post_type' => 'sb_space',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        $packages = get_posts([
            'post_type' => 'sb_package',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        $map = [];

        // Spaces
        foreach ($spaces as $space) {
            $footprint = $this->availability->get_conflict_group_ids((int) $space->ID);
            $map[$space->ID] = [
                'id' => (int) $space->ID,
                'type' => 'space',
                'footprint' => $footprint,
            ];
        }

        // Packages
        foreach ($packages as $package) {
            $space_ids = get_post_meta($package->ID, '_sb_package_space_ids', true) ?: [];
            if (empty($space_ids)) {
                $space_ids = [(int) get_post_meta($package->ID, '_sb_package_space_id', true)];
            }
            $space_ids = array_filter(array_map('intval', (array) $space_ids));

            $footprint = [(int) $package->ID];
            foreach ($space_ids as $sid) {
                if ($sid) {
                    $footprint = array_merge($footprint, $this->availability->get_conflict_group_ids($sid));
                }
            }
            $footprint = array_unique($footprint);

            $map[$package->ID] = [
                'id' => (int) $package->ID,
                'type' => 'package',
                'footprint' => $footprint,
            ];
        }

        return rest_ensure_response($map);
    }
}
