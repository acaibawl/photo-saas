<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'NotoSansJP';
            font-weight: normal;
            font-style: normal;
            src: url('{{ resource_path('fonts/NotoSansJP-Regular.otf') }}');
        }
        @font-face {
            font-family: 'NotoSansJP';
            font-weight: bold;
            font-style: normal;
            src: url('{{ resource_path('fonts/NotoSansJP-Regular.otf') }}');
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'NotoSansJP', sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            color: #0f172a;
            background-color: #ffffff;
        }
        .header-bar {
            background-color: #047857;
            padding: 22px 0;
        }
        .service-name {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 2px;
        }
        .content {
            padding: 40px 50px 50px;
        }
        .card {
            border: 2px solid #a7f3d0;
            border-radius: 16px;
            background-color: #ecfdf5;
            padding: 32px 30px;
        }
        .kindergarten-name {
            font-size: 16px;
            color: #475569;
            margin-bottom: 10px;
        }
        .child-name {
            font-size: 30px;
            font-weight: bold;
            color: #065f46;
            margin-bottom: 14px;
        }
        .label-badge {
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            background-color: #d1fae5;
            border: 1px solid #6ee7b7;
            border-radius: 999px;
            padding: 6px 20px;
            margin-bottom: 26px;
        }
        .qr-box {
            display: inline-block;
            background-color: #ffffff;
            border: 2px solid #a7f3d0;
            border-radius: 12px;
            padding: 18px;
        }
        .qr {
            width: 240px;
            height: 240px;
        }
        .guide-text {
            font-size: 13px;
            color: #334155;
            margin-top: 22px;
            line-height: 1.7;
        }
        .invite-url {
            font-size: 11px;
            color: #64748b;
            margin-top: 16px;
            word-break: break-all;
        }
        .divider {
            border: none;
            border-top: 1px solid #a7f3d0;
            margin: 28px 0 20px;
        }
        .expires-at {
            font-size: 12px;
            color: #64748b;
        }
        .footer-note {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <div class="service-name">{{ config('app.name') }}</div>
    </div>

    <div class="content">
        <div class="card">
            <div class="kindergarten-name">{{ $kindergartenName }}</div>
            <div class="child-name">{{ $childName }}</div>
            <div class="label-badge">{{ $label }}</div>

            <div>
                <div class="qr-box">
                    <img class="qr" src="{{ $qrDataUri }}" alt="invitation qr code">
                </div>
            </div>

            <div class="guide-text">
                スマートフォンのカメラで上のQRコードを読み取り、<br>
                お子さまの写真の閲覧・購入手続きを行ってください。
            </div>

            <div class="invite-url">{{ $inviteUrl }}</div>
        </div>

        <hr class="divider">

        <div class="expires-at">有効期限: {{ $expiresAt->clone()->setTimezone('Asia/Tokyo')->format('Y年m月d日 H:i') }}</div>
        <div class="footer-note">この案内は保護者の方専用です。第三者へ共有しないようご注意ください。</div>
    </div>
</body>
</html>
