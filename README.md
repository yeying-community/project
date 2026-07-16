# YeYing - Open Source Task Management System

**[中文文档](./README_CN.md)** | English

YeYing is an open source task and project management system based on Laravel, LaravelS/Swoole and Vue 2. It is released under the MIT License.

## Local Development

In local development, PHP/LaravelS runs directly on the host. MySQL, Redis, Manticore and other middleware run in Docker containers. Node.js/npm is only needed for frontend development and builds.

### Requirements

- macOS or Linux
- PHP 8.4 with Swoole
- Composer
- Node.js 20+ and npm
- Docker 20.10+ and Docker Compose v2+

### Initialize and start

Run from the project root:

```bash
./cmd local-install
./cmd local-up
./cmd local-start
```

Open:

```text
http://127.0.0.1:2222
```

Default local middleware endpoints:

```text
MySQL      127.0.0.1:23306
Redis      127.0.0.1:26379
Manticore  127.0.0.1:9306
AppStore   127.0.0.1:19080 (if deployed)
```

Stop the host service and middleware:

```bash
./cmd local-stop
./cmd local-down
```

Run the frontend development server separately when needed:

```bash
./cmd dev
```

Build frontend assets without starting the PHP service:

```bash
npm run build
```

## Production Deployment

Production uses a generated `project-vVERSION-COMMIT.tar.gz` package. The application runs directly on Ubuntu with PHP 8.4 and LaravelS/Swoole. Node.js is not required on the production server.

### 1. Build the package

Run on the build machine:

```bash
./scripts/package.sh
```

The script installs frontend dependencies, builds static assets, installs production Composer dependencies and writes the package to `output/`.

The package excludes `.env`, `node_modules`, Git history and runtime logs.

### 2. Install Ubuntu dependencies

The repository only includes `.env.template` as the configuration template. Copy it to `.env` and fill in deployment-specific values. `.env` is the active configuration, contains secrets, is ignored by Git, and must never be committed. SMTP and other business settings are configured by an administrator in the web console.

After extracting the package on Ubuntu, run:

```bash
sudo ./scripts/ubuntu-deps.sh --install
./scripts/ubuntu-deps.sh --check
```

This installs PHP 8.4, Swoole, Composer, LDAP/GMP and the other required PHP extensions, MySQL/Redis client tools, and build/runtime tools. `scripts/starter.sh` does not install PHP by itself.

### 3. Configure and initialize

```bash
cp .env.template .env
# Edit .env with APP_URL, MySQL, Redis, Manticore and LaravelS settings.
./scripts/install.sh
```

At minimum configure `APP_ENV=production`, `APP_DEBUG=false`, the MySQL and Redis endpoints, and:

```dotenv
DOO_DRIVER=opensource
LARAVELS_LISTEN_IP=127.0.0.1
LARAVELS_LISTEN_PORT=2222
```

When LaravelS runs on the host, Docker Compose service names such as `mysql` and `redis` are not resolvable. Use `127.0.0.1` when those containers publish their ports on the host. `scripts/install.sh` normalizes these two names automatically for `APP_ENV=production` and enables the open-source runtime driver.

### 4. Start and manage

```bash
./scripts/starter.sh start
./scripts/starter.sh status
./scripts/starter.sh restart
./scripts/starter.sh stop
```

Logs are written to `storage/logs/starter.log` and `storage/logs/laravel.log`.

Use Caddy or Nginx in front of LaravelS for HTTPS, WebSocket forwarding, upload limits and static asset caching. Keep the application listener on `127.0.0.1` in production.

## Operations

```bash
./cmd repassword
./cmd help
```

Back up MySQL, `public/uploads` and production configuration before upgrades. See `docs/` for the deployment, backup and recovery details.

## License

MIT License. Third-party components remain under their respective licenses.
