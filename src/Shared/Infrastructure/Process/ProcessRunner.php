<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Process;

use RuntimeException;

use function is_resource;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_get_contents;
use function stream_set_blocking;
use function usleep;

/**
 * Execute shell commands with timeout support.
 *
 * Provides a safe way to run external processes with:
 * - Configurable timeout (prevents hanging)
 * - Captured stdout/stderr output
 * - Proper resource cleanup
 */
final class ProcessRunner
{
    private const int DEFAULT_TIMEOUT_SECONDS = 5;
    private const int POLL_INTERVAL_MICROSECONDS = 20000; // 20ms

    /**
     * Run a command with timeout.
     *
     * @param string $command        Shell command to execute
     * @param int    $timeoutSeconds Maximum execution time (default: 5s)
     *
     * @return ProcessResult Result containing exit code and output
     *
     * @throws RuntimeException If process cannot be started or times out
     */
    public function run(string $command, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): ProcessResult
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process: '.$command);
        }

        // Close stdin - we don't send any input
        fclose($pipes[0]);

        // Set non-blocking mode for output streams
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startTime = microtime(true);

        while (true) {
            $status = proc_get_status($process);

            // Collect output
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (!$status['running']) {
                break;
            }

            // Check timeout
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $timeoutSeconds) {
                $this->terminateProcess($process, $pipes);

                throw new RuntimeException(sprintf(
                    'Command timed out after %ds: %s',
                    $timeoutSeconds,
                    $command
                ));
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        // Collect any remaining output
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';

        // Close pipes
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult($exitCode, $stdout, $stderr);
    }

    /**
     * Terminate a running process and clean up resources.
     *
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private function terminateProcess($process, array $pipes): void
    {
        // Send SIGKILL
        proc_terminate($process, 9);

        // Close all pipes
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }
}
