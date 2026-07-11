<?php

namespace App\Contracts;

use App\Models\User;

interface DooRuntimeInterface
{
    public function license(): array;

    public function licenseDecode($license): array;

    public function licenseSave($license): void;

    public function userId(): int;

    public function userExpired(): bool;

    public function userExpiredAt(): ?string;

    public function userEmail(): string;

    public function userEncrypt(): string;

    public function userToken(): string;

    public function userCreate($email, $password): User|null;

    public function tokenEncode($userid, $email, $encrypt, int $days = 15): string;

    public function tokenDecode($token): array;

    public function translate($text, ?string $lang = ''): string;

    public function md5s($text, string $password = ''): string;

    public function macs(): array;

    public function dooSN(): string;

    public function dooVersion(): string;

    public function pgpGenerateKeyPair($name, $email, string $passphrase = ''): array;

    public function pgpEncrypt($plaintext, $publicKey): string;

    public function pgpDecrypt($encryptedText, $privateKey, $passphrase = null): string;

    public function pgpEncryptApi($plaintext, $publicKey): string;

    public function pgpDecryptApi($encryptedText, $privateKey, $passphrase = null): array;

    public function pgpParseStr($string): array;

    public function pgpPublicFormat($key): string;
}
