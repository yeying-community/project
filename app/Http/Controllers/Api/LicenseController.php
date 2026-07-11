<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Module\Base;

/**
 * 在线授权客户端（与 SystemController::license 的离线粘贴并存）。
 *
 * 动态路由（routes/web.php）：
 *   api/license/email/send   -> email__send()
 *   api/license/login        -> login()
 *   api/license/login/confirm -> login__confirm()
 *   api/license/trial        -> trial()
 *   api/license/status       -> status()
 *   api/license/refresh      -> refresh()
 *   api/license/logout       -> logout()
 */
class LicenseController extends AbstractController
{
    protected function onlineLicenseDisabledResponse()
    {
        return Base::retError('社区版不支持在线授权');
    }

    /**
     * 发送邮箱验证码（登录与试用共用）
     */
    public function email__send()
    {
        User::auth('admin');
        return $this->onlineLicenseDisabledResponse();
    }

    /**
     * 邮箱 + 验证码登录并签发在线授权
     */
    public function login()
    {
        User::auth('admin');
        return $this->onlineLicenseDisabledResponse();
    }

    /**
     * 多条可用授权时，用户选定后确认签发（复用验证码）
     */
    public function login__confirm()
    {
        User::auth('admin');
        return $this->onlineLicenseDisabledResponse();
    }

    /**
     * 邮箱 + 验证码申请试用并签发
     */
    public function trial()
    {
        User::auth('admin');
        return $this->onlineLicenseDisabledResponse();
    }

    /**
     * 当前在线授权状态
     */
    public function status()
    {
        User::auth('admin');
        return Base::retSuccess('success', [
            'mode' => 'disabled',
            'status' => 'disabled',
            'plan' => 'community',
        ]);
    }

    /**
     * 进入授权页时的静默刷新：服务可达则更新授权数据，网络失败则不更新、不提示。
     */
    public function refresh()
    {
        User::auth('admin');
        return Base::retSuccess('success', [
            'mode' => 'disabled',
            'status' => 'disabled',
            'plan' => 'community',
        ]);
    }

    /**
     * 退出在线授权（释放座位 + 回落默认）
     */
    public function logout()
    {
        User::auth('admin');
        return $this->onlineLicenseDisabledResponse();
    }
}
