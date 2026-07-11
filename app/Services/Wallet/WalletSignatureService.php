<?php

namespace App\Services\Wallet;

use App\Exceptions\ApiException;
use Web3p\EthereumUtil\Util;
use Throwable;

class WalletSignatureService
{
    public function recoverPersonalSignAddress(string $message, string $signature): string
    {
        $signature = strtolower(trim($signature));
        if (!preg_match('/^0x([a-f0-9]{130})$/', $signature, $matches)) {
            throw new ApiException('钱包签名格式无效');
        }

        $raw = $matches[1];
        $r = substr($raw, 0, 64);
        $s = substr($raw, 64, 64);
        $v = hexdec(substr($raw, 128, 2));
        if ($v >= 35) {
            $v = ($v - 35) % 2;
        } else {
            $v = $v >= 27 ? $v - 27 : $v;
        }
        if (!in_array($v, [0, 1], true)) {
            throw new ApiException('钱包签名恢复参数无效');
        }

        try {
            $util = new Util();
            $publicKey = $util->recoverPublicKey($util->hashPersonalMessage($message), $r, $s, $v);
            return strtolower($util->publicKeyToAddress($publicKey));
        } catch (Throwable) {
            throw new ApiException('钱包签名验证失败');
        }
    }

    public function addressesMatch(string $expected, string $message, string $signature): bool
    {
        return strtolower(trim($expected)) === $this->recoverPersonalSignAddress($message, $signature);
    }
}
