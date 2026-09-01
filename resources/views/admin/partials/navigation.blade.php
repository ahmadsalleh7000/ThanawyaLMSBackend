@php($admin = auth()->user())
<nav class="admin-nav" aria-label="أقسام لوحة الإدارة">
    <div class="nav-group">
        <p class="nav-group-label">الرئيسية</p>
        <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="home" label="نظرة عامة" />
    </div>

    @if($admin->hasAdminPermission('content.manage') || $admin->hasAdminPermission('instructors.manage'))
    <div class="nav-group">
        <p class="nav-group-label">التعلّم والمحتوى</p>
        @if($admin->hasAdminPermission('content.manage'))
            <x-admin.nav-link :href="route('admin.academics.index')" :active="request()->routeIs('admin.academics.*')" icon="analytics" label="الهيكل الأكاديمي" />
            <x-admin.nav-link :href="route('admin.learning-paths.index')" :active="request()->routeIs('admin.learning-paths.*')" icon="course" label="المسارات التعليمية" />
            <x-admin.nav-link :href="route('admin.courses.index')" :active="request()->routeIs('admin.courses.*', 'admin.lessons.*', 'admin.assessment.*', 'admin.questions.*', 'admin.media.*')" icon="course" label="الدورات والمحتوى" />
            <x-admin.nav-link :href="route('admin.question-bank.index')" :active="request()->routeIs('admin.question-bank.*')" icon="question" label="بنك الأسئلة" />
            <x-admin.nav-link :href="route('admin.media-library.index')" :active="request()->routeIs('admin.media-library.*')" icon="media" label="مكتبة الوسائط" />
        @endif
        @if($admin->hasAdminPermission('instructors.manage'))<x-admin.nav-link :href="route('admin.instructors.index')" :active="request()->routeIs('admin.instructors.*')" icon="teacher" label="المدرسون" />@endif
    </div>
    @endif

    @if($admin->hasAdminPermission('users.manage') || $admin->hasAdminPermission('enrollments.manage') || $admin->hasAdminPermission('announcements.manage'))
    <div class="nav-group">
        <p class="nav-group-label">الطلاب والتواصل</p>
        @if($admin->hasAdminPermission('users.manage'))
            <x-admin.nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users" label="الطلاب والأسر" />
            <x-admin.nav-link :href="route('admin.support.index')" :active="request()->routeIs('admin.support.*')" icon="support" label="الدعم والمتابعة" />
            <x-admin.nav-link :href="route('admin.support-tickets.index')" :active="request()->routeIs('admin.support-tickets.*')" icon="announce" label="طلبات الدعم" />
            <x-admin.nav-link :href="route('admin.contact-messages.index')" :active="request()->routeIs('admin.contact-messages.*')" icon="announce" label="رسائل التواصل" />
        @endif
        @if($admin->hasAdminPermission('enrollments.manage'))<x-admin.nav-link :href="route('admin.enrollments.index')" :active="request()->routeIs('admin.enrollments.*')" icon="enrollment" label="التسجيلات والصلاحيات" />@endif
        @if($admin->hasAdminPermission('announcements.manage'))<x-admin.nav-link :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')" icon="announce" label="الإعلانات" />@endif
    </div>
    @endif

    @if($admin->hasAdminPermission('finance.manage'))
    <div class="nav-group">
        <p class="nav-group-label">المالية والتجارة</p>
        <x-admin.nav-link :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')" icon="orders" label="الطلبات" />
        <x-admin.nav-link :href="route('admin.payment-reviews.index')" :active="request()->routeIs('admin.payment-reviews.*')" icon="payment" label="مراجعة التحويلات" />
        @if($admin->hasAdminPermission('reconciliation.manage'))<x-admin.nav-link :href="route('admin.reconciliation.index')" :active="request()->routeIs('admin.reconciliation.*')" icon="reconcile" label="المطابقة المالية" />@endif
        @if($admin->hasAdminPermission('pricing.manage'))
            <x-admin.nav-link :href="route('admin.commerce.index')" :active="request()->routeIs('admin.commerce.index')" icon="orders" label="ملخص التجارة" />
            <x-admin.nav-link :href="route('admin.commerce.subscription-plans.index')" :active="request()->routeIs('admin.commerce.subscription-plans.*')" icon="orders" label="خطط الاشتراك" />
            <x-admin.nav-link :href="route('admin.commerce.coupons.index')" :active="request()->routeIs('admin.commerce.coupons.*')" icon="payment" label="أكواد الخصم" />
            <x-admin.nav-link :href="route('admin.commerce.payment-settings.index')" :active="request()->routeIs('admin.commerce.payment-settings.*')" icon="payment" label="قنوات التحويل" />
            <x-admin.nav-link :href="route('admin.commerce.bundles.index')" :active="request()->routeIs('admin.commerce.bundles.*')" icon="course" label="الباقات" />
        @endif
    </div>
    @endif

    @if($admin->hasAdminPermission('analytics.view') || $admin->hasAdminPermission('trust.manage'))
    <div class="nav-group">
        <p class="nav-group-label">القياس والجودة</p>
        @if($admin->hasAdminPermission('analytics.view'))<x-admin.nav-link :href="route('admin.analytics.index')" :active="request()->routeIs('admin.analytics.*')" icon="analytics" label="التحليلات والتقارير" />@endif
        @if($admin->hasAdminPermission('trust.manage'))<x-admin.nav-link :href="route('admin.trust.index')" :active="request()->routeIs('admin.trust.*')" icon="trust" label="الثقة والشهادات" />@endif
    </div>
    @endif

    @if($admin->hasAdminPermission('staff.manage') || $admin->hasAdminPermission('system.manage'))
    <div class="nav-group">
        <p class="nav-group-label">إدارة المنصة</p>
        @if($admin->hasAdminPermission('staff.manage'))<x-admin.nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')" icon="staff" label="فريق الإدارة" />@endif
        @if($admin->hasAdminPermission('system.manage'))<x-admin.nav-link :href="route('admin.system.index')" :active="request()->routeIs('admin.system.*')" icon="system" label="النظام والأمان" />@endif
        @if($admin->hasAdminPermission('system.manage'))<x-admin.nav-link :href="route('admin.privacy.index')" :active="request()->routeIs('admin.privacy.*')" icon="trust" label="الخصوصية والحذف" />@endif
    </div>
    @endif
</nav>
