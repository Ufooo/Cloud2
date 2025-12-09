<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum IdentityColor: string
{
    case Blue = 'blue';
    case Green = 'green';
    case Orange = 'orange';
    case Purple = 'purple';
    case Red = 'red';
    case Yellow = 'yellow';
    case Cyan = 'cyan';
    case Gray = 'gray';
}
