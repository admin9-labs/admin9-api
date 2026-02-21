<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Context;

abstract class BaseAuditEvent
{
    use Dispatchable;

    public readonly ?string $ip;

    public function __construct(
        public readonly string $action,
        public readonly ?int $userId,
        public readonly array $metadata = [],
        ?string $ip = null,
    ) {
        $this->ip = $ip ?? Context::get('ip');
    }

    public function toLogContext(): array
    {
        return array_filter([
            'action' => $this->action,
            'user_id' => $this->userId,
            'ip' => $this->ip,
            ...$this->metadata,
        ], fn ($value) => $value !== null);
    }
}
