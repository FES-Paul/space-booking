<?php declare(strict_types=1);

// OOP Admin AJAX handlers
add_action('wp_ajax_sb_update_booking_status', function () {
    if (!current_user_can('manage_space_bookings')) {
        wp_die('Unauthorized');
    }
    $container = \SpaceBooking\Container::instance();
    $controller = $container->get(\SpaceBooking\Controllers\AdminBookingController::class);
    $controller->handleStatusUpdate();
});

// Enqueue edit page scripts (only on booking edit page)
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($screen->id === 'space-booking_page_space-booking-bookings' && isset($_GET['edit'])) {
        wp_enqueue_script('jquery');
    }
});
