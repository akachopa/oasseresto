@props([
    'id',
    'url',
    'columns',
    'order' => null,
])

<div class="card overflow-hidden">
    @isset($filters)
        <div class="border-hairline flex flex-wrap items-end gap-3 border-b px-4 py-3">
            {{ $filters }}
        </div>
    @endisset

    <div class="oasse-table-wrap px-2 py-1">
        <table id="{{ $id }}" class="table-oasse" style="width:100%"
               data-oasse-table
               data-url="{{ $url }}"
               data-columns='@json($columns)'
               @if ($order) data-order='@json($order)' @endif>
        </table>
    </div>
</div>
