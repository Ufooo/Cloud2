<?php

namespace Nip\Network\Data;

use Nip\Network\Enums\RuleStatus;
use Nip\Network\Enums\RuleType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FirewallRuleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $port,
        public ?string $ipAddress,
        public RuleType $type,
        public RuleStatus $status,
        public string $displayableType,
        public string $displayableStatus,
    ) {}
}
