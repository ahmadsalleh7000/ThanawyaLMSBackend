@extends('admin.layout')
@section('title', 'نتائج الاختبار')
@section('content')
<header class="page-header"><div><div class="eyebrow">نتائج الطلاب</div><h1>{{ $training->title_ar }}</h1><p>المحاولات المسلّمة لهذا الاختبار فقط.</p></div><div class="actions-inline"><a class="button button-secondary" href="{{ route('admin.learning-paths.trainings.settings', $training) }}">إعدادات الاختبار</a><a class="button button-quiet" href="{{ route('admin.exams.index') }}">كل الاختبارات والامتحانات</a></div></header>
<section class="grid stats-grid exam-hub-attempt-stats" aria-label="ملخص النتائج">
    <article class="card compact-stat"><span class="stat-icon">#</span><div><strong>{{ $summary['attempts'] }}</strong><span>إجمالي المحاولات</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">%</span><div><strong>{{ $summary['average'] }}%</strong><span>متوسط النتيجة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">✓</span><div><strong>{{ $summary['passRate'] }}%</strong><span>معدل النجاح</span></div></article>
</section>
<form class="card toolbar" method="get" role="search"><label>البحث عن طالب<input type="search" name="search" maxlength="150" value="{{ $search }}" placeholder="اسم الطالب أو البريد الإلكتروني"></label><button class="button button-primary">بحث</button>@if($search)<a class="button button-quiet" href="{{ route('admin.exams.training-attempts', $training) }}">مسح</a>@endif</form>
<section class="card table-wrap"><table><caption class="sr-only">محاولات الطلاب في {{ $training->title_ar }}</caption><thead><tr><th scope="col">الطالب</th><th scope="col">النتيجة</th><th scope="col">الحالة</th><th scope="col">وقت التسليم</th></tr></thead><tbody>
@forelse($attempts as $attempt)
    <tr><td class="cell-title"><strong>{{ $attempt->student?->name ?? 'حساب محذوف' }}</strong><small>{{ $attempt->student?->email }}</small></td><td>{{ (float)$attempt->score }}%</td><td><span class="badge {{ $attempt->status === 'passed' ? 'published' : 'inactive' }}">{{ $attempt->status === 'passed' ? 'ناجح' : 'لم يجتز' }}</span></td><td><time datetime="{{ $attempt->submitted_at->toISOString() }}">{{ $attempt->submitted_at->format('Y/m/d H:i') }}</time></td></tr>
@empty
    <tr><td colspan="4"><div class="empty-state">{{ $search ? 'لا توجد محاولات لهذا الطالب في هذا الاختبار.' : 'لم يرسل الطلاب محاولات بعد.' }}</div></td></tr>
@endforelse
</tbody></table><div class="pagination">{{ $attempts->links() }}</div></section>
@endsection
