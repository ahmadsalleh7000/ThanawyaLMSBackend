@extends('admin.layout')
@section('title','الباقات')
@section('content')
<header class="page-header finance-page-hero commerce-page-hero"><div><div class="eyebrow">المسارات المدفوعة</div><h1>الباقات</h1><p>إدارة الباقات التي تجمع أكثر من دورة بسعر واحد.</p></div></header>
<div class="content-grid">
<main class="stack">
<section class="card"><div class="section-heading"><div><div class="eyebrow">الباقات</div><h2>مسارات متعددة بسعر واحد</h2></div><span class="badge published">{{ $bundles->count() }} باقة</span></div><div class="grid">@forelse($bundles as $bundle)<article class="detail-item"><div><small>{{ $bundle->courses->count() }} دورات</small><strong>{{ $bundle->name }}</strong><span>{{ $bundle->courses->pluck('title')->join(' · ') }}</span></div><div><strong>{{ number_format((float)$bundle->price) }} EGP</strong><form method="post" action="{{ route('admin.commerce.bundles.toggle',$bundle) }}">@csrf<button class="button button-quiet">{{ $bundle->is_active?'إيقاف':'تفعيل' }}</button></form></div></article>@empty<div class="empty-state">أنشئ أول باقة تعليمية.</div>@endforelse</div></section>
</main>
<aside class="stack profile-sidebar">
<section class="card"><div class="section-heading"><h2>باقة جديدة</h2></div><form class="stack" method="post" action="{{ route('admin.commerce.bundles.store') }}">@csrf<label>الاسم العربي<input name="name" required></label><label>الاسم الإنجليزي<input class="latin" name="name_en" required></label><label>السعر<input type="number" name="price" min="0.01" step="0.01" required></label><label>اختر دورتين على الأقل<select name="course_ids[]" multiple required>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label><button class="button button-primary">إنشاء الباقة</button></form></section>
</aside>
</div>
@endsection
