<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Pagination in this product's own vocabulary.
         *
         * Laravel ships Tailwind markup by default and this codebase has no
         * Tailwind, so the shipped views rendered as a bare column of links —
         * unstyled, ignoring the theme, and running to hundreds of page numbers
         * on a 4,500-row table.
         *
         * A window of pages rather than all of them, for the same reason.
         */
        Paginator::defaultView('vendor.pagination.nexabi');
        Paginator::defaultSimpleView('vendor.pagination.nexabi');
    }
}
