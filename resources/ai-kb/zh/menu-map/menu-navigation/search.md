---
id: menu-navigation.search.menu-map
title: 全局搜索入口在哪
type: menu-map
feature: menu-navigation
scope: end-user
locale: zh
aliases:
  - 怎么搜
  - 全局搜索在哪
  - 搜索框入口
  - 找东西的快捷键
  - 搜任务搜文件
  - 不知道任务属于哪个项目怎么搜
related_tools: [intelligent_search, search_dialogs, search_files, search_users]
related_pages: []
prerequisites: []
negative:
  - 全局搜索框不是固定显示，需要快捷键或菜单触发
  - 移动端无 Cmd+F 快捷键，需要在消息 / 项目页内点搜索图标
  - task ID 精确搜索只返回当前账号可查看的任务；不存在、无权限或已软删除任务不会作为 ID 命中
last_verified: v0.0.2
---

# 全局搜索入口在哪

## 路径
- 桌面端：任意页面按 Cmd/Ctrl + F 或 Cmd/Ctrl + /（`/`、`F` 同效）
- 桌面端：左侧栏顶部用户名旁的搜索图标（在部分版本里）
- 桌面端搜索框打开后默认聚焦输入框，输入关键词即时返回
- 移动端：消息 Tab 顶部搜索栏（搜会话和消息）
- 移动端：项目页顶部搜索栏（搜任务）
- 移动端：通讯录 Tab 顶部搜索栏（搜人）

## 搜索范围
- 任务名 / 项目名 / 文件名 / 联系人 / 历史消息内容
- 输入纯数字时先按 task ID 精确查找，范围是当前账号可访问的项目和任务；无需先知道 Project
- 任务搜索结果显示 `#taskID` 和所属 Project 名称，点击后打开任务详情弹窗
- 没有可访问的 ID 精确命中时，继续按原来的任务名称、描述和内容搜索
- 支持语义搜索（语义搜索能力由全文搜索 search 插件提供）

## 权限要求
- end-user 可用
- 结果只返回当前账号有权访问的对象
- 纯数字 task ID 精确定位不依赖 search 插件，其他全文 / 语义搜索能力仍取决于 search 插件

## 相关
- 搜索能力总览随全文搜索（search）插件提供，安装后可在 AI 助手中检索
- 高级搜索语法：高级筛选
