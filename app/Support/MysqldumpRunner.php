<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

class MysqldumpRunner
{
    /**
     * Run mysqldump with Windows/Linux-friendly connection strategies.
     *
     * @return string Absolute path to the SQL dump file
     */
    public static function dump(string $sqlPath): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Driver de base de données non supporté pour le backup : {$driver}");
        }

        $mysqldump = MysqldumpResolver::path();
        if ($mysqldump !== 'mysqldump' && $mysqldump !== 'mariadb-dump' && ! File::exists($mysqldump)) {
            throw new RuntimeException(
                'Outil d\'export MySQL/MariaDB introuvable sur ce serveur. Contactez l\'administrateur technique.'
            );
        }

        $host = (string) config("database.connections.{$connection}.host", '127.0.0.1');
        $port = (string) config("database.connections.{$connection}.port", 3306);
        $database = (string) config("database.connections.{$connection}.database");
        $username = (string) config("database.connections.{$connection}.username");
        $password = (string) config("database.connections.{$connection}.password");

        $defaultsFile = self::writeDefaultsFile($username, $password);
        $errors = [];
        $commonOptions = [
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
        ];

        try {
            foreach (self::strategies($host, $port) as $strategy) {
                @File::delete($sqlPath);

                $baseArgs = array_merge(
                    [$mysqldump, '--defaults-extra-file='.$defaultsFile],
                    $strategy,
                    $commonOptions
                );

                // Strategy A: --result-file
                $result = self::run([...$baseArgs, '--result-file='.$sqlPath, $database]);
                if ($result['ok'] && File::exists($sqlPath) && File::size($sqlPath) > 0) {
                    return $sqlPath;
                }
                $errors[] = ($strategy[0] ?? '').' result-file: '.($result['error'] ?: 'empty file');

                // Strategy B: capture stdout
                @File::delete($sqlPath);
                $result = self::run([...$baseArgs, $database]);
                if ($result['ok'] && $result['output'] !== '') {
                    File::put($sqlPath, $result['output']);
                    if (File::size($sqlPath) > 0) {
                        return $sqlPath;
                    }
                }
                $errors[] = ($strategy[0] ?? '').' stdout: '.($result['error'] ?: 'empty output');
            }
        } finally {
            if (File::exists($defaultsFile)) {
                @File::delete($defaultsFile);
            }
        }

        throw new RuntimeException(
            'L\'export de la base a échoué. Vérifiez que MySQL/MariaDB est démarré. '
            .Str::limit(implode(' | ', $errors), 800)
        );
    }

    /**
     * @return list<list<string>>
     */
    private static function strategies(string $host, string $port): array
    {
        $hosts = array_values(array_unique(array_filter([
            $host,
            $host === '127.0.0.1' ? 'localhost' : null,
            $host === 'localhost' ? '127.0.0.1' : null,
            '127.0.0.1',
            'localhost',
        ])));

        $strategies = [];
        foreach ($hosts as $h) {
            $strategies[] = ['--protocol=TCP', '--host='.$h, '--port='.$port];
        }

        // Some Windows stacks prefer no explicit protocol
        foreach ($hosts as $h) {
            $strategies[] = ['--host='.$h, '--port='.$port];
        }

        return $strategies;
    }

    private static function writeDefaultsFile(string $username, string $password): string
    {
        $path = storage_path('app/backup-tmp/mysqldump-'.Str::uuid()->toString().'.cnf');
        File::ensureDirectoryExists(dirname($path));

        // Escape for MySQL option files
        $user = str_replace(['\\', '"'], ['\\\\', '\"'], $username);
        $pass = str_replace(['\\', '"'], ['\\\\', '\"'], $password);

        $content = "[client]\nuser=\"{$user}\"\npassword=\"{$pass}\"\n";

        File::put($path, $content);

        // Restrictive perms on Linux; ignore on Windows
        @chmod($path, 0600);

        return $path;
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, error: string, output: string}
     */
    private static function run(array $command): array
    {
        // 1) Laravel Process without mutating env (inherit parent fully)
        try {
            $result = Process::timeout(600)->run($command);
            if ($result->successful()) {
                return ['ok' => true, 'error' => '', 'output' => $result->output()];
            }
            $lastError = trim($result->errorOutput() ?: $result->output());
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }

        // 2) Symfony Process with explicit inherited environment (fixes Win errno 10106 under Apache)
        try {
            $env = self::inheritedEnvironment();
            $process = new SymfonyProcess($command, base_path(), $env, null, 600);
            $process->run();
            if ($process->isSuccessful()) {
                return ['ok' => true, 'error' => '', 'output' => $process->getOutput()];
            }
            $lastError = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $lastError;
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }

        // 3) Windows: cmd.exe wrapper (sometimes restores Winsock for child)
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $line = implode(' ', array_map(
                    static fn (string $part): string => self::winQuote($part),
                    $command
                ));
                $process = new SymfonyProcess(
                    ['cmd.exe', '/c', $line],
                    base_path(),
                    self::inheritedEnvironment(),
                    null,
                    600
                );
                $process->run();
                if ($process->isSuccessful()) {
                    return ['ok' => true, 'error' => '', 'output' => $process->getOutput()];
                }
                $lastError = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $lastError;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return ['ok' => false, 'error' => $lastError ?? 'unknown', 'output' => ''];
    }

    /**
     * @return array<string, string>
     */
    private static function inheritedEnvironment(): array
    {
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        // Critical on Windows Apache child processes
        foreach (['SystemRoot', 'SYSTEMROOT', 'WINDIR', 'ComSpec', 'PATH', 'Pathext', 'TEMP', 'TMP'] as $key) {
            $val = getenv($key);
            if (is_string($val) && $val !== '') {
                $env[$key] = $val;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            if (! isset($env['SystemRoot'])) {
                $env['SystemRoot'] = $env['SYSTEMROOT'] ?? 'C:\\Windows';
            }
            if (! isset($env['WINDIR'])) {
                $env['WINDIR'] = $env['SystemRoot'];
            }
            if (! isset($env['ComSpec'])) {
                $env['ComSpec'] = $env['SystemRoot'].'\\system32\\cmd.exe';
            }
            if (! isset($env['PATH']) || ! str_contains(strtolower($env['PATH']), 'system32')) {
                $system32 = $env['SystemRoot'].'\\system32';
                $env['PATH'] = $system32.';'.$env['SystemRoot'].';'.($env['PATH'] ?? '');
            }
        } elseif (! isset($env['SystemRoot']) && isset($env['SYSTEMROOT'])) {
            $env['SystemRoot'] = $env['SYSTEMROOT'];
        }

        return $env;
    }

    private static function winQuote(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (! preg_match('/[\s"]/', $value)) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
