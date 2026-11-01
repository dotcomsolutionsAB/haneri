<?php

namespace Tests\Unit;

use App\Models\ProductVariantModel;
use PHPUnit\Framework\TestCase;

class VariantLightOptionTest extends TestCase
{
    public function test_option_label_appends_the_light_choice(): void
    {
        $with = new ProductVariantModel([
            'variant_value' => 'Walnut',
            'light_option' => 'with',
        ]);
        $without = new ProductVariantModel([
            'variant_value' => 'Walnut',
            'light_option' => 'without',
        ]);
        $colorOnly = new ProductVariantModel([
            'variant_value' => 'Walnut',
        ]);

        $this->assertSame('Walnut (With light)', $with->optionLabel());
        $this->assertSame('Walnut (Without light)', $without->optionLabel());
        $this->assertSame('Walnut', $colorOnly->optionLabel());
    }
}
