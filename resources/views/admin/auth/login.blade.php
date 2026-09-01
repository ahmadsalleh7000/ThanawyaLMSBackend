<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1739">
    <meta name="color-scheme" content="light">
    <title>دخول الإدارة | Ahmed LMS</title>
    <link rel="icon" href="{{ asset('icons/icon.svg') }}?v={{ filemtime(public_path('icons/icon.svg')) }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
</head>
<body>
<main class="login-page">
    <section class="login-story" aria-labelledby="platform-title">
        <a class="login-brand" href="/">
            <span class="brand-mark" aria-hidden="true">A</span>
            <span class="brand-copy"><strong>Ahmed LMS</strong><small>أكاديمية البرمجة</small></span>
        </a>
        <div class="login-message">
            <span>مركز قيادة الأكاديمية</span>
            <h1 id="platform-title">أدر المحتوى.<br><em>تابع التقدم.</em></h1>
            <p>مساحة موحدة وآمنة لإدارة الدورات والدروس والتقييمات وحسابات الطلاب وأولياء الأمور.</p>
        </div>
        <div class="login-trust" aria-label="خصائص الأمان">
            <span>✓ جلسة إدارية آمنة</span><span>✓ حماية الطلبات</span><span>✓ صلاحيات دقيقة</span>
        </div>
    </section>
    <section class="login-panel" aria-label="تسجيل دخول الإدارة">
        <form class="login-card login-form" method="post" action="{{ route('admin.login.store') }}" novalidate>
            @csrf
            <header class="login-card-header">
                <span class="login-card-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></span>
                <div><div class="eyebrow">للمسؤولين فقط</div><h2>مرحباً بعودتك</h2><p>أدخل بيانات حساب الإدارة للمتابعة بأمان.</p></div>
            </header>
            @error('email')<div class="errors" role="alert">{{ $message }}</div>@enderror
            <div class="login-field">
                <label for="email">البريد الإلكتروني</label>
                <span class="input-shell">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                </span>
            </div>
            <div class="login-field">
                <label for="password">كلمة المرور</label>
                <span class="input-shell password-input-shell">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="إظهار كلمة المرور" title="إظهار كلمة المرور" aria-pressed="false">
                        <svg class="icon password-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon password-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.1A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6c1.7 0 3.1-.4 4.4-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </span>
            </div>
            <label class="remember-row"><input type="checkbox" name="remember" value="1"> <span>تذكر هذا الجهاز</span></label>
            <button class="button button-primary login-submit" type="submit"><span>دخول لوحة الإدارة</span><span class="login-submit-arrow" aria-hidden="true">←</span></button>
            <p class="login-footnote">الدخول مراقب ومسجل لحماية بيانات المتعلمين.</p>
        </form>
    </section>
</main>
</body>
</html>
