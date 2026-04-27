<?php declare(strict_types=1);

namespace SpaceBooking\Services\Interfaces;

interface BookingRepositoryInterface
{
    public function get_confirmed_intervals(int $space_id, string $date, bool $for_update = false): array;
    public function find(int $id): ?array;
    public function findEnriched(int $id): ?array;
    public function getDashboardStats(): array;
    public function getAdminBookings(array $filters = []): array;
    public function find_by_email(string $email): array;
    public function find_by_token(string $token): ?array;
    public function find_by_stripe_pi(string $pi_id): ?array;
    public function get_extras(int $booking_id): array;
    public function cleanup_expired(): int;
    public function create(array $data): int;
    public function createWithTransaction(array $data, array $booked_intervals): int;
    public function save_extras(int $booking_id, array $extras): void;
    public function update_status(int $id, string $status, array $extra_data = []): bool;
    public function set_lookup_token(string $email, string $token, string $expires): void;
}
