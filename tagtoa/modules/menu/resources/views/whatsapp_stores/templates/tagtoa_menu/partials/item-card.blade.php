{{--
    TAGTOA MENU — Item Card Partial
    Included by tagtoa-menu-index.blade.php for every product in a category.

    Expects: $product (WhatsappStoreProduct), $whatsappStore
--}}
@php
    $isAvailable = $product->is_available ?? true;
    $hasDiscount = !empty($product->discount_price) && $product->discount_price < $product->selling_price;
    $displayPrice = $hasDiscount ? $product->discount_price : $product->selling_price;
    $currencyCode = $product->currency->currency_code ?? '';
    $img = $product->images_url[0] ?? asset('assets/images/default_service.png');

    $dineIn   = $product->dine_in   ?? true;
    $takeout  = $product->takeout   ?? true;
    $delivery = $product->delivery  ?? false;
@endphp

<a href="{{ route('whatsapp.store.product.details', [$whatsappStore->url_alias, $product->id]) }}"
   class="tm-item {{ !$isAvailable ? 'sold-out' : '' }}"
   data-dine_in="{{ $dineIn ? 1 : 0 }}"
   data-takeout="{{ $takeout ? 1 : 0 }}"
   data-delivery="{{ $delivery ? 1 : 0 }}"
   style="text-decoration:none;color:inherit">

    <img src="{{ $img }}" alt="{{ $product->name }}" class="tm-item-img" loading="lazy">

    <div class="tm-item-body">
        <div>
            <div class="tm-item-top">
                <div class="tm-item-name">
                    @if (!empty($product->featured))
                        <i class="fa-solid fa-star tm-featured-icon"></i>
                    @endif
                    {{ $product->name }}
                </div>
            </div>

            @if ($product->description)
                <div class="tm-item-desc">{{ strip_tags($product->description) }}</div>
            @endif

            <div class="tm-item-meta">
                @if (!$isAvailable)
                    <span class="tm-tag sold-out-tag"><i class="fa-solid fa-ban"></i> {{ __('messages.menu.sold_out') ?? 'Sold out' }}</span>
                @endif

                @if (!empty($product->prep_time))
                    <span class="tm-tag"><i class="fa-regular fa-clock"></i> {{ $product->prep_time }} min</span>
                @endif

                @if ($dineIn)
                    <span class="tm-tag"><i class="fa-solid fa-utensils"></i> Dine-in</span>
                @endif

                @if ($takeout)
                    <span class="tm-tag"><i class="fa-solid fa-bag-shopping"></i> Takeout</span>
                @endif

                @if ($delivery)
                    <span class="tm-tag"><i class="fa-solid fa-motorcycle"></i> Delivery</span>
                @endif
            </div>
        </div>

        <div class="tm-item-bottom">
            <div class="tm-item-price-row">
                <span class="tm-item-price {{ $hasDiscount ? 'discounted' : '' }}">
                    {{ currencyFormat($displayPrice, 2, $currencyCode) }}
                </span>
                @if ($hasDiscount)
                    <span class="tm-item-price-old">{{ currencyFormat($product->selling_price, 2, $currencyCode) }}</span>
                @endif
            </div>

            <button class="tm-add-btn"
                    data-id="{{ $product->id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $displayPrice }}"
                    data-img="{{ $img }}"
                    {{ !$isAvailable ? 'disabled' : '' }}
                    aria-label="Add {{ $product->name }} to cart">
                <i class="fa-solid {{ $isAvailable ? 'fa-plus' : 'fa-ban' }}"></i>
            </button>
        </div>
    </div>
</a>
