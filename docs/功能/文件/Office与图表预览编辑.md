---
状态: 当前有效
负责人: 项目维护组
最后验证: 2026-09-20
适用版本: main
---

# Office 与图表预览编辑

## 目的和范围

Project 文件中心支持常见文件预览。Word、Excel 和 PowerPoint 文件通过 OnlyOffice Document Server 预览或编辑；Markdown 中的 Mermaid 由前端渲染，PlantUML 代码块通过配置的 PlantUML Server 转换为 SVG。文件最终内容由 Project 配置的 Local 或 S3 持久化后端保存。

## Office 文件

打开 `docx`、`xlsx` 或 `pptx` 文件时，Project 生成文档会话并加载 OnlyOffice API。编辑保存由 OnlyOffice 回调 Project，Project 校验回调下载的文件后再提交到持久化存储。重新打开时，下载地址必须能访问当前版本的文件内容，不能依赖已删除的 release 目录临时文件。

生产部署至少需要：

- `OFFICE_ENABLED=true`。
- 浏览器可访问的 `/office/web-apps/apps/api/documents/api.js` 反代。
- OnlyOffice 能访问 Project 的内部 API 和文件下载地址。
- Project 能访问 OnlyOffice 的回调下载地址。

## PlantUML 图表

Markdown 使用 `plantuml` 或 `puml` fenced 代码块，浏览器请求同源路径：

```text
/plantuml/svg/~h...
```

生产推荐配置 `PLANTUML_SERVER_URL=/plantuml`，Nginx 再将 `/plantuml/` 去前缀代理到 PlantUML Server 的 `/` 根路径。不能把 `http://127.0.0.1:18080` 配给浏览器使用，因为那会指向用户自己的电脑。

## 失败排查

- Office 报 `Cannot GET /office/cache/...`：检查 OnlyOffice 回调下载地址、Project 的 `OFFICE_INTERNAL_DOCUMENT_BASE` 和 Nginx `/office/` 反代。
- PlantUML 图返回 404：检查 `PLANTUML_SERVER_URL` 是否为 `/plantuml`，以及 `proxy_pass` 是否以 `/` 结尾。
- 文件上传成功但重新打开失败：确认 `FILE_STORAGE_DISK`、S3 bucket/prefix 和持久化对象是否存在；`storage/app/tmp` 只用于临时处理，不能作为最终文件源。

