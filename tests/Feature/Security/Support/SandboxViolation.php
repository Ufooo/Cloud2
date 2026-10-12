<?php

namespace Tests\Feature\Security\Support;

use RuntimeException;

/**
 * The script under test tried to reach outside its throw-away directory, or it was refused before it ran.
 */
final class SandboxViolation extends RuntimeException {}
