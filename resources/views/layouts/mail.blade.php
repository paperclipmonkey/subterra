<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Notification')</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #222; margin: 0; padding: 0; }
        .mail-container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 32px; }
        h1 { color: #2c3e50; }
        p { line-height: 1.6; }
    </style>
</head>
<body>
    <div class="mail-container">
        @yield('content')
    </div>
</body>
</html>
