@extends('admin.layout')
@section('title','ملخص التجارة')
@section('content')
<header class="page-header finance-page-hero commerce-page-hero"><div><div class="eyebrow">الإدارة المالية</div><h1>ملخص التجارة</h1><p>نظرة سريعة على الإيرادات والعروض وروابط إدارة كل جزء بشكل مستقل.</p></div></header>
<section class="grid finance-stats finance-overview-stats">
@foreach([['إجمالي القيمة',$summary['gross']],['الخصومات',$summary['discounts']],['المحصل',$summary['collected']],['المسترد',$summary['refunded']]] as [$label,$value])<article class="card compact-stat"><span class="stat-icon">£</span><div><strong>{{ number_format($value,2) }}</strong><span>{{ $label }} · EGP</span></div></article>@endforeach
</section>
<section class="grid">
<a class="card detail-item" href="{{ route('admin.commerce.subscription-plans.index') }}"><div><small>اشتراك الطلاب</small><strong>خطط الاشتراك</strong><span>{{ $subscriptionPlans->where('is_active', true)->count() }} نشطة من {{ $subscriptionPlans->count() }} خطة</span></div><span class="badge published">إدارة</span></a>
<a class="card detail-item" href="{{ route('admin.commerce.coupons.index') }}"><div><small>العروض</small><strong>أكواد الخصم</strong><span>{{ $couponsCount }} كود مسجل</span></div><span class="badge published">إدارة</span></a>
<a class="card detail-item" href="{{ route('admin.commerce.payment-settings.index') }}"><div><small>التحويل اليدوي</small><strong>قنوات الدفع</strong><span>InstaPay والمحافظ ومهلة الطلب.</span></div><span class="badge published">إعدادات</span></a>
<a class="card detail-item" href="{{ route('admin.commerce.bundles.index') }}"><div><small>المسارات</small><strong>الباقات</strong><span>{{ $bundlesCount }} باقة تعليمية</span></div><span class="badge published">إدارة</span></a>
</section>
@endsection
