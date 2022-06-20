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

use App\Jobs\Util\ReminderJob;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Util\ReminderJob
 */
class ReminderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testReminderQueryCatchesDate()
    {
        $this->invoice->next_send_date = now()->format('Y-m-d');
        $this->invoice->save();

        $invoices = Invoice::where('next_send_date', Carbon::today())->get();

        $this->assertEquals(1, $invoices->count());
    }

    public function testReminderHits()
    {
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), Carbon::now()->addDays(7)->format('Y-m-d'));

     //   ReminderJob::dispatchNow();
    }

    public function testReminderHitsScenarioH1()
    {
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'before_due_date';
        $settings->num_days_reminder1 = 2;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_due_date';
        $settings->num_days_reminder2 = 14;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 30;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), Carbon::now()->addDays(30)->subDays(2)->format('Y-m-d'));

     //   ReminderJob::dispatchNow();
    }

    /* Cant set a reminder in the past so need to skip reminder 2 and go straigh to reminder 3*/
    public function testReminderNextSendRecalculation()
    {

        $this->invoice->date = now()->subDays(2)->format('Y-m-d');
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->reminder1_sent = now()->subDays(1)->format('Y-m-d');
        $this->invoice->last_sent_date = now()->subDays(1)->format('Y-m-d');
        $this->invoice->next_send_date = now()->subDays(1)->format('Y-m-d');
        $this->invoice->reminder2_sent = null;

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->num_days_reminder3 = 3;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice->fresh();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), now()->addDay()->format('Y-m-d'));
        
    }


    public function testReminder3NextSendRecalculation()
    {

        $this->invoice->date = now()->subDays(3)->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');
        $this->invoice->reminder1_sent = now()->subDays(2)->format('Y-m-d');
        $this->invoice->reminder2_sent = now()->subDays(1)->format('Y-m-d');
        
        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->num_days_reminder3 = 3;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice->fresh();
        
        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), now()->format('Y-m-d'));
        
    }

    public function testReminderIsSet()
    {
        $this->invoice->next_send_date = null;
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice = $this->invoice->service()->markSent()->save();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertNotNull($this->invoice->next_send_date);
    }


}
