<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasTitleImage
{
    public function titleImageUrl(): ?string
    {
        if (! $this->title_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->title_image_path);
    }
}
