# 共通設計: ページネーションとURLクエリ同期

## 目的

- 一覧画面のページ番号・フィルタを URL と同期し、再訪時再現性を担保する。

## 対象

- composable: useQuerySync
- component: PaginationControl

## 想定利用画面

- 04_staff_children_list.md
- 06_staff_child_classes.md
- 07_staff_photo_management.md
- 13_guardian_photo_gallery.md
- 15_guardian_orders.md
- 16_guardian_purchased_photos.md

## useQuerySync 仕様

### 入力

- defaults: Record<string, unknown>
- parseRules: Record<string, QueryParser>

### URL → state 初期復元（hydration）

- 画面初期表示時に `route.query` を `parseRules` で解釈し、`state` を構築する。
- 各キーは次の優先順位で決定する:
	1. URLクエリの有効値
	2. `defaults` の値
- ブラウザの戻る/進むで URL が変わった場合も同じ規則で再解釈し、`state` を復元する。
- これにより再訪・戻る操作時は「有効な URL 状態」が優先され、不要に defaults へ戻さない。

### 不正クエリ値の扱い

- `parseRules` で不正（型不一致、範囲外、未知 enum）と判定された値は採用しない。
- 不正値は該当キーのみ `defaults` にフォールバックする（他キーは保持）。
- 必要に応じて正規化後のクエリへ `router.replace` で1回だけ反映し、URLを自己修復する。

### state → URL 同期

- `state` 変更時は `toQuery` でクエリへ変換し URL を更新する。
- URL 更新は操作種別で `replace` / `push` を使い分ける。
- API 再取得は URL 同期後の `state` を正として実行する。

### 返却

- state
- setState
- resetState
- toQuery

### URL更新ルール（replace / push）

- submit（検索実行）:
	- フィルタ確定時は `page=1`（cursor方式を採用する画面なら `cursor=null`）へリセットして `router.push`。
	- 履歴を残し、戻る操作で直前の検索条件へ戻れるようにする。
- reset（条件クリア）:
	- `defaults` に戻して `router.replace`。
	- クリア操作は履歴を増やさず、連続リセットで履歴汚染しない。
- pagination 変更（page/per_page）:
	- `changePage`: `router.push`（ページ遷移履歴を残す）。
	- `changePerPage`: `page=1` へ戻して `router.push`。
- 入力中の軽微な同期（例: debounce中間状態や不正値補正）:
	- `router.replace` を使い、履歴を増やさない。

## PaginationControl props

- currentPage: number
- perPage: number
- total: number

## events

- changePage
- changePerPage

## 実装メモ

- query 変更時の API 再取得を debounce し、連打による過剰通信を避ける。
