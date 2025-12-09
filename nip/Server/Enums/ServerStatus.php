<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ServerStatus: string
{
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Deleting = 'deleting';
    case Provisioning = 'provisioning';
    case Locked = 'locked';
    case Resizing = 'resizing';
    case Stopping = 'stopping';
    case Off = 'off';
    case Unknown = 'unknown';
}
