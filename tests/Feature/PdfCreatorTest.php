<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */
namespace Tests\Feature;

use App\Jobs\Entity\CreateEntityPdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class PdfCreatorTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testCreditPdfCreated()
    {
        $credit_path = CreateEntityPdf::dispatchNow($this->credit->invitations->first());
    
        $this->assertTrue(Storage::disk('public')->exists($credit_path));
    }

    public function testInvoicePdfCreated()
    {
        $invoice_path = CreateEntityPdf::dispatchNow($this->invoice->invitations->first());

        $this->assertTrue(Storage::disk('public')->exists($invoice_path));
        }

    public function testQuotePdfCreated()
    {
        $quote_path = CreateEntityPdf::dispatchNow($this->quote->invitations->first());
    
        $this->assertTrue(Storage::disk('public')->exists($quote_path));
    }
}
