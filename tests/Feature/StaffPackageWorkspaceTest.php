<?php

namespace Tests\Feature;

use App\Models\{AddOn, Customer, Order, Product, User};
use App\Services\{FinancialReportService, OrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffPackageWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private User $owner;
    private Product $cake;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($this->owner);
        $this->cake = $this->catalogProduct(['product_name' => 'Celebration cake with a long descriptive package name', 'price' => 1000, 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Maria', 'last_name' => 'De la Cruz', 'phone_number' => '09171234567']);
        foreach (['local', 'public', 'staff_drafts', 'staff_references'] as $disk) Storage::fake($disk);
    }

    private function draft(): array
    {
        return session('staff_order_drafts.'.$this->owner->id);
    }

    private function openLine(): string
    {
        $this->get(route('orders.create'))->assertOk()->assertSee('Select package')->assertDontSee('name="items[', false);
        $line = array_key_first($this->draft()['offered_lines']);
        $this->get($this->url('customize', $line))->assertOk()->assertSee('Customize package')->assertSee('Design reference photos');
        return $line;
    }

    private function url(string $action, string $line): string
    {
        return route('orders.package.'.$action, ['product' => $this->cake, 'line' => $line, 'draft_id' => $this->draft()['draft_id']]);
    }

    private function line(string $key, array $changes = []): array
    {
        return array_replace(['draft_key' => $key, 'product_id' => $this->cake->id,
            'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1,
            'themes' => 'Blue flowers', 'special_request' => 'Happy birthday', 'add_ons' => []], $changes);
    }

    private function save(string $key, array $changes = []): void
    {
        $this->post($this->url('save', $key), ['items' => [$this->line($key, $changes)], 'draft_id' => $this->draft()['draft_id']])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    private function checkout(array $changes = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00', 'notes_text' => 'Call before pickup', 'expected_total' => 1000,
            'draft_id' => $this->draft()['draft_id'], 'submission_key' => $this->draft()['submission_key']], $changes);
    }

    public function test_multiple_designs_edit_and_repeat_saves_preserve_line_identity(): void
    {
        $first = $this->openLine();
        $this->save($first);
        $this->save($first);
        $second = $this->openLine();
        $this->save($second, ['themes' => 'Pink flowers', 'quantity' => 2]);
        $this->save($first, ['themes' => 'Gold lettering']);
        $this->assertCount(2, $this->draft()['items']);
        $this->assertSame([$first, $second], array_column($this->draft()['items'], 'draft_key'));
        $this->assertSame('Gold lettering', $this->draft()['items'][0]['themes']);
        $this->assertSame('Pink flowers', $this->draft()['items'][1]['themes']);
        $this->get(route('orders.details'))->assertOk()->assertSee('3,000.00')->assertSee('Create staff order');
        $this->post(route('orders.package.remove', $first), ['draft_id' => $this->draft()['draft_id']])->assertRedirect();
        $this->post(route('orders.package.remove', $first), ['draft_id' => $this->draft()['draft_id']])->assertRedirect();
        $this->assertSame([$second], array_column($this->draft()['items'], 'draft_key'));
        $this->post($this->url('save', $first), ['items' => [$this->line($first)]])->assertSessionHasErrors('items');
        $this->get($this->url('customize', $first))->assertRedirect(route('orders.create'));
    }

    public function test_forged_lines_and_cross_product_payloads_are_rejected(): void
    {
        $line = $this->openLine();
        $unknown = (string) Str::uuid();
        $this->get($this->url('customize', $unknown))->assertNotFound();
        $this->post($this->url('save', $unknown), ['items' => [$this->line($unknown)]])->assertNotFound();
        $this->post(route('orders.package.remove', $unknown), ['draft_id' => $this->draft()['draft_id']])->assertNotFound();
        $this->post($this->url('save', $line), ['items' => [$this->line($line, ['product_id' => 999999])]])->assertUnprocessable();
        $this->post(route('orders.continue'), ['items' => [$this->line($unknown)]])->assertSessionHasErrors('items.0.draft_key');
        $payload = $this->checkout();
        unset($payload['draft_id']);
        $this->post(route('orders.store'), $payload + ['items' => [$this->line($unknown)]])->assertSessionHasErrors('items');
        $this->save($line);
        $this->post(route('orders.continue'), ['items' => [$this->line($line)]])->assertSessionHasErrors('items.0.draft_key');
        $this->post(route('orders.store'), $this->checkout() + ['items' => [$this->line($line)]])->assertSessionHasErrors('items');
        $this->post(route('orders.package.remove', $line), ['draft_id' => $this->draft()['draft_id']])->assertRedirect();
        $this->post(route('orders.store'), $payload + ['items' => [$this->line($line)]])->assertSessionHasErrors('items');
        $this->assertSame([], $this->draft()['items']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_valid_photos_survive_invalid_quantity_and_repeated_staging(): void
    {
        $line = $this->openLine();
        $file = UploadedFile::fake()->image('design.png');
        $this->from($this->url('customize', $line))->post($this->url('save', $line), ['items' => [$this->line($line, ['quantity' => 0, 'images' => [$file]])]])
            ->assertSessionHasErrors('items.0.quantity');
        $photo = $this->draft()['package_editors'][$line]['staged_images'][0];
        Storage::disk('staff_drafts')->assertExists($photo['staged_path']);
        $this->get($this->url('customize', $line))->assertSee('design.png');
        $data = ['items' => [$this->line($line, ['images' => [$file]])]];
        $this->postJson($this->url('draft', $line), $data)->assertOk();
        $this->postJson($this->url('draft', $line), $data)->assertOk();
        $this->assertCount(1, $this->draft()['package_editors'][$line]['staged_images']);
        $this->save($line);
        $this->save($line);
        $this->assertCount(1, $this->draft()['items'][0]['staged_images']);
        $this->assertCount(1, Storage::disk('staff_drafts')->allFiles());
    }

    public function test_photo_limits_forged_removal_and_uncommitted_cleanup(): void
    {
        $line = $this->openLine();
        $files = array_map(fn ($i) => UploadedFile::fake()->image('photo-'.$i.'.png', 10 + $i, 10), range(1, 5));
        $this->postJson($this->url('draft', $line), ['items' => [$this->line($line, ['images' => $files])]])->assertOk();
        $this->postJson($this->url('draft', $line), ['items' => [$this->line($line, ['images' => [UploadedFile::fake()->image('sixth.png')]])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.images');
        $this->postJson($this->url('draft', $line), ['items' => [$this->line($line, ['images' => [UploadedFile::fake()->image('big.png')->size(5121)]])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.images.0');
        Storage::disk('staff_drafts')->put('other-draft/sentinel.png', 'keep');
        $this->postJson($this->url('draft', $line), ['items' => [$this->line($line, ['remove_staged_images' => ['other-draft/sentinel.png']])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.images');
        Storage::disk('staff_drafts')->assertExists('other-draft/sentinel.png');
        $photo = $this->draft()['package_editors'][$line]['staged_images'][0];
        $this->postJson($this->url('draft', $line), ['items' => [$this->line($line, ['remove_staged_images' => [$photo['staged_path']]])]])->assertOk();
        Storage::disk('staff_drafts')->assertMissing($photo['staged_path']);
        $this->post(route('orders.package.remove', $line), ['draft_id' => $this->draft()['draft_id']])->assertRedirect();
        $this->assertSame(['other-draft/sentinel.png'], Storage::disk('staff_drafts')->allFiles());
    }

    public function test_preview_requires_owner_session_draft_and_line_membership(): void
    {
        $line = $this->openLine();
        $this->save($line, ['images' => [UploadedFile::fake()->image('private.png')]]);
        $photo = $this->draft()['items'][0]['staged_images'][0];
        $url = route('orders.package.image', ['draft' => $this->draft()['draft_id'], 'line' => $line, 'image' => $photo['image_id']]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('orders.package.image', ['draft' => (string) Str::uuid(), 'line' => $line, 'image' => $photo['image_id']]))->assertNotFound();
        $this->get(route('orders.package.image', ['draft' => $this->draft()['draft_id'], 'line' => (string) Str::uuid(), 'image' => $photo['image_id']]))->assertNotFound();
        $this->get('/storage/order_drafts/'.$this->draft()['scope'].'/'.basename($photo['staged_path']))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]))->get($url)->assertNotFound();
        $this->actingAs($this->owner);
        $this->flushSession();
        $this->get($url)->assertNotFound();
    }

    public function test_customer_pickup_notes_and_inline_customer_survive_validation_back_and_reload(): void
    {
        $line = $this->openLine();
        $this->save($line);
        $customer = $this->postJson(route('orders.inlineCustomer'), ['first_name' => 'Ana', 'last_name' => 'Santos', 'phone_number' => '09179998888'])->assertOk()->json();
        $payload = $this->checkout(['customer_id' => $customer['id'], 'pickup_time' => '07:59']);
        $this->from(route('orders.details'))->post(route('orders.store'), $payload)->assertSessionHasErrors('pickup_time');
        $this->assertEquals($customer['id'], $this->draft()['details']['customer_id']);
        $this->assertSame('07:59', $this->draft()['details']['pickup_time']);
        $this->get(route('orders.details'))->assertOk()->assertSee('Call before pickup')->assertSee('Ana Santos');
        $this->post(route('orders.back'), $payload)->assertRedirect(route('orders.create'));
        $this->get($this->url('customize', $line))->assertOk();
        $this->save($line, ['themes' => 'Updated design']);
        $this->assertEquals($customer['id'], $this->draft()['details']['customer_id']);
        $this->postJson(route('orders.details.draft'), $this->checkout(['pickup_time' => '08:00']) + ['customer_picker' => ['first_name' => 'Unfinished', 'showAdd' => '1']])->assertOk();
        $this->get(route('orders.details'))->assertOk()->assertSee('Unfinished')->assertSee('08:00');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_catalog_changes_keep_details_and_require_affected_line_review(): void
    {
        $line = $this->openLine();
        $this->save($line);
        $payload = $this->checkout();
        $this->post(route('orders.back'), $payload)->assertRedirect();
        $this->cake->options()->first()->update(['price' => 1200]);
        $this->get(route('orders.details'))->assertRedirect(route('orders.create'))->assertSessionHasErrors('items');
        $this->post(route('orders.store'), $payload)->assertRedirect(route('orders.create'))->assertSessionHasErrors('items');
        $this->assertSame('Call before pickup', $this->draft()['details']['notes_text']);
        $this->get($this->url('customize', $line))->assertOk();
        $this->save($line);
        $this->get(route('orders.details'))->assertOk()->assertSee('1,200.00');
        $this->cake->update(['is_active' => false]);
        $this->get(route('orders.details'))->assertRedirect(route('orders.create'));
        $this->get($this->url('customize', $line))->assertOk()->assertSee('This package is unavailable');
        $this->assertCount(1, $this->draft()['items']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_save_rechecks_prices_and_equal_total_price_changes_cannot_bypass_review(): void
    {
        $extra = AddOn::create(['name' => 'Extra cupcakes', 'description' => 'Six cupcakes', 'price' => 100, 'is_active' => true]);
        $this->cake->addOns()->attach($extra);
        $line = $this->openLine();
        $changes = ['add_ons' => [['add_on_id' => $extra->id, 'quantity' => 1]]];
        $this->save($line, $changes);
        $this->cake->options()->first()->update(['price' => 900]);
        $extra->update(['price' => 200]);
        $this->post($this->url('save', $line), ['items' => [$this->line($line, $changes)]])->assertSessionHasErrors('items.0.package_option_id');
        $this->post(route('orders.store'), $this->checkout(['expected_total' => 1100]))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_owner_creation_approves_without_payment_and_replays_after_cleanup_return_original(): void
    {
        $line = $this->openLine();
        $this->save($line, ['images' => [UploadedFile::fake()->image('saved.png')]]);
        $abandoned = $this->openLine();
        $this->postJson($this->url('draft', $abandoned), ['items' => [$this->line($abandoned, ['images' => [UploadedFile::fake()->image('abandoned.png')]])]])->assertOk();
        $payload = $this->checkout();
        $response = $this->post(route('orders.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('approved', $order->review_status);
        $this->assertSame($this->owner->id, $order->reviewed_by);
        $this->assertNotNull($order->reviewed_at);
        $this->assertSame($this->owner->id, $order->user_id);
        $this->assertSame(0.0, $order->amount_paid);
        $this->assertFalse($order->needsStaffReview());
        $this->assertTrue($order->canRecordDeposit());
        $this->assertFalse($order->canStartPreparation());
        $this->assertNull(session('staff_order_drafts.'.$this->owner->id));
        $this->assertSame([], Storage::disk('staff_drafts')->allFiles());
        $image = $order->images()->sole();
        Storage::disk('staff_references')->assertExists($image->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get($image->url())->assertOk();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('value="cash"', false)->assertSee('value="gcash"', false)->assertDontSee('Confirm request');
        $this->post(route('orders.store'), $payload)->assertRedirect($response->headers->get('Location'));
        $this->get(route('orders.create'))->assertOk();
        $newDraft = $this->draft()['draft_id'];
        $this->post(route('orders.store'), $payload)->assertRedirect($response->headers->get('Location'));
        $this->assertSame($newDraft, $this->draft()['draft_id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_images', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('orders.store'), $this->checkout(['submission_key' => (string) Str::uuid()]))->assertSessionHasErrors('submission_key');
    }

    public function test_cash_and_gcash_deposits_require_exact_amount_and_gcash_reference(): void
    {
        foreach (['cash', 'gcash'] as $method) {
            $line = $this->openLine();
            $this->save($line);
            $this->post(route('orders.store'), $this->checkout())->assertRedirect()->assertSessionHasNoErrors();
            $order = Order::latest('id')->first();
            $payment = ['payment_type' => 'down_payment', 'amount' => 500, 'payment_method' => $method];
            $this->post(route('orders.payments.store', $order), array_replace($payment, ['amount' => 499]))->assertSessionHasErrors();
            if ($method === 'gcash') {
                $this->post(route('orders.payments.store', $order), $payment)->assertSessionHasErrors('reference_number');
                $payment['reference_number'] = 'STAFF-DEPOSIT-123';
            }
            $this->post(route('orders.payments.store', $order), $payment)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($method, $order->payments()->sole()->payment_method);
            $this->assertTrue($order->fresh()->canStartPreparation());
            $this->post(route('orders.payments.store', $order), $payment)->assertSessionHasErrors();
            $this->assertSame(500.0, $order->fresh()->amount_paid);
        }
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_paid_customer_cancellation_retains_deposit_once_in_reports(): void
    {
        $line = $this->openLine();
        $this->save($line);
        $this->post(route('orders.store'), $this->checkout())->assertRedirect();
        $order = Order::sole();
        $stale = $order->fresh()->load('payments');
        app(OrderService::class)->recordDownPayment($order, 500, 'cash', null, $this->owner);
        $this->get(route('orders.show', $order))->assertSee('Customer cancellation')->assertSee('retained as cancellation collection');
        app(OrderService::class)->cancelOrder($stale, $this->owner);
        $time = $order->fresh()->cancelled_at->toDateTimeString();
        $this->post(route('orders.cancel', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($time, $order->fresh()->cancelled_at->toDateTimeString());
        $this->assertSame('customer', $order->fresh()->cancellation_kind);
        $report = app(FinancialReportService::class);
        $businessDay = now(config('bakery.business_timezone'))->toDateString();
        $this->assertSame(500.0, $report->getCancellationIncome($businessDay, $businessDay));
        $this->assertSame(500.0, $report->getPaymentCollections($businessDay, $businessDay));
        $this->assertSame(0.0, $report->getSales($businessDay, $businessDay));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(500.0, $order->fresh()->amount_paid);
    }

    public function test_legacy_staff_selection_and_public_draft_are_preserved_and_isolated(): void
    {
        $line = (string) Str::uuid();
        $legacy = ['items' => [$this->line($line)], 'details' => ['customer_id' => $this->customer->id, 'notes_text' => 'Legacy notes'], 'submission_key' => (string) Str::uuid()];
        $public = ['items' => [$this->line((string) Str::uuid())], 'details' => ['first_name' => 'Public buyer'], 'submission_key' => (string) Str::uuid()];
        $this->withSession(['staff_order_draft' => $legacy, 'public_order_draft' => $public])->get(route('orders.create'))->assertOk();
        $this->assertSame($line, $this->draft()['items'][0]['draft_key']);
        $this->assertSame('Legacy notes', $this->draft()['details']['notes_text']);
        $this->post(route('orders.store'), $this->checkout())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($public, session('public_order_draft'));
    }

    public function test_new_staff_endpoints_reject_assistants_inactive_users_and_guests(): void
    {
        $line = $this->openLine();
        $draft = $this->draft();
        $urls = [route('orders.package.customize', ['product' => $this->cake, 'line' => $line, 'draft_id' => $draft['draft_id']]),
            route('orders.package.image', ['draft' => $draft['draft_id'], 'line' => $line, 'image' => (string) Str::uuid()])];
        $posts = [$this->url('save', $line), $this->url('draft', $line), route('orders.package.remove', $line), route('orders.details.draft'), route('orders.package.quote')];
        foreach ([User::factory()->create(['role' => 'assistant', 'is_active' => true]), User::factory()->create(['role' => 'owner', 'is_active' => false])] as $user) {
            $this->actingAs($user);
            foreach ($urls as $url) $this->get($url)->assertForbidden();
            foreach ($posts as $url) $this->postJson($url, ['draft_id' => $draft['draft_id']])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        foreach ($urls as $url) $this->get($url)->assertRedirect(route('login'));
        foreach ($posts as $url) $this->postJson($url, ['draft_id' => $draft['draft_id']])->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }
}
