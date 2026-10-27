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
        $this->assertSame('8414 51', GstInvoice::formatHsn('841451'));
        $this->assertSame('Confirmed', GstInvoice::statusLabel('completed', 'pending'));
        $this->assertSame('Delivered', GstInvoice::statusLabel('completed', 'completed'));
    }
}
