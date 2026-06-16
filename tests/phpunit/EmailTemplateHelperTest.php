<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SpaceBooking\Services\EmailTemplateHelper;

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

require_once dirname(__DIR__, 2) . '/includes/Services/EmailTemplateHelper.php';

final class EmailTemplateHelperTest extends TestCase
{
    public function test_package_question_rows_keep_custom_others_labels(): void
    {
        $rows = EmailTemplateHelper::package_question_rows_from_meta_string((string) json_encode([
            [
                'field_label' => 'Theme',
                'value' => 'Custom Theme',
                'others_text' => 'Neon Glow',
            ],
        ]));

        $this->assertSame([
            [
                'label' => 'Theme',
                'value' => 'Custom Theme',
                'others_text' => 'Neon Glow',
            ],
        ], $rows);
    }

    public function test_render_package_qa_html_uses_generic_details_label(): void
    {
        $html = EmailTemplateHelper::render_package_qa_html([
            [
                'label' => 'Theme',
                'value' => 'Custom Theme',
                'others_text' => 'Neon Glow',
            ],
        ]);

        $this->assertStringContainsString('Custom Theme', $html);
        $this->assertStringContainsString('Details:', $html);
        $this->assertStringNotContainsString('Others explanation:', $html);
    }
}
