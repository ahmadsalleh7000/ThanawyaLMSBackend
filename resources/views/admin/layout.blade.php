<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1739">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'الإدارة') | Ahmed LMS</title>
    <link rel="icon" href="{{ asset('icons/icon.svg') }}?v={{ filemtime(public_path('icons/icon.svg')) }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
</head>
<body>
<a class="skip-link" href="#admin-content">انتقل إلى المحتوى</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="التنقل الإداري">
        <div class="sidebar-head">
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <span class="brand-mark" aria-hidden="true">A</span>
                <span class="brand-copy"><strong>Ahmed LMS</strong><small>مركز إدارة الأكاديمية</small></span>
            </a>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="إغلاق قائمة الإدارة">×</button>
        </div>
        @include('admin.partials.navigation')
        <div class="admin-account">
            <span class="account-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
            <span class="account-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ config('admin_roles.roles.'.auth()->user()->adminRole().'.label') }}</small></span>
            <form method="post" action="{{ route('admin.logout') }}">@csrf
                <button class="logout-button" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h4a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-4"/></svg>
                </button>
            </form>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close tabindex="-1" aria-label="إغلاق قائمة الإدارة"></button>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div class="topbar-start">
                <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="فتح قائمة الإدارة"><span></span><span></span><span></span></button>
                <div class="breadcrumb"><span>Ahmed LMS</span><span aria-hidden="true">/</span><strong>@yield('title', 'الإدارة')</strong></div>
            </div>
            <div class="topbar-meta">
                <details class="admin-notifications">
                    <summary aria-label="الإشعارات{{ $adminUnreadNotificationCount ? '، '.$adminUnreadNotificationCount.' غير مقروءة' : '' }}">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                        @if($adminUnreadNotificationCount)<span class="notification-count">{{ $adminUnreadNotificationCount > 99 ? '99+' : $adminUnreadNotificationCount }}</span>@endif
                    </summary>
                    <div class="admin-notification-menu">
                        <header>
                            <div><strong>الإشعارات</strong><small>{{ $adminUnreadNotificationCount ? $adminUnreadNotificationCount.' تحتاج انتباهك' : 'لا توجد إشعارات جديدة' }}</small></div>
                            @if($adminUnreadNotificationCount)
                                <form method="post" action="{{ route('admin.notifications.read-all') }}">@csrf<button type="submit">تعليم الكل كمقروء</button></form>
                            @endif
                        </header>
                        <div class="admin-notification-list">
                            @forelse($adminHeaderNotifications as $notification)
                                <form method="post" action="{{ route('admin.notifications.read', $notification) }}">
                                    @csrf
                                    <button class="admin-notification-item {{ $notification->read_at ? '' : 'is-unread' }}" type="submit">
                                        <span class="notification-item-icon" aria-hidden="true">
                                            @if($notification->type === 'student_registration')
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                                            @else
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                                            @endif
                                        </span>
                                        <span class="notification-item-copy"><strong>{{ $notification->title_ar ?: $notification->title }}</strong><small>{{ str($notification->message_ar ?: $notification->message)->limit(88) }}</small><time datetime="{{ $notification->created_at->toISOString() }}">{{ $notification->created_at->diffForHumans() }}</time></span>
                                        @if(!$notification->read_at)<i aria-label="غير مقروء"></i>@endif
                                    </button>
                                </form>
                            @empty
                                <div class="admin-notification-empty"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><strong>كل شيء هادئ</strong><span>ستظهر طلبات التسجيل والتنبيهات المهمة هنا.</span></div>
                            @endforelse
                        </div>
                    </div>
                </details>
                <span class="secure-pill"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>جلسة إدارية محمية</span>
            </div>
        </header>
        <main class="admin-main" id="admin-content" tabindex="-1">
            @include('admin.partials.background-art')
            @if(session('success'))<div class="flash" role="status"><strong>تم بنجاح</strong><span>{{ session('success') }}</span></div>@endif
            @if(session('error'))<div class="errors" role="alert"><div><strong>تعذر تنفيذ العملية</strong><ul><li>{{ session('error') }}</li></ul></div></div>@endif
            @if($errors->any())<div class="errors" role="alert"><div><strong>راجع البيانات المدخلة</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
