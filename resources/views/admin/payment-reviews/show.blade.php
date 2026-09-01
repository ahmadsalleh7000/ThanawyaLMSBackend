@extends('admin.layout')
@section('title','مراجعة تحويل')
@section('content')
@php
    $statusLabels = [
        'submitted' => 'بانتظار المراجعة',
        'under_review' => 'قيد المراجعة',
        'approved' => 'تم الاعتماد',
        'rejected' => 'مرفوض',
        'needs_information' => 'يحتاج معلومات',
    ];
    $statusClass = match ($submission->status) {
        'approved' => 'published',
        'rejected' => 'inactive',
        default => 'draft',
    };
    $isRegistration = $submission->order->product_type === 'subscription';
@endphp

<div class="payment-review-page">
    <header class="payment-review-header">
        <div class="payment-review-heading">
            <div class="eyebrow">طلب <span class="latin">{{ $submission->order->public_id }}</span></div>
            <h1>مراجعة إثبات الدفع</h1>
            <p>تحقق من بيانات التحويل والإيصال قبل اعتماد {{ $isRegistration ? 'تسجيل الطالب' : 'الطلب' }}.</p>
            <div class="payment-review-meta" aria-label="بيانات الطلب المختصرة">
                <span><small>الطالب</small><strong>{{ $submission->user->name }}</strong></span>
                <span><small>تاريخ الإرسال</small><strong>{{ $submission->created_at->format('Y/m/d · H:i') }}</strong></span>
                @if($submission->order->beneficiary && $submission->order->beneficiary_user_id !== $submission->user_id)
                    <span><small>المستفيد</small><strong>{{ $submission->order->beneficiary->name }}</strong></span>
                @endif
            </div>
        </div>
        <div class="payment-review-header-actions">
            <span class="badge {{ $statusClass }}">{{ $statusLabels[$submission->status] ?? $submission->status }}</span>
            <a class="button button-quiet" href="{{ route('admin.payment-reviews.index') }}">العودة إلى القائمة</a>
        </div>
    </header>

    <section class="payment-review-comparison" aria-labelledby="payment-comparison-title">
        <div class="payment-proof-panel">
            <div class="payment-review-section-heading">
                <div><span class="section-label">إثبات الدفع</span><h2 id="payment-comparison-title">الإيصال المرفوع</h2></div>
                <small>{{ $submission->receipt_original_name }}</small>
            </div>
            <div class="payment-proof-viewport">
                @if(str_starts_with($submission->receipt_mime,'image/'))
                    <img src="{{ route('admin.payment-reviews.receipt',$submission) }}" alt="إيصال التحويل المقدم من {{ $submission->user->name }}">
                @else
                    <iframe title="إيصال التحويل" src="{{ route('admin.payment-reviews.receipt',$submission) }}"></iframe>
                @endif
            </div>
            <button type="button" class="payment-proof-link" data-dialog-open="payment-proof-dialog">عرض بالحجم الكامل</button>
        </div>

        <aside class="payment-information-panel" aria-labelledby="payment-information-title">
            <div class="payment-review-section-heading"><div><span class="section-label">البيانات المقدمة</span><h2 id="payment-information-title">معلومات التحويل</h2></div></div>
            <div class="payment-detail-group">
                <h3>الدفع</h3>
                <dl class="payment-detail-list">
                    <div><dt>طريقة الدفع</dt><dd>{{ config('payments.manual_transfer.channels.'.$submission->channel.'.label') }}</dd></div>
                    <div class="is-emphasis"><dt>المبلغ</dt><dd>{{ number_format((float)$submission->submitted_amount,2) }} <span class="latin">EGP</span></dd></div>
                    <div><dt>وقت التحويل</dt><dd>{{ $submission->transferred_at->format('Y/m/d · H:i') }}</dd></div>
                </dl>
            </div>
            <div class="payment-detail-group">
                <h3>المرسل والمرجع</h3>
                <dl class="payment-detail-list">
                    <div><dt>معرّف المرسل</dt><dd class="latin">{{ $submission->sender_identifier }}</dd></div>
                    <div class="is-emphasis"><dt>رقم العملية</dt><dd class="latin">{{ $submission->transaction_id }}</dd></div>
                    <div><dt>المبلغ المطلوب</dt><dd>{{ number_format((float)$submission->order->amount,2) }} <span class="latin">EGP</span></dd></div>
                </dl>
            </div>
        </aside>
    </section>

    @if($submission->matching_receipts_count>1)
        <section class="payment-risk-notice" aria-labelledby="duplicate-warning-title">
            <div><span class="section-label">يحتاج انتباهاً</span><h2 id="duplicate-warning-title">إيصال دفع محتمل التكرار</h2><p>ظهرت بصمة هذا الملف في <strong>{{ $submission->matching_receipts_count - 1 }}</strong> {{ $submission->matching_receipts_count - 1 === 1 ? 'عملية أخرى' : 'عمليات أخرى' }}. راجع السجلات المرتبطة قبل الاعتماد.</p></div>
            <div class="payment-risk-links">
                @foreach($submission->matchingReceipts->where('id','!=',$submission->id) as $match)
                    <a href="{{ route('admin.payment-reviews.show',$match) }}"><span>{{ $match->user->name }}</span><strong class="latin">{{ $match->transaction_id }}</strong></a>
                @endforeach
            </div>
        </section>
    @endif

    @if(in_array($submission->status,['submitted','under_review']))
        <section class="payment-decision-panel" aria-labelledby="review-decision-title">
            <div><span class="section-label">الخطوة الأخيرة</span><h2 id="review-decision-title">قرار المراجعة</h2><p>أكد أن الإثبات يطابق بيانات التحويل قبل اتخاذ القرار.</p></div>
            <div class="payment-decision-actions">
                <button type="button" class="button button-primary" data-dialog-open="approve-registration-dialog">اعتماد التسجيل والدفع</button>
                <button type="button" class="button button-secondary" data-dialog-open="information-registration-dialog">طلب معلومات</button>
                <button type="button" class="button payment-reject-button" data-dialog-open="reject-registration-dialog">رفض التسجيل</button>
            </div>
        </section>
    @else
        <section class="payment-decision-panel is-complete" aria-labelledby="review-decision-title">
            <div><span class="section-label">قرار محفوظ</span><h2 id="review-decision-title">اكتملت المراجعة</h2><p>{{ $submission->review_note ?: 'تم اعتماد الطلب بعد مطابقة بيانات التحويل.' }}</p></div>
            <div class="payment-reviewer"><small>تمت المراجعة بواسطة</small><strong>{{ $submission->reviewer?->name ?? 'النظام' }}</strong><span>{{ $submission->reviewed_at?->format('Y/m/d · H:i') }}</span></div>
        </section>
    @endif

    @if($submission->status==='approved' && $isRegistration)
        @php
            $studentMessageSent = $studentWhatsApp?->isSent() ?? false;
            $guardianMessageSent = $guardianWhatsApp?->isSent() ?? false;
        @endphp
        <section class="guardian-handoff-panel communication-panel" aria-labelledby="whatsapp-communication-title">
            <div class="communication-heading">
                <div><span class="section-label">بعد الاعتماد</span><h2 id="whatsapp-communication-title">التواصل عبر WhatsApp</h2><p>افتح كل رسالة عندما تكون مستعداً لإرسالها. فتح WhatsApp لا يعني أن الرسالة تم تسليمها.</p></div>
                <span class="badge published">التسجيل معتمد</span>
            </div>
            <div class="communication-grid">
                <article class="communication-card">
                    <div class="communication-card-heading"><div><small>الطالب</small><h3>{{ $submission->user->name }}</h3><span class="latin">{{ preg_replace('/^\+20/', '0', $submission->user->phone_e164) }}</span></div><span class="communication-status {{ $studentMessageSent ? 'is-sent' : '' }}" data-whatsapp-status="student">WhatsApp الطالب: {{ $studentMessageSent ? 'تم الإرسال' : 'قيد الانتظار' }}</span></div>
                    <p>رسالة اعتماد الحساب واسم المستخدم ورابط الدخول. لا تتضمن كلمة مرور الطالب.</p>
                    @if($studentWhatsApp)
                        <small class="communication-time">آخر فتح: {{ $studentWhatsApp->opened_at?->format('Y/m/d · H:i') ?? $studentWhatsApp->created_at->format('Y/m/d · H:i') }}@if($studentMessageSent) · أكد الإرسال {{ $studentWhatsApp->sentBy?->name }} في {{ $studentWhatsApp->sent_at?->format('Y/m/d · H:i') }}@endif</small>
                    @endif
                    <div class="communication-actions">
                        <form method="post" action="{{ route('admin.payment-reviews.student-whatsapp',$submission) }}" target="_blank" data-whatsapp-open="student" data-processing-text="جارٍ تجهيز الرسالة...">@csrf<button class="button button-secondary" type="submit">{{ $studentWhatsApp ? 'فتح رسالة الطالب مجدداً' : 'فتح رسالة الطالب' }}</button><small class="communication-error" data-whatsapp-error role="alert"></small></form>
                        <form method="post" action="{{ route('admin.payment-reviews.whatsapp.sent',[$submission,'student']) }}" data-processing data-processing-text="جارٍ التأكيد..." data-whatsapp-confirm-for="student" @if(!$studentWhatsApp || $studentMessageSent) hidden @endif>@csrf<button class="button button-quiet" type="submit">تأكيد الإرسال</button></form>
                    </div>
                </article>
                <article class="communication-card">
                    <div class="communication-card-heading"><div><small>ولي الأمر</small><h3>{{ $invitation?->parent?->name ?? 'حساب ولي الأمر' }}</h3><span class="latin">{{ preg_replace('/^\+20/', '0', $invitation?->parent_phone_e164 ?? '') }}</span></div><span class="communication-status {{ $guardianMessageSent ? 'is-sent' : '' }}" data-whatsapp-status="guardian">WhatsApp ولي الأمر: {{ $guardianMessageSent ? 'تم الإرسال' : 'قيد الانتظار' }}</span></div>
                    <p>{{ $invitation?->guardian_account_was_created ? 'سيُنشئ النظام كلمة مؤقتة جديدة عند الفتح ويلغي السابقة.' : 'رسالة ربط الطالب بالحساب الحالي دون تغيير كلمة المرور.' }}</p>
                    @if($guardianWhatsApp)
                        <small class="communication-time">آخر فتح: {{ $guardianWhatsApp->opened_at?->format('Y/m/d · H:i') ?? $guardianWhatsApp->created_at->format('Y/m/d · H:i') }}@if($guardianMessageSent) · أكد الإرسال {{ $guardianWhatsApp->sentBy?->name }} في {{ $guardianWhatsApp->sent_at?->format('Y/m/d · H:i') }}@endif</small>
                    @endif
                    <div class="communication-actions">
                        <form method="post" action="{{ route('admin.payment-reviews.guardian-whatsapp',$submission) }}" target="_blank" data-whatsapp-open="guardian" data-processing-text="جارٍ تجهيز الرسالة...">@csrf<button class="button button-secondary" type="submit">{{ $guardianWhatsApp ? 'فتح رسالة ولي الأمر مجدداً' : 'فتح رسالة ولي الأمر' }}</button><small class="communication-error" data-whatsapp-error role="alert"></small></form>
                        <form method="post" action="{{ route('admin.payment-reviews.whatsapp.sent',[$submission,'guardian']) }}" data-processing data-processing-text="جارٍ التأكيد..." data-whatsapp-confirm-for="guardian" @if(!$guardianWhatsApp || $guardianMessageSent) hidden @endif>@csrf<button class="button button-quiet" type="submit">تأكيد الإرسال</button></form>
                    </div>
                </article>
            </div>
        </section>
    @endif
</div>

<dialog class="admin-dialog payment-proof-dialog" id="payment-proof-dialog" aria-labelledby="payment-proof-dialog-title">
    <div class="admin-dialog-body">
        <div class="admin-dialog-heading"><div><div class="eyebrow">الإثبات الأصلي</div><h2 id="payment-proof-dialog-title">{{ $submission->receipt_original_name }}</h2></div><button class="admin-dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button></div>
        <div class="payment-proof-full">
            @if(str_starts_with($submission->receipt_mime,'image/'))<img src="{{ route('admin.payment-reviews.receipt',$submission) }}" alt="إيصال التحويل بالحجم الكامل">@else<iframe title="إيصال التحويل بالحجم الكامل" src="{{ route('admin.payment-reviews.receipt',$submission) }}"></iframe>@endif
        </div>
    </div>
</dialog>

@if(in_array($submission->status,['submitted','under_review']))
<dialog class="admin-dialog payment-review-dialog" id="approve-registration-dialog" aria-labelledby="approve-registration-title">
    <form class="admin-dialog-body" method="post" action="{{ route('admin.payment-reviews.review',$submission) }}" data-processing data-processing-text="جارٍ الاعتماد...">@csrf
        <input type="hidden" name="decision" value="approved">
        <div class="admin-dialog-heading"><div><div class="eyebrow">تأكيد الاعتماد</div><h2 id="approve-registration-title">اعتماد التسجيل؟</h2></div><button class="admin-dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button></div>
        <p>سيتم تفعيل حساب الطالب وتجهيز حساب ولي الأمر وربطهما. لن تُفتح أو تُرسل أي رسالة WhatsApp تلقائياً؛ ستظهر إجراءات التواصل اليدوي بعد الاعتماد.</p>
        @if($submission->matching_receipts_count>1)<label>سبب اعتماد الإيصال المكرر<textarea name="review_note" required minlength="10" maxlength="2000"></textarea></label>@endif
        <div class="admin-dialog-actions"><button type="button" class="button button-quiet" data-dialog-close>إلغاء</button><button class="button button-primary" type="submit">اعتماد التسجيل</button></div>
    </form>
</dialog>

<dialog class="admin-dialog payment-review-dialog" id="information-registration-dialog" aria-labelledby="information-registration-title">
    <form class="admin-dialog-body" method="post" action="{{ route('admin.payment-reviews.review',$submission) }}" data-processing data-processing-text="جارٍ إرسال الطلب...">@csrf
        <input type="hidden" name="decision" value="needs_information">
        <div class="admin-dialog-heading"><div><div class="eyebrow">استكمال الطلب</div><h2 id="information-registration-title">طلب معلومات إضافية</h2></div><button class="admin-dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button></div>
        <label>رسالة واضحة للطالب<textarea name="review_note" required minlength="5" maxlength="2000" placeholder="مثال: يرجى رفع صورة أوضح يظهر بها رقم العملية."></textarea></label>
        <div class="admin-dialog-actions"><button type="button" class="button button-quiet" data-dialog-close>إلغاء</button><button class="button button-secondary" type="submit">إرسال الطلب</button></div>
    </form>
</dialog>

<dialog class="admin-dialog payment-review-dialog" id="reject-registration-dialog" aria-labelledby="reject-registration-title">
    <form class="admin-dialog-body" method="post" action="{{ route('admin.payment-reviews.review',$submission) }}" data-processing data-processing-text="جارٍ رفض الطلب...">@csrf
        <input type="hidden" name="decision" value="rejected">
        <div class="admin-dialog-heading"><div><div class="eyebrow">قرار نهائي</div><h2 id="reject-registration-title">رفض التسجيل</h2></div><button class="admin-dialog-close" type="button" data-dialog-close aria-label="إغلاق">×</button></div>
        <p>اكتب سبباً واضحاً وقابلاً للتنفيذ. سيظل حساب الطالب غير نشط.</p>
        <label>سبب الرفض<textarea name="review_note" required minlength="5" maxlength="2000" placeholder="وضح البيانات غير الصحيحة أو الخطوة التالية المتاحة."></textarea></label>
        <div class="admin-dialog-actions"><button type="button" class="button button-quiet" data-dialog-close>إلغاء</button><button class="button button-danger" type="submit">تأكيد الرفض</button></div>
    </form>
</dialog>
@endif
@endsection
