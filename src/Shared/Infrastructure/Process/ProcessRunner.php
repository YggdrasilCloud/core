<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Process;

use RuntimeException;

use function fclose;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_get_contents;
use function stream_set_blocking;
use function strlen;
use function substr;
use function usleep;

/**
 * Execute shell commands with timeout support.
 *
 * Provides a safe way to run external processes with:
 * - Configurable timeout (prevents hanging)
 * - Captured stdout/stderr output (capped to prevent memory exhaustion)
 * - Proper resource cleanup
 * - Graceful termination (SIGTERM then SIGKILL)
 */
class ProcessRunner
{
    private const int DEFAULT_TIMEOUT_SECONDS = 5;
    private const int POLL_INTERVAL_MICROSECONDS = 20000; // 20ms
    private const int MAX_OUTPUT_BYTES = 262144; // 256 KiB per stream

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

            // Collect output with cap
            $stdoutChunk = stream_get_contents($pipes[1]) ?: '';
            $stderrChunk = stream_get_contents($pipes[2]) ?: '';

            if ($stdoutChunk !== '') {
                $stdout = $this->appendCapped($stdout, $stdoutChunk);
            }
            if ($stderrChunk !== '') {
                $stderr = $this->appendCapped($stderr, $stderrChunk);
            }

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
        $stdout = $this->appendCapped($stdout, stream_get_contents($pipes[1]) ?: '');
        $stderr = $this->appendCapped($stderr, stream_get_contents($pipes[2]) ?: '');

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
        // First SIGTERM for graceful shutdown
        proc_terminate($process, 15);
        usleep(100000); // 100ms grace period

        // Then SIGKILL if still running
        proc_terminate($process, 9);

        // Close all pipes
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    /**
     * Append chunk to buffer, keeping only the last MAX_OUTPUT_BYTES.
     * Keeps the end (most useful for debugging errors).
     */
    private function appendCapped(string $buffer, string $chunk): string
    {
        if ($chunk === '') {
            return $buffer;
        }

        $buffer .= $chunk;

        if (strlen($buffer) <= self::MAX_OUTPUT_BYTES) {
            return $buffer;
        }

        // Keep the tail (most recent output)
        return substr($buffer, -self::MAX_OUTPUT_BYTES);
    }
}
