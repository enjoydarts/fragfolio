# FragFolio

[![CI](https://github.com/enjoydarts/fragfolio/actions/workflows/ci.yml/badge.svg)](https://github.com/enjoydarts/fragfolio/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/enjoydarts/fragfolio/branch/main/graph/badge.svg)](https://codecov.io/gh/enjoydarts/fragfolio)

**FragFolio** — AI支援による香水コレクション管理アプリケーション

あなたの香水コレクションを効率的に管理し、AI技術を活用した高度な検索機能で新しい香水を発見できるWebアプリケーションです。

## 主な機能

- 🌟 **コレクション管理**: 香水の詳細情報を記録し、コレクションを体系的に整理
- 🔍 **AI検索**: AI技術を活用した高度な検索で新しい香水を発見
- 📊 **着用ログ**: 着用記録を管理し、使用パターンを分析
- 🌐 **多言語対応**: 日本語・英語の切り替え対応
- 🔐 **認証システム**: セキュアなユーザー登録・ログイン機能
- 👥 **管理者機能**: 香水データベースの管理機能

## 技術スタック

### バックエンド
- **フレームワーク**: Laravel 12
- **言語**: PHP 8.4
- **データベース**: MySQL 8.4
- **スキーマ管理**: sqldef
- **テスト**: Pest v4.1 + PHPUnit v12
- **静的解析**: PHPStan + Larastan
- **コードスタイル**: Laravel Pint

### フロントエンド
- **フレームワーク**: React 19
- **言語**: TypeScript
- **ビルドツール**: Vite
- **スタイリング**: Tailwind CSS
- **テスト**: Vitest + Testing Library + MSW
- **国際化**: i18next

### 開発・運用
- **CI/CD**: GitHub Actions
- **コンテナ**: Docker + Docker Compose
- **アーキテクチャ**: ARM64対応（Apple Silicon）
- **コードカバレッジ**: Codecov連携

## 開発環境セットアップ

### 必要な環境
- Docker & Docker Compose
- Git

### セットアップ手順

1. **リポジトリをクローン**
   ```bash
   git clone https://github.com/enjoydarts/fragfolio.git
   cd fragfolio
   ```

2. **初期セットアップを実行**
   ```bash
   make setup
   ```

3. **アプリケーションにアクセス**
   - バックエンド: http://localhost:8002
   - フロントエンド: http://localhost:3002
   - phpMyAdmin: http://localhost:8082

## 開発用コマンド

### 基本操作
```bash
# 開発環境起動
make dev

# ログ確認
make logs

# コンテナ停止
make down
```

### テスト実行
```bash
# 全テスト実行
make test

# バックエンドテストのみ
make test-backend

# フロントエンドテストのみ
make test-frontend
```

### コード品質チェック
```bash
# Lint実行
make lint

# Lint自動修正
make lint-fix

# コードフォーマット
make format
```

### データベース操作
```bash
# スキーマ適用
make db-schema

# シード実行
make db-seed

# データベースリセット
make db-reset
```

## プロジェクト構成

```
fragfolio/
├── backend/           # Laravel API
├── frontend/          # React SPA
├── database/          # データベーススキーマ（sqldef）
├── .github/           # GitHub Actions設定
├── docker-compose.yml # Docker構成
└── Makefile          # 開発用コマンド
```

## ライセンス

このプロジェクトはMITライセンスの下で公開されています。
