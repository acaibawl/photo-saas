# 共通コンポーネント設計書一覧

このディレクトリは、複数画面で再利用する Vue コンポーネントと composable の設計をまとめる。

## 前提

- Nuxt 4 + Composition API + script setup
- API は backend/routes/api.php を正本とする
- 画面固有ロジックは pages/composables 側に残し、共通 UI と共通振る舞いのみをここに集約する

## 一覧

1. 01_auth_guard_and_gate.md
2. 02_app_shell_layout.md
3. 03_filter_bar.md
4. 04_data_table.md
5. 05_form_field_group.md
6. 06_confirm_dialog.md
7. 07_photo_grid_and_card.md
8. 08_async_feedback.md
9. 09_pagination_query_sync.md
10. 10_status_badges.md

## 利用方針

- 画面の責務: API 呼び出し、ページ遷移、画面固有状態
- 共通コンポーネント責務: UI 表示、入力イベント、共通バリデーション表示、アクセシビリティ
- composable 責務: 認証ガード、クエリ同期、トースト通知、API エラー正規化

## 導入優先度

1. 認証ガード系（01, 02）
2. 入力・一覧基盤（03, 04, 05, 09）
3. 操作補助（06, 08, 10）
4. 写真ドメインUI（07）
