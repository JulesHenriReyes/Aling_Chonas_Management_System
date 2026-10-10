<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UiPresentationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private function snapshot(string $name, TestResponse $response): void
    {
        $response->assertOk();
        // Optional local visual review output; all fixtures use the test database.
        if ($directory = getenv('BAKERY_UI_SNAPSHOT_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $html = str_replace('http://localhost', 'http://127.0.0.1:8000', $response->getContent());
            file_put_contents($directory.'/'.$name.'.html', $html);
        }
    }

    public function test_order_pages_preserve_actions_and_price_locking_in_every_lifecycle_state(): void
    {
        $owner = User::create(['first_name' => 'Review', 'last_name' => 'Owner', 'email' => 'review@example.test', 'password' => 'password123', 'role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'De la Cruz Santiago', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Custom celebration cake with buttercream flowers and a very long design description', 'price' => 123456.78, 'is_active' => true]);
        $service = app(OrderService::class);
        $data = ['customer_id' => $customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'notes_text' => 'Keep the cake level. Customer will call before pickup.', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1, 'layers' => 3, 'themes' => 'Pastel blue and gold floral celebration', 'special_request' => 'Happy birthday, Maria! Please keep the inscription and every design detail visible.']]];
        $order = $service->createInternalOrder($data, $owner);
        // Keep coverage of existing internal requests created before automatic Owner approval.
        $order->forceFill(['status' => 'pending', 'review_status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null])->save();
        $this->actingAs($owner);
        $pending = $this->get('/orders/'.$order->id);
        $pending->assertDontSee('name="unit_price"', false)->assertDontSee('value="down_payment"', false)->assertSee('Confirm request')->assertSee('id="staff-review-heading"', false);
        $this->snapshot('order-pending', $pending);
        $this->confirmPaymentFixture($order);
        $unpaidApproval = $this->get('/orders/'.$order->id);
        $unpaidApproval->assertSee('value="down_payment"', false)->assertDontSee('value="preparing"', false)->assertDontSee('id="staff-review-heading"', false)->assertSee('Review history');
        $service->recordDownPayment($order, $order->required_down_payment, 'gcash', 'TEST-REF-123', $owner);
        $confirmed = $this->get('/orders/'.$order->id);
        $confirmed->assertDontSee('name="unit_price"', false)->assertSee('value="preparing"', false)->assertDontSee('name="pickup_confirmed"', false);
        $this->snapshot('order-confirmed', $confirmed);
        $service->updateStatus($order, 'preparing', $owner);
        $preparing = $this->get('/orders/'.$order->id);
        $preparing->assertSee('value="ready_for_pickup"', false)->assertDontSee('id="staff-review-heading"', false)->assertSee('Review history')->assertDontSee('Staff confirmation opens');
        $this->snapshot('order-preparing', $preparing);
        $service->updateStatus($order, 'ready_for_pickup', $owner);
        $ready = $this->get('/orders/'.$order->id);
        $ready->assertDontSee('value="completed"', false)->assertSee('At pickup:')->assertSee('name="pickup_confirmed"', false)->assertDontSee('id="staff-review-heading"', false);
        $this->snapshot('order-ready-unpaid', $ready);
        // Explicit fully paid legacy state for normal status completion coverage.
        $order->payments()->create(['user_id' => $owner->id, 'amount' => $order->remaining_balance, 'payment_type' => 'final_payment', 'payment_method' => 'cash', 'payment_date' => now()]);
        $paid = $this->get('/orders/'.$order->id);
        $paid->assertSee('value="completed"', false)->assertDontSee('value="final_payment"', false);
        $this->snapshot('order-ready-paid', $paid);
        $service->updateStatus($order, 'completed', $owner);
        $completed = $this->get('/orders/'.$order->id);
        $completed->assertDontSee('Cancel Order')->assertDontSee('name="unit_price"', false)->assertSee('TEST-REF-123')->assertDontSee('Order actions')->assertDontSee('id="staff-review-heading"', false)->assertSee('Review history');
        $this->snapshot('order-completed', $completed);
        $cancelledOrder = $service->createInternalOrder($data, $owner);
        $this->confirmPaymentFixture($cancelledOrder);
        $service->cancelOrder($cancelledOrder, $owner);
        $cancelled = $this->get('/orders/'.$cancelledOrder->id);
        $cancelled->assertSee('Cancelled')->assertDontSee('Order actions')->assertDontSee('id="staff-review-heading"', false)->assertDontSee('value="final_payment"', false)->assertDontSee('name="unit_price"', false);
        $this->snapshot('order-cancelled', $cancelled);
        foreach (['orders', 'dashboard', 'pickup-schedule', 'reports', 'expenses', 'customers', 'products', 'supplies', 'users'] as $page) {
            $this->snapshot($page, $this->get('/'.$page));
        }
        foreach (['customers/'.$customer->id, 'customers/'.$customer->id.'/edit', 'customers/create', 'orders/create', 'users/create', 'users/'.$owner->id.'/edit'] as $page) {
            $this->snapshot(str_replace('/', '-', $page), $this->get('/'.$page));
        }
    }

    public function test_public_status_guidance_and_empty_receipt_panels_follow_the_current_stage(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Stage', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Stage cake', 'price' => 1000, 'is_active' => true]);
        $service = app(OrderService::class);
        $order = $service->createPublicOrder(['customer_id' => $customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1]]]);
        $this->actingAs($owner);
        $this->get('/orders/'.$order->id)->assertSee('Confirm request')->assertDontSee('id="proof-review"', false);
        $this->confirmPaymentFixture($order);
        $this->get('/orders/'.$order->id)->assertSee('id="proof-review"', false)->assertDontSee('id="staff-review-heading"', false);
        // Verified public deposits are posted by receipt review; seed that completed step for presentation coverage.
        $order->payments()->create(['user_id' => $owner->id, 'amount' => $order->required_down_payment, 'payment_type' => 'down_payment', 'payment_method' => 'gcash', 'reference_number' => 'STAGE-DEPOSIT', 'payment_date' => now()]);
        $service->updateStatus($order, 'preparing', $owner);
        $this->get('/orders/'.$order->id)->assertDontSee('id="proof-review"', false)->assertSee('Review history');
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Preparing')->assertDontSee('pay the exact 50% deposit to secure your booking')->assertDontSee('After staff confirmation');
        $service->updateStatus($order, 'ready_for_pickup', $owner);
        $service->completePickup($order, 'cash', null, $owner, true);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('No further payment is due')->assertDontSee('After staff confirmation');
    }

    public function test_public_receipt_remains_unpaid_and_validation_preserves_selected_items(): void
    {
        $product = $this->catalogProduct(['product_name' => 'Birthday cake', 'price' => 1000, 'is_active' => true]);
        $this->snapshot('login', $this->get('/login'));
        $this->snapshot('public-empty', $this->get('/'));
        $data = ['first_name' => 'Maria', 'last_name' => 'Santos', 'expected_total' => 2000, 'phone_number' => '09171234567', 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 2, 'layers' => 2, 'themes' => 'Blue and gold', 'special_request' => 'Happy birthday!']]];
        $this->post('/order', $data)->assertRedirect(route('public.order.payment', \App\Models\Order::sole()->private_token));
        $success = $this->get(route('public.order.payment', \App\Models\Order::sole()->private_token));
        $success->assertSee('Awaiting staff confirmation')->assertDontSee('Business GCash payment QR')->assertSee('2,000.00')->assertSee('Blue and gold');
        $this->snapshot('public-success', $success);
        $this->post(route('public.order.continue'), ['items' => $data['items']])->assertRedirect(route('public.order.details'));
        $this->from(route('public.order.details'))->post('/order', array_merge($data, ['phone_number' => '123']))->assertSessionHasErrors('phone_number');
        $invalid = $this->get(route('public.order.details'));
        $invalid->assertSee('value="123"', false)->assertSee('validation-errors')->assertSee('Blue and gold', false);
        $this->snapshot('public-validation', $invalid);
    }

    public function test_staff_workspace_recommendations_presentation(): void
    {
        $owner = User::create([
            'first_name' => 'Workspace',
            'last_name' => 'Owner',
            'email' => 'workspace-owner@example.test',
            'password' => 'password123',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        // 1. Dashboard checks (sidebar-label, layout constraint, sticky header, active border, skeleton)
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertOk();

        // Recommendation 1: Sidebar category header hierarchy
        $dashboardResponse->assertSee('<span class="sidebar-label">Manage</span>', false);
        $dashboardResponse->assertSee('<span class="sidebar-label">Finance</span>', false);
        $dashboardResponse->assertSee('<span class="sidebar-label">Admin</span>', false);

        // Recommendation 2: Layout constraint inside main
        $dashboardResponse->assertSee('<div class="max-w-7xl mx-auto staff-content-container">', false);

        // Recommendation 3: Sticky header visual polish
        $dashboardResponse->assertSee('bg-white/80 backdrop-blur-md', false);

        // Recommendation 4: Active navigation accent border & rounded-r-lg
        $dashboardResponse->assertSee('border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold', false);
        $dashboardResponse->assertSee('border-l-[3px] border-transparent text-cocoa-100', false);
        $dashboardResponse->assertSee('rounded-r-lg rounded-l-none', false);

        // Recommendation 6: Dashboard skeleton screens
        $dashboardResponse->assertSee('<template id="dashboard-skeleton">', false);
        $dashboardResponse->assertSee('skeleton-pulse', false);

        // Nested sub-route activation verification: /supplies and /inventory/history
        $suppliesResponse = $this->get('/supplies');
        $suppliesResponse->assertOk();
        $this->assertStringContainsString('border-l-[3px] border-cocoa-300', $suppliesResponse->getContent());

        $historyResponse = $this->get('/inventory/history');
        $historyResponse->assertOk();
        $this->assertStringContainsString('border-l-[3px] border-cocoa-300', $historyResponse->getContent());

        // Recommendation 5 & CSS checks
        $css = file_get_contents(public_path('css/bakery-ui.css'));
        $this->assertStringContainsString('.sidebar-label', $css);
        $this->assertStringContainsString('.staff-content-container', $css);
        $this->assertStringContainsString('container-type: inline-size', $css);
        $this->assertStringContainsString('@container (max-width: 768px)', $css);
        $this->assertStringContainsString('@container (max-width: 420px)', $css);
        $this->assertStringContainsString('backdrop-filter: blur(8px)', $css);
        $this->assertStringContainsString('.skeleton', $css);
        $this->assertStringContainsString('@keyframes skeleton-pulse', $css);
    }

    public function test_stable_page_navigation_and_floating_row_actions_presentation(): void
    {
        $css = file_get_contents(public_path('css/bakery-ui.css'));
        $js = file_get_contents(public_path('js/bakery-ui.js'));

        // GET filters and page navigation must not morph changing content snapshots.
        $this->assertStringContainsString('@view-transition { navigation: none; }', $css);
        $this->assertStringNotContainsString('navigation: auto', $css);
        $this->assertStringNotContainsString('view-transition-name: main-content', $css);
        $this->assertStringContainsString('scrollbar-gutter: stable', $css);

        // Every retained font has a matching early preload and cannot swap after first paint.
        $typography = file_get_contents(public_path('css/bakery-typography.css'));
        $preloads = file_get_contents(resource_path('views/partials/ui-font-preloads.blade.php'));
        $this->assertStringNotContainsString('font-display:swap', $typography);
        $this->assertSame(5, substr_count($typography, 'font-display:optional'));
        foreach (['calistoga-400', 'dm-sans-400', 'dm-sans-500', 'dm-sans-600', 'dm-sans-700'] as $font) {
            $this->assertStringContainsString($font, $preloads);
            $this->assertFileExists(public_path('fonts/'.$font.'.ttf'));
        }
        $this->assertStringContainsString("@include('partials.ui-font-preloads')", file_get_contents(resource_path('views/partials/ui-assets.blade.php')));

        // Action buttons: floating popover dropdown instead of vertical inline expansion
        $this->assertStringContainsString('.row-actions {', $css);
        $this->assertStringContainsString('position: relative', $css);
        $this->assertStringContainsString('.row-actions > div {', $css);
        $this->assertStringContainsString('position: absolute', $css);
        $this->assertStringContainsString('z-index: 70', $css);
        $this->assertStringContainsString('.row-actions.drop-up', $css);
        $this->assertStringContainsString('@keyframes row-actions-enter', $css);

        // JS Popover management: click-outside, auto-flip detection, arrow navigation
        $this->assertStringContainsString("document.addEventListener('click'", $js);
        $this->assertStringContainsString('.row-actions[open]', $js);
        $this->assertStringContainsString('drop-up', $js);
        $this->assertStringContainsString('spaceAbove', $js);
        $this->assertStringContainsString("document.addEventListener('focusout'", $js);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $js);
        $this->assertStringContainsString("event.key === 'ArrowUp'", $js);
        // Action button enhancements: elevation, hover lift, active press, and Stock in iconography
        $this->assertStringContainsString('.ui-button.primary:hover { background:#2c1810; filter: brightness(1.1);', $css);
        $this->assertStringContainsString('.ui-button.primary:active { transform: translateY(0);', $css);
        $this->assertStringContainsString('.row-actions summary:hover', $css);
        $this->assertStringContainsString('.row-actions summary:active', $css);

        // Check Stock in button icon in supplies view
        $suppliesView = file_get_contents(resource_path('views/admin/supplies/index.blade.php'));
        $this->assertStringContainsString('<x-icon name="plus" /> Stock in', $suppliesView);
    }

    public function test_public_storefront_layout_and_quiet_button_presentation(): void
    {
        $css = file_get_contents(public_path('css/bakery-ui.css'));
        $layout = file_get_contents(resource_path('views/public/layout.blade.php'));
        $payment = file_get_contents(resource_path('views/public/payment.blade.php'));

        // 1. Quiet button styling: completely flat, no box-shadow, no translateY, subtle hover background
        $this->assertStringContainsString('.ui-button.quiet, .ui-button.subtle {', $css);
        $this->assertStringContainsString('box-shadow:none !important;', $css);
        $this->assertStringContainsString('.ui-button.quiet:hover, .ui-button.subtle:hover,', $css);
        $this->assertStringContainsString('background:transparent !important;', $css);
        $this->assertStringContainsString('.ui-button.quiet:active, .ui-button.subtle:active {', $css);

        // Primary button elevation and hover lift are preserved
        $this->assertStringContainsString('.ui-button.primary:hover { background:#2c1810; filter: brightness(1.1);', $css);
        $this->assertStringContainsString('.ui-button.primary:active { transform: translateY(0);', $css);

        // 2. Layout tightening in layout.blade.php
        $this->assertStringContainsString('id="main-content" tabindex="-1" class="flex-grow max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 py-6 sm:px-6 lg:px-8 2xl:px-12"', $layout);
        $this->assertStringContainsString('class="bg-cocoa-700 text-cocoa-100 py-8 px-4 sm:px-6 lg:px-8 2xl:px-12 mt-6"', $layout);

        // Layout tightening in bakery-ui.css
        $this->assertStringContainsString('body.public-store main#main-content { padding-top: 1.5rem; padding-bottom: 1.5rem; }', $css);
        $this->assertStringContainsString('.public-store footer { margin-top: 1.5rem;', $css);

        // 3. Order status page spacing and sticky sidebar
        $this->assertStringContainsString('class="public-order-status max-w-5xl mx-auto space-y-4"', $payment);
        $this->assertStringContainsString('class="lg:col-span-5 sidebar-column space-y-4 lg:sticky lg:top-20"', $payment);
        $this->assertStringContainsString('.public-order-status .sidebar-column,', $css);
        $this->assertStringContainsString('.public-order-status .lg\:col-span-5 {', $css);
        $this->assertStringContainsString('position: sticky;', $css);
        $this->assertStringContainsString('top: 5rem;', $css);
        $this->assertStringContainsString('align-self: start;', $css);

        // 4. Live HTTP rendering verification of storefront index, cart actions, and order status layout
        $product = $this->catalogProduct(['product_name' => 'Celebration Cake', 'price' => 1200, 'is_active' => true]);
        $storeResponse = $this->get('/');
        $storeResponse->assertOk()
            ->assertSee('id="main-content" tabindex="-1" class="flex-grow max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 py-6 sm:px-6 lg:px-8 2xl:px-12"', false)
            ->assertSee('class="bg-cocoa-700 text-cocoa-100 py-8 px-4 sm:px-6 lg:px-8 2xl:px-12 mt-6"', false)
            ->assertSee('Select package');

        // Add package to draft and verify quiet button ("Remove") and primary button ("Continue") in cart
        $this->post(route('public.order.continue'), [
            'items' => [[
                'product_id' => $product->id,
                'package_option_id' => $product->options()->first()->id,
                'quantity' => 1,
            ]],
        ])->assertRedirect(route('public.order.details'));

        $cartResponse = $this->get('/');
        $cartResponse->assertOk()
            ->assertSee('<button class="ui-button quiet">Remove</button>', false)
            ->assertSee('Add another package')
            ->assertSee('Continue to contact and pickup');

        // Create order and verify order status page HTTP rendering
        $customer = Customer::create(['first_name' => 'Elena', 'last_name' => 'Reyes', 'phone_number' => '09171234567']);
        $order = app(OrderService::class)->createPublicOrder([
            'customer_id' => $customer->id,
            'pickup_date' => now()->addDays(2)->toDateString(),
            'pickup_time' => '15:00',
            'items' => [[
                'product_id' => $product->id,
                'package_option_id' => $product->options()->first()->id,
                'quantity' => 1,
            ]],
        ]);

        $paymentResponse = $this->get(route('public.order.payment', $order->private_token));
        $paymentResponse->assertOk()
            ->assertSee('public-order-status max-w-5xl mx-auto space-y-4', false)
            ->assertSee('lg:col-span-7 space-y-4', false)
            ->assertSee('lg:col-span-5 sidebar-column space-y-4 lg:sticky lg:top-20', false)
            ->assertSee('mt-6', false);

        $customize = $this->get(route('public.package.customize', [$product, (string) \Illuminate\Support\Str::uuid()]));
        $customize->assertOk()
            ->assertSee('mobile-sticky-actions', false)
            ->assertSee('Save package to order');
    }

    public function test_tooltip_components_render_accessible_attributes_and_guidance(): void
    {
        // 1. Blade Component Unit Rendering & Accessibility Checks
        $renderedTooltip = Blade::render('<x-tooltip text="Helpful hint for staff" />');
        $this->assertStringContainsString('x-data="{ show: false }"', $renderedTooltip);
        $this->assertStringContainsString('role="tooltip"', $renderedTooltip);
        $this->assertStringContainsString('aria-describedby="tooltip-', $renderedTooltip);
        $this->assertStringContainsString('@focusin="show = true"', $renderedTooltip);
        $this->assertStringContainsString('@focusout="show = false"', $renderedTooltip);
        $this->assertStringContainsString('@mouseenter="show = true"', $renderedTooltip);
        $this->assertStringContainsString('@mouseleave="show = false"', $renderedTooltip);
        $this->assertStringContainsString('@click.prevent.stop="show = !show"', $renderedTooltip);
        $this->assertStringContainsString('@click.outside="show = false"', $renderedTooltip);
        $this->assertStringContainsString('bg-cocoa-800 text-white rounded-lg p-2.5 text-xs shadow-lg', $renderedTooltip);
        $this->assertStringContainsString('max-w-[calc(100vw-2rem)]', $renderedTooltip);
        $this->assertStringContainsString('Helpful hint for staff', $renderedTooltip);

        $this->assertStringContainsString('x-id="[\'tooltip\']"', $renderedTooltip);
        $this->assertStringContainsString(':aria-describedby="$id(\'tooltip\')"', $renderedTooltip);
        $this->assertStringContainsString(':id="$id(\'tooltip\')"', $renderedTooltip);

        // Status component auto-tooltip generation with standard hyphen normalization
        $renderedPendingStatus = Blade::render('<x-status value="pending" />');
        $this->assertStringContainsString('role="tooltip"', $renderedPendingStatus);
        $this->assertStringContainsString('Order is awaiting initial review and confirmation by bakery staff', $renderedPendingStatus);

        $renderedHyphenStatus = Blade::render('<x-status value="confirmed" label="Confirmed - awaiting deposit" />');
        $this->assertStringContainsString('role="tooltip"', $renderedHyphenStatus);
        $this->assertStringContainsString('awaiting 50% deposit from customer to lock schedule', $renderedHyphenStatus);

        $renderedStockStatus = Blade::render('<x-status value="low_stock" />');
        $this->assertStringContainsString('role="tooltip"', $renderedStockStatus);
        $this->assertStringContainsString('Current stock is at or below the reorder point', $renderedStockStatus);

        // 2. Public Storefront Targets
        $product = $this->catalogProduct(['product_name' => 'Caramel Crunch Cake', 'price' => 1500, 'is_active' => true]);

        // Add package to draft and load details
        $this->post(route('public.order.continue'), [
            'items' => [[
                'product_id' => $product->id,
                'package_option_id' => $product->options()->first()->id,
                'quantity' => 1,
            ]],
        ])->assertRedirect(route('public.order.details'));

        $detailsResponse = $this->get(route('public.order.details'));
        $detailsResponse->assertOk()
            ->assertSee('Exact 50% Deposit')
            ->assertSee('A 50% deposit is required to reserve your order once staff confirms availability', false)
            ->assertSee('Complimentary items (e.g. candles, toppers, or boxes) bundled with this package at no extra charge.', false);

        // Package line fields guidance check
        $customizeResponse = $this->get(route('public.package.customize', [$product, (string) \Illuminate\Support\Str::uuid()]));
        $customizeResponse->assertOk()
            ->assertSee('Attach cake inspiration or design sketches (up to 5 photos, 5 MB each). These guide our cake decorators and are not payment receipts.', false);

        // Public order payment page check
        $customer = Customer::create(['first_name' => 'Clara', 'last_name' => 'Batumbakal', 'phone_number' => '09181234567']);
        $order = app(OrderService::class)->createPublicOrder([
            'customer_id' => $customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => [[
                'product_id' => $product->id,
                'package_option_id' => $product->options()->first()->id,
                'quantity' => 1,
            ]],
        ]);

        $paymentResponse = $this->get(route('public.order.payment', $order->private_token));
        $paymentResponse->assertOk()
            ->assertSee('Exact 50% Deposit')
            ->assertSee('The 50% deposit locks in your baking schedule', false)
            ->assertSee('Private order link')
            ->assertSee('Bookmark or copy this unique private link to track your order status and upload receipts without needing an account.', false);

        // 3. Admin Workspace Targets
        $owner = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Owner',
            'email' => 'admin-tooltip@example.test',
            'password' => 'password123',
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->actingAs($owner);

        // Admin Orders Index (unified status tabs)
        $ordersResponse = $this->get('/orders');
        $ordersResponse->assertOk()
            ->assertDontSee('Order workflow queues')
            ->assertSee('Awaiting Deposit')
            ->assertSee('Receipt Verification')
            ->assertSee('Pending Review')
            ->assertSee('Confirmed');

        // Date range filtering on orders index
        $filteredResponse = $this->get('/orders?date_from=' . now()->addDays(2)->toDateString() . '&date_to=' . now()->addDays(4)->toDateString());
        $filteredResponse->assertOk()->assertSee($order->order_number);
        $excludedResponse = $this->get('/orders?date_from=' . now()->addDays(5)->toDateString() . '&date_to=' . now()->addDays(7)->toDateString());
        $excludedResponse->assertOk()->assertDontSee($order->order_number);

        // Admin Orders Show (origin badge)
        $showResponse = $this->get('/orders/' . $order->id);
        $showResponse->assertOk()
            ->assertSee('Public Web Order')
            ->assertSee('Order submitted online by customer via public storefront.', false);

        // Admin Proof Review (receipt audit checkbox)
        $this->confirmPaymentFixture($order);
        $proof = $order->paymentProofs()->create([
            'reference_number' => '123456789012',
            'status' => 'awaiting_verification',
            'file_path' => 'proofs/test.jpg',
        ]);
        $proofResponse = $this->get('/orders/' . $order->id);
        $proofResponse->assertOk()
            ->assertSee('I checked the successful incoming transaction in the business GCash account, including the exact amount and reference.', false);

        // Admin Supplies Index (stock level badge and header tooltip)
        $suppliesResponse = $this->get('/supplies');
        $suppliesResponse->assertOk()
            ->assertSee('Stock status')
            ->assertSee('Availability uses combined usable stock. Expired and unknown ingredient entries stay on hand.', false);

        // Admin Supplies Operation Form (stock reload concurrency tooltip and recorded column header tooltip)
        $operationResponse = $this->get(route('inventory.create', 'stocktake'));
        $operationResponse->assertOk()
            ->assertSee('Count every remaining entry, including expired and unknown stock.', false)
            ->assertSee('Reload stock', false);

        // Admin Reports Index (financial metrics tooltips)
        $reportsResponse = $this->get('/reports');
        $reportsResponse->assertOk()
            ->assertSee('Total invoiced value of orders with Completed status during this period.', false)
            ->assertSee('All verified cash and GCash payments received and ledgered in this period.', false)
            ->assertSee('Completed sales + retained cancellation deposits', false);
    }

    public function test_paid_extras_and_included_items_render_images_on_order_details(): void
    {
        $owner = User::create(['first_name' => 'Admin', 'last_name' => 'Owner', 'email' => 'owner_extras@example.test', 'password' => 'password123', 'role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Teresa', 'last_name' => 'Magbanua', 'phone_number' => '09179998888']);
        $product = $this->catalogProduct(['product_name' => 'Fiesta Cake', 'price' => 1500, 'is_active' => true]);
        $extra = \App\Models\AddOn::create([
            'name' => 'Special Cupcakes',
            'description' => 'Box of 6 cupcakes',
            'price' => 250,
            'photo_path' => 'catalog/add-ons/cupcakes_test.jpg',
            'is_active' => true,
        ]);
        $product->addOns()->attach($extra);

        $order = app(OrderService::class)->createInternalOrder([
            'customer_id' => $customer->id,
            'pickup_date' => now()->addDays(2)->toDateString(),
            'pickup_time' => '15:00',
            'items' => [[
                'product_id' => $product->id,
                'package_option_id' => $product->options()->first()->id,
                'quantity' => 1,
                'add_ons' => [[
                    'add_on_id' => $extra->id,
                    'quantity' => 2,
                ]],
            ]],
        ], $owner);

        $this->actingAs($owner);
        $response = $this->get(route('orders.show', $order));

        $response->assertOk()
            ->assertSee('Paid extra:')
            ->assertSee('Special Cupcakes')
            ->assertSee('catalog/add-ons/cupcakes_test.jpg')
            ->assertSee('₱500.00')
            ->assertSee('₱250.00 each');
    }

    public function test_inventory_detail_sliding_drawer_presentation_and_endpoint(): void
    {
        $owner = User::create(['first_name' => 'Baker', 'last_name' => 'Owner', 'email' => 'drawer_test@example.test', 'password' => 'password123', 'role' => 'owner', 'is_active' => true]);
        $supply = \App\Models\Supply::create([
            'supply_name' => 'Dutch Cocoa Powder',
            'category' => 'ingredients',
            'unit' => 'kg',
            'current_quantity' => 15.5,
            'reorder_level' => 3.0,
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        // 1. Check supplies index view renders sliding drawer elements without backdrop blur
        $indexResponse = $this->get('/supplies');
        $indexResponse->assertOk()
            ->assertSee('data-detail-drawer', false)
            ->assertSee('openSupplyDetail', false)
            ->assertSee('bg-black/25', false)
            ->assertSee('!$event.ctrlKey && !$event.metaKey && $event.button === 0', false)
            ->assertDontSee('data-detail-drawer fixed inset-0 bg-black/40 backdrop-blur', false);

        // 2. Check JSON show endpoint for slide-over drawer async loading
        $jsonResponse = $this->getJson(route('supplies.show', $supply));
        $jsonResponse->assertOk()
            ->assertJsonPath('supply.supply_name', 'Dutch Cocoa Powder')
            ->assertJsonPath('supply.formatted_quantity', '15.50')
            ->assertJsonPath('supply.formatted_reorder_level', '3.00')
            ->assertJsonPath('supply.unit', 'kg')
            ->assertJsonPath('routes.edit', route('supplies.edit', $supply))
            ->assertJsonPath('routes.history', route('inventory.history', ['supply_id' => $supply->id]));
    }
}

