<x-mail::message>
{{ $invitedName }} 様

「{{ $kindergartenName }}」にスタッフとして招待されました。

以下のリンクから初回設定を行ってください。

<x-mail::button :url="$invitationUrl">
招待を受諾して初回設定を行う
</x-mail::button>

有効期限: {{ $expiresAt->toRfc3339String() }}

よろしくお願いいたします。<br>
{{ config('app.name') }}
</x-mail::message>
