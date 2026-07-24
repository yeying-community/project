---
id: appstore.concept
title: 应用市场是什么
type: concept
feature: appstore
scope: admin
locale: zh
aliases:
  - 应用商店
  - 应用市场
  - AppStore
  - 插件市场
  - 装插件在哪
  - 插件管理
related_tools: []
related_pages: [application]
prerequisites:
  - 需要系统管理员权限
negative:
  - 不是 iOS / Android 那种第三方应用商店，仅装 YeYing 内部插件
  - 普通成员看不到「应用商店」入口
  - 不支持单用户安装，所有插件对全员生效
last_verified: v0.0.1
---

# 应用市场是什么

## 定义
应用市场（AppStore）是 YeYing 的插件管理后台，让系统管理员一键安装 / 卸载 / 更新各种功能插件，例如 AI 助手、审批、签到、OnlyOffice 等。其本体是一个名为 `appstore` 的微应用，注册在 `application/admin` 位置（见 `store/mutations.js` 第 396 行）。

## 关键属性
- **微应用形态**：通过 `MicroApps` 加载 iframe，URL 为 `appstore/internal`
- **后端校验**：`App\Module\Apps::isInstalled($appId)` 读取 `docker/appstore/config/{appId}/config.yml` 中 `status: installed` 判断
- **未安装会抛 ApiException**：`Apps::isInstalledThrow()` 提示「应用「X」未安装」
- **社区发布链路（迁移中）**：社区应用经过 release 校验、审核发布、部署机 Agent 任务和健康检查；Project 只在 Agent 成功后读取本地安装状态镜像
- **生命周期 Hook**：用户创建 / 离职会调 `dispatchUserHook` 通知各插件（user_onboard / offboard / update）

## 运维 dry-run
部署机可运行 `scripts/appstore-agent.sh --dry-run` 检查社区发布制品和共享依赖连通性。该检查不下载镜像、不修改插件配置、不启动或停止任何容器；检查后任务会归还为待执行状态。

## 插件类型
- 官方内置：ai、approve、checkin/face、office、drawio、minder、okr、search（manticore）、fileview
- 社区插件：以 `community_` 前缀命名，如 `community_kuaifan_memos`、`community_kuaifan_kpi`、`community_Learntotolearn_roomly`

## 与「微应用菜单」的区别
- **应用市场**：管理插件「装/卸/更新」的容器
- **微应用菜单**：插件装好后注册到「应用」页的菜单项，普通成员可见的入口

## 不支持
- 不支持卸载 `appstore` 自身（`isInstalled('appstore')` 强制返回 true）
- 不支持普通成员浏览未装插件列表
- 不支持安装非 YeYing 兼容的任意 Docker 镜像

## 相关
- 安装：[[appstore.install.howto]]
- 卸载：[[appstore.uninstall.howto]]
- 入口：[[appstore.entry.menu-map]]
