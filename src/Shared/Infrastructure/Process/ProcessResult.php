<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Process;

/**
 * Result of a process execution.
 */
final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Get combined stdout and stderr output.
     */
    public function getOutput(): string
    {
        $output = $this->stdout;
        if ($this->stderr !== '') {
            $output .= ($output !== '' ? "\n" : '').$this->stderr;
        }

        return $output;
    }
}
