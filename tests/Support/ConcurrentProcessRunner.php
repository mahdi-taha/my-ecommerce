<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class ConcurrentProcessRunner
{
    private string $barrierDirectory;

    public function __construct(private readonly int $timeoutSeconds = 30)
    {
        $this->barrierDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ecommerce-concurrency-'.bin2hex(random_bytes(12));

        if (! mkdir($this->barrierDirectory, 0700, true) && ! is_dir($this->barrierDirectory)) {
            throw new RuntimeException('The concurrency barrier directory could not be created.');
        }
    }

    /**
     * @param  list<array{action: string, payload: array<string, mixed>}>  $workers
     * @return list<array<string, mixed>>
     */
    public function run(array $workers): array
    {
        $processes = [];

        try {
            foreach ($workers as $index => $worker) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql-concurrency-worker.php'),
                    $worker['action'],
                    base64_encode(json_encode($worker['payload'], JSON_THROW_ON_ERROR)),
                    $this->barrierDirectory,
                    (string) $index,
                ], base_path(), $this->workerEnvironment());
                $process->setTimeout($this->timeoutSeconds);
                $process->start();
                $processes[] = $process;
            }

            $this->waitUntil(fn () => collect(array_keys($workers))->every(
                fn (int $index) => is_file($this->barrierDirectory.DIRECTORY_SEPARATOR."ready-{$index}")
            ), 'The concurrency workers did not reach the ready barrier.');

            file_put_contents($this->barrierDirectory.DIRECTORY_SEPARATOR.'release', 'go', LOCK_EX);

            return array_map(function (Process $process): array {
                $process->wait();

                if (! $process->isSuccessful()) {
                    throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
                }

                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($result)) {
                    throw new RuntimeException('A concurrency worker returned an invalid result.');
                }

                return $result;
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            $this->removeBarrierDirectory();
        }
    }

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        return array_filter([
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ], fn (string $value) => $value !== '');
    }

    private function waitUntil(callable $condition, string $failureMessage): void
    {
        $deadline = hrtime(true) + ($this->timeoutSeconds * 1_000_000_000);

        while (! $condition()) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException($failureMessage);
            }

            usleep(10_000);
        }
    }

    private function removeBarrierDirectory(): void
    {
        if (! is_dir($this->barrierDirectory)) {
            return;
        }

        foreach (glob($this->barrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->barrierDirectory);
    }
}
