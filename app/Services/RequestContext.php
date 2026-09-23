<?php

namespace App\Services;

use App\Module\ClientContext;
use Illuminate\Http\Request;
use Swoole\Coroutine;

/**
 * 请求上下文
 */
class RequestContext
{
    /** @var string 请求ID的上下文键 */
    private const CONTEXT_KEY = 'request_id';

    /** @var string 请求ID前缀 */
    private const REQUEST_ID_PREFIX = 'req';

    /** @var int 上下文的TTL（生存时间） */
    private const TTL_SECONDS = 3600;  // 上下文 TTL 为 1 小时

    /** @var string 协程上下文中存放 ClientContext 的键 */
    private const CTX_SLOT = '__client_context__';

    /**
     * 非协程运行时（Task Worker / CLI / artisan / 队列）的降级存储。
     *
     * HTTP 请求都跑在各自的协程里，ClientContext 存放于 Swoole 协程上下文
     * （Coroutine::getContext()）——它按协程隔离、且在协程结束时被自动销毁，
     * 因此无需手动清理、也不会跨请求泄漏。只有在没有协程（cid<0）时才落到
     * 这个单槽静态数组；单槽会被 begin()/clean() 覆盖或清空，同样不会无界增长。
     *
     * 早期实现用一个以「请求ID」为键的静态数组做存储，但请求ID在一次请求内
     * 无法稳定解析（Swoole 下 request() 取不到 handle() 时写入的属性），导致
     * 每次 get/set/has 都生成新ID并残留一个 ClientContext，Worker 生命周期内
     * 无界累积直至 128MB OOM。改用协程上下文后从根上消除该泄漏。
     *
     * @var array<string, ClientContext>
     */
    private static array $fallbackContext = [];

    /**
     * 生成请求唯一ID
     */
    public static function generateRequestId(): string
    {
        $pid = getmypid();
        $cid = Coroutine::getCid() ?? 0;
        $microtime = str_replace('.', '', microtime(true));
        return self::REQUEST_ID_PREFIX . '_' . $pid . '_' . $cid . '_' . $microtime . '_' . mt_rand(1000, 9999);
    }

    /**
     * 为长生命周期 Worker 中的每个 HTTP 请求初始化独立上下文。
     */
    public static function begin(Request $request): string
    {
        $requestId = self::generateRequestId();
        $request->attributes->set(self::CONTEXT_KEY, $requestId);

        $store = self::coroutineStore();
        if ($store !== null) {
            $store[self::CTX_SLOT] = new ClientContext();
        } else {
            self::$fallbackContext[self::CTX_SLOT] = new ClientContext();
        }

        return $requestId;
    }

    /**
     * 返回当前协程的上下文存储（ArrayObject），非协程环境返回 null。
     */
    private static function coroutineStore(): ?\ArrayAccess
    {
        if (Coroutine::getCid() < 0) {
            return null;
        }
        return Coroutine::getContext();
    }

    /**
     * 获取当前请求ID
     */
    public static function getCurrentRequestId($requestId = null): ?string
    {
        // 如果提供了有效的请求ID，直接返回
        if ($requestId && str_starts_with($requestId, self::REQUEST_ID_PREFIX)) {
            return $requestId;
        }

        // 尝试从当前请求获取
        $request = request();
        if ($request && method_exists($request, 'attributes') && $request->attributes) {
            if (!$request->attributes->has(static::CONTEXT_KEY)) {
                $request->attributes->set(static::CONTEXT_KEY, self::generateRequestId());
            }
            return $request->attributes->get(static::CONTEXT_KEY);
        }

        // 如果没有请求上下文，生成一个新的请求ID
        return self::generateRequestId();
    }

    /**
     * 获取当前请求的上下文示例
     *
     * 以「当前协程」为粒度解析：同一请求内的所有调用命中同一个 ClientContext，
     * 不再因请求ID解析不稳定而残留孤儿上下文。$requestId 参数保留以兼容签名，
     * 现有调用方均未使用它跨请求寻址（见 begin() 注释）。
     */
    public static function getCurrentRequestContext($requestId = null): ?ClientContext
    {
        $store = self::coroutineStore();
        if ($store !== null) {
            if (!isset($store[self::CTX_SLOT])) {
                $store[self::CTX_SLOT] = new ClientContext();
            } else {
                $store[self::CTX_SLOT]->update();
            }
            return $store[self::CTX_SLOT];
        }

        // 非协程降级：单槽存储
        if (!isset(self::$fallbackContext[self::CTX_SLOT])) {
            self::$fallbackContext[self::CTX_SLOT] = new ClientContext();
        } else {
            self::$fallbackContext[self::CTX_SLOT]->update();
        }
        return self::$fallbackContext[self::CTX_SLOT];
    }

    /**
     * 清理过期的降级上下文数据（协程上下文由 Swoole 自动回收，无需此处处理）。
     */
    public static function cleanExpired(): void
    {
        $now = microtime(true);

        foreach (self::$fallbackContext as $slot => $context) {
            if ($now - $context->updatedAt > self::TTL_SECONDS) {
                unset(self::$fallbackContext[$slot]);
            }
        }
    }

    /** ***************************************************************************************** */
    /** ***************************************************************************************** */
    /** ***************************************************************************************** */

    /**
     * 设置请求上下文
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $requestId
     * @return void
     */
    public static function set(string $key, mixed $value, ?string $requestId = null): void
    {
        $context = self::getCurrentRequestContext($requestId);
        if ($context === null) {
            return;
        }

        $context->set($key, $value);

        // 概率性清理，避免频繁清理影响性能
        if (mt_rand(1, 100) === 1) {
            self::cleanExpired();
        }
    }

    /**
     * 批量设置上下文数据
     *
     * @param array<string, mixed> $data
     * @param string|null $requestId
     * @return void
     */
    public static function setMultiple(array $data, ?string $requestId = null): void
    {
        $context = self::getCurrentRequestContext($requestId);
        if ($context === null) {
            return;
        }

        $context->setMultiple($data);
    }

    // 与 set 方法的区别是，save 方法会返回传入的 value 值
    public static function save(string $key, mixed $value, ?string $requestId = null): mixed
    {
        self::set($key, $value, $requestId);
        return $value;
    }

    /**
     * 获取请求上下文
     *
     * @param string $key
     * @param mixed $default
     * @param string|null $requestId
     * @return mixed
     */
    public static function get(string $key, mixed $default = null, ?string $requestId = null): mixed
    {
        $context = self::getCurrentRequestContext($requestId);
        if ($context === null) {
            return $default;
        }

        return $context->get($key, $default);
    }

    /**
     * 获取当前请求的所有上下文数据
     *
     * @param string|null $requestId
     * @return array<string, mixed>
     */
    public static function getAll(?string $requestId = null): array
    {
        $context = self::getCurrentRequestContext($requestId);
        if ($context === null) {
            return [];
        }

        return $context->context ?? [];
    }

    /**
     * 判断请求上下文是否存在
     *
     * @param string $key
     * @param string|null $requestId
     * @return bool
     */
    public static function has(string $key, ?string $requestId = null): bool
    {
        $context = self::getCurrentRequestContext($requestId);
        if ($context === null) {
            return false;
        }

        return $context->has($key);
    }

    /**
     * 清理请求上下文
     *
     * 协程环境下从当前协程上下文移除 ClientContext（协程结束时 Swoole 亦会
     * 自动回收，这里只是尽早释放）；非协程环境清空降级单槽。
     *
     * @param string|null $requestId 保留以兼容签名，未使用
     * @return void
     */
    public static function clean(?string $requestId = null): void
    {
        $store = self::coroutineStore();
        if ($store !== null) {
            unset($store[self::CTX_SLOT]);
            return;
        }
        unset(self::$fallbackContext[self::CTX_SLOT]);
    }

    /**
     * 以中间件 terminate 阶段传入的请求实例清理其上下文。
     *
     * 协程上下文在请求协程结束时会被 Swoole 自动销毁，因此上下文不会泄漏；
     * 本方法在 terminate 阶段尽早释放当前协程/降级槽，属于双保险。
     *
     * @param Request $request
     * @return void
     */
    public static function cleanRequest(Request $request): void
    {
        self::clean();
    }

    /** ***************************************************************************************** */
    /** ***************************************************************************************** */
    /** ***************************************************************************************** */

    /**
     * 更新请求的基本URL
     *
     * @param Request $request
     * @return void
     */
    public static function updateBaseUrl($request)
    {
        if ($request->path() !== 'api/system/setting') {
            return;
        }
        $schemeAndHttpHost = $request->getSchemeAndHttpHost();
        if (str_contains($schemeAndHttpHost, '127.0.0.1') || str_contains($schemeAndHttpHost, 'localhost')) {
            return;
        }
        \Cache::forever('RequestContext::base_url', $schemeAndHttpHost);
    }

    /**
     * 替换请求的基本URL
     *
     * @param string $url
     * @return string
     */
    public static function replaceBaseUrl(string $url): string
    {
        // 先提取主机部分
        $pattern = '/^(https?:\/\/[^\/?#:]+(:\d+)?)/i';
        if (!preg_match($pattern, $url, $matches)) {
            return $url; // 如果不是有效URL直接返回
        }

        $schemeAndHttpHost = $matches[1] ?? '';
        if (!$schemeAndHttpHost) {
            return $url;
        }

        // 只检查主机部分是否为本地主机
        if (str_contains($schemeAndHttpHost, '127.0.0.1') || str_contains($schemeAndHttpHost, 'localhost')) {
            $baseUrl = \Cache::get('RequestContext::base_url');
            if ($baseUrl) {
                return $baseUrl . substr($url, strlen($schemeAndHttpHost));
            }
        }

        return $url;
    }

    /**
     * 清除基本URL缓存
     */
    public static function clearBaseUrlCache(): void
    {
        \Cache::forget('RequestContext::base_url');
    }
}
