<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Module\Base;
use App\Module\Doo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureAdminUser extends Command
{
    protected $signature = 'dootask:ensure-admin {--email=admin@yeying.com : 管理员邮箱} {--password= : 指定初始密码，留空则自动生成}';
    protected $description = '确保系统至少有一个管理员账号';

    public function handle(): int
    {
        $existingAdmin = User::where('identity', 'like', '%,admin,%')
            ->whereNull('disable_at')
            ->orderBy('userid')
            ->first();

        if ($existingAdmin) {
            $this->info("管理员已存在：{$existingAdmin->email} (ID: {$existingAdmin->userid})");
            return self::SUCCESS;
        }

        $email = trim((string)$this->option('email'));
        if (!Base::isEmail($email)) {
            $this->error('管理员邮箱格式不正确');
            return self::FAILURE;
        }

        $password = (string)($this->option('password') ?: $this->generateInitialPassword());
        $user = DB::transaction(function () use ($email, $password) {
            $user = User::whereEmail($email)->lockForUpdate()->first();
            $encrypt = Base::generatePassword(6);
            $payload = [
                'identity' => $this->withAdminIdentity($user?->getRawOriginal('identity') ?? ''),
                'nickname' => $user?->nickname ?: '管理员',
                'profession' => $user?->profession ?: '管理员',
                'az' => $user?->az ?: 'A',
                'encrypt' => $encrypt,
                'password' => Doo::md5s($password, $encrypt),
                'changepass' => 1,
                'email_verity' => 1,
                'disable_at' => null,
                'bot' => 0,
            ];

            if ($user) {
                $user->updateInstance($payload);
            } else {
                $user = User::createInstance(array_merge($payload, [
                    'email' => $email,
                    'userimg' => '',
                    'login_num' => 0,
                    'last_ip' => '127.0.0.1',
                    'line_ip' => '127.0.0.1',
                    'task_dialog_id' => 0,
                    'created_ip' => '127.0.0.1',
                ]));
            }

            $user->save();
            return $user;
        });

        $this->info("已创建/修复管理员账号：{$user->email} (ID: {$user->userid})");
        $this->line("邮箱: {$user->email}");
        $this->line("密码: {$password}");
        $this->warn('请登录后立即修改密码，或使用 ./cmd repassword 重置。');

        return self::SUCCESS;
    }

    private function generateInitialPassword(): string
    {
        return bin2hex(random_bytes(8)) . 'Aa1!';
    }

    private function withAdminIdentity(?string $identity): string
    {
        $items = array_filter(explode(',', trim((string)$identity, ',')));
        $items[] = 'admin';

        return Base::arrayImplode($items);
    }
}
