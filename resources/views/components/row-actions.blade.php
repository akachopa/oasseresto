@props(['detail' => null, 'edit' => null, 'delete' => null, 'extra' => []])

<div class="flex items-center justify-end gap-1">
    @foreach ($extra as $item)
        <a href="{{ $item['url'] }}" title="{{ $item['label'] }}"
           class="btn-icon text-muted hover:bg-panel-soft hover:text-brand-600">
            <x-icon :name="$item['icon'] ?? 'eye'" class="h-4 w-4" />
        </a>
    @endforeach

    @if ($detail)
        <a href="{{ $detail }}" title="Detail" class="btn-icon text-muted hover:bg-panel-soft hover:text-brand-600">
            <x-icon name="eye" class="h-4 w-4" />
        </a>
    @endif

    @if ($edit)
        <a href="{{ $edit }}" title="Ubah" class="btn-icon text-muted hover:bg-panel-soft hover:text-brand-600">
            <x-icon name="pencil" class="h-4 w-4" />
        </a>
    @endif

    @if ($delete)
        <form method="POST" action="{{ $delete }}" class="inline"
              onsubmit="return confirm('Hapus data ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" title="Hapus" class="btn-icon text-muted hover:bg-negative/10 hover:text-negative">
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        </form>
    @endif
</div>
