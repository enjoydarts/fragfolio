# fragfolio

AI-powered fragrance portfolio management application with React 19 RSC, Laravel 12, multi-language support, and Cloudflare Turnstile security.

## 技術スタック

- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: React 19 with Server Components (RSC), Node.js 24
- **Database**: MySQL 8.4 with sqldef for schema management
- **Styling**: Tailwind CSS
- **AI APIs**: OpenAI GPT & Anthropic Claude (user selectable)
- **Authentication**: Cloudflare Turnstile, WebAuthn/FIDO2
- **Internationalization**: react-i18next (Japanese/English)
- **State Management**: Zustand
- **Development**: Docker & Docker Compose

## プロジェクト構成

```
fragfolio/
├── backend/          # Laravel 12 API
├── frontend/         # React 19 RSC application
├── database/         # SQL schema files for sqldef
├── docker/           # Docker configuration
└── docs/            # Documentation
```

## 開発コマンド

### Docker環境

```bash
# 環境起動
docker-compose up -d

# 環境停止
docker-compose down

# 環境再構築
docker-compose build
```

### Laravel Backend

```bash
# パッケージインストール
composer install

# 依存関係更新
composer update

# 開発サーバー起動
php artisan serve

# テスト実行
php artisan test

# コード整形
./vendor/bin/pint

# 静的解析
./vendor/bin/phpstan analyse
```

### React Frontend

```bash
# パッケージインストール
npm ci

# 開発サーバー起動
npm run dev

# プロダクションビルド
npm run build

# テスト実行
npm run test

# リント
npm run lint

# フォーマット
npm run format
```

### データベース管理

**重要: データベースエラーが発生した場合は必ずsqldefを実行すること**

```bash
# スキーマ適用（正しいコマンド）
docker-compose exec backend sh -c "mysqldef -u root -prootpassword -h mysql fragfolio < /var/www/html/sqldef/schema.sql"

# テスト用データベースのスキーマ適用
docker-compose exec backend sh -c "mysqldef -u root -prootpassword -h mysql fragfolio_test < /var/www/html/sqldef/schema.sql"

# スキーマドライラン（変更確認）
docker-compose exec backend sh -c "mysqldef -u root -prootpassword -h mysql fragfolio --dry-run < /var/www/html/sqldef/schema.sql"

# シーダー実行
docker-compose exec backend php artisan db:seed

# キューワーカー管理
docker-compose restart queue-worker
docker-compose logs queue-worker
```

### 品質管理

```bash
# 全テスト実行
make test

# リント実行
make lint

# リント修正
make lint-fix
```

## 環境変数

### Backend (.env)
```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=fragfolio
DB_USERNAME=fragfolio
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@fragfolio.local"

OPENAI_API_KEY=your_openai_key
ANTHROPIC_API_KEY=your_anthropic_key
TURNSTILE_SITE_KEY=your_turnstile_site_key
TURNSTILE_SECRET_KEY=your_turnstile_secret_key
```

### Frontend (.env)
```
REACT_APP_API_URL=http://localhost:8002/api
REACT_APP_TURNSTILE_SITE_KEY=your_turnstile_site_key
```

## 翻訳ファイル

### 多言語対応の翻訳ファイル場所

#### フロントエンド (React i18next)
- **日本語**: `frontend/src/i18n/locales/ja.json`
- **英語**: `frontend/src/i18n/locales/en.json`

#### バックエンド (Laravel)
- **日本語**: `backend/lang/ja/` ディレクトリ内
  - `auth.php` - 認証関連メッセージ
  - `validation.php` - バリデーションメッセージ
  - `turnstile.php` - Turnstile関連メッセージ
- **英語**: `backend/lang/en/` ディレクトリ内（同様の構成）

**重要**: 新しい翻訳キーを使用する際は、必ず対応する翻訳ファイルに追加すること。

## 主要機能

- 香水コレクション管理
- AIを活用したブランド・香水名正規化
- マルチ言語対応（日英）
- 管理者・一般ユーザー権限管理
- セキュアな認証システム（WebAuthn/FIDO2対応）
- 香りノート管理システム
- 着用ログ追跡
- 他言語対応（日本語・英語）

## 開発フロー

1. Docker環境起動
2. データベーススキーマ適用（sqldef）
3. シーダーでマスターデータ投入
4. フロントエンド・バックエンド開発サーバー起動
5. テスト駆動開発
6. CI/CD パイプライン実行

## アクセスURL

- **フロントエンド**: http://localhost:3002
- **バックエンドAPI**: http://localhost:8002
- **phpMyAdmin**: http://localhost:8082
- **Mailpit (メールテスト)**: http://localhost:8025
- **MySQL**: localhost:3308

## 開発規約（必須遵守）

### コーディング規約
- **多言語対応**: 全てのユーザー向けテキストは翻訳キー（t()）を使用。ハードコーディング禁止
- **UseCase分離**: ビジネスロジックは必ずUseCaseクラスに分離。Controllerに直接記述禁止
- **use文**: 可読性のため全てのインポート文を明示。省略禁止
- **設定外部化**: URL等の設定値はconfig/envから取得。ハードコーディング禁止
- フロントで確認や通知、アラートなどは共通のダイアログ・トーストなどを使うこと

### 開発環境規約
- **Docker使用**: 開発環境はDocker Compose使用。ローカルコマンド実行禁止
- **コンテナ実行**: `docker-compose exec backend`、`docker-compose exec frontend`でコマンド実行

### アーキテクチャ規約
- **クリーンアーキテクチャ**: ビジネスロジックはUseCaseに集約
- **責任分離**: Controller → UseCase → Repository の流れを厳守
- **依存性注入**: コンストラクタインジェクションを使用

### 品質管理
- **テスト必須**: 新機能は必ずテストコード作成
- **静的解析**: PHPStan、ESLintの警告解消必須
- **コード整形**: Pint、Prettierでのフォーマット必須
