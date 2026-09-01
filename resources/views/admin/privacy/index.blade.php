@extends('admin.layout')
@section('title','الخصوصية وحذف الحسابات')
@section('content')
<header class="top"><div><div class="eyebrow">حوكمة البيانات</div><h1>طلبات حذف الحسابات</h1><p>مراجعة بشرية موثقة قبل تنفيذ إخفاء الهوية آلياً في الموعد المحدد.</p></div></header>
<section class="stats-grid">
    @foreach(['pending'=>'قيد المراجعة','approved'=>'موافق عليه','rejected'=>'مرفوض','completed'=>'مكتمل','failed'=>'فشل التنفيذ'] as $key=>$label)
    <article class="stat-card"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong></article>
    @endforeach
</section>
<section class="card" style="margin-top:16px">
    <div class="heading"><div><h2>سياسة التنفيذ والاحتفاظ</h2><small>تحذف بيانات الحساب والتعلم والتواصل، بينما تحفظ السجلات المالية الدنيا لمدة {{ $retentionYears }} سنوات وفق سياسة المنصة القابلة للمراجعة.</small></div></div>
    <div class="filters">@foreach([''=>'الكل','pending'=>'قيد المراجعة','approved'=>'موافق عليه','rejected'=>'مرفوض','completed'=>'مكتمل','failed'=>'فشل التنفيذ'] as $value=>$label)<a class="button {{ $selectedStatus===$value || (!$selectedStatus && $value==='')?'primary':'button-quiet' }}" href="{{ route('admin.privacy.index',array_filter(['status'=>$value])) }}">{{ $label }}</a>@endforeach</div>
</section>
<section class="card" style="margin-top:16px">
    <div class="table-wrap"><table><thead><tr><th>الحساب</th><th>الحالة</th><th>موعد التنفيذ</th><th>المراجعة</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($requests as $item)<tr><td><strong>{{ $item->user->name }}</strong><small>{{ $item->user->email }} · {{ $item->user->role }}</small></td><td><span class="badge {{ in_array($item->status,['approved','completed'])?'published':($item->status==='pending'?'warning':'draft') }}">{{ $item->status }}</span>@if($item->processing_error)<small class="danger-text">{{ $item->processing_error }}</small>@endif</td><td><time datetime="{{ $item->scheduled_for->toISOString() }}">{{ $item->scheduled_for->format('Y-m-d H:i') }}</time>@if($item->legal_records_until)<small>السجلات القانونية حتى {{ $item->legal_records_until->format('Y-m-d') }}</small>@endif</td><td>@if($item->reviewer)<strong>{{ $item->reviewer->name }}</strong><small>{{ $item->review_note }}</small>@else<span>لم يراجع بعد</span>@endif</td><td>@if($item->status==='pending')<form method="post" action="{{ route('admin.privacy.review',$item) }}">@csrf<textarea name="review_note" required minlength="5" maxlength="2000" placeholder="سبب القرار والإجراءات المتخذة"></textarea><div class="actions"><button class="button primary" name="decision" value="approved">موافقة</button><button class="button danger" name="decision" value="rejected">رفض</button></div></form>@else<small>{{ $item->processed_at?'نُفذ في '.$item->processed_at->format('Y-m-d H:i'):'لا يوجد إجراء متاح' }}</small>@endif</td></tr>@empty<tr><td colspan="5">لا توجد طلبات مطابقة.</td></tr>@endforelse
    </tbody></table></div>{{ $requests->links() }}
</section>
@endsection
