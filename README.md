# Auto Duea

删除「从未验证邮箱」的账号。

- 已验证邮箱的账号永远不会被删除；管理员（id=1、管理员用户组）也不会。
- 「宽限期」内的新注册账号不会被删除，避免误删刚注册还在等验证邮件的玩家。
- 自动删除默认**关闭**，需要在后台手动打开。

## 后台

安装并启用后，进入「扩展」页面点击 **Auto Duea**，可以看到：

- 未验证邮箱的账号数量、已过宽限期可删除的数量、累计已删除、上次运行时间；
- 「立即删除」按钮（会二次确认）；
- 自动删除开关、运行间隔（小时）、宽限期（天）、每批删除数量。

设置保存在 Flarum 的 settings 表中（`auto_duea_*`）。

## 命令行

```bash
php flarum auto-duea:status        # 查看统计
php flarum auto-duea:run            # 删除一批（默认 100 个，最多 500 个）
php flarum auto-duea:run --all      # 删除全部可删除的账号
php flarum auto-duea:run --limit=50 # 指定本次删除数量
php flarum auto-duea:sweep          # 按设置判断是否该自动清理（定时任务调用的就是它）
```

## 定时任务

插件通过 Flarum 自带调度器注册了 `auto-duea:sweep`（每小时检查一次，是否真的执行由「自动删除」开关和运行间隔决定）。需要在服务器上配置 cron：

```
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

没有配置 cron 时，自动删除不会运行，但后台的「立即删除」和命令行都可用。

## 说明

- 判断依据是 `users.is_email_confirmed`（Flarum 核心字段），不是第三方登录插件的虚拟邮箱前缀。
- 删除操作走 Eloquent 模型（会触发 Flarum 的 `Deleted` 事件并清理通知）。
