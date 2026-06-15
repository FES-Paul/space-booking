<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/Services/BookingRepository.php';
require_once dirname(__DIR__, 2) . '/includes/Services/Traits/HasConflictGroups.php';
require_once dirname(__DIR__, 2) . '/includes/Services/Traits/HasDynamicSlots.php';
require_once dirname(__DIR__, 2) . '/includes/Services/Traits/HasFixedSlots.php';
require_once dirname(__DIR__, 2) . '/includes/Services/Traits/HasOverlapDetection.php';
require_once dirname(__DIR__, 2) . '/includes/Services/Traits/HasSlotGeneration.php';
require_once dirname(__DIR__, 2) . '/includes/Services/AvailabilityService.php';

final class AvailabilityServiceTimelineTest extends TestCase
{
    public function test_build_full_slot_timeline_keeps_missing_middle_slots_visible(): void
    {
        $service = new \SpaceBooking\Services\AvailabilityService(new \SpaceBooking\Services\BookingRepository());
        $method = new ReflectionMethod($service, 'build_full_slot_timeline');
        $method->setAccessible(true);

        $timeline = $method->invoke($service, [10, 223, 224], [
            10 => [
                ['slot_id' => 'main-1', 'start' => '09:30', 'end' => '11:30', 'available' => true],
                ['slot_id' => 'main-2', 'start' => '12:30', 'end' => '14:30', 'available' => true],
                ['slot_id' => 'main-4', 'start' => '18:30', 'end' => '20:30', 'available' => true],
            ],
            223 => [
                ['slot_id' => 'garden-1', 'start' => '09:30', 'end' => '11:30', 'available' => true],
                ['slot_id' => 'garden-2', 'start' => '12:30', 'end' => '14:30', 'available' => true],
                ['slot_id' => 'garden-3', 'start' => '15:30', 'end' => '17:30', 'available' => true],
                ['slot_id' => 'garden-4', 'start' => '18:30', 'end' => '20:30', 'available' => true],
            ],
            224 => [
                ['slot_id' => 'lawn-1', 'start' => '09:30', 'end' => '11:30', 'available' => true],
                ['slot_id' => 'lawn-2', 'start' => '12:30', 'end' => '14:30', 'available' => true],
                ['slot_id' => 'lawn-3', 'start' => '15:30', 'end' => '17:30', 'available' => true],
                ['slot_id' => 'lawn-4', 'start' => '18:30', 'end' => '20:30', 'available' => true],
            ],
        ]);

        $this->assertSame(
            ['09:30-11:30', '12:30-14:30', '15:30-17:30', '18:30-20:30'],
            array_map(
                static fn(array $slot): string => $slot['start'] . '-' . $slot['end'],
                $timeline,
            ),
        );
        $this->assertFalse($timeline[2]['available']);
        $this->assertTrue($timeline[3]['available']);
    }
}
