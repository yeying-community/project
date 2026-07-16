<?php

namespace App\Services;

use App\Module\Base;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

/**
 * Resolves SMTP settings shared by verification mail, notifications and tests.
 * Database emailSetting values take precedence over .env MAIL_* fallbacks.
 */
class MailTransportService
{
    /** @return array{smtp_server:string,port:int,account:string,password:string} */
    public static function settings(?array $database = null): array
    {
        $database ??= Base::setting('emailSetting');
        $smtp = config('mail.mailers.smtp', []);

        $settings = [
            'smtp_server' => trim((string) ($database['smtp_server'] ?? '') ?: (string) ($smtp['host'] ?? '')),
            'port' => (int) (($database['port'] ?? '') ?: ($smtp['port'] ?? 0)),
            'account' => trim((string) ($database['account'] ?? '') ?: (string) ($smtp['username'] ?? '')),
            'password' => (string) (($database['password'] ?? '') ?: ($smtp['password'] ?? '')),
        ];

        if ($settings['smtp_server'] === '' || $settings['port'] <= 0 || $settings['account'] === '' || $settings['password'] === '') {
            throw new \InvalidArgumentException('系统邮箱尚未配置，请在管理员后台配置，或在 .env 中配置 MAIL_HOST、MAIL_PORT、MAIL_USERNAME 和 MAIL_PASSWORD。');
        }

        return $settings;
    }

    public static function mailer(?array $database = null): Mailer
    {
        $settings = self::settings($database);
        $scheme = $settings['port'] === 465 ? 'smtps' : 'smtp';
        $dsn = sprintf(
            '%s://%s:%s@%s:%d?verify_peer=0',
            $scheme,
            rawurlencode($settings['account']),
            rawurlencode($settings['password']),
            $settings['smtp_server'],
            $settings['port']
        );

        return new Mailer(Transport::fromDsn($dsn));
    }
}
