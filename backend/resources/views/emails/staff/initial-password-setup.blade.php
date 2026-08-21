<x-mail::message>
{{ $staffName }} 様

「{{ $kindergartenName }}」の管理者アカウントが作成されました。

以下のリンクから初回パスワードを設定してください。

<x-mail::button :url="$setupUrl">
初回パスワード設定を開始する
</x-mail::button>

リンクの有効期限が切れた場合は、運営者に再発行を依頼してください。

よろしくお願いいたします。<br>
{{ config('app.name') }}
</x-mail::message>
