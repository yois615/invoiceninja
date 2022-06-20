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

namespace Tests\Feature\Import\CSV;

use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Jobs\Import\CSVImport;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Import\Providers\Csv
 */
class CsvImportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testExpenseCsvImport()
    {
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/expenses.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'expense.client',
            1 => 'expense.project',
            2 => 'expense.public_notes',
            3 => 'expense.amount',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['expense' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-expense', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $count = $csv_importer->import('expense');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasProject('officiis'));
    }

    public function testVendorCsvImport()
    {
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/vendors.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'vendor.name',
            19 => 'vendor.currency_id',
            20 => 'vendor.public_notes',
            21 => 'vendor.private_notes',
            22 => 'vendor.first_name',
            23 => 'vendor.last_name',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['vendor' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $pre_import = Vendor::count();

        Cache::put($hash . '-vendor', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $csv_importer->import('vendor');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasVendor('Ludwig Krajcik DVM'));
    }

    public function testProductImport()
    {
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/products.csv'
        );
        $hash = Str::random(32);
        Cache::put($hash . '-product', base64_encode($csv), 360);

        $column_map = [
            1 => 'product.product_key',
            2 => 'product.notes',
            3 => 'product.cost',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['product' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        $csv_importer = new Csv($data, $this->company);

        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('product');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasProduct('officiis'));
        // $this->assertTrue($base_transformer->hasProduct('maxime'));
    }

    public function testClientImport()
    {
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/clients.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            1 => 'client.balance',
            2 => 'client.paid_to_date',
            0 => 'client.name',
            19 => 'client.currency_id',
            20 => 'client.public_notes',
            21 => 'client.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
            24 => 'contact.email',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-client', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Ludwig Krajcik DVM'));
        $this->assertTrue($base_transformer->hasClient('Bradly Jaskolski Sr.'));
        
        $client_id = $base_transformer->getClient('Ludwig Krajcik DVM', null);

        $c = Client::find($client_id);

        $this->assertEquals($client_id, $c->id);

        $client_id = $base_transformer->getClient(
            'a non existent clent',
            'brook59@example.org'
        );

        $this->assertEquals($client_id, $c->id);
    }

    public function testInvoiceImport()
    {
        /*Need to import clients first*/
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/clients.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            1 => 'client.balance',
            2 => 'client.paid_to_date',
            0 => 'client.name',
            19 => 'client.currency_id',
            20 => 'client.public_notes',
            21 => 'client.private_notes',
            22 => 'contact.first_name',
            23 => 'contact.last_name',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['client' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-client', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('client');

        $base_transformer = new BaseTransformer($this->company);

        $this->assertTrue($base_transformer->hasClient('Ludwig Krajcik DVM'));

        /* client import verified*/

        /*Now import invoices*/
        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/invoice.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            1 => 'client.email',
            3 => 'payment.amount',
            5 => 'invoice.po_number',
            8 => 'invoice.due_date',
            9 => 'item.discount',
            11 => 'invoice.partial_due_date',
            12 => 'invoice.public_notes',
            13 => 'invoice.private_notes',
            0 => 'client.name',
            2 => 'invoice.number',
            7 => 'invoice.date',
            14 => 'item.product_key',
            15 => 'item.notes',
            16 => 'item.cost',
            17 => 'item.quantity',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['invoice' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-invoice', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $csv_importer->import('invoice');

        $this->assertTrue($base_transformer->hasInvoice('801'));

        /* Lets piggy back payments tests here to save rebuilding the test multiple times*/

        $csv = file_get_contents(
            base_path() . '/tests/Feature/Import/payments.csv'
        );
        $hash = Str::random(32);

        $column_map = [
            0 => 'payment.client_id',
            1 => 'payment.invoice_number',
            2 => 'payment.amount',
            3 => 'payment.date',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['payment' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash . '-payment', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $csv_importer->import('payment');

        $this->assertTrue($base_transformer->hasInvoice('801'));

        $invoice_id = $base_transformer->getInvoiceId('801');

        $invoice = Invoice::find($invoice_id);

        $this->assertTrue($invoice->payments()->exists());
        $this->assertEquals(3, $invoice->payments()->count());
        $this->assertEquals(1200, $invoice->payments()->sum('payments.amount'));
    }
}

// public function testClientCsvImport()
// {
//  $csv = file_get_contents(base_path().'/tests/Feature/Import/clients.csv');
//  $hash = Str::random(32);
//  $column_map = [
//      1 => 'client.balance',
//      2 => 'client.paid_to_date',
//      0 => 'client.name',
//      19 => 'client.currency_id',
//      20 => 'client.public_notes',
//      21 => 'client.private_notes',
//      22 => 'contact.first_name',
//      23 => 'contact.last_name',
//  ];

//  $data = [
//      'hash'        => $hash,
//      'column_map'  => [ 'client' => [ 'mapping' => $column_map ] ],
//      'skip_header' => true,
//      'import_type' => 'csv',
//  ];

//  $pre_import = Client::count();

//  Cache::put( $hash . '-client', base64_encode( $csv ), 360 );

//  CSVImport::dispatchNow( $data, $this->company );

//  $this->assertGreaterThan( $pre_import, Client::count() );
// }
