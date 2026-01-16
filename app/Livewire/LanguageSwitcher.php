<?php

namespace App\Livewire;

use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $currentLocale;

    public function mount(): void
    {
        $this->currentLocale = app()->getLocale();
    }

    public function updatedCurrentLocale(): void
    {
        $this->currentLocale = app()->getLocale();
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
