<?php declare(strict_types=1);

namespace SpaceBooking;

use SpaceBooking\Services\Interfaces\BookingRepositoryInterface;
use SpaceBooking\Services\Interfaces\InventoryServiceInterface;
use SpaceBooking\Services\Interfaces\PricingServiceInterface;
use SpaceBooking\Services\AvailabilityService;
use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\DatabaseService;
use SpaceBooking\Services\InventoryService;
use SpaceBooking\Services\PricingService;
use SpaceBooking\Services\WooCommerceService;
use ReflectionClass;
use RuntimeException;

/**
 * Enhanced DI Container with interface bindings.
 * Singleton pattern.
 */
final class Container
{
    public static function create(): self
    {
        return self::$instance ??= new self();
    }

    public static function instance(): self
    {
        return self::create();
    }

    private array $services = [];

    private array $bindings = [
        BookingRepositoryInterface::class => BookingRepository::class,
        InventoryServiceInterface::class => InventoryService::class,
        PricingServiceInterface::class => PricingService::class,
        AvailabilityService::class => AvailabilityService::class,
        DatabaseService::class => DatabaseService::class,
        // Add more
    ];

    private static ?self $instance = null;

    private function __construct() {}

    public function get(string $abstract): object
    {
        // Interface resolution
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            $abstract = $concrete;
        }

        if (!isset($this->services[$abstract])) {
            $this->services[$abstract] = $this->build($abstract);
        }
        return $this->services[$abstract];
    }

    private function build(string $concrete): object
    {
        if ($concrete === static::class) {
            return $this;
        }

        // Simple reflection for constructor injection (basic)
        $reflector = new ReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();
        $params = [];

        foreach ($dependencies as $param) {
            $type = $param->getType();
            if (!$type) {
                throw new RuntimeException("Cannot resolve {$param->getName()} in {$concrete}");
            }
            $paramClass = $type->getName();
            $params[] = $this->get($paramClass);
        }

        return $reflector->newInstanceArgs($params);
    }

    /**
     * Bind interface to concrete (called once at boot)
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function reset(): void
    {
        $this->services = [];
    }
}
