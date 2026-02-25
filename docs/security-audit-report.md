# Admin9 API 安全审计报告

> 审计日期：2026-02-25
> 审计范围：基于 `docs/security-audit-dimensions.md` 的 32 项安全维度
> 项目定位：Laravel 12 REST API 骨架项目（JWT + Spatie Permission + Service Layer）
> 说明：仅审计脚手架自身应保障的安全性，不涉及下游项目按部署环境定制的配置项。

## 审计结果总览

- ✅ 通过：28 项
- ⚠️ 需修复：4 项（#3、#8、#15、#31）

---

## 全景总览

| # | 维度 | 状态 | 类别 |
|---|------|------|------|
| 1 | JWT 生命周期合理性 | ✅ 通过 | 认证 |
| 2 | Refresh Token 滥用防护 | ✅ 通过 | 认证 |
| 3 | 密码策略强度 | ⚠️ 需修复 | 认证 |
| 4 | 密码重置流程安全性 | ✅ 通过 | 认证 |
| 5 | 登录失败处理 | ✅ 通过 | 认证 |
| 6 | Permission 中间件覆盖完整性 | ✅ 通过 | 授权 |
| 7 | 超级管理员保护边界一致性 | ✅ 通过 | 授权 |
| 8 | 批量赋权越权升级 | ⚠️ 需修复 | 授权 |
| 9 | Mass Assignment 防护 | ✅ 通过 | 授权 |
| 10 | SQL 注入防护 | ✅ 通过 | 输入验证 |
| 11 | XSS 攻击防范 | ✅ 通过 | 输入验证 |
| 12 | CSRF 适用性确认 | ✅ 通过 | 输入验证 |
| 13 | 请求验证覆盖率 | ✅ 通过 | 输入验证 |
| 14 | 密码存储安全 | ✅ 通过 | 敏感数据 |
| 15 | JWT Secret 管理 | ⚠️ 需修复 | 敏感数据 |
| 16 | 环境变量保护 | ✅ 通过 | 敏感数据 |
| 17 | API 响应数据脱敏 | ✅ 通过 | 敏感数据 |
| 18 | 审计日志覆盖完整性 | ✅ 通过 | 日志审计 |
| 19 | 日志信息泄露 | ✅ 通过 | 日志审计 |
| 20 | 日志文件权限与存储安全 | ✅ 通过 | 日志审计 |
| 21 | 异常处理机制 | ✅ 通过 | 错误处理 |
| 22 | 生产环境错误脱敏 | ✅ 通过 | 错误处理 |
| 23 | 堆栈跟踪暴露 | ✅ 通过 | 错误处理 |
| 24 | 事务一致性 | ✅ 通过 | 数据库 |
| 25 | 依赖版本锁定 | ✅ 通过 | 数据库 |
| 26 | 各端点限流配置合理性 | ✅ 通过 | 速率限制 |
| 27 | 测试覆盖率 | ✅ 通过 | 代码质量 |
| 28 | 架构分层一致性 | ✅ 通过 | 代码质量 |
| 29 | 重复造轮子 | ✅ 通过 | 代码质量 |
| 30 | 命名规范与最佳实践 | ✅ 通过 | 代码质量 |
| 31 | CLI 命令生产环境访问控制 | ⚠️ 需修复 | 部署安全 |
| 32 | Seeder 生产环境禁用机制 | ✅ 通过 | 部署安全 |

---

## 一、认证与会话管理

### 1. JWT 生命周期合理性 ✅

- Access Token TTL：60 分钟（`config/jwt.php:92`），合理
- Refresh TTL：20160 分钟 / 14 天（`config/jwt.php:121`），可接受
- `refresh_iat => false`（`config/jwt.php:120`），刷新窗口从首次签发算起，不会无限滚动续期
- 必需 claims 包含 `iss, iat, exp, nbf, sub, jti`（`config/jwt.php:148-155`），完整
- `lock_subject => true`（`config/jwt.php:192`），防止跨模型 token 冒用
- 算法 `HS256`（`config/jwt.php:135`），对称签名，适合单体应用

### 2. Refresh Token 滥用防护 ✅

- `refresh()` 调用 `$this->auth->refresh(true, true)`（`AuthController.php:94`），旧 token 立即加入黑名单
- 刷新端点有速率限制 `throttle:10,1`（`routes/api.php:23`）
- 刷新时检查用户是否被禁用（`AuthController.php:87-101`）

### 3. 密码策略强度 ⚠️ 需修复

- 登录/重置验证仅 `min:8, max:128`，无复杂度要求（`AuthRequest.php:16`，`ResetPasswordRequest.php:14`）
- CLI 命令（`SuperAdminCreate.php:31-35`，`SuperAdminResetPassword.php:34-38`）完全无密码强度校验

**修复建议：** 添加 `Password::min(8)->mixedCase()->numbers()` 规则；CLI 命令中添加密码强度验证。

### 4. 密码重置流程安全性 ✅

- 使用 Laravel 内置 `Password::broker()`（`UserService.php:161`）
- Token 有效期 60 分钟（`config/auth.php:101`），节流 60 秒（`config/auth.php:102`）
- 成功重置后自动删除 token，保证单次使用
- 超级管理员密码重置受保护：非本人不可触发（`UserService.php:154-158`）

### 5. 登录失败处理 ✅

- 登录端点有速率限制 `throttle:5,1`（`routes/api.php:20`）
- 登录失败会记录审计日志（`AuthService.php:9-14`）
- 登录失败响应不区分"用户不存在"和"密码错误" — 正确的安全实践

---

## 二、授权与访问控制

### 6. Permission 中间件覆盖完整性 ✅

所有 system 路由均在 `auth:api` + `permission:xxx` 双重守卫下，无裸露端点。公开端点均有独立限流。`EnsureUserIsActive` 全局注册在 `api` 组中（`bootstrap/app.php:23-25`）。

### 7. 超级管理员保护边界一致性 ✅

所有 Service/Controller 一致守护 super-admin 不可变规则，覆盖修改、角色同步、禁用、密码重置、角色 CRUD、列表隐藏等全部场景。详见 `UserService.php:40-158`、`RoleService.php:24-113`。

### 8. 批量赋权越权升级 ⚠️ 需修复

- `syncRoles()` 已验证 role_ids 存在性、guard_name、不可包含 super-admin（`UserService.php:89-98`）

**风险：** 拥有 `users.update` 权限的操作者可给目标用户分配任意非 super-admin 角色，包括拥有比操作者自身更高权限的角色。同理 `roles.update` 可修改任意角色的菜单权限。

**修复建议：**
- `syncRoles()` 中校验操作者只能分配自己已拥有的角色（或其子集）
- 或将 `users.update`（基本信息）和角色分配拆分为两个独立权限

### 9. Mass Assignment 防护 ✅

所有 Model 的 `$fillable` 严格限定。User 的 `is_active` 不在 fillable 中，仅通过 `forceFill` 修改。`password` 有 `'hashed'` cast 自动哈希。`$hidden` 包含 `password, remember_token`。

---

## 三、输入验证与注入防护

### 10. SQL 注入防护 ✅

全局未使用原始 SQL 查询，所有操作通过 Eloquent/Query Builder 完成。Filter 类均继承 `AbstractFilter`，声明式规则定义，不涉及手动拼接。路由参数通过 `Route::whereNumber()` 约束（`routes/system.php:23`）。

### 11. XSS 攻击防范 ✅

所有字符串类型用户输入字段均使用 `regex:/^[^<>]*$/` 验证，禁止 HTML 标签注入。`name` 等字段使用 `alpha_dash`，`permission` 使用 `regex:/^[a-zA-Z0-9_.]+$/`。项目为纯 JSON API，XSS 风险面极小。

### 12. CSRF 适用性确认 ✅

无状态 JWT 架构，不依赖 Cookie 身份验证。API 中间件组未注册 `VerifyCsrfToken`。`supports_credentials => false`（`config/cors.php:32`）。配置正确。

### 13. 请求验证覆盖率 ✅

所有写入端点 100% 覆盖 FormRequest。DELETE 端点无请求体，仅依赖路由参数（已通过 `whereNumber` 约束）。所有 Controller 写入方法均使用 `$request->validated()` 获取数据。

---

## 四、敏感数据处理

### 14. 密码存储安全 ✅

- User 模型使用 `'password' => 'hashed'` cast（`User.php:53`），自动 bcrypt 哈希
- `BCRYPT_ROUNDS=12`（`.env.example:16`），符合 OWASP 推荐
- 密码重置使用 Token-Based 链接，不传输明文密码

### 15. JWT Secret 管理 ⚠️ 需修复

- `config/jwt.php:18` 从 `env('JWT_SECRET')` 读取密钥，正确
- `.env.example` 中缺少 `JWT_SECRET` 条目，新开发者可能遗漏此配置

**修复建议：** 在 `.env.example` 中添加：
```
JWT_SECRET=
# Generate with: php artisan jwt:secret
```

### 16. 环境变量保护 ✅

- `.gitignore` 已覆盖 `.env`、`.env.backup`、`.env.production`
- `storage/*.key` 已排除
- `APP_DEBUG=true` 仅在 `.env.example` 中，生产环境需手动设为 `false`

### 17. API 响应数据脱敏 ✅

- User `$hidden` 包含 `password, remember_token`（`User.php:40-43`）
- `AuthController::me()` 使用白名单模式仅返回 `id, name, email, roles`（`AuthController.php:65-68`）
- `respondWithToken()` 仅返回 `access_token, token_type, expires_in`（`AuthController.php:126-131`）
- 审计日志通过 `logExcept(['password'])` 排除密码字段（`User.php:67`）

---

## 五、日志与审计

### 18. 审计日志覆盖完整性 ✅

使用 `spatie/laravel-activitylog` 实现数据库级审计。模型级通过 `LogsActivity` trait 自动记录变更，业务操作通过 `activity()` helper 手动记录。`AppServiceProvider` 自动注入 `ip`、`user_agent`、`request_method`、`request_url`。

### 19. 日志信息泄露 ✅

- User 模型 `logExcept(['password'])`，密码不入日志
- 登录失败仅记录 email，不记录密码尝试
- 密码重置审计不记录 token 或密码

### 20. 日志文件权限与存储安全 ✅

- 审计日志存数据库（`activity_log` 表），保留 365 天（`config/activitylog.php:14`）
- 应用日志在 `storage/logs/`，已 gitignore
- 根目录 `.gitignore` 包含 `*.log`

---

## 六、错误处理与信息泄露

### 21. 异常处理机制 ✅

全局异常处理器拦截所有异常并转为 JSON 响应。`BusinessException` → 业务错误消息，`AuthenticationException` → "Unauthenticated"，`ValidationException` → 验证错误详情，`HttpException` → HTTP 错误，其他 → "Something went wrong"。JWT 异常映射为 `AuthenticationException`（`bootstrap/app.php:34`）。

### 22. 生产环境错误脱敏 ✅

`APP_DEBUG=false` 时，未知异常仅返回 `"Something went wrong"` + 500，不暴露类名、文件路径、错误消息。Eloquent 严格模式仅在非生产环境启用（`AppServiceProvider.php:40`）。

### 23. 堆栈跟踪暴露 ✅

生产环境不返回异常类名、文件路径、行号、堆栈跟踪。`BusinessException` 仅暴露开发者指定的消息和错误码。

---

## 七、数据库与数据一致性

### 24. 事务一致性 ✅

所有多步操作均使用 `DB::transaction()`：UserService（创建+分配角色、更新+同步角色、状态切换）、RoleService（创建/更新+同步菜单权限、删除用悲观锁 `lockForUpdate()`）、MenuService（创建、更新+权限迁移、删除+清理孤立权限）。多处使用 `DB::afterCommit()` 确保审计日志仅在事务提交后写入。

### 25. 依赖版本锁定 ✅

`composer.lock` 已提交到版本控制，`.gitignore` 中未排除。

---

## 八、速率限制与抗滥用

### 26. 各端点限流配置合理性 ✅

| 端点 | 限流 |
|------|------|
| `POST /api/auth/login` | `throttle:5,1` |
| `POST /api/auth/refresh` | `throttle:10,1` |
| `POST /api/password/reset` | `throttle:5,1` |
| `GET /api/health` | `throttle:30,1` |
| `/api/system/*` 全局 | `throttle:60,1` |
| `PATCH users/{user}/status` | `throttle:10,1`（嵌套） |
| `POST users/{user}/reset-password` | `throttle:10,1`（嵌套） |

---

## 九、代码质量与部署安全

### 27. 测试覆盖率 ✅

约 23 个测试文件，195+ 测试用例。覆盖认证、授权、超级管理员保护、审计事件、密码重置、CLI 命令、路由约束等关键路径。

### 28. 架构分层一致性 ✅

所有模块严格遵守 Controller → Service → Model 分层。Controller 仅负责验证输入、委托 Service、返回响应。审计日志统一在 Service 层完成。

### 29. 重复造轮子 ✅

充分利用 Laravel 生态：密码重置用 `Password::broker()`，权限用 `spatie/laravel-permission`，审计用 `spatie/laravel-activitylog`，过滤器用 `mitoop/laravel-query-builder`，树形结构用 `staudenmeir/laravel-adjacency-list`。

### 30. 命名规范与最佳实践 ✅

路由使用 kebab-case + dot notation 命名，控制器 PascalCase，权限 `resource.action` 格式，数据库字段 snake_case，Service/Filter 命名统一。均符合 Laravel 社区惯例。

### 31. CLI 命令生产环境访问控制 ⚠️ 需修复

`super-admin:create`（`SuperAdminCreate.php:17-49`）和 `super-admin:reset-password`（`SuperAdminResetPassword.php:17-48`）：
- 无生产环境确认提示（缺少 `confirmToProceed()`）
- 无密码强度校验（`SuperAdminCreate` 甚至接受空密码）
- 无 CLI 操作审计日志

**修复建议：**
1. 添加 `$this->confirmToProceed()` 生产环境确认
2. 添加密码强度验证逻辑
3. 添加 CLI 操作审计日志记录

### 32. Seeder 生产环境禁用机制 ✅

`DatabaseSeeder.php:17-19` 使用 `is_prod()` 检查，在任何数据操作前返回。子 Seeder 通过 `DatabaseSeeder` 调用，受到保护。

---

## 修复清单

| 优先级 | 维度 | 问题 | 修复方向 |
|--------|------|------|----------|
| P0 | #8 批量赋权越权 | 操作者可分配超出自身权限的角色 | `syncRoles()` 校验操作者角色子集 |
| P1 | #3 密码策略强度 | 仅 min:8，CLI 无校验 | 添加 `Password::min(8)->mixedCase()->numbers()` |
| P1 | #31 CLI 生产防护 | 无确认、无密码校验、无审计 | 添加 `confirmToProceed()` + 密码验证 + 审计 |
| P2 | #15 JWT Secret 占位 | `.env.example` 缺少条目 | 添加 `JWT_SECRET=` + 注释 |
