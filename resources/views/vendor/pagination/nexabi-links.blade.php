{{--
    The same control for a plain (non-Livewire) paginator, where paging is a
    link rather than a component call.
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
                <a class="pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    <span aria-hidden="true">‹</span><span>Previous</span>
                </a>
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
                                <a class="pager__page" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    <span>Next</span><span aria-hidden="true">›</span>
                </a>
            @else
                <span class="pager__btn is-off" aria-disabled="true">
                    <span>Next</span><span aria-hidden="true">›</span>
                </span>
            @endif
        </div>
    </nav>
@endif
