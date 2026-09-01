@extends('admin.layout')
@section('title','أكواد الخصم')
@section('content')
<header class="page-header finance-page-hero commerce-page-hero promo-page-hero">
    <div>
        <div class="eyebrow">العروض</div>
        <h1>أكواد الخصم</h1>
        <p>أنشئ عروضاً موجهة للاشتراكات أو الدورات، وتابع استخدامها من شاشة واحدة واضحة.</p>
    </div>
    <div class="promo-page-actions">
        <button type="button" class="button button-primary curriculum-create-trigger" data-inline-form-toggle="add-promo-code-panel" aria-controls="add-promo-code-panel" aria-expanded="{{ $errors->any() || old('code') !== null ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة كود خصم</button>
        <a class="button button-secondary" href="{{ route('admin.commerce.index') }}">ملخص التجارة</a>
    </div>
</header>

<section class="grid promo-summary-grid" aria-label="ملخص أكواد الخصم">
    <article class="card compact-stat"><span class="stat-icon">%</span><div><strong>{{ $promoSummary['total'] }}</strong><span>كل الأكواد</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">✓</span><div><strong>{{ $promoSummary['active'] }}</strong><span>أكواد نشطة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">⏱</span><div><strong>{{ $promoSummary['scheduled'] }}</strong><span>مجدولة لاحقاً</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">!</span><div><strong>{{ $promoSummary['expired'] }}</strong><span>منتهية</span></div></article>
</section>

<section class="curriculum-inline-create promo-inline-create" id="add-promo-code-panel" aria-labelledby="add-promo-code-panel-title" @unless($errors->any() || old('code') !== null) hidden @endunless>
    <form class="curriculum-inline-create-form promo-create-form" method="post" action="{{ route('admin.commerce.coupons.store') }}" data-processing data-processing-text="جارٍ إنشاء الكود...">@csrf
        <div class="curriculum-inline-create-header"><div class="curriculum-dialog-title"><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 8h14M5 16h14"/></svg></span><div><div class="eyebrow">كود جديد</div><h2 id="add-promo-code-panel-title">إنشاء كود خصم</h2><p>حدد قيمة الخصم ونطاقه، ثم سيظهر مباشرة داخل القائمة.</p></div></div><button class="curriculum-inline-close" type="button" data-inline-form-close="add-promo-code-panel" aria-label="إغلاق نموذج إضافة كود الخصم">×</button></div>
        @if($errors->any())<div class="curriculum-create-errors" role="alert"><strong>راجع بيانات كود الخصم</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="curriculum-create-fields promo-create-fields">
            <label><span>الكود</span><input class="latin promo-code-input" name="code" value="{{ old('code') }}" required maxlength="100" placeholder="BACK2SCHOOL"></label>
            <label><span>نوع الخصم</span><select name="type"><option value="percent" @selected(old('type') === 'percent')>نسبة مئوية</option><option value="fixed" @selected(old('type') === 'fixed')>قيمة ثابتة</option></select></label>
            <label><span>القيمة</span><input type="number" name="value" value="{{ old('value') }}" min="0.01" step="0.01" required placeholder="10"><small>للنسبة المئوية استخدم 10 بدلاً من 0.10.</small></label>
            <label><span>الحد الأدنى</span><input type="number" name="minimum_amount" value="{{ old('minimum_amount') }}" min="0" step="0.01" placeholder="اختياري"></label>
            <label><span>لكل طالب</span><input type="number" name="max_per_user" value="{{ old('max_per_user', 1) }}" min="1" required></label>
            <label><span>إجمالي الاستخدام</span><input type="number" name="max_redemptions" value="{{ old('max_redemptions') }}" min="1" placeholder="بدون حد"></label>
            <label><span>ينتهي في</span><input type="date" name="expires_at" value="{{ old('expires_at') }}"></label>
            <div class="promo-targeting-panel full">
                <strong>نطاق تطبيق الكود</strong>
                <small>اترك القوائم فارغة ليعمل الكود على كل المنتجات. اختر خططاً أو دورات لتقييده.</small>
                <div class="form-grid">
                    <label>خطط الاشتراك<select name="subscription_plan_ids[]" multiple>@foreach($subscriptionPlans as $plan)<option value="{{ $plan->id }}" @selected(collect(old('subscription_plan_ids', []))->contains($plan->id))>{{ $plan->name_ar }}</option>@endforeach</select></label>
                    <label>الدورات<select name="course_ids[]" multiple>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(collect(old('course_ids', []))->contains($course->id))>{{ $course->title }}</option>@endforeach</select></label>
                </div>
            </div>
        </div>
        <div class="curriculum-inline-create-footer"><div class="curriculum-create-note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>يمكنك إيقاف الكود أو تفعيله من القائمة بعد إنشائه.</span></div><div class="curriculum-inline-actions"><button type="button" class="button button-quiet" data-inline-form-close="add-promo-code-panel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> إلغاء</button><button class="button button-primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إنشاء الكود</button></div></div>
    </form>
</section>

<section class="card promo-list-card">
    <div class="section-heading">
        <div>
            <div class="eyebrow">العروض الحالية</div>
            <h2>قائمة الأكواد</h2>
        </div>
        <span class="badge published">{{ $coupons->count() }} كود</span>
    </div>
    <div class="promo-code-list">
        @forelse($coupons as $coupon)
            @php
                $isExpired = $coupon->expires_at?->isPast();
                $isScheduled = $coupon->starts_at?->isFuture();
                $scopeLabel = $coupon->subscriptionPlans->isNotEmpty()
                    ? 'اشتراكات'
                    : ($coupon->courses->isNotEmpty() ? 'دورات' : 'كل المنتجات');
                $scopeItems = $coupon->subscriptionPlans->isNotEmpty()
                    ? $coupon->subscriptionPlans->pluck('name_ar')->join('، ')
                    : ($coupon->courses->isNotEmpty() ? $coupon->courses->pluck('title')->join('، ') : 'ينطبق على الاشتراكات والدورات غير المقيدة.');
                $limitLabel = $coupon->max_redemptions ? $coupon->redeemed_count.' / '.$coupon->max_redemptions : $coupon->redeemed_count.' / ∞';
                $couponCourseIds = $coupon->courses->pluck('id');
                $couponPlanIds = $coupon->subscriptionPlans->pluck('id');
            @endphp
            <article class="promo-code-row {{ $coupon->is_active && ! $isExpired ? 'is-active' : 'is-muted' }}">
                <div class="promo-code-main">
                    <span class="promo-code-token latin">{{ $coupon->code }}</span>
                    <div>
                        <strong>{{ number_format((float)$coupon->value) }} {{ $coupon->type === 'percent' ? '%' : 'EGP' }}</strong>
                        <small>{{ $coupon->type === 'percent' ? 'خصم بنسبة مئوية' : 'خصم بقيمة ثابتة' }}</small>
                    </div>
                </div>
                <div class="promo-code-scope">
                    <span class="badge draft">{{ $scopeLabel }}</span>
                    <small>{{ $scopeItems }}</small>
                </div>
                <div class="promo-code-usage">
                    <strong>{{ $limitLabel }}</strong>
                    <small>{{ $coupon->reserved_count }} محجوزة · لكل طالب {{ $coupon->max_per_user }}</small>
                </div>
                <div class="promo-code-state">
                    @if($isExpired)
                        <span class="badge inactive">منتهي</span>
                    @elseif($isScheduled)
                        <span class="badge draft">مجدول</span>
                    @else
                        <span class="badge {{ $coupon->is_active ? 'published' : 'inactive' }}">{{ $coupon->is_active ? 'نشط' : 'متوقف' }}</span>
                    @endif
                    <small>{{ $coupon->expires_at ? 'ينتهي '.$coupon->expires_at->format('Y/m/d') : 'بدون تاريخ انتهاء' }}</small>
                </div>
                <form method="post" action="{{ route('admin.commerce.coupons.toggle',$coupon) }}">
                    @csrf
                    <button class="button button-quiet">{{ $coupon->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                </form>
                <details class="promo-code-details">
                    <summary class="button button-secondary">إدارة</summary>
                    <div class="promo-code-details-grid">
                        <form class="promo-edit-form" method="post" action="{{ route('admin.commerce.coupons.update', $coupon) }}" data-processing data-processing-text="جارٍ حفظ التعديلات...">
                            @csrf @method('put')
                            <h3>تعديل الكود</h3>
                            <div class="promo-edit-fields">
                                <label><span>الكود</span><input class="latin promo-code-input" name="code" value="{{ old('code', $coupon->code) }}" required maxlength="100"></label>
                                <label><span>نوع الخصم</span><select name="type"><option value="percent" @selected(old('type', $coupon->type) === 'percent')>نسبة مئوية</option><option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>قيمة ثابتة</option></select></label>
                                <label><span>القيمة</span><input type="number" name="value" value="{{ old('value', $coupon->value) }}" min="0.01" step="0.01" required></label>
                                <label><span>الحد الأدنى</span><input type="number" name="minimum_amount" value="{{ old('minimum_amount', $coupon->minimum_amount) }}" min="0" step="0.01"></label>
                                <label><span>لكل طالب</span><input type="number" name="max_per_user" value="{{ old('max_per_user', $coupon->max_per_user) }}" min="1" required></label>
                                <label><span>إجمالي الاستخدام</span><input type="number" name="max_redemptions" value="{{ old('max_redemptions', $coupon->max_redemptions) }}" min="1" placeholder="بدون حد"></label>
                                <label><span>يبدأ في</span><input type="date" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d')) }}"></label>
                                <label><span>ينتهي في</span><input type="date" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}"></label>
                                <label class="promo-active-toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active))><span>الكود نشط</span></label>
                                <label><span>خطط الاشتراك</span><select name="subscription_plan_ids[]" multiple>@foreach($subscriptionPlans as $plan)<option value="{{ $plan->id }}" @selected($couponPlanIds->contains($plan->id))>{{ $plan->name_ar }}</option>@endforeach</select></label>
                                <label><span>الدورات</span><select name="course_ids[]" multiple>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($couponCourseIds->contains($course->id))>{{ $course->title }}</option>@endforeach</select></label>
                            </div>
                            <div class="promo-edit-actions">
                                <button class="button button-primary">حفظ التعديلات</button>
                            </div>
                        </form>
                        <div class="promo-usage-panel">
                            <h3>آخر الاستخدامات</h3>
                            @forelse($coupon->redemptions->take(8) as $redemption)
                                <div class="promo-usage-row">
                                    <div>
                                        <strong>{{ $redemption->user?->name ?? 'طالب محذوف' }}</strong>
                                        <small class="latin">{{ $redemption->user?->email ?? '—' }}</small>
                                    </div>
                                    <div>
                                        <span class="badge {{ $redemption->status === 'redeemed' ? 'published' : 'draft' }}">{{ $redemption->status === 'redeemed' ? 'مستخدم' : 'محجوز' }}</span>
                                        <small>{{ number_format((float)$redemption->discount_amount) }} EGP · {{ $redemption->order?->public_id }}</small>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">لم يستخدم أي طالب هذا الكود بعد.</div>
                            @endforelse
                            <form method="post" action="{{ route('admin.commerce.coupons.destroy',$coupon) }}" data-confirm="{{ $coupon->redemptions->isNotEmpty() ? 'لا يمكن حذف كود له استخدامات. يمكنك إيقافه بدلاً من ذلك.' : 'حذف كود الخصم نهائياً؟' }}">
                                @csrf @method('delete')
                                <button class="button button-danger" @disabled($coupon->redemptions->isNotEmpty())>حذف الكود</button>
                            </form>
                        </div>
                    </div>
                </details>
            </article>
        @empty
            <div class="empty-state promo-empty"><strong>لا توجد أكواد خصم بعد.</strong><span>اضغط على إضافة كود خصم لإنشاء أول عرض، ثم سيظهر هنا مع نطاقه واستخدامه.</span><button type="button" class="button button-primary academic-empty-action" data-inline-form-toggle="add-promo-code-panel" aria-controls="add-promo-code-panel" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة كود خصم</button></div>
        @endforelse
    </div>
</section>
@endsection
