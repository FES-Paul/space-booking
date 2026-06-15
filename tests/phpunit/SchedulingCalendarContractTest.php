<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchedulingCalendarContractTest extends TestCase
{
    private string $pluginRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginRoot = dirname(__DIR__, 2);
    }

    public function test_month_availability_route_and_frontend_calendar_contract_exist(): void
    {
        $availabilityController = (string) file_get_contents($this->pluginRoot . '/includes/Controllers/AvailabilityController.php');
        $availabilityService = (string) file_get_contents($this->pluginRoot . '/includes/Services/AvailabilityService.php');
        $step2Scheduling = (string) file_get_contents($this->pluginRoot . '/src/components/steps/Step2Scheduling.tsx');
        $apiClient = (string) file_get_contents($this->pluginRoot . '/src/utils/api.ts');

        $this->assertStringContainsString('/availability/month', $availabilityController);
        $this->assertStringContainsString('get_month_availability', $availabilityController);
        $this->assertStringContainsString('validate_month', $availabilityController);
        $this->assertStringContainsString('get_unavailable_dates_for_month', $availabilityService);
        $this->assertStringContainsString('apply_global_resource_blocking', $availabilityService);
        $this->assertStringContainsString('build_full_slot_timeline', $availabilityService);
        $this->assertStringContainsString('$slot_copy[\'available\'] = $is_available_in_all;', $availabilityService);
        $this->assertStringContainsString('$has_available_slots = count(array_filter($result[\'slots\'] ?? [], fn($slot) => !empty($slot[\'available\']))) > 0;', $availabilityService);
        $this->assertStringContainsString('$has_available_slots = count(array_filter($slots, fn($slot) => !empty($slot[\'available\']))) > 0;', $availabilityController);
        $this->assertStringContainsString('react-calendar', $step2Scheduling);
        $this->assertStringContainsString('fetchMonthAvailability', $step2Scheduling);
        $this->assertStringContainsString('const hasSelectableSlots = slots.some((slot) => slot.available);', $step2Scheduling);
        $this->assertStringContainsString('tileDisabled', $step2Scheduling);
        $this->assertStringContainsString('fetchMonthAvailability', $apiClient);
    }
}
