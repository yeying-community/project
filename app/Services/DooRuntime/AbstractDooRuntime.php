<?php

namespace App\Services\DooRuntime;

use App\Contracts\DooRuntimeInterface;
use App\Module\Base;
use Carbon\Carbon;

abstract class AbstractDooRuntime implements DooRuntimeInterface
{
    public function userExpired(): bool
    {
        $expiredAt = $this->userExpiredAt();
        return $expiredAt && Carbon::parse($expiredAt)->isBefore(Carbon::now());
    }

    public function pgpEncryptApi($plaintext, $publicKey): string
    {
        $content = Base::array2json($plaintext);
        $content = $this->pgpEncrypt($content, $publicKey);
        return preg_replace("/\s*-----(BEGIN|END) PGP MESSAGE-----\s*/i", "", $content);
    }

    public function pgpDecryptApi($encryptedText, $privateKey, $passphrase = null): array
    {
        $content = "-----BEGIN PGP MESSAGE-----\n\n" . $encryptedText . "\n-----END PGP MESSAGE-----";
        $content = $this->pgpDecrypt($content, $privateKey, $passphrase);
        return Base::json2array($content);
    }

    public function pgpParseStr($string): array
    {
        $array = [
            'encrypt_type' => '',
            'encrypt_id' => '',
            'client_type' => '',
            'client_key' => '',
        ];
        $string = str_replace(";", "&", $string);
        parse_str($string, $params);
        foreach ($params as $key => $value) {
            $key = strtolower(trim($key));
            if ($key) {
                $array[$key] = trim($value);
            }
        }
        if ($array['client_type'] === 'pgp' && $array['client_key']) {
            $array['client_key'] = $this->pgpPublicFormat($array['client_key']);
        }
        return $array;
    }

    public function pgpPublicFormat($key): string
    {
        $key = str_replace(["-", "_", "$"], ["+", "/", "\n"], $key);
        if (!str_contains($key, '-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
            $key = "-----BEGIN PGP PUBLIC KEY BLOCK-----\n\n" . $key . "\n-----END PGP PUBLIC KEY BLOCK-----";
        }
        return $key;
    }
}
