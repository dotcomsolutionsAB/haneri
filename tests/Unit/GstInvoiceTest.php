<?php

namespace Tests\Unit;

use App\services\GstInvoice;
use PHPUnit\Framework\TestCase;

class GstInvoiceTest extends TestCase
{
    public function test_amount_in_words_uses_the_indian_grouping(): void
    {
        $this->assertSame('Rupees Three Thousand Ninety-Eight Only', GstInvoice::amountInWords(3098));
        $this->assertSame('Rupees Two Thousand Six Hundred Twenty-Five and Forty-Two Paise Only', GstInvoice::amountInWords(2625.42));
    }

    public function test_same_state_splits_cgst_and_sgst(): void
    {
        $uttarakhand = GstInvoice::findState('Uttarakhand');
        $split = GstInvoice::taxSplit(472.58, $uttarakhand, $uttarakhand);

        $this->assertTrue($split['intra']);
        $this->assertEqualsWithDelta(236.29, $split['cgst'], 0.001);
        $this->assertEqualsWithDelta(236.29, $split['sgst'], 0.001);
        $this->assertSame(0.0, $split['igst']);
    }

    public function test_another_state_charges_igst(): void
    {
        $split = GstInvoice::taxSplit(
            472.58,
            GstInvoice::findState('Uttar Pradesh'),
            GstInvoice::findState('Uttarakhand')
        );

        $this->assertFalse($split['intra']);
        $this->assertSame(472.58, $split['igst']);
    }

    public function test_gstin_sets_the_seller_state(): void
    {
        $state = GstInvoice::sellerState('05AAAAA0000A1Z5', '', 'A-48, Sector 57, Noida');

        $this->assertSame('05', $state['code']);
        $this->assertSame('Uttarakhand', $state['name']);
    }

    public function test_shipping_address_carries_the_place_of_supply(): void
    {
        $ship = GstInvoice::shippingBlock(
            'ASHUTOSH, 8220151241, HARIDWAR, Uttarakhand, India, 249403, PLOT NO 94A SECTOR 6A SIDCUL HARIDWAR'
        );

        $this->assertSame('05', $ship['state']['code']);
        $this->assertSame('PLOT NO 94A SECTOR 6A SIDCUL HARIDWAR', $ship['lines'][0]);
    }

    public function test_hsn_is_grouped_and_an_unshipped_order_reads_as_confirmed(): void
    {
        $this->assertSame('841451', GstInvoice::formatHsn('841451'));
        $this->assertSame('84145100', GstInvoice::formatHsn('8414 51 00'));
        $this->assertSame('Confirmed', GstInvoice::statusLabel('completed', 'pending'));
        $this->assertSame('Delivered', GstInvoice::statusLabel('completed', 'completed'));
    }

    public function test_seller_address_is_cleaned_for_print(): void
    {
        $this->assertSame(
            'First Floor, A 48, Sector 57, Noida - 201301, Uttar Pradesh',
            GstInvoice::formatSellerAddress(
                'First Floor, A48 , Sector 57, Noida - 201301, Uttar pradesh, IN'
            )
        );
    }

    public function test_hsn_summary_includes_shipping_in_taxable_value(): void
    {
        $items = [
            (object) [
                'price' => 1180,
                'quantity' => 1,
                'variant' => (object) ['hsn' => '841451'],
            ],
        ];
        $rows = GstInvoice::hsnSummary($items, 1180.0, 0.0, false, '841451', 118.0);

        $this->assertEqualsWithDelta(1100.0, $rows[0]['taxable'], 0.01);
        $this->assertEqualsWithDelta(198.0, $rows[0]['igst'], 0.01);
    }

    public function test_hsn_summary_groups_taxable_value_for_igst(): void
    {
        $items = [
            (object) [
                'price' => 8495,
                'quantity' => 2,
                'variant' => (object) ['hsn' => '841451'],
            ],
        ];
        $rows = GstInvoice::hsnSummary($items, 16990.0, 0.0, false, '841451');

        $this->assertCount(1, $rows);
        $this->assertSame('841451', $rows[0]['hsn']);
        $this->assertSame(2, $rows[0]['qty']);
        $this->assertEqualsWithDelta(14398.31, $rows[0]['taxable'], 0.01);
        $this->assertEqualsWithDelta(2591.69, $rows[0]['igst'], 0.01);
        $this->assertSame(0.0, $rows[0]['cgst']);
    }
}
