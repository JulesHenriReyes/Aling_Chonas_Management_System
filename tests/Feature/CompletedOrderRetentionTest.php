<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, User};
use App\Services\{CompletedOrderRetentionService, DesignChangeService, DesignChangeStore, OrderService};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CompletedOrderRetentionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private DesignChangeStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-11 04:00:00', 'UTC'));
        $this->store = new class(storage_path('app/retention-test-'.Str::uuid())) extends DesignChangeStore {
            public function __construct(private string $caseRoot) {}
            public function root(): string { return $this->caseRoot; }
        };
        $this->app->instance(DesignChangeStore::class, $this->store);
        foreach (['public', 'staff_references', 'design_references', 'receipts'] as $disk) Storage::fake($disk);
    }

    private function order(string $status = 'completed', int $days = 61): Order
    {
        $customer = Customer::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Celebration cake', 'price' => 1000, 'is_active' => true]);
        $order = app(OrderService::class)->createPublicOrder(['customer_id' => $customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00',
            'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id,
                'quantity' => 1, 'themes' => 'Pink flowers', 'special_request' => 'Happy birthday!']]]);
        $order->forceFill(['status' => $status, 'completed_at' => now()->subDays($days)])->save();
        return $order;
    }

    private function photo(Order $order, string $disk = 'public', bool $attach = true): array
    {
        $path = match ($disk) {
            'public' => 'order_images/'.$order->id.'/'.Str::uuid().'.png',
            'staff_references' => 'staff/'.$order->id.'/'.Str::uuid().'.png',
            'design_references' => 'design-changes/'.$this->store->identity($order).'/'.Str::uuid().'/'.Str::uuid().'.png',
        };
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=');
        Storage::disk($disk)->put($path, $png);
        $image = $attach ? $order->orderDetails()->first()->images()->create(['order_id' => $order->id,
            'file_path' => $path, 'original_filename' => 'Reference.png']) : null;
        return ['disk' => $disk, 'file_path' => $path, 'key' => $image ? 'image-'.$image->id : (string) Str::uuid(),
            'id' => $image?->id, 'name' => 'Reference.png'];
    }

    private function history(Order $order, string $status = 'declined', ?array $extra = null): string
    {
        $before = app(DesignChangeService::class)->snapshot($order);
        $proposed = $before;
        $proposed[0]['themes'] = 'Sage green and cream';
        if ($extra) $proposed[0]['references'][] = $extra;
        $id = (string) Str::uuid();
        DB::transaction(function () use ($order, $status, $before, $proposed, $id) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->store->locked($order, function ($state) use ($order, $status, $before, $proposed, $id) {
                $state['requests'][$id] = ['id' => $id, 'status' => $status,
                    'submitted_at' => now()->subDays(420)->toIso8601String(), 'before' => $before,
                    'proposed' => $proposed, 'base_fingerprint' => app(DesignChangeService::class)->fingerprint($before),
                    'reviewer_id' => null, 'reviewer_name' => 'Owner', 'decided_at' => now()->subDays(419)->toIso8601String(),
                    'reason' => 'This decoration needs a different package.'];
                $this->store->write($order, $state);
            });
        });
        return $id;
    }

    public function test_only_completed_orders_are_cleaned_even_if_other_statuses_have_old_completion_dates(): void
    {
        $photos = [];
        foreach (['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'cancelled', 'completed'] as $status) {
            $order = $this->order($status, 400);
            $photos[$status] = [$order, $this->photo($order)];
            $this->history($order);
        }
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['files']);
        $this->assertSame(1, $result['histories_expired']);
        foreach ($photos as $status => [$order, $photo]) {
            $state = $this->store->read($order, true);
            if ($status === 'completed') {
                Storage::disk('public')->assertMissing($photo['file_path']);
                $this->assertSame([], $state['requests']);
            } else {
                Storage::disk('public')->assertExists($photo['file_path']);
                $this->assertCount(1, $state['requests']);
                $this->assertArrayNotHasKey('retention', $state);
            }
        }
    }

    public function test_sixty_day_boundary_uses_completed_at_and_skips_missing_or_future_dates(): void
    {
        $orders = [];
        foreach ([now()->subDays(60)->addSecond(), now()->subDays(60), null, now()->addDay()] as $date) {
            $order = $this->order();
            $order->forceFill(['completed_at' => $date, 'updated_at' => now()->subYears(2)])->save();
            $orders[] = [$order, $this->photo($order)];
        }
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(1, $result['files']);
        foreach ($orders as $index => [$order, $photo]) {
            $this->assertSame($index !== 1, Storage::disk('public')->exists($photo['file_path']));
        }
    }

    public function test_regular_staff_approved_historical_and_abandoned_design_photos_are_included(): void
    {
        $order = $this->order();
        $photos = [$this->photo($order), $this->photo($order, 'staff_references'),
            $this->photo($order, 'design_references'), $this->photo($order, 'design_references', false)];
        $this->history($order, 'approved', $photos[3]);
        $photos[] = $this->photo($order, 'design_references', false);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame([], $result['errors']);
        $this->assertSame(5, $result['files']);
        foreach ($photos as $photo) Storage::disk($photo['disk'])->assertMissing($photo['file_path']);
        $this->assertCount(1, $this->store->read($order, true)['requests']);
    }

    public function test_preview_and_cleanup_preserve_schema_order_details_image_metadata_and_payment_records(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $this->history($order);
        $owner = User::factory()->create(['role' => 'owner']);
        $order->payments()->create(['user_id' => $owner->id, 'amount' => 1000, 'payment_type' => 'final_payment',
            'payment_method' => 'cash', 'payment_date' => now()->subDays(61)]);
        Storage::disk('receipts')->put('private/payment.png', 'payment evidence');
        Storage::disk('public')->put('products/catalog.png', 'catalog photo');
        $tables = Schema::getTableListing();
        $columns = collect($tables)->mapWithKeys(fn ($table) => [$table => Schema::getColumnListing($table)])->all();
        $rows = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
        $stateBefore = file_get_contents($this->store->statePath($order));
        $preview = app(CompletedOrderRetentionService::class)->run();
        $this->assertSame(1, $preview['files']);
        Storage::disk('public')->assertExists($photo['file_path']);
        $this->assertSame($stateBefore, file_get_contents($this->store->statePath($order)));
        $applied = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame([], $applied['errors']);
        foreach ($rows as $table => $data) {
            $this->assertSame($data, DB::table($table)->get()->toJson(), $table);
            $this->assertSame($columns[$table], Schema::getColumnListing($table));
        }
        $this->assertSame($tables, Schema::getTableListing());
        Storage::disk('receipts')->assertExists('private/payment.png');
        Storage::disk('public')->assertExists('products/catalog.png');
    }

    public function test_history_expires_at_365_days_with_a_valid_small_state_and_visible_expiration(): void
    {
        $order = $this->order('completed', 365);
        $this->photo($order);
        $this->history($order);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(1, $result['histories_expired']);
        $state = $this->store->read($order, true);
        $this->assertSame([], $state['requests']);
        $this->assertNotEmpty($state['retention']['history_expired_at']);
        $this->assertFileExists(dirname($this->store->statePath($order)).'/registered');
        $this->assertLessThan(600, filesize($this->store->statePath($order)));
        $this->get(route('public.order.payment', $order->private_token))->assertOk()->assertSee('Design-change history expired');
        $summary = app(DesignChangeService::class)->summary($order);
        $this->assertTrue($summary['available']);
        $this->assertFalse($summary['hold']);
        $this->assertSame([], $summary['history']);
    }

    public function test_text_history_remains_until_the_one_year_boundary(): void
    {
        $order = $this->order('completed', 365);
        $order->forceFill(['completed_at' => now()->subDays(365)->addSecond()])->save();
        $this->photo($order);
        $this->history($order);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(0, $result['histories_expired']);
        $this->assertCount(1, $this->store->read($order, true)['requests']);
    }

    public function test_pending_requests_and_unsettled_approval_journals_are_protected(): void
    {
        foreach (['pending', 'applying'] as $status) {
            $order = $this->order('completed', 400);
            $photo = $this->photo($order);
            $this->history($order, $status);
            $result = app(CompletedOrderRetentionService::class)->run(true);
            Storage::disk('public')->assertExists($photo['file_path']);
            $this->assertArrayNotHasKey('retention', $this->store->read($order, true));
            $this->assertSame(0, $result['files']);
        }
    }

    public function test_complaint_hold_and_release_are_file_only_and_supported_by_the_command(): void
    {
        $order = $this->order('completed', 400);
        $photo = $this->photo($order);
        $this->history($order);
        $attributes = $order->fresh()->getAttributes();
        $this->artisan('orders:reference-hold', ['order' => $order->order_number, '--reason' => 'Complaint under review'])->assertSuccessful();
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(0, $result['files']);
        Storage::disk('public')->assertExists($photo['file_path']);
        $this->assertCount(1, $this->store->read($order, true)['requests']);
        $this->assertSame($attributes, $order->fresh()->getAttributes());
        $this->artisan('orders:reference-hold', ['order' => $order->order_number, '--release' => true])->assertSuccessful();
        $this->assertSame(1, app(CompletedOrderRetentionService::class)->run(true)['files']);
        $this->artisan('orders:reference-hold', ['order' => $order->order_number, '--reason' => 'Too late'])->assertFailed();
    }

    public function test_a_shared_photo_is_preserved_for_an_active_order_then_deleted_when_both_expire(): void
    {
        $completed = $this->order();
        $photo = $this->photo($completed);
        $active = $this->order('pending');
        $active->orderDetails()->first()->images()->create(['order_id' => $active->id,
            'file_path' => $photo['file_path'], 'original_filename' => 'Shared.png']);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(1, $result['protected_files']);
        Storage::disk('public')->assertExists($photo['file_path']);
        $active->forceFill(['status' => 'completed'])->save();
        app(CompletedOrderRetentionService::class)->run(true);
        app(CompletedOrderRetentionService::class)->run(true);
        Storage::disk('public')->assertMissing($photo['file_path']);
    }

    public function test_a_photo_referenced_only_in_an_active_orders_history_is_protected(): void
    {
        $completed = $this->order();
        $photo = $this->photo($completed);
        $active = $this->order('pending');
        $this->history($active, 'declined', $photo);
        app(CompletedOrderRetentionService::class)->run(true);
        Storage::disk('public')->assertExists($photo['file_path']);
    }

    public function test_corrupt_or_missing_registered_history_stops_deletion(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $this->history($order);
        file_put_contents($this->store->statePath($order), '{broken');
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertNotEmpty($result['errors']);
        Storage::disk('public')->assertExists($photo['file_path']);
        unlink($this->store->statePath($order));
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertNotEmpty($result['errors']);
        Storage::disk('public')->assertExists($photo['file_path']);
    }

    public function test_non_reference_and_traversal_paths_cannot_delete_business_files(): void
    {
        $order = $this->order();
        Storage::disk('public')->put('products/catalog.png', 'catalog');
        $image = $order->orderDetails()->first()->images()->create(['order_id' => $order->id,
            'file_path' => 'order_images/'.$order->id.'/../../products/catalog.png', 'original_filename' => 'Bad.png']);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertNotEmpty($result['errors']);
        Storage::disk('public')->assertExists('products/catalog.png');
        $image->update(['file_path' => 'products/catalog.png']);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertNotEmpty($result['errors']);
        Storage::disk('public')->assertExists('products/catalog.png');
    }

    public function test_a_reference_reused_by_the_catalog_is_preserved(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $order->orderDetails()->first()->product->update(['photo_path' => $photo['file_path']]);
        $result = app(CompletedOrderRetentionService::class)->run(true);
        $this->assertSame(1, $result['protected_files']);
        Storage::disk('public')->assertExists($photo['file_path']);
    }

    public function test_reference_attachment_cannot_repopulate_an_expired_order(): void
    {
        $order = $this->order();
        $this->photo($order);
        app(CompletedOrderRetentionService::class)->run(true);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderService::class)->attachImage($order, 'staff/'.$order->id.'/late.png', 'Late.png', $order->orderDetails()->first(), $owner);
    }

    public function test_recycled_receipt_identity_cannot_sweep_the_previous_private_uploads(): void
    {
        $order = $this->order();
        $old = $this->photo($order, 'design_references');
        $this->history($order);
        $order->forceFill(['receipt_key' => (string) Str::uuid()])->save();
        $new = $this->photo($order, 'design_references');
        app(CompletedOrderRetentionService::class)->run(true);
        Storage::disk('design_references')->assertExists($old['file_path']);
        Storage::disk('design_references')->assertMissing($new['file_path']);
    }

    public function test_failed_deletion_is_journaled_and_a_subsequent_run_retries_safely(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $failure = new class($this->store) extends CompletedOrderRetentionService {
            protected function deleteFile(string $absolute): bool { return false; }
        };
        $result = $failure->run(true);
        $this->assertNotEmpty($result['errors']);
        $state = $this->store->read($order, true);
        $this->assertNotEmpty($state['retention']['photos_expired_at']);
        $this->assertCount(1, $state['retention']['pending_deletions']);
        Storage::disk('public')->assertExists($photo['file_path']);
        $this->assertSame(1, app(CompletedOrderRetentionService::class)->run(true)['files']);
        Storage::disk('public')->assertMissing($photo['file_path']);
        $this->assertSame([], $this->store->read($order, true)['retention']['pending_deletions']);
    }

    public function test_cleanup_is_idempotent_and_an_overlapping_job_does_not_run(): void
    {
        $order = $this->order();
        $this->photo($order);
        $this->history($order);
        app(CompletedOrderRetentionService::class)->run(true);
        $state = file_get_contents($this->store->statePath($order));
        $this->assertSame(0, app(CompletedOrderRetentionService::class)->run(true)['files']);
        $this->assertSame($state, file_get_contents($this->store->statePath($order)));
        $lock = fopen($this->store->root().'/retention.lock', 'c+b');
        flock($lock, LOCK_EX);
        try { $this->assertTrue(app(CompletedOrderRetentionService::class)->run(true)['already_running']); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function test_expired_photos_show_notices_and_protected_links_return_gone(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $change = $this->history($order);
        $beforeRevision = app(DesignChangeService::class)->liveRevision($order);
        app(CompletedOrderRetentionService::class)->run(true);
        $this->assertNotSame($beforeRevision, app(DesignChangeService::class)->liveRevision($order));
        $this->get(route('public.order.payment', $order->private_token))->assertOk()
            ->assertSee('Reference photos expired')->assertSee('different package')
            ->assertDontSee('href="'.route('public.order.reference', ['token' => $order->private_token, 'image' => $photo['id']]).'"', false);
        $this->get(route('public.order.reference', ['token' => $order->private_token, 'image' => $photo['id']]))->assertStatus(410);
        $this->get(route('public.designChanges.reference', ['token' => $order->private_token,
            'change' => $change, 'reference' => $photo['key']]))->assertStatus(410);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('orders.show', $order))->assertOk()->assertSee('Reference photos expired');
        $this->getJson(route('orders.show', $order))->assertOk()
            ->assertJsonPath('order.photos_expired', true)->assertJsonCount(0, 'order.images');
    }

    public function test_command_defaults_to_preview_and_scheduler_uses_the_business_timezone(): void
    {
        $order = $this->order();
        $photo = $this->photo($order);
        $this->artisan('orders:prune-references')->expectsOutput('Preview only. No photos or history were removed.')->assertSuccessful();
        Storage::disk('public')->assertExists($photo['file_path']);
        $this->artisan('orders:prune-references', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing($photo['file_path']);
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'orders:prune-references'));
        $this->assertCount(1, $events);
        $this->assertSame('0 2 * * *', $events->first()->expression);
        $this->assertSame(config('bakery.business_timezone'), $events->first()->timezone);
    }
}
