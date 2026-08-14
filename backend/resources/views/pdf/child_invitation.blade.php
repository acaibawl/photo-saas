<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            padding-top: 60px;
        }
        .kindergarten-name {
            font-size: 20px;
            margin-bottom: 8px;
        }
        .child-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .label {
            font-size: 16px;
            color: #555555;
            margin-bottom: 24px;
        }
        .qr {
            width: 280px;
            height: 280px;
        }
        .invite-url {
            font-size: 12px;
            color: #555555;
            margin-top: 16px;
            word-break: break-all;
        }
        .expires-at {
            font-size: 12px;
            color: #888888;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="kindergarten-name">{{ $kindergartenName }}</div>
    <div class="child-name">{{ $childName }}</div>
    <div class="label">{{ $label }}</div>
    <img class="qr" src="{{ $qrDataUri }}" alt="invitation qr code">
    <div class="invite-url">{{ $inviteUrl }}</div>
    <div class="expires-at">有効期限: {{ $expiresAt->toRfc3339String() }}</div>
</body>
</html>
