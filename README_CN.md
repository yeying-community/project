# 夜莺 - 开源任务管理系统

**[English](./README.md)** | 中文文档

夜莺 是基于 Laravel、LaravelS/Swoole 和 Vue 2 的开源任务与项目管理系统，采用 MIT License 发布。

- 社区：夜莺社区
- 邮箱：`yeying.community@gmail.com`
- 数据库：MySQL 8.4
- 搜索：Manticore（可选但建议生产启用）

## 本地开发

本地开发模式下，PHP/LaravelS 在宿主机运行，MySQL、Redis、Manticore 等中间件通过 Docker 容器运行。Node.js/npm 只用于前端开发和构建，不参与 PHP 服务运行。

### 环境要求

- macOS 或 Linux
- PHP 8.4
- PHP Swoole 扩展
- Composer
- Node.js 20+ 和 npm
- Docker 20.10+、Docker Compose v2+

### 初始化本地环境

在项目根目录执行：

```bash
./cmd local-install
```

该命令用于初始化本地 `.env`、PHP 依赖和运行目录，不会把 PHP 服务放进容器。

### 启动本地中间件

```bash
./cmd local-up
```

默认使用的本地中间件端口包括：

```text
MySQL      127.0.0.1:23306
Redis      127.0.0.1:26379
Manticore  127.0.0.1:9306
AppStore   127.0.0.1:19080（如已部署）
```

### 启动本地 PHP 服务

```bash
./cmd local-start
```

访问：

```text
http://127.0.0.1:2222
```

查看、停止和重启：

```bash
./cmd local-stop
./cmd local-start
```

### 前端开发模式

需要实时编译前端时，另开终端执行：

```bash
./cmd dev
```

修改前端后，开发服务器会自动重新编译。不要同时运行多个前端开发服务器。

### 本地生产构建

只构建前端静态资源，不启动生产 PHP 服务：

```bash
npm run build
```

构建产物位于：

```text
public/js/build/
public/manifest.json
```

停止本地中间件：

```bash
./cmd local-down
```

## 生产部署

生产部署使用构建好的 `project-v版本号-提交号.tar.gz`，应用在 Ubuntu 宿主机以 PHP 8.4 + LaravelS/Swoole 进程运行，不使用应用容器。MySQL、Redis、Manticore 可以部署在独立服务器或容器中。

配置文件约定：仓库只提交 `.env.template` 作为配置模板；复制为 `.env` 后填写实际值。`.env` 是生效配置，包含密码和密钥，已被 Git 忽略，禁止提交到远端。SMTP 邮箱和其他业务设置由管理员在后台配置，不放入 `.env`。

### 1. 构建生产包

在构建机执行：

```bash
./scripts/package.sh
```

脚本会依次执行：

- `npm install`
- 前端生产构建
- `composer install --no-dev`
- 生成包含 `vendor` 和前端静态资源的生产包

输出目录：

```text
output/project-v版本号-提交号.tar.gz
```

生产包不包含：

- `.env`
- `node_modules`
- Git 历史
- 运行日志

### 2. 上传并解压

在 Ubuntu 服务器执行：

```bash
sudo mkdir -p /opt/yeying
sudo tar -xzf project-v版本号-提交号.tar.gz -C /opt/yeying
cd /opt/yeying
```

### 3. 安装 Ubuntu 运行依赖

第一次部署或 PHP/Swoole 未安装时执行：

```bash
sudo ./scripts/ubuntu-deps.sh --install
```

该脚本负责安装：

- PHP 8.4、PHP CLI 和 PHP 开发包
- Swoole
- MySQL、Redis、LDAP、GMP、cURL、XML、DOM、mbstring、GD、Imagick、ZIP、FFI 等 PHP 扩展
- Composer
- Swoole 编译依赖和基础运维工具

安装后检查：

```bash
./scripts/ubuntu-deps.sh --check
```

应至少看到：

```text
OK command: php
OK php extension: swoole
OK command: composer
```

### 4. 配置生产环境

```bash
cp .env.template .env
```

编辑 `.env`，至少配置：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名

DB_CONNECTION=mysql
DB_HOST=MySQL地址
DB_PORT=3306
DB_DATABASE=yeying
DB_USERNAME=yeying
DB_PASSWORD=数据库密码

REDIS_HOST=Redis地址
REDIS_PORT=6379

SEARCH_HOST=Manticore地址
SEARCH_PORT=9306

LARAVELS_LISTEN_IP=127.0.0.1
LARAVELS_LISTEN_PORT=2222
RUNTIME_DRIVER=opensource
```

宿主机运行时请将 `DB_HOST`、`DB_PORT`、`REDIS_HOST` 和 `REDIS_PORT` 配置为宿主机可访问的实际地址。模板已经按本地中间件暴露端口填写默认值，生产环境请替换为实际数据库和 Redis 地址。

不要把生产 `.env` 提交到 Git。生产 SMTP 邮箱在管理员后台的系统邮箱设置中配置，不写入 README 或源码。

### 5. 初始化应用

```bash
./scripts/install.sh
```

该命令会：

- 生成 `APP_KEY`
- 创建 Laravel 可写目录
- 安装生产 Composer 依赖
- 执行数据库迁移
- 清理配置、路由和视图缓存
- 修复 `storage` 和 `bootstrap/cache` 权限

### 6. 启动和管理服务

```bash
./scripts/starter.sh start
./scripts/starter.sh status
./scripts/starter.sh restart
./scripts/starter.sh stop
```

日志：

```text
storage/logs/starter.log
storage/logs/laravel.log
```

启动脚本只负责启动 PHP/LaravelS，不负责安装 PHP。若出现 `PHP 8.4 is not installed`，先执行：

```bash
sudo ./scripts/ubuntu-deps.sh --install
./scripts/install.sh
```

### 7. 生产入口

建议让 LaravelS 只监听 `127.0.0.1:2222`，前面使用 Caddy 或 Nginx 提供：

- HTTPS
- 域名访问
- WebSocket 转发
- 上传大小和超时配置
- 静态资源缓存

不要把生产 `.env`、MySQL 密码和 SMTP 授权码写入仓库。

## 常用运维命令

```bash
./cmd repassword       # 重置管理员密码
./cmd help              # 查看命令帮助
```

生产升级前请先备份数据库和 `public/uploads`。升级、备份、systemd 自动启动和恢复流程详见 `docs/`。

## License

MIT License。第三方组件和原始版权声明以各自许可证为准。
