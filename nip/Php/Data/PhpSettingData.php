<?php

namespace Nip\Php\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PhpSettingData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('max_upload_size')]
        public ?int $maxUploadSize,
        #[MapInputName('max_execution_time')]
        public ?int $maxExecutionTime,
        #[MapInputName('opcache_enabled')]
        public bool $opcacheEnabled,
    ) {}
}
