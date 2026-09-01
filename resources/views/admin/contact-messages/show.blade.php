@extends('admin.layout')
@section('title', 'رسالة تواصل')
@section('content')
@php
    $statusLabels = ['new' => 'جديدة', 'in_progress' => 'قيد المتابعة', 'resolved' => 'مكتملة'];
@endphp
<header class="page-header">
    <div>
        <div class="eyebrow latin">{{ $message->public_reference }}</div>
        <h1>{{ $message->subject }}</h1>
        <p>{{ $message->name }} · <span class="latin">{{ $message->email }}</span></p>
    </div>
    <a class="button button-quiet" href="{{ route('admin.contact-messages.index') }}">العودة للصندوق</a>
</header>

<section class="grid content-columns">
    <main class="card stack">
        <div class="section-heading">
            <div>
                <div class="eyebrow">محتوى الرسالة</div>
                <h2>{{ $message->subject }}</h2>
            </div>
            <span class="badge {{ $message->status === 'resolved' ? 'published' : ($message->status === 'new' ? 'inactive' : 'draft') }}">
                {{ $statusLabels[$message->status] }}
            </span>
        </div>
        <p style="white-space:pre-wrap;line-height:1.9">{{ $message->message }}</p>
        <div class="student-context">
            <span>اللغة: {{ $message->locale === 'ar' ? 'العربية' : 'English' }}</span>
            <span>أُرسلت {{ $message->created_at->diffForHumans() }}</span>
            @if($message->user?->role === 'student')
                <a href="{{ route('admin.users.show', $message->user) }}">حساب مسجل: {{ $message->user->name }}</a>
            @elseif($message->user)
                <span>حساب مسجل: {{ $message->user->name }}</span>
            @endif
        </div>
        @if($message->admin_note || $message->last_reply_body)
            <div class="detail-list">
                @if($message->admin_note)
                    <article class="detail-item">
                        <div>
                            <small>ملاحظة داخلية</small>
                            <p style="white-space:pre-wrap">{{ $message->admin_note }}</p>
                        </div>
                    </article>
                @endif
                @if($message->last_reply_body)
                    <article class="detail-item">
                        <div>
                            <small>آخر رد مرسل · {{ $message->replied_at?->format('Y/m/d H:i') }} @if($message->replier) · {{ $message->replier->name }} @endif</small>
                            <p style="white-space:pre-wrap">{{ $message->last_reply_body }}</p>
                        </div>
                    </article>
                @endif
            </div>
        @endif
    </main>

    <aside class="stack">
        <section class="card">
            <div class="section-heading"><h2>إدارة الرسالة</h2></div>
            <form class="stack" method="post" action="{{ route('admin.contact-messages.update', $message) }}">
                @csrf
                @method('put')
                <label>الحالة
                    <select name="status">
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($message->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>ملاحظة داخلية
                    <textarea name="admin_note" maxlength="2000" placeholder="اكتب ما تم فحصه أو الخطوة التالية...">{{ old('admin_note', $message->admin_note) }}</textarea>
                </label>
                @if($message->handler)
                    <small>آخر معالجة بواسطة {{ $message->handler->name }}</small>
                @endif
                <button class="button button-primary">حفظ الحالة والملاحظة</button>
            </form>
        </section>

        <section class="card">
            <div class="section-heading"><h2>إرسال رد من الإدارة</h2></div>
            <form class="stack" method="post" action="{{ route('admin.contact-messages.reply', $message) }}">
                @csrf
                <label>الرد
                    <textarea name="reply_body" required minlength="5" maxlength="5000" placeholder="اكتب الرد الذي سيصل إلى بريد المرسل...">{{ old('reply_body') }}</textarea>
                </label>
                <label>الحالة بعد الرد
                    <select name="status">
                        <option value="in_progress">قيد المتابعة</option>
                        <option value="resolved" selected>مكتملة</option>
                    </select>
                </label>
                <button class="button button-primary">إرسال الرد</button>
                <a class="button button-secondary" dir="ltr" href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}">فتح البريد يدوياً</a>
            </form>
        </section>
    </aside>
</section>
@endsection
