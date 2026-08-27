{{--
    Pagination, in this product's own vocabulary.

    Laravel ships Tailwind markup by default, and this codebase has no Tailwind
    — so the shipped views rendered as a column of unstyled links. This is the
    same control drawn from tokens.css like everything else, which is also why
    it follows the theme instead of ignoring it.

    A window of pages rather than every page: at 4,500 leads the default view
    ran to hundreds of numbers.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        <div class="pager__count">
            Showing <b>{{ $paginator->firstItem() }}</b>–<b>{{ $paginator->lastItem() }}</b>
            of <b>{{ number_format($paginator->total()) }}</b>
        </div>

        <div class="pager__nav">
            @if ($paginator->onFirstPage())
                <span class="pager__btn is-off" aria-disabled="true">
                    <span aria-hidden="true">‹</span><span>Previous</span>
                </span>
            @else
                <button type="button" class="pager__btn"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        rel="prev">
                    <span aria-hidden="true">‹</span><span>Previous</span>
                </button>
            @endif

            <div class="pager__pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pager__gap" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pager__page is-on" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" class="pager__page"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')">
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <button type="button" class="pager__btn"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        rel="next">
                    <span>Next</span><span aria-hidden="true">›</span>
                </button>
            @else
                <span class="pager__btn is-off" aria-disabled="true">
                    <span>Next</span><span aria-hidden="true">›</span>
                </span>
            @endif
        </div>
    </nav>
@endif
