<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->getAttribute('name');
    }

    public function getSubheading(): ?string
    {
        return '@'.$this->getRecord()->getAttribute('handle');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [UserResource::impersonateAction()];
    }
}
