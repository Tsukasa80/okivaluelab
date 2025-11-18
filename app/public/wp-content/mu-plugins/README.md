# OVL μ-Plugin Stack

Members プラグイン側で遷移を管理する方針に合わせ、mu-plugins は表示専用の最小構成に再構築しました。旧コードはすべて `_archive-20251107/` に退避しているため、必要に応じて個別ファイルを戻せます。

## 構成

```
10-ovl-bootstrap.php   // OVL: 共通ヘルパと `OVL_ENABLE_REDIRECTS` 定義（デフォルト false）
20-ovl-ui-front.php    // OVL: body_class などフロント表示専用フック
25-ovl-docs.php        // OVL: 資料アップロード/ダウンロード用ヘルパ & ACFフック
27-ovl-docs-admin.php  // OVL: 物件編集画面に資料削除メタボックスを追加
30-ovl-shortcodes.php  // OVL: shortcodes/*.php を自動ロード
shortcodes/            // OVL: 1ファイル=1ショートコード
ovl-pages/             // OVL: 固定ページ描画ロジック (`[ovl_page slug="..."]`)
_archive-20251107/     // OVL: 旧 mu-plugins（リダイレクト/権限上書き含む）
```

※ `40-ovl-routing.php` と `routing/` ディレクトリは `_archive-20251107/` に移動済み。リダイレクト系は Members が担当し、mu-plugins からは読み込まれません。

## 固定ページ運用（Block Theme）

1. `ovl-pages/page-{slug}.php` を作成し、`ovl_get_template_context()` を受け取るクロージャを `return`。
2. 固定ページ本文を `[ovl_page slug="{slug}"]` 1本にする（ブロックテーマでも競合なし）。
3. 将来 `templates/page-{slug}.html` を追加してもショートコード方式を継続可能。

## ショートコードの追加手順

1. `shortcodes/sc-xxxx.php` を新規作成。
2. ファイル内で `function_exists` をガードしつつコールバックを定義。
3. `add_shortcode( 'xxxx', 'コールバック' );` を登録。保存だけ/リダイレクトなしで描画に専念する。

`[ovl_greeting]`、`[ovl_download_button]`、`[ovl_page]` が実装例です。

### 資料ダウンロード機能

- `acf/update_value/name=doc_url` フィルターでアップロードされたファイルを `private/docs/{post_id}/` に自動移動し、Public uploads から削除します。
- `[ovl_download_button]` はログイン済みユーザーにのみ署名付きURL（`/download.php?file=...`）を表示し、未ログイン時はログイン導線を返します。
- `download.php` は `ovl_get_doc_basename()` で管理されるファイルのみをストリームし、イベントフック `ovl/download_logged` でログを残します。
- 物件編集画面（`property` 投稿）のサイドメタボックスから、現在の資料名確認と「資料を削除」ボタンで `private/docs/{post_id}/` 内のファイルと `doc_url` メタを一括クリアできます。

## リダイレクト方針

- `OVL_ENABLE_REDIRECTS` は false 固定。Members プラグイン（Private Site 等）が遷移を制御します。
- カスタムリダイレクトを再導入したい場合は `_archive-20251107/` から該当ファイルを戻し、明示的に新フラグやモジュールを設計してください。

## テスト観点

1. 未ログインで `/` や `/property_list/` を開き、Members 設定どおりの遷移になること。
2. `[ovl_page slug="member-gate"]` を挿入した固定ページが表示専用で動作すること。
3. `[ovl_greeting]` がログイン/未ログインで文言を切り替えること。
4. `[ovl_download_button]` がログイン時のみリンクを表示し、リンク先 `/download.php?...` から資料を取得できること（未ログイン時は導線のみ）。
5. 物件編集画面の「資料を削除」ボタンで `private/docs/{post_id}/` と `doc_url` がクリアされること。
6. 不要なリダイレクトが発生せず、WP-Members を無効化してもエラーが出ないこと。
