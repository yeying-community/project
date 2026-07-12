---
id: license.online.howto
title: 在线授权（邮箱验证码登录 / 申请试用 / 自动续期）
type: howto
feature: license
scope: super-admin
locale: zh
aliases:
  - 在线授权
  - 邮箱授权
  - 邮箱验证码授权
  - 用 appstore 账号授权
  - 申请试用
  - 试用授权
  - 在线 License
  - 自动续期
  - 授权邮箱登录
  - 在线授权到期
  - 在线授权冻结
  - 在线授权被吊销
  - 换机 deactivate
  - 选择要使用的授权
  - 一个账号多条授权
  - 登录时选择授权
  - SN 与 License 不匹配
  - MAC 与 License 不匹配
  - 终端SN与License不匹配
  - 终端MAC与License不匹配
  - 换机后在线授权失效
related_tools: []
related_pages: []
prerequisites:
  - 仅在你保留并接通自建授权中心时才需要本页信息
  - 当前开源运行时默认关闭在线授权入口
negative:
  - 社区版 / MIT fork 默认不暴露在线授权 UI，也不会调用官方授权中心
  - 若你需要在线授权，必须自行实现对应服务端、签发逻辑和前端入口
last_verified: v0.0.1
---

# 在线授权（邮箱验证码登录 / 申请试用 / 自动续期）

当前开源运行时默认只保留离线 / 社区授权。原有“在线授权”链路已从默认产品流程中关闭，不再向官方授权中心发起登录、试用、续期或退出请求。

- **离线授权**：手动粘贴项目自己的 License 文本（见 [[license.howto]]）。
- **在线授权**：仅作为二次开发预留概念，默认关闭。

## 当前行为

- License 页面默认展示离线授权信息
- `POST api/license/status` 与 `POST api/license/refresh` 会返回 `mode=disabled`
- 其它 `api/license/*` 在线授权接口会直接返回“社区版不支持在线授权”

## 如果你要自己恢复在线授权

如果你希望保留 SaaS 式授权能力，需要自行完成至少这些部分：

1. 自建授权中心接口，替代原有 App Store 依赖
2. 自定义 license 签发和续期格式，并与 `Doo::licenseSave()` 对齐
3. 恢复前端在线授权入口和交互
4. 重新定义在线授权状态机、提醒文案和迁移策略

## 不支持
- 社区版默认不提供邮箱验证码授权、试用申请、在线续期
- 默认发行物不再假定存在官方授权中心
