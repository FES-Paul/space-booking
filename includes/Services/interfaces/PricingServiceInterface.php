<?php declare(strict_types=1);

namespace SpaceBooking\Services\Interfaces;

interface PricingServiceInterface
{
    /**
     * @return array {
     *   base_price: float,
     *   modifier_price: float,
     *   extras_price: float,
     *   total_price: float,
     *   duration_hours: float,
     *   breakdown: array
     * }
     */
    public function calculate(
        int $space_id,
        string $date,
        string $start_time,
        string $end_time,
        array $extras = [],
        ?int $package_id = null
    ): array;
}
