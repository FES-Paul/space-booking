<?php declare(strict_types=1);

namespace SpaceBooking\Admin\Views;

use SpaceBooking\Services\BookingRepository;

final class BookingsList
{
    public function __construct(
        private BookingRepository $repo
    ) {}

    public function render(array $filters = []): void
    {
        $bookings = $this->repo->getAdminBookings($filters);
        $grouped = $this->groupByMonth($bookings);
        ?>
<div class="sb-calendar-grouped">
    <?php if (empty($grouped)): ?>
    <p><?php esc_html_e('No bookings match your filters.', 'space-booking'); ?></p>
    <?php else: ?>
    <?php foreach ($grouped as $month_data): ?>
    <section class="sb-month-section">
        <h3><?php echo esc_html($month_data['name']); ?></h3>
        <div class="sb-calendar-grid">
            <?php $days_in_month = array_keys($month_data['days']);
                            sort($days_in_month);
                            foreach ($days_in_month as $date): 
                                $day_data = $month_data['days'][$date]; ?>
            <div class="sb-day-cell">
                <div class="sb-day-header"><?php echo esc_html($day_data['day']); ?></div>
                <?php if (!empty($day_data['bookings'])): ?>
                <?php foreach ($day_data['bookings'] as $b): 
                                            $time = substr($b['start_time'], 0, 5) . '-' . substr($b['end_time'], 0, 5);
                                            $edit_url = admin_url('admin.php?page=space-booking-bookings&edit=' . $b['id']); ?>
                <a href="<?php echo esc_url($edit_url); ?>" class="sb-booking"
                    style="text-decoration:none; color:inherit; display:block;">
                    <?php echo esc_html($time . ' - ' . $b['customer_name']); ?>
                    <span
                        class="sb-status sb-status--<?php echo esc_attr($b['status']); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $b['status']))); ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
    }

    private function groupByMonth(array $bookings): array
    {
        $grouped = [];
        foreach ($bookings as $b) {
            $date = new DateTime($b['booking_date']);
            $month_key = $date->format('Y-m');
            $month_name = $date->format('F Y');
            $day_key = $b['booking_date'];
            $grouped[$month_key]['name'] = $month_name;
            $grouped[$month_key]['days'][$day_key]['day'] = $date->format('M j');
            $grouped[$month_key]['days'][$day_key]['bookings'][] = $b;
        }
        return $grouped;
    }
}
?>