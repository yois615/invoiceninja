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
namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Invoice;
use App\DataMapper\InvoiceItem;
use Illuminate\Support\Str;
/**
 * @test
 */
class InventoryManagementTest extends TestCase
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

    public function testInventoryMovements()
    {

        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'in_stock_quantity' => 100,
            'stock_notification' => true,
            'stock_notification_threshold' => 99
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id, 
            'company_id' => $this->company->id, 
            'client_id' => $this->client->id
        ]);

        $invoice->company->track_inventory = true;
        $invoice->push();


        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = 1;
        $invoice_item->product_key = $product->product_key;
        $invoice_item->notes = $product->notes;
        $invoice_item->quantity = 10;
        $invoice_item->cost = 100;

        $line_items[] = $invoice_item;
        $invoice->line_items = $line_items;
        $invoice->number = Str::random(16);

        $invoice->client_id = $this->client->hashed_id;

        $invoice_array = $invoice->toArray();
        $invoice_array['client_id'] = $this->client->hashed_id;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/invoices/', $invoice_array)
            ->assertStatus(200);

        $product = $product->refresh();

        $this->assertEquals(90, $product->in_stock_quantity);


        // $arr = $response->json();
        // $invoice_hashed_id = $arr['data']['id'];

        // $invoice_item = new InvoiceItem;
        // $invoice_item->type_id = 1;
        // $invoice_item->product_key = $product->product_key;
        // $invoice_item->notes = $product->notes;
        // $invoice_item->quantity = 5;
        // $invoice_item->cost = 100;

        // $line_items2[] = $invoice_item;
        // $invoice->line_items = $line_items2;

        // $response = $this->withHeaders([
        //     'X-API-SECRET' => config('ninja.api_secret'),
        //     'X-API-TOKEN' => $this->token,
        // ])->put('/api/v1/invoices/'.$invoice_hashed_id, $invoice->toArray())
        // ->assertStatus(200);

        // $product = $product->refresh();

        // $this->assertEquals(95, $product->in_stock_quantity);
    }
}
