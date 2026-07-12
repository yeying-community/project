---
id: license.howto
title: 申请与录入社区授权配置
type: howto
feature: license
scope: super-admin
locale: zh
aliases:
  - 申请社区授权配置
  - 录入社区授权配置
  - 怎么填授权配置
  - 提交授权配置
  - 上传授权
  - 授权配置填哪里
  - 终端绑定
  - 怎么扩容用户数
related_tools: []
related_pages: []
prerequisites:
  - 需要进入「系统设置」→「License」页面（仅管理员可见）
  - 保存 License 仅超级管理员（第一个注册用户）可执行
negative:
  - 不能在终端外部直接编辑 License 文件，必须走管理端 API
  - 开源运行时下，License 只是项目本地的授权描述文件，不再依赖官方签发
  - 若你自定义了 people / SN / MAC 约束，仍需保证内容与当前实例匹配
last_verified: v0.0.1
---

# 申请与录入社区授权配置

> 本文介绍**社区授权配置**（手动粘贴本地 JSON 文本）。
> 在开源运行时（`DOO_DRIVER=opensource`）下，授权配置会保存为项目本地 JSON；在线授权是否可用取决于你是否保留并接通自己的授权中心。

## 入口
桌面端：左上角头像 →「系统设置」→「License」→「社区授权」Tab（仅管理员可见）。
对应后端：`POST api/system/license`，`type=save` 写入。

## 操作步骤

### 第 1 步 - 获取当前终端信息
在「License」页面顶部，夜莺会显示：

- **doo_sn** — 终端 SN（机器指纹，每次部署生成）
- **macs** — 当前服务器网卡 MAC 列表（用于绑定校验）
- **doo_version** — 当前主程序版本号
- **user_count** — 当前真实用户数（剔除机器人和禁用号）

这些字段是申请 License 时必须提供给签发方的信息。

### 第 2 步 - 准备授权配置内容
开源运行时默认就是社区版，不录入任何内容也能正常使用；如果你希望明确保存一份本地授权配置，可准备 JSON 文本，例如：

```json
{
  "edition": "MIT",
  "type": "community",
  "people": 0,
  "sn": "当前页面显示的 doo_sn",
  "mac": ["当前页面显示的 macs"],
  "expired_at": null
}
```

字段说明：
- `people=0` 表示不限制人数
- `sn` 建议直接使用当前页面展示的 `doo_sn`
- `mac` 可填当前实例的一个或多个 MAC；不想做 MAC 绑定时可传空数组
- `expired_at` 留空或 `null` 表示不过期

### 第 3 步 - 录入授权配置
1. 把准备好的 JSON 文本整段复制
2. 粘贴到「社区授权」页的输入框
3. 点击「保存」（接口字段 `license`，调用 `type=save`）
4. 后端用 `Doo::licenseSave()` 写入项目本地的 License 文件
5. 页面自动刷新校验结果：`info` 字段重新解析，`error` 数组为空表示通过

## 校验结果解读
返回结构里 `error` 数组列出当前不满足的规则：

- `终端SN与License不匹配` — License 对应的 SN ≠ 当前 doo_sn（多发生在换机迁移）
- `终端MAC与License不匹配` — License MAC 名单与本机网卡无交集
- `终端用户数超过License限制` — `user_count > people`，需要扩容或停用账号
- `终端License已过期` — `expired_at` 已经过去

详细处理见 [[license.expire.faq]]。

## 不支持
- 普通管理员能看 License 信息但不能保存；只有超级管理员（id=1）能 save
- 不支持上传文件方式，仅接受文本字段
- 旧版官方 `doo.so` 加密 License 不能直接被开源运行时解析；迁移时应改写成项目自己的 JSON 结构
