<?php

// app/Forms/Components/ImageSelector.php
namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class ImageSelector extends Field
{
    protected string $view = 'forms.components.image-selector';

    protected array $images = [];
    protected ?string $selectedLabel = 'Seleccionada';

    public function images(array $images): static
    {
        $this->images = $images;
        return $this;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function selectedLabel(string $label): static
    {
        $this->selectedLabel = $label;
        return $this;
    }

    public function getSelectedLabel(): string
    {
        return $this->selectedLabel;
    }
}
