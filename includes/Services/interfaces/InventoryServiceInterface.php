<?php declare(strict_types=1);

namespace SpaceBooking\Services\Interfaces;

interface InventoryServiceInterface
{
    public function get_available_extras(int $space_id, string $date, string $start_time, string $end_time): array;
    public function get_booked_quantity(int $extra_id, string $date, string $start_time, string $end_time): int;
    public function validate_extras(array $extras, string $date, string $start_time, string $end_time, int $exclude_booking_id = 0): array;
}
