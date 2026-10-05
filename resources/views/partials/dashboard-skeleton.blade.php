<div class="space-y-6" aria-hidden="true">
    {{-- Metric Cards Skeleton --}}
    <div class="metrics">
        @for ($i = 0; $i < 4; $i++)
            <div class="bg-white border border-cocoa-100 rounded-xl p-5 block">
                <div class="skeleton skeleton-pulse w-28 h-3"></div>
                <div class="skeleton skeleton-pulse w-14 h-8 mt-2"></div>
                <div class="skeleton skeleton-pulse w-40 h-3 mt-2"></div>
            </div>
        @endfor
    </div>

    {{-- Two-Column Grid Skeleton --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Today's Pickups Skeleton --}}
        <div class="bg-white border border-cocoa-100 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-cocoa-100">
                <div class="skeleton skeleton-pulse w-36 h-4"></div>
                <div class="skeleton skeleton-pulse w-14 h-3"></div>
            </div>
            @for ($i = 0; $i < 3; $i++)
                <div class="flex items-center justify-between py-2 border-b border-cocoa-100/60 last:border-0">
                    <div class="space-y-1.5">
                        <div class="skeleton skeleton-pulse w-24 h-4"></div>
                        <div class="skeleton skeleton-pulse w-32 h-3"></div>
                    </div>
                    <div class="skeleton skeleton-pulse w-16 h-5 rounded-full"></div>
                </div>
            @endfor
        </div>

        {{-- Recent Orders Skeleton --}}
        <div class="lg:col-span-2 bg-white border border-cocoa-100 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-cocoa-100">
                <div class="skeleton skeleton-pulse w-32 h-4"></div>
                <div class="skeleton skeleton-pulse w-20 h-3"></div>
            </div>
            <div class="space-y-3">
                <div class="skeleton skeleton-pulse w-full h-8 rounded-lg"></div>
                @for ($i = 0; $i < 4; $i++)
                    <div class="skeleton skeleton-pulse w-full h-10 rounded-lg"></div>
                @endfor
            </div>
        </div>
    </div>
</div>
