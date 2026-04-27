<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use PDO;
use RuntimeException;
use WPDB;

final class DatabaseService
{
    private WPDB $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function select(string $query, array $args = [], string $output = ARRAY_A): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, ...$args),
            $output
        ) ?: [];
    }

    public function selectOne(string $query, array $args = [], string $output = ARRAY_A): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($query, ...$args),
            $output
        );

        return $row ?: null;
    }

    public function scalar(string $query, array $args = []): mixed
    {
        return $this->wpdb->get_var(
            $this->wpdb->prepare($query, ...$args)
        );
    }

    public function insert(string $table, array $data, array $format = []): int|false
    {
        $inserted = $this->wpdb->insert($table, $data, $format);
        return $inserted ? (int) $this->wpdb->insert_id : false;
    }

    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int|false
    {
        return $this->wpdb->update($table, $data, $where, $format, $where_format);
    }

    public function delete(string $table, array $where, array $where_format = []): int|false
    {
        return $this->wpdb->delete($table, $where, $where_format);
    }

    public function query(string $query, array $args = []): bool|int
    {
        return $this->wpdb->query(
            $this->wpdb->prepare($query, ...$args)
        );
    }

    public function transaction(callable $callback): mixed
    {
        $this->wpdb->query('START TRANSACTION');
        try {
            $result = $callback();
            $this->wpdb->query('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function getPrefix(): string
    {
        return $this->wpdb->prefix;
    }

    public function lastError(): string
    {
        return $this->wpdb->last_error;
    }

    public function getLastInsertId(): int
    {
        global $wpdb;
        return (int) $wpdb->insert_id;
    }
}
