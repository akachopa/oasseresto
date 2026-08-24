<?php

declare(strict_types=1);

namespace App\Modules\Core\Livewire;

use App\Modules\Core\Support\GlobalSearchIndex;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pencarian global Ctrl/Cmd + K (PLAN 47).
 */
class GlobalSearch extends Component
{
    public bool $open = false;

    public string $term = '';

    #[On('open-global-search')]
    public function openPalette(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->term = '';
    }

    public function render()
    {
        return view('core.livewire.global-search', [
            'groups' => strlen($this->term) >= 2
                ? app(GlobalSearchIndex::class)->search($this->term)
                : [],
        ]);
    }
}
