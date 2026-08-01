{{--
    TAGTOA — liens des modules directement dans le menu latéral Biztap (au lieu
    du bouton flottant séparé, jugé peu clair). Inclus depuis l'override
    Modules/Tagtoa/resources/views/overrides/layouts/sidebar.blade.php, juste
    après @include('layouts.menu') — ne touche donc JAMAIS le menu Biztap natif.
    Même structure/markup que layouts/menu.blade.php (nav-item / nav-link /
    aside-menu-icon / aside-menu-title) pour un rendu visuel identique.
--}}
<li class="nav-item tagtoa-menu-divider">
    <span class="aside-menu-title text-uppercase text-muted" style="font-size:11px;padding-left:1rem;letter-spacing:.05em">TAGTOA</span>
</li>

@php
    $tagtoaMenuItems = [
        ['route' => 'tagtoa.hub', 'icon' => 'fa-solid fa-layer-group', 'color' => 'icon-color-bs-blue', 'label' => __('TAGTOA (aperçu)')],
        ['route' => 'tagtoa.site.dashboard.index', 'icon' => 'fa-solid fa-globe', 'color' => 'icon-color-bs-blue', 'label' => __('Site web')],
        ['route' => 'tagtoa.menu.dashboard.index', 'icon' => 'fa-solid fa-utensils', 'color' => 'icon-color-bs-orange', 'label' => __('Menu')],
        ['route' => 'tagtoa.pay.dashboard.index', 'icon' => 'fa-solid fa-wallet', 'color' => 'icon-color-bs-green', 'label' => __('Pay')],
        ['route' => 'tagtoa.loyalty.dashboard.index', 'icon' => 'fa-solid fa-star', 'color' => 'icon-color-bs-darkyellow', 'label' => __('Fidélité')],
        ['route' => 'tagtoa.links.dashboard.index', 'icon' => 'fa-solid fa-link', 'color' => 'icon-color-bs-purple', 'label' => __('Links')],
        ['route' => 'tagtoa.event.dashboard.index', 'icon' => 'fa-solid fa-ticket', 'color' => 'icon-color-bs-red', 'label' => __('Event')],
        ['route' => 'tagtoa.booking.dashboard.index', 'icon' => 'fa-solid fa-calendar-check', 'color' => 'icon-color-bs-teal', 'label' => __('Rendez-vous')],
        ['route' => 'tagtoa.pos.index', 'icon' => 'fa-solid fa-cash-register', 'color' => 'icon-color-bs-pink', 'label' => __('Caisse (POS)')],
        ['route' => 'tagtoa.cards.index', 'icon' => 'fa-solid fa-credit-card', 'color' => 'icon-color-bs-lightred', 'label' => __('Carte TAGTOA')],
        ['route' => 'tagtoa.billing.index', 'icon' => 'fa-solid fa-chart-line', 'color' => 'icon-color-bs-peach', 'label' => __('Revenus')],
    ];
@endphp

@foreach ($tagtoaMenuItems as $item)
    @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
    <li class="nav-item {{ request()->routeIs($item['route'].'*') ? 'active' : '' }}">
        <a class="nav-link d-flex align-items-center py-3 gap-3" aria-current="page" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-trigger="hover" title="{{ $item['label'] }}" href="{{ route($item['route']) }}">
            <span class="aside-menu-icon"><i class="{{ $item['icon'] }} {{ $item['color'] }}"></i></span>
            <span class="aside-menu-title">{{ $item['label'] }}</span>
        </a>
    </li>
@endforeach
