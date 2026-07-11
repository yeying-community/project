<?php

namespace App\Services\DooRuntime;

use App\Exceptions\ApiException;
use Symfony\Component\Process\Process;
use Throwable;

class OpenPgpService
{
    protected string $binary;

    public function __construct()
    {
        $this->binary = (string) (config('dootask.gpg_binary') ?: $this->findBinary());
        if ($this->binary === '') {
            throw new ApiException('未找到 GnuPG，请安装 gpg 后重试');
        }
    }

    public function generateKeyPair(string $name, string $email, string $passphrase = ''): array
    {
        return $this->withHome(function (string $home) use ($name, $email, $passphrase): array {
            $identity = sprintf('%s <%s>', trim($name) ?: 'YeYing User', trim($email));
            $this->run(['--batch', '--pinentry-mode', 'loopback', '--passphrase', $passphrase,
                '--quick-generate-key', $identity, 'rsa3072', 'cert', '0'], $home);
            $fingerprint = trim($this->run(['--batch', '--with-colons', '--list-keys', $email], $home));
            preg_match('/^fpr:::::::::([^:]+):/m', $fingerprint, $matches);
            $fingerprint = $matches[1] ?? '';
            if ($fingerprint === '') {
                throw new ApiException('PGP 密钥生成失败');
            }
            $this->run(['--batch', '--pinentry-mode', 'loopback', '--passphrase', $passphrase,
                '--quick-add-key', $fingerprint, 'rsa3072', 'encrypt', '0'], $home);
            return [
                'public_key' => $this->run(['--batch', '--armor', '--export', $fingerprint], $home),
                'private_key' => $this->run(['--batch', '--pinentry-mode', 'loopback', '--passphrase', $passphrase,
                    '--armor', '--export-secret-keys', $fingerprint], $home),
                'passphrase' => $passphrase,
            ];
        });
    }

    public function encrypt(string $plaintext, string $publicKey): string
    {
        return $this->withHome(function (string $home) use ($plaintext, $publicKey): string {
            $this->import($home, $publicKey);
            return $this->run(['--batch', '--armor', '--trust-model', 'always', '--encrypt', '--recipient', $this->keyId($publicKey, $home)], $home, $plaintext);
        });
    }

    public function decrypt(string $encryptedText, string $privateKey, ?string $passphrase = null): string
    {
        return $this->withHome(function (string $home) use ($encryptedText, $privateKey, $passphrase): string {
            $this->import($home, $privateKey, $passphrase ?? '');
            return $this->run(['--batch', '--pinentry-mode', 'loopback', '--passphrase', $passphrase ?? '', '--decrypt'], $home, $encryptedText);
        });
    }

    protected function keyId(string $key, string $home): string
    {
        $output = $this->run(['--batch', '--with-colons', '--list-keys'], $home);
        preg_match('/^fpr:::::::::([^:]+):/m', $output, $matches);
        return $matches[1] ?? throw new ApiException('PGP 公钥无效');
    }

    protected function import(string $home, string $key, string $passphrase = ''): void
    {
        $this->run(['--batch', '--pinentry-mode', 'loopback', '--passphrase', $passphrase, '--import'], $home, $key);
    }

    protected function run(array $arguments, string $home, ?string $input = null): string
    {
        $process = new Process(array_merge([$this->binary, '--homedir', $home], $arguments));
        $process->setInput($input);
        $process->setTimeout(30);
        try {
            $process->mustRun();
        } catch (Throwable $e) {
            throw new ApiException('PGP 操作失败：' . trim($process->getErrorOutput() ?: $e->getMessage()));
        }
        return $process->getOutput();
    }

    protected function withHome(callable $callback): mixed
    {
        $home = sys_get_temp_dir() . '/yeying-gpg-' . bin2hex(random_bytes(12));
        mkdir($home, 0700, true);
        try {
            return $callback($home);
        } finally {
            $this->removeDirectory($home);
        }
    }

    protected function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }
        @rmdir($directory);
    }

    protected function findBinary(): string
    {
        foreach (['gpg', '/opt/homebrew/bin/gpg', '/usr/local/bin/gpg', '/usr/bin/gpg'] as $binary) {
            if (str_contains($binary, '/') ? is_executable($binary) : trim((string) shell_exec('command -v ' . escapeshellarg($binary))) !== '') {
                return $binary;
            }
        }
        return '';
    }
}
