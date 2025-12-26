<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Process;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

use function sprintf;

/**
 * Execute shell commands with timeout support.
 *
 * Thin wrapper around Symfony Process providing:
 * - Configurable timeout (prevents hanging)
 * - Captured stdout/stderr output
 * - Consistent API via ProcessResult
 */
final readonly class ProcessRunner
{
    private const int DEFAULT_TIMEOUT_SECONDS = 5;

    /**
     * Run a command with timeout.
     *
     * @param string $command        Shell command to execute
     * @param int    $timeoutSeconds Maximum execution time (default: 5s)
     *
     * @return ProcessResult Result containing exit code and output
     *
     * @throws RuntimeException If process times out
     */
    public function run(string $command, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): ProcessResult
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException(
                sprintf('Command timed out after %ds: %s', $timeoutSeconds, $command),
                0,
                $e
            );
        }

        return new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }

    /**
     * Run a command from array arguments (safer, no shell escaping needed).
     *
     * @param string[] $arguments      Command and arguments as array
     * @param int      $timeoutSeconds Maximum execution time (default: 5s)
     *
     * @return ProcessResult Result containing exit code and output
     *
     * @throws RuntimeException If process times out
     */
    public function runArray(array $arguments, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): ProcessResult
    {
        $process = new Process($arguments);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException(
                sprintf('Command timed out after %ds: %s', $timeoutSeconds, implode(' ', $arguments)),
                0,
                $e
            );
        }

        return new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
