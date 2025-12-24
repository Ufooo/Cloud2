<?php

namespace Nip\Server\Services\SSH;

class ExecutionResult
{
    public function __construct(
        public readonly string $output,
        public readonly int $exitCode,
        public readonly float $duration
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->isSuccessful();
    }

    public function getOutputLines(): array
    {
        return explode("\n", $this->output);
    }

    public function getTrimmedOutput(): string
    {
        return trim($this->output);
    }

    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'exit_code' => $this->exitCode,
            'duration' => $this->duration,
            'successful' => $this->isSuccessful(),
        ];
    }
}
