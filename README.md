# PHP Tools - Modular Website Building Toolkit
A modular PHP library for building websites with SSO authentication, community features, API gateway and unified exception handling.

## Features
- **SSO Module**: Token + AuthCode dual-factor session management with IP binding, support for MySQL/Redis storage.
- **Community Module**: User management, ACL permission control, post management, file upload, internal notifications, cache driver, logging, and CAPTCHA generation.
- **API Gateway**: Lightweight router, global middleware support (auth, rate limiting, versioning), unified JSON response and exception handling.
- **Unified Exception Handling**: Automatic detection of API/Web requests, returns structured JSON or debug HTML.
- **Out of the box**: PSR-4 autoloading, centralized configuration, minimal dependencies.

## Requirements
- PHP >= 8.0
- Composer
- MySQL 5.7+ (or MariaDB 10.2+)
- Redis (optional, for distributed rate limiting and SSO storage)

## Quick Start
### 1. Create project and install dependencies
```bash
composer require tiao2/php-tools
```

Or clone the repository manually and run:
```bash
composer install
```

### 2. Configure environment variables
Copy `.env.example` to `.env` and adjust settings according to your environment:
```bash
cp .env.example .env
```

Edit `.env` to set database connection, SSO expiration times, rate limiting parameters, etc. All modules are enabled by default and can be disabled via `*_ENABLED` variables.

### 3. Create database tables
Execute the following SQL to create the required tables (SSO sessions, users, posts, etc.):
```sql
-- See schema.sql in the project root (recommended to combine the SQL from the documentation into one file)
```

### 4. Implement user authentication interface
The SSO module requires you to provide real user verification logic. Create a class that implements `PhpTools\SSO\AuthenticatorInterface`, for example:
```php
use PhpTools\SSO\AuthenticatorInterface;

class MyAuthenticator implements AuthenticatorInterface
{
    public function authenticate(string $username, string $password): ?int
    {
        // Query database, verify password
        // Return user ID or null
    }
}
```

Then pass an instance of it to `SSO::fromEnv()` in your entry file `index.php`.

### 5. Configure web server
Point your website root to `public/` (if you use `public/index.php`) or directly to the project root's `index.php`. It is recommended to use `public/` as the public directory for better security.

### 6. Access the application
Visit your domain. If everything is configured correctly, you will see the default API response (or routes you defined).

## Directory Structure
```
my-php-tools/
├── .env.example                 # Environment variables template
├── composer.json
├── bootstrap.php                # Initialization (load env, exception handler)
├── index.php                    # Main entry point (route dispatching)
├── public/                      # Public resources (optional, recommended to move index.php here)
├── src/
│   ├── SSO/                     # SSO authentication module
│   ├── Community/               # Community features module
│   ├── API/                     # API gateway module
│   └── ModuleManager.php        # Top-level module manager
└── logs/                        # Log directory (created automatically)
```

## Module Overview
### SSO (Single Sign-On)
- Login: `$sso->login($username, $password)` returns token and authcode.
- Verification: `$sso->verify($token, $authcode, $ip)` returns user ID.
- Storage: Default MySQL, can be switched to Redis via `.env`.
- IP Binding: IPv4 exact match, IPv6 prefix match (default /64).

### Community
All sub-modules extend `CommunityBase` and use SSO for authentication. Sub-module switches are controlled by `COMMUNITY_*_ENABLED`.

| Sub-module | Main Features |
|------------|---------------|
| User       | User profile query and update |
| Acl        | Role-based permission checking |
| Content    | Post creation, listing, deletion (with permission checks) |
| File       | File upload, download, deletion (with type and size restrictions) |
| Notification | Send, query, mark-as-read for internal notifications |
| Cache      | File cache implementation (supports TTL) |
| Log        | Simple file logger |
| Captcha    | Math or text CAPTCHA generation |

### API Gateway
- Router: Supports `GET/POST/PUT/DELETE`, path parameters `{id}`.
- Middleware:
  - `AuthMiddleware`: Reads `X-API-Token` and `X-API-AuthCode` from headers for authentication.
  - `RateLimitMiddleware`: Supports in-memory or Redis rate limiting, returns 429 status with `Retry-After` header.
  - `VersionMiddleware`: Sets controller namespace prefix based on `X-API-Version` header.
- Exception Handling: Unified capture; API requests return JSON errors, HTML requests show a debug page (in development mode).

## Configuration Reference (.env)
| Variable | Description | Default |
|----------|-------------|---------|
| APP_DEBUG | Debug mode, shows detailed errors | false |
| SSO_ENABLED | Enable SSO module | true |
| COMMUNITY_ENABLED | Enable Community module | true |
| API_ENABLED | Enable API module | true |
| SSO_TOKEN_EXPIRE | Token expiration (seconds) | 3600 |
| SSO_AUTHCODE_EXPIRE | AuthCode expiration (seconds) | 3600 |
| SSO_IPV6_PREFIX | IPv6 prefix length to compare | 64 |
| DB_HOST / DB_PORT etc. | Database connection parameters | localhost |
| API_RATE_LIMIT_REQUESTS | Max requests per window | 100 |
| API_RATE_LIMIT_WINDOW | Rate limit window (seconds) | 60 |
| API_RATE_LIMIT_DRIVER | Rate limit driver (memory/redis) | memory |

> See `.env.example` for more configuration items

## Usage Examples
### 1. Enable SSO Login
```php
$sso = SSO::fromEnv(new MyAuthenticator());
$result = $sso->login('admin', 'password');
// Returns ['token' => '...', 'authcode' => '...']
```

### 2. Create a Post (Community Content)
```php
$postManager = new PostManager($sso, $pdo, $aclChecker);
$postId = $postManager->create('Hello World', 'Content...', $token, $authCode);
```

### 3. Register API Routes
```php
$router->get('/articles', function (Request $req) {
    // Public endpoint, no auth required
    return Response::json(['items' => []]);
});

$router->post('/articles', function (Request $req) {
    // Requires auth (AuthMiddleware ensures $req->userId is set)
    $data = $req->getJsonBody();
    // Create article...
    return Response::json(['id' => 123], 201);
});
```

## Extending
- New storage driver: Implement `StorageInterface`, instantiate in `SSO::fromEnv` as needed.
- New rate limiter: Implement `RateLimiterInterface`, inject into `RateLimitMiddleware`.
- New middleware: Implement `MiddlewareInterface`, register via `$router->addGlobalMiddleware()`.
- New Community sub-module: Extend `CommunityBase`, add dependencies in `ModuleManager`.

## License
This project is licensed under the Apache License 2.0 - see the LICENSE file for details.

## Contact
For questions or suggestions, please open an issue or pull request.
