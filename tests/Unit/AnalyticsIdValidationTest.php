<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AnalyticsIdValidationTest extends TestCase
{
    public function test_ga4_id_pattern(): void
    {
        $this->assertMatchesRegularExpression('/^G-[A-Z0-9]+$/i', 'G-ABC123');
        $this->assertDoesNotMatchRegularExpression('/^G-[A-Z0-9]+$/i', 'UA-123');
        $this->assertDoesNotMatchRegularExpression('/^G-[A-Z0-9]+$/i', 'GTM-ABC');
    }

    public function test_gtm_id_pattern(): void
    {
        $this->assertMatchesRegularExpression('/^GTM-[A-Z0-9]+$/i', 'GTM-XXXXXX');
        $this->assertDoesNotMatchRegularExpression('/^GTM-[A-Z0-9]+$/i', 'G-ABC123');
    }

    public function test_meta_pixel_id_pattern(): void
    {
        $this->assertMatchesRegularExpression('/^\d{5,20}$/', '123456789012345');
        $this->assertDoesNotMatchRegularExpression('/^\d{5,20}$/', 'abc');
        $this->assertDoesNotMatchRegularExpression('/^\d{5,20}$/', '12');
    }
}
