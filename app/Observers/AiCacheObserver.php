<?php

namespace App\Observers;

use App\Services\AiChatService;
use Illuminate\Database\Eloquent\Model;

class AiCacheObserver
{
    public function created(Model $model): void  { AiChatService::invalidateCache(); }
    public function updated(Model $model): void  { AiChatService::invalidateCache(); }
    public function deleted(Model $model): void  { AiChatService::invalidateCache(); }
    public function restored(Model $model): void { AiChatService::invalidateCache(); }
}
