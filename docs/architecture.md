# Universal Markdown Editor for WordPress：第一版架构

- 状态：Accepted；用户于 2026-11-04 明确接受第 12 节全部六项取舍
- 产品依据：#1
- 架构任务：#2
- 决策记录：[ADR 0001](adr/0001-markdown-editor-and-storage.md)
- 日期：2026-11-04

本文固定第一版的模块边界、编辑状态、数据契约、安全边界和兼容策略。本文不是实现完成证明；其中规划的文件和命令只有在对应 Issue 合并后才算真实存在。

## 1. 设计目标与取舍

### 1.1 必须成立

1. 插件只依赖 WordPress 和自身打包依赖，不依赖主题。
2. Markdown 原文可恢复，前台始终读取标准 `post_content`。
3. 预览和保存使用同一服务端转换管线，浏览器提交的 HTML 不可信。
4. 已有内容只有经过明确保存才改变，失败和冲突不能静默覆盖。
5. 停用插件不影响已保存 HTML 的展示。
6. 编辑器只在有权限的文章后台加载，私有原文不进入公开 REST。

### 1.2 核心选择

| 设计点 | 备选方案 | 选择 | 理由 |
| --- | --- | --- | --- |
| Gutenberg 集成 | 自定义区块；替换编辑器；插件入口 + 独立工作区 | `registerPlugin` 入口 + 全屏 Modal 工作区 | 不给 `post_content` 增加自定义区块定界符，不接管原生区块系统 |
| 预览转换 | 浏览器解析；双解析器；服务端解析 | 防抖调用服务端预览接口 | 与保存共用解析及过滤规则，牺牲少量网络延迟换取一致性和安全边界 |
| 内容模型 | 只存 Markdown；只存 HTML；双存储 | 私有 meta 存 Markdown，`post_content` 存安全 HTML | 同时满足继续编辑和停用后展示 |
| 保存方式 | 信任标准文章请求中的 HTML；专用接口 | 专用 REST 保存接口 | 能从源码重新生成 HTML，并执行权限、版本和冲突检查 |
| 已有 HTML 转 Markdown | 自动反转；用户明确导入；不处理 | 仅纯文本可直接导入；Gutenberg/HTML 不自动反转 | 任意区块和 HTML 反转可能丢语义，第一版优先保护原文 |
| 原生模式切换 | 双向实时同步；显式切换；禁止切换 | 共享已保存 HTML，原生修改形成可检测冲突 | 不伪造双向无损转换，仍保留原生编辑能力 |
| 代码高亮 | 强制前后台高亮；完全不做；可配置 | 保留 `language-*` 类，高亮默认关闭 | 避免与主题或其他插件重复处理 |

### 1.3 第一版不做

- 不支持 page、自定义文章类型、经典编辑器或站点编辑器。
- 不支持混合 Markdown/区块文档，不注册自定义区块。
- 不自动将任意 HTML、区块或短代码反向转换为 Markdown。
- 不支持原始 HTML 作为 Markdown 的可执行/可渲染扩展；原始 HTML 按文本转义。
- 不在前端加载高亮脚本，不实现 AI、协作、云同步或独立媒体系统。
- 不承诺 MySQL 多表写入的数据库级原子性；使用预校验、revision、快照和补偿恢复降低部分写入风险，并对恢复路径做集成测试。

## 2. 支持范围与依赖

### 2.1 运行基线

- WordPress：`>= 6.4`。选择该版本是因为其核心提供 opt-in post meta revisions 框架。
- PHP：`>= 8.1`。这是插件兼容下限，不是建议继续使用已停止安全维护的 PHP；生产环境应使用 WordPress 支持且仍受维护的 PHP。
- 文章类型：仅内置 `post`，且要求该文章类型启用 `revisions` 支持。若站点禁用 revisions，插件仍允许编辑，但明确提示恢复能力下降，不宣称可恢复。
- 构建环境：Node.js 22 LTS 和 npm；最终版本由 #3 的 `package.json`、lockfile 和文档固定。
- 浏览器：支持所选 WordPress 版本后台支持的现代浏览器；具体实测清单由 #8 记录，不提前声称全量兼容。

### 2.2 依赖方向与打包

| 依赖 | 许可证 | 用途 | 决策 |
| --- | --- | --- | --- |
| `league/commonmark` 2.x | BSD-3-Clause | 服务端 CommonMark 解析及表格、删除线等扩展 | 生产依赖；#3 选择兼容 PHP 8.1 的维护版本并写入 `composer.lock` |
| WordPress `wp_kses` 及 URL 协议过滤 | GPL-2.0-or-later | 生成 HTML 的第二道安全过滤 | 平台能力，不另造 HTML 白名单库 |
| `@wordpress/scripts` | GPL-2.0-or-later | Gutenberg 资源构建和检查 | 开发依赖；lockfile 固定 |
| WordPress 提供的 `@wordpress/*` 运行包 | GPL-2.0-or-later | 编辑器 UI、数据、组件、媒体库 | 构建时 external，运行时使用 WordPress 分发版本，不另打包 React |
| PHP-Scoper | MIT | 为 Composer 生产依赖加插件专属前缀 | 仅构建期使用；#3 验证其版本和 scoping 结果，发布 ZIP 不包含全局未隔离的 `League\\` 自动加载 |
| PHPUnit、PHPUnit Polyfills、WordPress 测试套件 | BSD-3-Clause、BSD-3-Clause、GPL-2.0-or-later | PHP 单元和 WordPress 集成验证 | 仅开发依赖；#3 固定真实配置 |
| Jest、Testing Library | MIT、MIT | JavaScript 单元和交互测试 | 仅开发依赖；#3 固定真实配置 |

插件自身以 `GPL-2.0-or-later` 发布，与 WordPress 生态兼容。上表记录直接依赖的许可证；#3 必须根据实际锁定版本复核 Composer/npm 元数据和传递依赖许可证，遇到不兼容或被撤销的许可证时停止引入。若插件许可证要与本决策不同，需先修订本节和 ADR，再开始打包。

生产 ZIP 只包含插件代码、插件许可证、构建后的后台资源、经过命名空间隔离的生产 vendor、直接依赖许可证和必要文档；不包含开发测试、node_modules、构建工具或秘密。依赖升级需重新跑解析差异、许可证检查、安全检查和冲突插件测试。

### 2.3 插件入口与版本策略

- 主入口固定为 `universal-markdown-editor.php`，文件头声明插件名称、`Version`、`Requires at least: 6.4`、`Requires PHP: 8.1` 和 `GPL-2.0-or-later`。
- 主入口先检查 PHP/WordPress 版本，再加载经 Composer 隔离的生产依赖并启动 `UniversalMarkdownEditor\\` 命名空间；兼容性不满足时只停止加载和显示后台提示，不触发文章写入。
- 插件版本采用 SemVer，并由 `UME_VERSION` 暴露；资产版本绑定插件版本，发布后版本和产物不可原地改写。首个开发版为 `0.1.0`，正式发布仍以 #8 的门禁为准。
- 插件版本与 `_ume_converter_version` 相互独立：前者描述发行物，后者只描述 Markdown 转换及存储输出契约。普通修复不提高 converter version，语义变化按第 11 节处理。
- 激活和停用钩子不得创建、修改或删除文章及插件 meta。当前版本不需要自定义数据库表或必须执行的激活迁移；卸载策略按 4.3 节保留数据。

## 3. 模块边界

规划命名空间为 `UniversalMarkdownEditor\\`，REST 命名空间为 `ume/v1`，JS/CSS handle 前缀为 `ume-`，hook 前缀为 `ume_`。插件主入口仅负责兼容检查和容器装配。

```text
Gutenberg sidebar/menu
        |
        v
Editor session + Toolbar + Media adapter
        |                         |
        | REST source only        | WordPress media library
        v                         v
REST Markdown Controller -----> permission + nonce + input limits
        |
        +---- Preview: Converter -> HtmlSanitizer -> response only
        |
        +---- Save: ConflictPolicy -> Converter -> HtmlSanitizer
                                      |
                                      v
                              Markdown SaveService
                                      |
                          +-----------+------------+
                          |                        |
                          v                        v
                 private post meta          standard post_content
```

| 模块 | 职责 | 不得承担 |
| --- | --- | --- |
| `Admin\EditorPlugin` | 注册文章后台入口、传递 REST root/nonce/能力配置 | 解析 Markdown、直接读写数据库 |
| `Admin\Assets` | 仅在 post 编辑页加载构建资源 | 前台全局加载、输出整篇私有原文 |
| `Editor\Session` | 管理加载、编辑、预览、保存、冲突、关闭状态 | 将预览 HTML 当作保存依据 |
| `Editor\Toolbar` | 选区感知地转换 Markdown 源码 | HTML 安全判断、媒体权限判断 |
| `Editor\MediaAdapter` | 调用 WordPress 媒体库，返回附件 URL/alt | 自行上传、绕过媒体能力、持久化 blob URL |
| `REST\MarkdownController` | 参数验证、权限、nonce 依赖、错误映射 | 解析规则细节、直接拼接 SQL |
| `Markdown\Converter` | 唯一 Markdown AST 到 HTML 转换入口 | 文章保存、Gutenberg 状态管理 |
| `Markdown\HtmlSanitizer` | 对转换结果执行标签、属性和协议白名单 | 接受客户端白名单或 HTML |
| `Post\MarkdownRepository` | 私有 meta 和文章快照读写 | UI、REST 请求解析 |
| `Post\SaveService` | 冲突校验、revision、写入顺序、失败补偿和返回 | 信任 base hash、跳过权限检查 |

依赖方向保持为：Admin/Editor → REST → application service → Markdown/Post；Markdown 核心不反向依赖 Gutenberg 或 REST。

## 4. 数据契约

### 4.1 字段

所有字段均为 `single=true`、下划线私有 key、`show_in_rest=false`。插件自定义 REST 在 `edit_post` 权限后读取，不开放 WordPress 通用 meta REST 写入。

| 位置/字段 | 类型 | 含义 | 空值语义 |
| --- | --- | --- | --- |
| `post_content` | string | 经服务端转换和过滤的标准 HTML，无自定义区块定界符 | 空字符串是合法空文章 |
| `_ume_markdown_source` | string | 用户最后成功保存的 UTF-8 Markdown 原文 | 空字符串是合法源码，不能据此判断文章是否受管 |
| `_ume_content_format` | string | 固定为 `markdown` 表示文章由本插件管理 | 字段不存在表示普通文章；其他值为不兼容/损坏状态 |
| `_ume_converter_version` | integer | 转换契约版本，第一版固定 `1` | 受管文章缺失或非正整数视为不兼容，不自动保存 |
| `_ume_content_hash` | string(64) | 最后成功保存的原始 `post_content` 的小写 SHA-256 | 受管文章缺失视为需要恢复/重存，不覆盖冲突 |
| `_ume_saved_source_hash` | string(64) | 最后成功保存 Markdown 字节的小写 SHA-256 | 用于一致性诊断，不作为唯一并发锁 |

`_ume_markdown_source`、`_ume_content_format`、`_ume_converter_version`、`_ume_content_hash`、`_ume_saved_source_hash` 均声明 `revisions_enabled=true`。权限回调不得使作者编辑他人文章或公开读取原文。字段名和格式属于第一版稳定契约，重命名必须有迁移和回滚方案。

### 4.2 不变量

成功保存后必须同时满足：

```text
content_format == "markdown"
converter_version == 1
markdown_source == request.source
post_content == sanitize(convert(markdown_source))
content_hash == hash("sha256", post_content)
saved_source_hash == hash("sha256", markdown_source)
```

- source 按 UTF-8 原文保存，不 trim、不自动规范化换行、不移除末尾换行；REST transport 只移除 WordPress 对参数增加的 slashes，不改变用户字节内容。
- `post_modified_gmt` 只作为辅助并发信号，内容 hash 才是受管文章的变更依据，避免同秒保存漏判。
- 非受管文章以其打开时的 `post_content` hash 和 `post_modified_gmt` 为基线。
- 渲染器 patch 升级不自动提高 converter version，也不批量重写旧文章；只有输出契约发生需要迁移的变化才递增版本并新增 ADR。
- 第一版没有旧字段迁移。未来版本按 converter version 读取，未显式迁移前不在前台动态重渲染。

### 4.3 Revision、停用与卸载

- 保存前保留当前文章内容及启用 revision 的插件 meta 快照；保存后创建新 revision，确保原文和 HTML 可对应恢复。实现必须使用 WordPress revision API，并通过集成测试验证实际顺序，不能只检查 meta 参数。
- 若站点关闭 revisions，界面显示恢复能力受限；失败时仍使用内存快照执行补偿，但不宣称具备历史恢复。
- 停用插件：不运行卸载逻辑，不过滤 `the_content`，已有 HTML 继续由主题展示，meta 全部保留。
- 卸载插件：第一版默认仍保留文章内容和插件 meta，不提供一键删除数据。
- 恢复 revision 后必须重新计算或恢复配套字段；检测到 source/hash/HTML 不一致时进入冲突提示，不自动选择一边覆盖另一边。

## 5. REST 契约

所有路由使用登录 Cookie + `X-WP-Nonce`。WordPress 负责 REST Cookie nonce 检查，每个 `permission_callback` 再执行 `current_user_can('edit_post', $id)`；上传媒体另由 WordPress 媒体流程检查上传权限。只有 JSON body，源码长度上限 1 MiB，ID 必须为正整数且 `post_type === 'post'`。

### 5.1 读取编辑会话

`GET /ume/v1/posts/{id}/markdown`

返回：

```json
{
  "postId": 123,
  "managed": true,
  "source": "# Title",
  "html": "<h1>Title</h1>\n",
  "contentFormat": "markdown",
  "converterVersion": 1,
  "contentHash": "64 lowercase hex characters",
  "sourceHash": "64 lowercase hex characters",
  "modifiedGmt": "2026-11-04T00:00:00+00:00",
  "revisionsEnabled": true
}
```

非受管文章返回 `managed=false`、`source=null`、当前 HTML 和基线 hash。返回 HTML 仅供有权限用户预览，不能在保存接口中回传为可信输入。

### 5.2 预览

`POST /ume/v1/posts/{id}/markdown/preview`

```json
{ "source": "# Title" }
```

成功返回 `html`、`converterVersion` 和 `sourceHash`。不更新文章、meta、revision 或 modified time。空字符串是合法输入。

### 5.3 保存

`PUT /ume/v1/posts/{id}/markdown`

```json
{
  "source": "# Title",
  "baseContentHash": "previous hash",
  "baseSourceHash": "previous source hash or null for an unmanaged post",
  "baseModifiedGmt": "previous modified time"
}
```

服务端重新加载文章，校验基线（受管文章同时比对 source hash，以避免不同 Markdown 源码渲染为相同 HTML 时静默覆盖），转换并过滤源码，然后保存 source、HTML、格式和 hash。响应与读取接口相同，以服务端结果为唯一成功状态。

错误契约：

| HTTP | 代码示例 | 含义与客户端行为 |
| --- | --- | --- |
| 400 | `ume_invalid_source` | 类型、长度或参数错误；保留本地源码 |
| 401/403 | WordPress 标准错误或 `ume_forbidden` | 重新登录或停止操作；不重试写入 |
| 404 | `ume_post_not_found` | 文章不存在或类型不支持 |
| 409 | `ume_edit_conflict` | 基线变化；保留本地源码，提供复制/下载和重新加载，不自动合并 |
| 413 | `ume_source_too_large` | 超过 1 MiB；不解析、不写入 |
| 422 | `ume_parse_failed`、`ume_unsupported_version` | 无有效完整输出；不进入文章写入 |
| 500 | `ume_save_failed` | 写入或补偿失败；保留本地源码并显示恢复提示，记录无敏感信息的错误日志 |

保存响应前重新读取数据库并校验不变量。校验失败不得返回成功。

## 6. 编辑器状态与交互

### 6.1 入口与 Gutenberg 隔离

使用 `registerPlugin`、`PluginSidebar`/`PluginSidebarMoreMenuItem` 提供入口，点击后打开全屏 `Modal`。不替换 Gutenberg store，不修改区块定界符，不将普通文章自动转成插件区块。

打开前置条件：编辑器已加载、当前为 post、用户可编辑、Gutenberg 没有未保存改动。若已有标题、区块或设置未保存，先引导用户使用原生保存，避免成功后的刷新丢失这些改动。

工作区打开期间使用公开的 `core/editor` `lockPostSaving` 和 `lockPostAutosaving`，并配对本插件 lock key；无论成功、失败、关闭或异常都释放自己的锁，不能解除其他插件的锁。保存成功并清理本地 dirty 状态后刷新编辑页，以服务端内容重新初始化 Gutenberg，避免手工维护一套块状态同步逻辑。

### 6.2 状态模型

会话包含：`postId`、`managed`、`source`、`savedSource`、`html`、基线 hash/modified、converter version、dirty、preview status、save status 和错误。

```text
closed
  -> preflight
       -> native-dirty: 提示先原生保存，不打开工作区
       -> loading
            -> import-prompt       (普通已有文章)
            -> clean               (受管文章)
            -> load-error
import-prompt
  -> clean (空源码，或纯文本导入；尚未写数据库)
  -> closed
clean/dirty
  -> previewing -> clean/dirty | preview-error
  -> saving -> save-success -> reload
            -> save-error (源码保留)
            -> conflict (源码保留)
dirty -> close-confirm -> closed
clean -> closed
```

- `savedSource` 只随打开或服务端保存成功改变；网络失败不得更新它。
- 每次源码变化使 dirty=true，并触发 400ms 防抖预览。请求带递增序号并用 `AbortController` 取消旧请求；只有最新序号可更新 HTML。
- 预览失败不阻塞继续编辑，也不清空旧 HTML；明确标记“预览可能过期”。保存不依赖预览成功。
- 关闭 dirty 会话、刷新页面或离开页面前提示；保存按钮重复点击只产生一个请求。
- conflict 不提供自动覆盖按钮。用户可复制/下载本地 Markdown，或明确重新加载服务端版本。
- 工具栏只操作 source 和 selection；选区替换后恢复合理焦点与选区，支持中文、空选区、撤销/重做和键盘操作。

### 6.3 预览安全与布局

预览使用服务端过滤后的 HTML。工作区采用 `sandbox` 无脚本权限的 iframe `srcDoc` 隔离预览，禁止脚本、表单提交和顶层导航；图片可正常加载。iframe 样式由插件提供，不带入主题后台 CSS。

桌面为源码/预览左右分栏；窄屏切换为“源码 / 预览”单栏标签，避免压缩出不可用小栏。所有工具按钮有可访问名称、焦点样式和键盘行为。

## 7. 新文章、已有文章和原生模式

### 7.1 新文章

Gutenberg auto-draft 已有 post ID 且当前用户可编辑时，创建本地空 Markdown 会话。第一次显式保存将 `auto-draft` 转为 `draft`；已有 `draft`、`pending`、`future`、`private` 或 `publish` 状态保持不变，发布仍走 WordPress 原生流程。预览接口不创建文章或改变状态。

### 7.2 普通已有文章

打开时不修改任何数据，先显示导入提示：

- 内容为空：从空源码开始。
- 不含区块定界符或 HTML 标签的纯文本/Markdown：可将当前 `post_content` 作为本地 source 导入，并先预览。
- 含 Gutenberg 区块、HTML、短代码或 embed：不自动反转。用户取消并保留原状，或明确新建空 Markdown 草稿后手工迁移；保存替换前再次提示影响范围。

首次保存前创建可恢复的 revision（若启用），并明确提示“保存后正文将由 Markdown 生成的 HTML 替换”。不能只因打开工作区、点击预览或停用插件而写入。

### 7.3 已受管文章

正常打开以 `_ume_markdown_source` 为源。若字段缺失、converter version 不支持、source hash 不匹配或当前 `post_content` hash 与 `_ume_content_hash` 不同，则进入恢复/冲突状态：

- 不自动将 HTML 反转成 Markdown。
- 不自动用旧 source 覆盖用户在原生模式做出的修改。
- 展示两个版本的时间和可预览内容，允许复制/下载 source、重新加载，或由用户明确选择以某一边继续；该选择再次经过保存冲突检查。

### 7.4 切回 Gutenberg

保存后的 HTML 可由 Gutenberg 原生模式解析和编辑，但这不代表双向同步。受管文章在原生编辑区显示提示；一旦原生保存改变 `post_content`，下次打开 Markdown 会检测 hash 冲突。原生编辑不会静默更新或删除 Markdown meta。

## 8. Markdown 转换与安全策略

### 8.1 唯一转换管线

`Markdown\Converter` 使用 `league/commonmark` 的 Environment 和核心解析器，只增加 MVP 需要的 Table、Strikethrough、Autolink 等扩展；不因使用完整 GFM 扩展而无意接受任务列表等额外语法。

固定配置：

```php
[
    'html_input' => 'escape',
    'allow_unsafe_links' => false,
    'max_nesting_level' => 100,
    'max_delimiters_per_line' => 1000,
]
```

另外限制源码 1 MiB。`html_input=escape` 表示用户写入的 HTML 作为文本显示，不执行；解析器输出再经过 `HtmlSanitizer`，不是安全流程的替代品。

### 8.2 HTML 与 URL 白名单

允许由 MVP 语法产生的标签：`p`、`h1`–`h6`、`strong`、`em`、`del`、`ul`、`ol`、`li`、`blockquote`、`pre`、`code`、`table`、`thead`、`tbody`、`tr`、`th`、`td`、`a`、`img`、`hr`、`br`。

属性仅允许必要集合：链接 `href/title`，图片 `src/alt/title/width/height`，代码 `class`，有序列表 `start`，表格单元格 `align`。使用 `wp_kses` 和 WordPress 协议过滤；`javascript:`、`vbscript:`、`file:`、所有 `data:` 和事件属性均拒绝。即使解析器允许部分 data image，本插件也不允许。

`class` 只用于 `language-*` 等解析器生成值；高亮设置默认 false。第一版不添加 `target=_blank`，因此无需依赖 `noopener` 修复反向 tabnabbing。

### 8.3 权限、隐私和资源边界

- nonce 不是授权替代；每个读取、预览和保存请求都检查具体 post 权限。
- meta 不出现在公开 REST、feed 或前台；后台 bootstrap 只发送 REST 配置，不输出全站或他人原文。
- 日志不记录完整 Markdown、文章 HTML、nonce、Cookie 或凭据，只记录 post ID、错误码和必要诊断。
- 预览不持久化，且受相同输入限制和权限保护，防止成为匿名高成本解析接口。
- 媒体上传使用 WordPress 原生能力和权限，插件不接受任意远程下载或 blob/data URL 持久化。

## 9. 保存完整性与故障恢复

保存服务按以下顺序执行：

1. 重新读取 post 和 meta，验证权限、post type、版本、基线 hash/modified。
2. 完整执行转换和过滤；失败时不开始数据库写入。
3. 读取旧 post_content 和全部插件 meta 作为恢复快照，按 WordPress API 创建保存前 revision（可用时）。
4. 使用原子唯一 option 锁按文章串行化保存（60 秒租期，异常退出后自动过期），在锁内重新读取基线；显式保存旧版本与新版本 revision，临时抑制 WordPress 对同一次文章更新的自动 revision hook，避免创建“新 HTML + 旧 Markdown meta”的不一致 revision；更新文章和 meta，随后重新读取并验证全部不变量。
5. 任一步骤失败时，使用快照执行补偿恢复并再次读取验证；返回 `ume_save_failed`，UI 保留本地源码。
6. 补偿也无法恢复时返回明确严重错误，记录 post ID 和失败阶段，提示用户不要继续覆盖并使用 revision/快照恢复；不得仍返回成功。

这是 WordPress 应用层一致性策略，不伪装成所有数据库都能提供的事务。集成测试必须注入文章更新、meta 更新和回读校验失败，验证旧内容、原文及恢复提示。

## 10. 测试与验收映射

| 层级 | 必须覆盖 | 对应 Issue |
| --- | --- | --- |
| PHP 单元 | 支持语法、空输入、恶意标签/属性/协议、深嵌套、超长输入、稳定输出 | #4 |
| JS 单元 | session 状态迁移、dirty、请求乱序、冲突、选区工具栏、中文输入 | #5、#7 |
| WordPress 集成 | 权限/nonce、双存储、不变量、自动保存锁、重复保存、revision、失败补偿、停用/卸载 | #6、#8 |
| 媒体集成 | 选择、取消、上传失败、无权限、稳定 URL、无 blob/data | #7、#8 |
| E2E | 新建、已有纯文本、Gutenberg 提示、预览、保存、刷新、冲突、原生模式、窄屏 | #5–#8 |
| 主题回归 | 一个默认主题和一个非 2BLOG 第三方主题；停用后 HTML 可读 | #8 |
| 构建与供应链 | PHP/JS 检查、依赖漏洞、scoped autoload、干净 ZIP、源 Commit、SHA-256 | #3、#8 |

#3 建立可重复命令后，每个实现 PR 必须给出真实退出码、测试数量和未覆盖项。本机无 PHP/WordPress 时不得把静态审查写成集成通过。

## 11. 版本演进

- `converter_version=1` 对应本文的语法、白名单和存储契约。
- 新增普通 Markdown 语法若改变已有输出，先写设计变更和 golden fixtures；必要时递增版本，但不在前台批量迁移。
- 私有字段重命名或语义变化必须有新 ADR、读写兼容期、按需迁移、失败回滚和备份说明。
- 第一版卸载不清理数据。未来提供清理功能时必须独立确认、备份和权限检查。
- 修改已接受架构时新增 ADR；本文保留历史判断，不静默重写旧决策。

## 12. 接受条件

#2 只有在用户确认以下关键取舍后才完成：

1. 采用 Gutenberg 插件入口 + 独立工作区，不采用自定义区块。
2. 采用服务端预览和保存共用转换管线。
3. 采用上述六个持久化字段及私有 REST 策略。
4. 接受已有 Gutenberg/HTML 不自动反转，以及原生编辑产生显式冲突的行为。
5. 接受 WordPress >=6.4、PHP >=8.1、第一版仅支持 post。
6. 接受 revision + 快照 + 补偿恢复，而非宣称跨表数据库事务。

用户接受后，按 #3 → #4 → #5 → #6 → #7 → #8 实施。若对其中任何一点选择其他方案，先新增或修订设计决策，再改变实现计划。

## 参考资料

- [WordPress：扩展文章编辑器 UI](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-edit-post/)
- [Gutenberg：core/editor 数据 API](https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/)
- [WordPress：自定义 REST endpoint 与 permission_callback](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [WordPress：REST Cookie 与 X-WP-Nonce](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [WordPress 6.4：Post Meta Revisions 框架](https://make.wordpress.org/core/2023/10/24/framework-for-storing-revisions-of-post-meta-in-6-4/)
- [WordPress：PHP 支持说明（2026）](https://make.wordpress.org/core/2026/05/22/php-support-clarification-2026/)
- [League CommonMark：不可信输入安全配置](https://commonmark.thephpleague.com/2.x/security/)
- [League CommonMark：2.x 升级与兼容说明](https://commonmark.thephpleague.com/2.x/upgrading/)
