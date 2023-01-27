<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseTransaction;
use App\Models\User;
use App\Models\Voucher;
use App\Service\PhotoVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_eligibility_failed_because_the_customer_already_got_one()
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        Voucher::factory()->create([
            'lock_for' => $customer->id,
            'given_to' => $customer->id
        ]);

        $this->get('/api/campaign/annyversary/check')
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'You already participate in this campaign',
                'data' => null
            ]);
    }

    public function test_eligibility_failed_because_all_voucher_occupied()
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        Voucher::factory()->create([
            'lock_for' => 100,
            'given_to' => 100
        ]);

        $this->get('/api/campaign/annyversary/check')
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Oops! all voucher already occupied',
                'data' => null
            ]);
    }

    public function test_eligibility_failed_because_the_purchasements_less_than_3_products()
    {
        Voucher::factory(5)->create();

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(2)->create([
            'customer_id' => $customer->id
        ]);

        $this->get('/api/campaign/annyversary/check')
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'You are not eligible in this campaign yet',
                'data' => null
            ]);
    }

    public function test_eligibility_failed_because_the_total_purchasements_less_than_100_dollars()
    {
        Voucher::factory(5)->create();

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 10
        ]);

        $this->get('/api/campaign/annyversary/check')
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'You are not eligible in this campaign yet',
                'data' => null
            ]);
    }

    public function test_eligibility_success()
    {
        Voucher::factory(5)->create();

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        $this->get('/api/campaign/annyversary/check')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'message' => 'You are eligible to participate in this campaign',
                'data' => null
            ]);
    }

    public function test_enter_submission_failed_becuase_file_is_not_attached()
    {

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        Voucher::factory()->create([
            'lock_for' => $customer->id
        ]);

        $this->post('/api/campaign/annyversary/submission')
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Silakan unggah foto anda',
                'data' => null
            ]);
    }

    public function test_enter_submission_failed_becuase_file_attached_is_not_an_image()
    {
        Storage::fake();
        $file = UploadedFile::fake()->create('file.php');

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        Voucher::factory()->create([
            'lock_for' => $customer->id
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'File yang di unggah harus berupa gambar',
                'data' => null
            ]);
    }

    public function test_enter_submission_failed_becuase_file_attached_is_larger_than_2mb()
    {
        Storage::fake();
        $file = UploadedFile::fake()->create('file.jpg', 4048);

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        Voucher::factory()->create([
            'lock_for' => $customer->id
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Maksimum ukuran file yang diunggah adalah 2Mb',
                'data' => null
            ]);
    }

    public function test_enter_submission_failed_becuase_voucher_locked_for_current_customer_not_found()
    {
        Storage::fake();
        $file = UploadedFile::fake()->image('file.jpg');

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'You are not eligible in this campaign',
                'data' => null
            ]);
    }

    public function test_enter_submission_failed_becuase_submission_has_exceeds_10_minutes()
    {
        Storage::fake();
        $file = UploadedFile::fake()->image('file.jpg');

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50
        ]);

        Voucher::factory()->create([
            'lock_for' => $customer->id,
            'created_at' => Carbon::now()->subMinutes(11),
            'updated_at' => Carbon::now()->subMinutes(11)
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Submission failed, you must enter submission in less than or equal 10 minutes after eligibility check',
                'data' => null
            ]);

        $this->assertDatabaseMissing((new Voucher)->getTable(), [
            'lock_for' => $customer->id
        ]);
    }

    public function test_enter_submission_failed_becuase_photo_verification_api_returns_false()
    {
        Storage::fake();
        $file = UploadedFile::fake()->image('file.jpg');

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50.00
        ]);

        Voucher::factory()->create([
            'lock_for' => $customer->id,
            'created_at' => Carbon::now()->subMinutes(5),
            'updated_at' => Carbon::now()->subMinutes(5)
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertStatus(400)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Please upload a photo as required',
                'data' => null
            ]);
    }

    public function test_enter_submission_success()
    {
        $photoService = app()->make(PhotoVerificationService::class);
        $photoService->faking();

        Storage::fake();
        $file = UploadedFile::fake()->image('file.jpg');

        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->create([
            'customer_id' => $customer->id
        ]));

        PurchaseTransaction::factory(3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 50.00
        ]);

        $voucher = Voucher::factory()->create([
            'lock_for' => $customer->id,
            'created_at' => Carbon::now()->subMinutes(5),
            'updated_at' => Carbon::now()->subMinutes(5)
        ]);

        $this->post('/api/campaign/annyversary/submission', [
            'photo' => $file
        ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'message' => 'Congratulation! enjoy FREE cash to spend',
                'data' => [
                    'code' => $voucher->code
                ]
            ]);
    }
}
