@extends('admin.layout')
@section('title','خطط الاشتراك')
@section('content')
<header class="page-header finance-page-hero commerce-page-hero"><div><div class="eyebrow">اشتراك الطلاب</div><h1>خطط الاشتراك</h1><p>إدارة رسوم الفصل والعام الدراسي التي تظهر أثناء تسجيل الطالب.</p></div></header>
<div class="content-grid">
<main class="stack">
<section class="card"><div class="section-heading"><div><div class="eyebrow">الخطط الحالية</div><h2>رسوم الفصل والعام الدراسي</h2></div><span class="badge published">{{ $subscriptionPlans->where('is_active',true)->count() }} نشطة</span></div>
<div class="stack">@foreach($subscriptionPlans as $plan)<form class="detail-item" method="post" action="{{ route('admin.commerce.subscription-plans.update',$plan) }}">@csrf @method('put')<div class="form-grid" style="flex:1"><label>الاسم العربي<input name="name_ar" value="{{ $plan->name_ar }}" required></label><label>English name<input class="latin" name="name_en" value="{{ $plan->name_en }}" required></label><label>المدة<select name="billing_period"><option value="semester" @selected($plan->billing_period==='semester')>فصل دراسي</option><option value="year" @selected($plan->billing_period==='year')>عام كامل</option></select></label><label>السعر (EGP)<input type="number" name="price" min="0.01" step="0.01" value="{{ $plan->price }}" required></label><label>الترتيب<input type="number" name="display_order" min="0" value="{{ $plan->display_order }}" required></label><label>الحالة<select name="is_active"><option value="1" @selected($plan->is_active)>نشطة</option><option value="0" @selected(!$plan->is_active)>متوقفة</option></select></label></div><button class="button button-secondary">حفظ</button></form>@endforeach</div></section>
</main>
<aside class="stack profile-sidebar">
<section class="card"><div class="section-heading"><h2>خطة اشتراك جديدة</h2></div><form class="stack" method="post" action="{{ route('admin.commerce.subscription-plans.store') }}">@csrf<label>الكود<input class="latin" name="code" placeholder="semester-2" required></label><label>الاسم العربي<input name="name_ar" required></label><label>الاسم الإنجليزي<input class="latin" name="name_en" required></label><div class="form-grid"><label>المدة<select name="billing_period"><option value="semester">فصل</option><option value="year">عام</option></select></label><label>السعر<input type="number" name="price" min="0.01" step="0.01" required></label></div><input type="hidden" name="display_order" value="10"><button class="button button-primary">إضافة الخطة</button></form></section>
</aside>
</div>
@endsection
