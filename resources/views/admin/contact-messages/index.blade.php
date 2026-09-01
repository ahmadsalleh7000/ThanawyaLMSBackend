@extends('admin.layout')
@section('title', 'رسائل التواصل')
@section('content')
@php
    $statusLabels = ['new' => 'جديدة', 'in_progress' => 'قيد المتابعة', 'resolved' => 'مكتملة'];
@endphp
<header class="page-header community-page-hero">
    <div>
        <div class="eyebrow">صندوق دعم مركزي</div>
        <h1>رسائل التواصل</h1>
        <p>راجع استفسارات الزوار والطلاب، وحدد ما يحتاج متابعة.</p>
    </div>
</header>
<section class="community-stats" aria-label="ملخص الرسائل">
    @foreach(['new' => ['جديدة', 'تحتاج مراجعة'], 'in_progress' => ['قيد المتابعة', 'فتحها الفريق'], 'resolved' => ['مكتملة', 'تم التعامل معها']] as $status => $copy)
        <a href="{{ route('admin.contact-messages.index', ['status' => $status]) }}">
            <span class="community-stat-icon">{{ $status === 'new' ? '!' : ($status === 'resolved' ? '✓' : '◷') }}</span>
            <div><strong>{{ $counts[$status] }}</strong><small>{{ $copy[0] }}</small></div>
            <em>{{ $copy[1] }}</em>
        </a>
    @endforeach
</section>
<section class="card stack">
    <form class="filter-bar" method="get">
        <label>بحث<input name="q" value="{{ request('q') }}" placeholder="الاسم أو البريد أو رقم المتابعة"></label>
        <label>الحالة
            <select name="status">
                <option value="">كل الحالات</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="button button-secondary">تطبيق</button>
        <a class="button button-quiet" href="{{ route('admin.contact-messages.index') }}">مسح</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>المرسل</th><th>الموضوع</th><th>الحالة</th><th>المعالجة</th><th>التاريخ</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($messages as $message)
                    <tr>
                        <td><strong>{{ $message->name }}</strong><small class="latin">{{ $message->email }}</small><small class="latin">{{ $message->public_reference }}</small></td>
                        <td>{{ $message->subject }}</td>
                        <td><span class="badge {{ $message->status === 'resolved' ? 'published' : ($message->status === 'new' ? 'inactive' : 'draft') }}">{{ $statusLabels[$message->status] }}</span></td>
                        <td>
                            @if($message->replied_at)
                                <strong>تم الرد</strong><small>{{ $message->replied_at->diffForHumans() }}</small>
                            @elseif($message->handler)
                                <strong>{{ $message->handler->name }}</strong><small>قيد المتابعة</small>
                            @else
                                <span class="badge inactive">لم تُسند</span>
                            @endif
                        </td>
                        <td>{{ $message->created_at->format('Y/m/d H:i') }}</td>
                        <td><a class="button button-quiet" href="{{ route('admin.contact-messages.show', $message) }}">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state">لا توجد رسائل مطابقة.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $messages->links() }}</div>
</section>
@endsection
