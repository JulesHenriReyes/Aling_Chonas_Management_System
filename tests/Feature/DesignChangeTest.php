<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, PaymentProof, User};
use App\Services\{DesignChangeService, DesignChangeStore, OrderReviewService, OrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DesignChangeTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private function order(int $lines = 1): Order
    {
        $customer = Customer::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Celebration cake', 'price' => 1000, 'is_active' => true]);
        return app(OrderService::class)->createPublicOrder(['customer_id' => $customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00',
            'items' => array_fill(0, $lines, ['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id,
                'quantity' => 1, 'themes' => 'Pink flowers', 'special_request' => 'Happy birthday!'])]);
    }

    private function paid(Order $order): User
    {
        $owner = User::factory()->create(['role' => 'owner']);
        app(OrderReviewService::class)->confirm($order, $owner, true);
        $proof = PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt.jpg', 'reference_number' => 'PAID-'.$order->id]);
        app(OrderService::class)->recordDownPayment($order->fresh(), $order->required_down_payment, 'gcash', $proof->reference_number, $owner, null, $proof);
        $order->refresh();
        return $owner;
    }

    private function draft(Order $order): array
    {
        return $this->get(route('public.designChanges.form', $order->private_token))->assertOk()->viewData('draft');
    }

    private function payload(array $draft, string $theme = 'Sage green and cream'): array
    {
        $items = $draft['items'];
        foreach ($items as &$item) $item['themes'] = $theme;
        return ['draft_id' => $draft['id'], 'pause_acknowledged' => '1', 'items' => $items];
    }

    private function submit(Order $order): string
    {
        $draft = $this->draft($order);
        $theme = $draft['snapshot'][0]['themes'] === 'Sage green and cream' ? 'Gold and white' : 'Sage green and cream';
        $this->post(route('public.designChanges.submit', $order->private_token), $this->payload($draft, $theme))->assertSessionHasNoErrors()->assertRedirect();
        return $draft['id'];
    }

    private function approve(Order $order, string $id, User $staff)
    {
        return $this->actingAs($staff)->post(route('orders.designChanges.approve', ['order' => $order, 'change' => $id]), ['feasibility_confirmed' => '1']);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('reference.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII='));
    }

    public function test_submission_is_file_only_and_duplicate_submission_has_one_pending_request(): void
    {
        $order = $this->order();
        $tables = Schema::getTableListing();
        $columns = collect($tables)->mapWithKeys(fn ($table) => [$table => Schema::getColumnListing($table)])->all();
        $attributes = $order->fresh()->getAttributes();
        $draft = $this->draft($order);
        $url = route('public.designChanges.submit', $order->private_token);
        $this->post($url, $this->payload($draft))->assertSessionHasNoErrors();
        $this->post($url, $this->payload($draft))->assertSessionHasNoErrors();
        $this->assertSame($attributes, $order->fresh()->getAttributes());
        $this->assertSame('Pink flowers', $order->orderDetails()->first()->themes);
        $this->assertCount(1, app(DesignChangeStore::class)->read($order, true)['requests']);
        $this->assertSame($tables, Schema::getTableListing());
        foreach ($columns as $table => $fields) $this->assertSame($fields, Schema::getColumnListing($table));
        $this->assertFileExists(app(DesignChangeStore::class)->statePath($order));
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Preparation paused.')->assertSee('Withdraw change request');
    }

    public function test_owner_and_assistant_can_approve_all_packages_without_changing_money_or_pickup(): void
    {
        foreach (['owner', 'assistant'] as $role) {
            $order = $this->order(2);
            $owner = $this->paid($order);
            $staff = $role === 'owner' ? $owner : User::factory()->create(['role' => 'assistant']);
            $before = $order->getAttributes();
            $payments = $order->payments()->get()->map->getAttributes()->all();
            $prices = $order->orderDetails()->get()->map(fn ($line) => [$line->unit_price, $line->quantity, $line->layers])->all();
            $id = $this->submit($order);
            $this->approve($order, $id, $staff)->assertSessionHasNoErrors()->assertRedirect();
            $firstDecision = app(DesignChangeService::class)->find($order, $id);
            $this->approve($order, $id, $staff)->assertSessionHasNoErrors();
            $this->assertSame($firstDecision, app(DesignChangeService::class)->find($order, $id));
            $this->assertSame('approved', $firstDecision['status']);
            $this->assertSame($staff->id, $firstDecision['reviewer_id']);
            $this->assertSame($before, $order->fresh()->getAttributes());
            $this->assertSame($payments, $order->payments()->get()->map->getAttributes()->all());
            $this->assertSame($prices, $order->orderDetails()->get()->map(fn ($line) => [$line->unit_price, $line->quantity, $line->layers])->all());
            $this->assertSame(['Sage green and cream'], $order->orderDetails()->pluck('themes')->unique()->values()->all());
            $this->assertFalse(app(DesignChangeService::class)->summary($order)['hold']);
            $this->get(route('orders.show', $order))->assertOk()->assertSee('Design change history');
        }
    }

    public function test_review_hold_blocks_confirmation_start_and_ready_then_withdrawal_releases_it(): void
    {
        $order = $this->order();
        $staff = User::factory()->create(['role' => 'assistant']);
        $id = $this->submit($order);
        $this->actingAs($staff)->post(route('orders.confirm', $order), ['feasibility_confirmed' => '1'])->assertSessionHasErrors('design_change');
        $this->post(route('public.designChanges.withdraw', ['token' => $order->private_token, 'change' => $id]))->assertSessionHasNoErrors();
        $this->post(route('public.designChanges.withdraw', ['token' => $order->private_token, 'change' => $id]))->assertSessionHasNoErrors();
        $this->post(route('orders.confirm', $order), ['feasibility_confirmed' => '1'])->assertSessionHasNoErrors();

        $order = $this->order();
        $owner = $this->paid($order);
        $id = $this->submit($order);
        $this->actingAs($staff)->patch(route('orders.updateStatus', $order), ['status' => 'preparing'])->assertSessionHasErrors('design_change');
        $this->approve($order, $id, $owner)->assertSessionHasNoErrors();
        $this->patch(route('orders.updateStatus', $order), ['status' => 'preparing'])->assertSessionHasNoErrors();
        $id = $this->submit($order->fresh());
        $this->patch(route('orders.updateStatus', $order), ['status' => 'ready_for_pickup'])->assertSessionHasErrors('design_change');
        $this->assertSame('preparing', $order->fresh()->status);
        $this->post(route('public.designChanges.withdraw', ['token' => $order->private_token, 'change' => $id]))->assertSessionHasNoErrors();
        $this->patch(route('orders.updateStatus', $order), ['status' => 'ready_for_pickup'])->assertSessionHasNoErrors();
    }

    public function test_decline_requires_explanation_and_keeps_current_design(): void
    {
        $order = $this->order(); $id = $this->submit($order);
        $staff = User::factory()->create(['role' => 'assistant']);
        $url = route('orders.designChanges.decline', ['order' => $order, 'change' => $id]);
        $this->actingAs($staff)->post($url, ['reason' => '  '])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => 'This needs a different package and extra payment.'])->assertSessionHasNoErrors();
        $this->assertSame('Pink flowers', $order->orderDetails()->first()->themes);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('This needs a different package and extra payment.');
        $this->approve($order, $id, $staff)->assertSessionHasErrors('design_change');
    }

    public function test_unchanged_requests_acknowledgement_and_non_design_fields_are_rejected(): void
    {
        $order = $this->order(); $draft = $this->draft($order);
        $url = route('public.designChanges.submit', $order->private_token);
        $this->post($url, $this->payload($draft, 'Pink flowers'))->assertSessionHasErrors('design_change');
        $payload = $this->payload($draft); unset($payload['pause_acknowledged']);
        $this->post($url, $payload)->assertSessionHasErrors('pause_acknowledged');
        $payload = $this->payload($draft); $line = array_key_first($payload['items']); $payload['items'][$line]['price'] = 1;
        $this->post($url, $payload)->assertSessionHasErrors('items.'.$line);
        $this->assertFalse(app(DesignChangeService::class)->summary($order)['hold']);
    }

    public function test_only_one_pending_request_and_no_new_requests_after_ready_cancelled_completed_or_staff_order(): void
    {
        $order = $this->order(); $id = $this->submit($order);
        $this->get(route('public.designChanges.form', ['token' => $order->private_token, 'restart' => 1]))->assertRedirect();
        $this->assertSame($id, app(DesignChangeService::class)->summary($order)['pending']['id']);
        foreach (['ready_for_pickup', 'completed', 'cancelled'] as $status) {
            $order = $this->order(); $draft = $this->draft($order); $order->update(['status' => $status]);
            $this->post(route('public.designChanges.submit', $order->private_token), $this->payload($draft))->assertSessionHasErrors('design_change');
        }
        $order = $this->order(); $order->update(['user_id' => User::factory()->create(['role' => 'owner'])->id]);
        $this->get(route('public.designChanges.form', $order->private_token))->assertNotFound();
        $this->get(route('public.designChanges.form', str_repeat('f', 64)))->assertNotFound();
    }

    public function test_uploaded_references_are_private_and_historical_photos_survive_replacement(): void
    {
        Storage::fake('design_references'); Storage::fake('public');
        $order = $this->order(); $line = $order->orderDetails()->first();
        Storage::disk('public')->put('order_images/original.png', $this->photo()->getContent());
        $original = $line->images()->create(['order_id' => $order->id, 'file_path' => 'order_images/original.png', 'original_filename' => 'Original.png']);
        $draft = $this->draft($order);
        $upload = $this->postJson(route('public.designChanges.upload', $order->private_token), ['draft_id' => $draft['id'], 'line_id' => $line->id, 'images' => [$this->photo()]])->assertOk()->json('images.0');
        $payload = $this->payload($draft); $payload['items'][$line->id]['keep_images'] = []; $payload['items'][$line->id]['staged_images'] = [$upload['key']];
        $this->post(route('public.designChanges.submit', $order->private_token), $payload)->assertSessionHasNoErrors();
        $this->get($upload['url'])->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $staff = User::factory()->create(['role' => 'assistant']);
        $this->approve($order, $draft['id'], $staff)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('order_images', ['id' => $original->id]);
        $replacement = $line->images()->first();
        $this->assertStringStartsWith('design-changes/', $replacement->file_path);
        $this->get($replacement->customerUrl($order->private_token))->assertOk();
        $this->get($replacement->url())->assertOk();
        $historyUrl = route('public.designChanges.reference', ['token' => $order->private_token, 'change' => $draft['id'], 'reference' => 'image-'.$original->id]);
        $this->get($historyUrl)->assertOk();
        $other = $this->order();
        $this->get($replacement->customerUrl($other->private_token))->assertNotFound();
        $this->get(route('public.designChanges.reference', ['token' => $other->private_token, 'change' => $draft['id'], 'reference' => $upload['key']]))->assertNotFound();
        $this->get(route('public.order.reference', ['token' => str_repeat('a',64), 'image' => $replacement->id]))->assertNotFound();
    }

    public function test_upload_validation_and_foreign_package_and_reference_ids(): void
    {
        $order = $this->order(); $draft = $this->draft($order); $other = $this->order(); $foreign = $other->orderDetails()->first();
        $this->postJson(route('public.designChanges.upload', $order->private_token), ['draft_id' => $draft['id'], 'line_id' => $foreign->id, 'images' => [$this->photo()]])->assertNotFound();
        $this->postJson(route('public.designChanges.upload', $order->private_token), ['draft_id' => $draft['id'], 'line_id' => array_key_first($draft['items']), 'images' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]])->assertUnprocessable();
        $payload = $this->payload($draft); $line = array_key_first($draft['items']); $payload['items'][$line]['keep_images'] = [123456];
        $this->post(route('public.designChanges.submit', $order->private_token), $payload)->assertSessionHasErrors('design_change');
    }

    public function test_stale_draft_and_stale_approval_cannot_overwrite_a_changed_design(): void
    {
        $order = $this->order(); $draft = $this->draft($order); $line = $order->orderDetails()->first();
        $line->update(['themes' => 'Owner changed it']);
        $this->post(route('public.designChanges.submit', $order->private_token), $this->payload($draft))->assertSessionHasErrors('design_change');
        $this->get(route('public.designChanges.form', ['token' => $order->private_token, 'restart' => 1]));
        $id = $this->submit($order); $line->update(['themes' => 'External change']);
        $this->approve($order, $id, User::factory()->create(['role' => 'owner']))->assertSessionHasErrors('design_change');
        $this->assertSame('External change', $line->fresh()->themes);
    }

    public function test_inactive_staff_and_customers_cannot_review(): void
    {
        $order = $this->order(); $id = $this->submit($order);
        $url = route('orders.designChanges.approve', ['order' => $order, 'change' => $id]);
        $this->post($url, ['feasibility_confirmed' => '1'])->assertRedirect(route('login'));
        $this->approve($order, $id, User::factory()->create(['role' => 'assistant', 'is_active' => false]))->assertForbidden();
        $this->assertSame('pending', app(DesignChangeService::class)->find($order, $id)['status']);
    }

    public function test_cancelled_orders_close_the_request_without_changing_payments(): void
    {
        $order = $this->order(); $this->paid($order); $id = $this->submit($order);
        $payment = $order->payments()->first()->getAttributes();
        $this->post(route('public.order.cancel', $order->private_token), ['confirm_cancellation' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('closed', app(DesignChangeService::class)->summary($order->fresh())['latest']['status']);
        $this->assertSame($payment, $order->payments()->first()->getAttributes());
        $this->approve($order, $id, User::factory()->create(['role' => 'owner']))->assertSessionHasErrors('design_change');
    }

    public function test_interrupted_approval_recovers_a_rollback_and_a_commit_without_duplicate_updates(): void
    {
        foreach ([false, true] as $committed) {
            $order = $this->order(); $id = $this->submit($order); $store = app(DesignChangeStore::class);
            DB::transaction(function () use ($order, $id, $store) {
                Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $store->locked($order, function ($state) use ($order, $id, $store) {
                    $state['requests'][$id]['status'] = 'applying';
                    $state['requests'][$id]['reviewer_id'] = 123;
                    $state['requests'][$id]['reviewer_name'] = 'Reviewer';
                    $state['requests'][$id]['decided_at'] = now()->toIso8601String();
                    $store->write($order, $state);
                });
            });
            if ($committed) $order->orderDetails()->first()->update(['themes' => 'Sage green and cream']);
            app(DesignChangeService::class)->recover($order);
            app(DesignChangeService::class)->recover($order);
            $decision = app(DesignChangeService::class)->find($order, $id);
            $this->assertSame($committed ? 'approved' : 'pending', $decision['status']);
            $this->assertSame($committed ? 123 : null, $decision['reviewer_id']);
            $this->assertSame(!$committed, app(DesignChangeService::class)->summary($order)['hold']);
        }
    }

    public function test_corrupt_or_missing_registered_files_hold_preparation_safely(): void
    {
        $order = $this->order(); $staff = $this->paid($order); $id = $this->submit($order);
        $path = app(DesignChangeStore::class)->statePath($order);
        file_put_contents($path, '{broken');
        $this->actingAs($staff)->patch(route('orders.updateStatus', $order), ['status' => 'preparing'])->assertSessionHasErrors('design_change');
        unlink($path);
        $this->patch(route('orders.updateStatus', $order), ['status' => 'preparing'])->assertSessionHasErrors('design_change');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_live_status_response_is_small_and_revision_changes_on_request_and_review(): void
    {
        $order = $this->order(); $url = route('public.order.liveStatus', $order->private_token);
        $before = $this->getJson($url)->assertOk()->json('revision');
        $id = $this->submit($order);
        $response = $this->getJson($url)->assertOk(); $pending = $response->json('revision');
        $this->assertNotSame($before, $pending);
        $this->assertLessThan(128, strlen($response->getContent()));
        $this->approve($order, $id, User::factory()->create(['role' => 'assistant']))->assertSessionHasNoErrors();
        $this->assertNotSame($pending, $this->getJson($url)->json('revision'));
        $this->get(route('orders.index', ['queue' => 'changes']))->assertOk();
    }

    public function test_failed_database_update_rolls_back_every_package_and_restores_the_pending_journal(): void
    {
        $order = $this->order(2); $id = $this->submit($order);
        $second = $order->orderDetails()->orderBy('id')->get()->last()->id;
        \App\Models\OrderDetail::updating(function ($line) use ($second) {
            if ($line->id === $second) throw new \RuntimeException('Simulated second-package save failure.');
        });
        try {
            app(DesignChangeService::class)->review($order, $id, 'approved', User::factory()->create(['role' => 'owner']));
            $this->fail('The simulated failure did not occur.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated second-package save failure.', $error->getMessage());
        }
        $this->assertSame(['Pink flowers'], $order->orderDetails()->pluck('themes')->unique()->values()->all());
        $this->assertSame('pending', app(DesignChangeService::class)->find($order, $id)['status']);
        $this->assertTrue(app(DesignChangeService::class)->summary($order)['hold']);
    }

    public function test_recycled_numeric_ids_do_not_hide_the_current_order_or_load_another_identity(): void
    {
        $order = $this->order(); $id = $this->submit($order);
        $old = clone $order; $old->receipt_key = 'old-recycled-record';
        $store = app(DesignChangeStore::class); $state = $store->read($old, true);
        $state['requests'][$id] = app(DesignChangeService::class)->find($order, $id);
        $store->locked($old, fn () => $store->write($old, $state));
        $this->assertSame([$order->id], $store->pendingOrderIds());
        $this->assertNotSame($store->statePath($old), $store->statePath($order));
        $this->assertSame(hash('sha256', $order->receipt_key), $store->read($order, true)['identity']);
    }
}
