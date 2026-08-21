<x-mail::message>
メールアドレスの確認をお願いします。

以下のボタンをクリックして、メールアドレスの確認を完了してください。

<x-mail::button :url="$verificationUrl">
メールアドレスを確認する
</x-mail::button>

このメールに心当たりがない場合は、何も操作する必要はありません。

よろしくお願いいたします。<br>
{{ config('app.name') }}
</x-mail::message>
