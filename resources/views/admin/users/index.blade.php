@extends('admin.layout')
@section('title', 'الطلاب والأسر')
@section('content')
<header class="page-header users-page-hero community-page-hero">
    <div><div class="eyebrow">إدارة المجتمع التعليمي</div><h1>الطلاب وأولياء الأمور</h1><p>راجع الحسابات، تابع الوصول إلى التعلم، وأنشئ روابط أسرية واضحة.</p></div>
    <a class="button button-primary" href="#family-link"><span aria-hidden="true">＋</span> ربط أسرة</a>
</header>

<section class="users-page-stats" aria-label="ملخص الحسابات">
    <a href="{{ route('admin.users.index',['role'=>'student']) }}"><span>◉</span><div><strong>{{ $stats['students'] }}</strong><small>طلاب</small></div></a>
    <a href="{{ route('admin.users.index',['role'=>'parent']) }}"><span>◎</span><div><strong>{{ $stats['parents'] }}</strong><small>أولياء أمور</small></div></a>
    <a href="{{ route('admin.users.index',['status'=>'active']) }}"><span>✓</span><div><strong>{{ $stats['active'] }}</strong><small>حسابات نشطة</small></div></a>
    <a href="{{ route('admin.users.index',['linkage'=>'unlinked']) }}" class="{{ $stats['unlinked'] ? 'attention' : '' }}"><span>○</span><div><strong>{{ $stats['unlinked'] }}</strong><small>دون رابط أسري</small></div></a>
</section>

<form class="card users-filter" method="get" role="search">
    <input type="hidden" name="view" value="{{ $display }}">
    <label class="search-field"><span class="sr-only">بحث الحسابات</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="{{ request('search') }}" placeholder="الاسم أو البريد..."></label>
    <select name="role" aria-label="نوع الحساب"><option value="">كل الحسابات</option><option value="student" @selected(request('role')==='student')>الطلاب</option><option value="parent" @selected(request('role')==='parent')>أولياء الأمور</option></select>
    <select name="status" aria-label="حالة الحساب"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>معطل</option></select>
    <select name="linkage" aria-label="الرابط الأسري"><option value="">كل الروابط</option><option value="linked" @selected(request('linkage')==='linked')>مرتبط بأسرة</option><option value="unlinked" @selected(request('linkage')==='unlinked')>دون رابط</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" @selected(request('sort','latest')==='latest')>الأحدث</option><option value="name" @selected(request('sort')==='name')>الاسم</option><option value="activity" @selected(request('sort')==='activity')>الأكثر نشاطاً</option></select>
    <button class="button button-secondary">تطبيق</button>
    @if(request()->hasAny(['search','role','status','linkage','sort']))<a class="button button-quiet" href="{{ route('admin.users.index',['view'=>$display]) }}">مسح</a>@endif
</form>

<div class="users-workspace">
    <main>
        <div class="users-results-head"><div><strong>{{ $users->total() }} حساباً</strong><span>حالة الوصول والروابط الأسرية</span></div><div class="users-results-tools"><span>صفحة {{ $users->currentPage() }} من {{ $users->lastPage() }}</span><x-admin.view-switcher :display="$display" /></div></div>
        @if($display === 'table')
        <div class="card users-table-wrap table-wrap">
            <table class="users-admin-table">
                <thead><tr><th>الحساب</th><th>النوع</th><th>الحالة</th><th>النشاط</th><th>الرابط الأسري</th><th>تاريخ الانضمام</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php($family = $user->role === 'student' ? $user->linkedParents : $user->linkedStudents)
                    <tr class="{{ $user->is_active ? '' : 'user-table-inactive' }}">
                        <td><div class="user-table-identity"><span class="user-admin-avatar role-{{ $user->role }}">{{ mb_substr($user->name,0,1) }}</span><div><strong>{{ $user->name }}</strong><small class="latin">{{ $user->email }}</small></div></div></td>
                        <td><span class="user-role-pill role-{{ $user->role }}">{{ $user->role === 'student' ? 'طالب' : 'ولي أمر' }}</span></td>
                        <td><span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">{{ $user->is_active ? 'نشط' : 'معطل' }}</span></td>
                        <td><div class="user-table-activity"><strong>{{ $user->role === 'student' ? $user->enrollments_count : $user->linked_students_count }}</strong><small>{{ $user->role === 'student' ? 'تسجيلات بالدورات' : 'طلاب مرتبطون' }}</small></div></td>
                        <td>@if($family->isNotEmpty())<div class="user-table-family">@foreach($family->take(2) as $member)<span>{{ $member->name }}</span>@endforeach @if($family->count()>2)<em>+{{ $family->count()-2 }}</em>@endif</div>@else<span class="unlinked-label">○ دون رابط</span>@endif</td>
                        <td><time datetime="{{ $user->created_at->toDateString() }}">{{ $user->created_at->format('Y/m/d') }}</time></td>
                        <td><div class="user-table-actions">@if($user->role === 'student')<a class="icon-action" href="{{ route('admin.users.show',$user) }}" aria-label="عرض ملف {{ $user->name }}" title="عرض ملف التعلم">◉</a>@endif<form method="post" action="{{ route('admin.users.toggle',$user) }}">@csrf<button class="icon-action {{ $user->is_active ? 'deactivate' : 'activate' }}" aria-label="{{ $user->is_active ? 'تعطيل' : 'تفعيل' }} حساب {{ $user->name }}" title="{{ $user->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}">{{ $user->is_active ? '×' : '✓' }}</button></form></div></td>
                    </tr>
                @empty<tr><td colspan="7"><div class="empty-state"><strong>لا توجد حسابات مطابقة</strong><span>غيّر الفلاتر أو ابحث ببيانات أخرى.</span></div></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @else
        <div class="users-admin-grid">
        @forelse($users as $user)
            @php($family = $user->role === 'student' ? $user->linkedParents : $user->linkedStudents)
            <article class="card user-admin-card {{ $user->is_active ? '' : 'user-admin-card-inactive' }}">
                <header><span class="user-admin-avatar role-{{ $user->role }}">{{ mb_substr($user->name,0,1) }}</span><div><strong>{{ $user->name }}</strong><small class="latin">{{ $user->email }}</small><span>{{ $user->role === 'student' ? 'حساب طالب' : 'حساب ولي أمر' }}</span></div><span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">{{ $user->is_active ? 'نشط' : 'معطل' }}</span></header>
                <div class="user-admin-facts">
                    @if($user->role === 'student')<span><strong>{{ $user->enrollments_count }}</strong><small>تسجيلات</small></span><span><strong>{{ $user->linked_parents_count }}</strong><small>أولياء أمور</small></span>@else<span><strong>{{ $user->linked_students_count }}</strong><small>طلاب مرتبطون</small></span><span><strong>{{ $user->created_at->diffForHumans(null,true) }}</strong><small>عمر الحساب</small></span>@endif
                </div>
                @if($family->isNotEmpty())<div class="user-family"><small>{{ $user->role === 'student' ? 'أولياء الأمور' : 'الطلاب المرتبطون' }}</small>@foreach($family->take(3) as $member)<span>{{ $member->name }}</span>@endforeach @if($family->count()>3)<em>+{{ $family->count()-3 }}</em>@endif</div>@else<div class="user-no-family"><span>○</span><div><strong>لا يوجد رابط أسري</strong><small>استخدم نموذج الربط لإتاحة متابعة الطالب.</small></div></div>@endif
                <footer>
                    @if($user->role === 'student')<a class="button button-secondary" href="{{ route('admin.users.show',$user) }}">عرض ملف التعلم</a>@else<span class="parent-account-note">تظهر بيانات الطلاب بعد إنشاء الرابط</span>@endif
                    <form method="post" action="{{ route('admin.users.toggle',$user) }}">@csrf<button class="button {{ $user->is_active ? 'button-danger' : 'button-secondary' }}">{{ $user->is_active ? 'تعطيل' : 'تفعيل' }}</button></form>
                </footer>
            </article>
        @empty<div class="card empty-state users-empty"><strong>لا توجد حسابات مطابقة</strong><span>غيّر الفلاتر أو ابحث ببيانات أخرى.</span></div>@endforelse
        </div>
        @endif
        <div class="pagination">{{ $users->links() }}</div>
    </main>

    <aside id="family-link"><details class="card family-link-card" @if($errors->any()) open @endif><summary><span><small>متابعة أسرية</small><strong>ربط ولي أمر بطالب</strong></span><b aria-hidden="true">＋</b></summary><p>يمنح الرابط ولي الأمر حق متابعة الدورات والتقدم فقط.</p><form class="stack" method="post" action="{{ route('admin.users.link') }}">@csrf<label>ولي الأمر<select name="parent_id" required><option value="">اختر ولي الأمر</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id')==$parent->id)>{{ $parent->name }} · {{ $parent->email }}</option>@endforeach</select></label><label>الطالب<select name="student_id" required><option value="">اختر الطالب</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id')==$student->id)>{{ $student->name }} · {{ $student->email }}</option>@endforeach</select></label><label>الصف الدراسي<select name="grade"><option value="grade-1">الأول الثانوي</option><option value="grade-2">الثاني الثانوي</option><option value="grade-3">الثالث الثانوي</option></select></label><div class="family-link-note"><span aria-hidden="true">✓</span><small>يمكن تحديث الصف بإعادة حفظ الرابط نفسه.</small></div><button class="button button-primary">حفظ الرابط الأسري</button></form></details></aside>
</div>
@endsection
