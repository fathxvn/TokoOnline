@if ($paginator->hasPages())
    <nav aria-label="Halaman produk">
        <ul class="pagination">

            {{-- Halaman sebelumnya --}}
            @if ($paginator->onFirstPage())
                <li class="disabled">
                    <span aria-label="Sebelumnya">&laquo;</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}"
                        rel="prev" aria-label="Sebelumnya">
                        &laquo;
                    </a>
                </li>
            @endif

            {{-- Nomor halaman --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="disabled">
                        <span>{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="active" aria-current="page">
                                <span>{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Halaman berikutnya --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}"
                        rel="next" aria-label="Berikutnya">
                        &raquo;
                    </a>
                </li>
            @else
                <li class="disabled">
                    <span aria-label="Berikutnya">&raquo;</span>
                </li>
            @endif

        </ul>
    </nav>
@endif