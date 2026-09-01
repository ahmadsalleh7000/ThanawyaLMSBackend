@extends('admin.layout')
@section('title', 'بنك الأسئلة')
@php
    $typeLabels = ['single_choice' => 'اختيار من متعدد', 'true_false' => 'صح أو خطأ'];
    $difficultyLabels = ['easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب'];
@endphp
@section('content')
<div class="question-workspace" dir="rtl">
    <nav class="question-tabs" aria-label="مساحة الأسئلة والاختبارات">
        <a class="{{ $activeTab === 'questions' ? 'is-active' : '' }}" href="{{ route('admin.question-bank.index', ['tab' => 'questions']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h4"/></svg> بنك الأسئلة</a>
        <a class="{{ $activeTab === 'quizzes' ? 'is-active' : '' }}" href="{{ route('admin.question-bank.index', ['tab' => 'quizzes']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l2 2 4-5M5 4h14v16H5zM8 18h8"/></svg> الاختبارات</a>
    </nav>

    @if($activeTab === 'questions')
        <header class="page-header management-page-header question-bank-hero">
            <div><h1>بنك الأسئلة</h1><p>أنشئ الأسئلة ونظمها لاستخدامها في الاختبارات المختلفة.</p></div>
            <div class="actions-inline"><a class="button button-secondary" href="#create-question"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M5 21h14"/></svg> استيراد أسئلة</a><button type="button" class="button button-primary curriculum-create-trigger" data-inline-form-toggle="create-question" aria-controls="create-question" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة سؤال</button></div>
        </header>

        <section class="curriculum-inline-create question-create-panel" id="create-question" aria-labelledby="create-question-title" @unless($errors->any()) hidden @endunless>
            <form class="curriculum-inline-create-form" method="post" action="{{ route('admin.question-bank.store') }}">
                @csrf
                <div class="curriculum-inline-create-header">
                    <div class="curriculum-dialog-title"><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h4"/></svg></span><div><div class="eyebrow">بنك الأسئلة</div><h2 id="create-question-title">إضافة سؤال جديد</h2><p>أضف السؤال مرة واحدة، ثم استخدمه داخل أي اختبار.</p></div></div>
                    <button class="curriculum-inline-close" type="button" data-inline-form-close="create-question" aria-label="إغلاق نموذج إضافة السؤال">×</button>
                </div>
                @if($errors->any())<div class="curriculum-create-errors" role="alert"><strong>راجع بيانات السؤال</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <div class="curriculum-create-fields question-create-fields">@include('admin.question-bank.fields',['item'=>null])</div>
                <div class="curriculum-inline-create-footer"><div class="curriculum-create-note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>سيظهر السؤال في القائمة ويمكن إضافته لاحقاً لأي اختبار.</span></div><div class="curriculum-inline-actions"><button type="button" class="button button-quiet" data-inline-form-close="create-question"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> إلغاء</button><button class="button button-primary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة إلى البنك</button></div></div>
            </form>
        </section>

        <section class="question-bank-stats" aria-label="ملخص بنك الأسئلة">
            <article><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h4"/></svg></span><strong>{{ number_format($stats['total']) }}</strong><small>إجمالي الأسئلة</small></article>
            <article><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg></span><strong>{{ number_format($stats['single_choice']) }}</strong><small>اختيار من متعدد</small></article>
            <article><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 12 4 4L18 6M18 18 6 6"/></svg></span><strong>{{ number_format($stats['true_false']) }}</strong><small>صح أو خطأ</small></article>
            <article><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16M8 6v14h8V6M10 10h4"/></svg></span><strong>{{ number_format($stats['unused']) }}</strong><small>غير مستخدمة</small></article>
        </section>

        <form class="card question-filter" method="get" role="search">
            <input type="hidden" name="tab" value="questions">
            <input type="hidden" name="view" value="{{ $display }}">
            <label class="search-field"><span class="sr-only">البحث</span><input name="search" value="{{ $search }}" placeholder="ابحث في الأسئلة..."></label>
            <select name="topic" aria-label="الموضوع"><option value="">كل الموضوعات</option>@foreach($topics as $topic)<option value="{{ $topic }}" @selected(request('topic')===$topic)>{{ $topic }}</option>@endforeach</select>
            <select name="difficulty" aria-label="الصعوبة"><option value="">كل الصعوبات</option>@foreach($difficultyLabels as $value=>$label)<option value="{{ $value }}" @selected(request('difficulty')===$value)>{{ $label }}</option>@endforeach</select>
            <select name="type" aria-label="نوع السؤال"><option value="">كل الأنواع</option>@foreach($typeLabels as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select>
            <select name="usage" aria-label="الاستخدام"><option value="">كل الاستخدامات</option><option value="used" @selected(request('usage')==='used')>مستخدم في اختبار</option><option value="unused" @selected(request('usage')==='unused')>غير مستخدم</option></select>
            <select name="status" aria-label="الحالة"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>فعال</option><option value="inactive" @selected(request('status')==='inactive')>مؤرشف</option></select>
            <button class="button button-secondary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7z"/></svg> تصفية</button>
            @if(request()->hasAny(['search','topic','difficulty','type','usage','status']))<a class="button button-quiet" href="{{ route('admin.question-bank.index', ['tab' => 'questions']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> مسح الفلاتر</a>@endif
        </form>

        <section class="question-list-panel">
                <div class="question-results-head"><div><strong>{{ $questions->total() }} سؤال</strong><span>مرتبة حسب الحالة والأحدث</span></div><div class="listing-head-tools"><span>صفحة {{ $questions->currentPage() }} من {{ $questions->lastPage() }}</span><x-admin.view-switcher :display="$display" /></div></div>
                @if($display === 'table')
                    @include('admin.question-bank.table')
                @else
                <div class="question-bank-grid">
                    @forelse($questions as $question)
                    <article class="card bank-question {{ $question->is_active ? '' : 'bank-question-disabled' }}">
                        <header>
                            <label class="question-select"><input type="checkbox" form="bulk-question-form" name="question_ids[]" value="{{ $question->id }}"><span class="sr-only">تحديد السؤال</span></label>
                            <span class="question-number" aria-label="رقم السؤال">{{ str_pad($questions->firstItem() + $loop->index, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="bank-topic"><span>{{ $question->topic_ar }}</span></div>
                            <span class="badge {{ $question->is_active ? 'active' : 'inactive' }}">{{ $question->is_active ? 'فعال' : 'مؤرشف' }}</span>
                        </header>
                        <div class="bank-prompt"><strong>{{ $question->prompt_ar }}</strong></div>
                        <div class="bank-tags"><span>{{ $typeLabels[$question->type] ?? $question->type }}</span><span class="difficulty-{{ $question->difficulty }}">{{ $difficultyLabels[$question->difficulty] ?? $question->difficulty }}</span><span>مستخدم في {{ $question->quizzes_count }} اختبارات</span></div>
                        @if($question->type === 'single_choice')
                            <ol class="bank-options">@foreach(array_slice($question->options_ar, 0, 4) as $index => $option)<li class="{{ $index === $question->correct_option ? 'correct' : '' }}"><span>{{ $index + 1 }}</span><b>{{ $option }}</b>@if($index === $question->correct_option)<em>صحيحة</em>@endif</li>@endforeach</ol>
                        @endif
                        <footer>
                            <details class="bank-editor"><summary>تعديل</summary><form class="form-grid bank-edit" method="post" action="{{ route('admin.question-bank.update',$question) }}">@csrf @method('put') @include('admin.question-bank.fields',['item'=>$question])<button class="button button-primary full">حفظ التعديلات</button></form></details>
                            <form method="post" action="{{ route('admin.question-bank.duplicate',$question) }}">@csrf<button class="button button-quiet"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h10v12H8zM6 16H4V4h12v2"/></svg> نسخ</button></form>
                            <form method="post" action="{{ route('admin.question-bank.toggle',$question) }}" @if($question->is_active && $question->quizzes_count) data-confirm="هذا السؤال مستخدم في {{ $question->quizzes_count }} اختبارات. سيتم أرشفته فقط ولن يحذف." @endif>@csrf<button class="button button-quiet"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $question->is_active ? 'M4 7h16M9 7v13h6V7M10 11h4' : 'M4 4v6h6M20 20v-6h-6M5 19A9 9 0 0 0 19 5' }}"/></svg> {{ $question->is_active ? 'أرشفة' : 'استعادة' }}</button></form>
                        </footer>
                    </article>
                    @empty
                        <div class="card empty-state bank-empty"><strong>{{ request()->hasAny(['search','topic','difficulty','type','usage','status']) ? 'لم نجد أسئلة مطابقة' : 'لا توجد أسئلة بعد' }}</strong><span>{{ request()->hasAny(['search','topic','difficulty','type','usage','status']) ? 'جرّب تغيير البحث أو إزالة بعض الفلاتر.' : 'ابدأ بإضافة أول سؤال إلى بنك الأسئلة.' }}</span><button type="button" class="button button-primary" data-inline-form-toggle="create-question" aria-controls="create-question" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة سؤال</button></div>
                    @endforelse
                </div>
                @endif
                <form id="bulk-question-form" class="card question-bulk-bar" method="post" action="{{ $quizzes->first() ? route('admin.question-bank.quizzes.questions.attach', $quizzes->first()) : '#' }}">@csrf<span>حدد أسئلة لإضافتها إلى أول اختبار، أو افتح تبويب الاختبارات لاختيار اختبار محدد.</span><button class="button button-secondary" @disabled(! $quizzes->first())><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة المحدد للاختبار</button></form>
                <div class="pagination">{{ $questions->links() }}</div>
        </section>
    @else
        <header class="page-header question-bank-hero">
            <div><h1>الاختبارات</h1><p>أنشئ الاختبارات وأضف إليها أسئلة من بنك الأسئلة.</p></div>
            <button type="button" class="button button-primary" data-inline-form-toggle="create-quiz" aria-controls="create-quiz" aria-expanded="{{ old('name_ar') !== null ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> اختبار جديد</button>
        </header>
        <form class="card question-filter" method="get" role="search">
            <input type="hidden" name="tab" value="quizzes">
            <label class="search-field"><span class="sr-only">البحث</span><input name="quiz_search" value="{{ $quizSearch }}" placeholder="ابحث باسم الاختبار..."></label>
            <select name="status" aria-label="الحالة"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>غير نشط</option></select>
            <button class="button button-secondary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7z"/></svg> تصفية</button>
            @if(request()->hasAny(['quiz_search','status']))<a class="button button-quiet" href="{{ route('admin.question-bank.index', ['tab' => 'quizzes']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> مسح الفلاتر</a>@endif
        </form>
        <section class="quiz-management-layout">
            <main class="quiz-list card">
                <div class="quiz-table-head">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6"/></svg> اسم الاختبار</span>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4"/></svg> النوع</span>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l2 2 4-5M5 4h14v16H5z"/></svg> الأسئلة</span>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19 10 9l4 6 3-5 3 9"/></svg> النجاح</span>
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9M9 12l2 2 5-6"/></svg> الحالة</span>
                    <span class="sr-only">الإجراءات</span>
                </div>
                @forelse($quizzes as $quiz)
                    <article class="quiz-card">
                        <div class="quiz-main-cell"><strong>{{ $quiz->name_ar }}</strong>@if($quiz->description_ar)<small>{{ $quiz->description_ar }}</small>@endif</div>
                        <span>{{ $quiz->type === 'required' ? 'إلزامي' : 'تدريب' }}</span>
                        <span>{{ $quiz->questions_count }} سؤال</span>
                        <span>{{ $quiz->passing_score }}%</span>
                        <span class="badge {{ $quiz->is_active ? 'active' : 'inactive' }}">{{ $quiz->is_active ? 'نشط' : 'غير نشط' }}</span>
                        <details class="quiz-builder"><summary class="quiz-open-action"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 12H3M12 5l7 7-7 7"/></svg><span>فتح الاختبار</span></summary>
                            <section class="quiz-detail-surface">
                                <section class="quiz-current-questions">
                                    <header><div><h3>أسئلة الاختبار</h3><p>يمكنك ترتيب الأسئلة أو إزالتها من الاختبار بدون حذفها من بنك الأسئلة.</p></div><button type="button" class="button button-primary" data-dialog-open="add-questions-{{ $quiz->id }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة أسئلة</button></header>
                                    <form class="quiz-question-search" role="search"><input type="search" placeholder="ابحث داخل الاختبار..." aria-label="البحث داخل أسئلة الاختبار"><select aria-label="نوع السؤال"><option>كل الأنواع</option><option>اختيار من متعدد</option><option>صح أو خطأ</option></select></form>
                                    <ol class="quiz-question-list is-detail">@forelse($quiz->questions as $question)<li><span class="drag-handle" aria-hidden="true">☰</span><span class="quiz-question-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div class="quiz-question-copy"><strong>{{ $question->prompt_ar }}</strong><small>{{ $typeLabels[$question->type] ?? $question->type }} · {{ $difficultyLabels[$question->difficulty] ?? $question->difficulty }} · {{ $question->topic_ar }}</small><details><summary>معاينة السؤال</summary><ol class="bank-options">@foreach($question->options_ar as $index => $option)<li class="{{ $index === $question->correct_option ? 'correct' : '' }}"><span>{{ $index + 1 }}</span><b>{{ $option }}</b>@if($index === $question->correct_option)<em>صحيحة</em>@endif</li>@endforeach</ol></details></div><span class="question-points">1 درجة</span><details class="question-menu"><summary aria-label="إجراءات السؤال"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg></summary><div><form method="post" action="{{ route('admin.question-bank.quizzes.questions.move', [$quiz, $question, 'up']) }}">@csrf<button @disabled($loop->first)>نقل لأعلى</button></form><form method="post" action="{{ route('admin.question-bank.quizzes.questions.move', [$quiz, $question, 'down']) }}">@csrf<button @disabled($loop->last)>نقل لأسفل</button></form><form method="post" action="{{ route('admin.question-bank.duplicate', $question) }}">@csrf<button>نسخ السؤال</button></form><form method="post" action="{{ route('admin.question-bank.quizzes.questions.detach', [$quiz, $question]) }}" data-confirm="سيتم إزالة السؤال من هذا الاختبار فقط. سيبقى محفوظاً في بنك الأسئلة.">@csrf @method('delete')<button class="danger-text">إزالة من الاختبار</button></form></div></details></li>@empty<li class="quiz-empty-state"><strong>لا توجد أسئلة في الاختبار حتى الآن</strong><span>أضف أسئلة من بنك الأسئلة لبدء بناء الاختبار.</span><button type="button" class="button button-primary" data-dialog-open="add-questions-{{ $quiz->id }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة أسئلة</button></li>@endforelse</ol>
                                </section>
                            </section>
                        </details>
                        <dialog class="admin-dialog quiz-add-drawer" id="add-questions-{{ $quiz->id }}" data-question-picker>
                            <form method="post" action="{{ route('admin.question-bank.quizzes.questions.attach',$quiz) }}">@csrf
                                <header><div><h2>إضافة أسئلة للاختبار</h2><p>اختر أسئلة من بنك الأسئلة لإضافتها إلى الاختبار.</p><small>{{ $quiz->name_ar }}</small></div><a class="button button-quiet" href="#create-question"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إنشاء سؤال جديد</a><button type="button" data-dialog-close aria-label="إغلاق"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
                                <div class="quiz-drawer-filters"><label class="quiz-drawer-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="ابحث في بنك الأسئلة..." aria-label="ابحث عن سؤال"></label><select aria-label="النوع"><option>كل الأنواع</option><option>اختيار من متعدد</option><option>صح أو خطأ</option></select><select aria-label="الصعوبة"><option>كل الصعوبات</option><option>سهل</option><option>متوسط</option><option>صعب</option></select></div>
                                <div class="quiz-picker-toolbar"><div class="quiz-picker-segments" aria-label="توفر الأسئلة"><button type="button" class="is-active" data-picker-filter="available">المتاحة</button><button type="button" data-picker-filter="all">الكل</button><button type="button" data-picker-filter="added">المضافة</button></div><label class="quiz-select-all"><input type="checkbox" data-picker-select-all><span>تحديد الكل</span></label><span>{{ $activeQuestions->reject(fn($question) => $quiz->questions->contains($question->id))->count() }} متاح</span></div>
                                <div class="bank-import-list quiz-drawer-list">@foreach($activeQuestions as $question)@php($alreadyAdded = $quiz->questions->contains($question->id))<article class="quiz-picker-card {{ $alreadyAdded ? 'is-added' : '' }}" data-picker-card data-picker-state="{{ $alreadyAdded ? 'added' : 'available' }}"><input type="checkbox" name="question_ids[]" value="{{ $question->id }}" @disabled($alreadyAdded)><div class="quiz-picker-copy"><div class="quiz-picker-question-line"><strong>{{ \Illuminate\Support\Str::limit($question->prompt_ar, 150) }}</strong>@if($alreadyAdded)<em><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg> مضاف</em>@endif</div><small>{{ $typeLabels[$question->type] ?? $question->type }} · {{ $difficultyLabels[$question->difficulty] ?? $question->difficulty }} · {{ $question->topic_ar }}</small></div></article>@endforeach</div>
                                <footer><span><b data-picker-count>0</b> أسئلة محددة</span><button type="button" class="button button-quiet" data-dialog-close>إلغاء</button><button class="button button-primary" data-picker-submit disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span data-picker-submit-label>إضافة الأسئلة</span></button></footer>
                            </form>
                        </dialog>
                        <div class="quiz-row-actions">
                            <form method="post" action="{{ route('admin.question-bank.quizzes.duplicate',$quiz) }}">@csrf<button class="quiz-row-action" title="نسخ الاختبار"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h10v12H8zM6 16H4V4h12v2"/></svg><span>نسخ</span></button></form>
                            <form method="post" action="{{ route('admin.question-bank.quizzes.toggle',$quiz) }}">@csrf<button class="quiz-row-action {{ $quiz->is_active ? 'is-warning' : 'is-success' }}" title="{{ $quiz->is_active ? 'تعطيل الاختبار' : 'تفعيل الاختبار' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $quiz->is_active ? 'M10 9v6M14 9v6M5 12a7 7 0 1 0 14 0 7 7 0 0 0-14 0' : 'M8 5v14l11-7z' }}"/></svg><span>{{ $quiz->is_active ? 'تعطيل' : 'تفعيل' }}</span></button></form>
                        </div>
                    </article>
                @empty
                    <div class="empty-state bank-empty"><strong>لا توجد اختبارات بعد</strong><span>أنشئ اختبارك الأول ثم أضف إليه أسئلة من بنك الأسئلة.</span><button type="button" class="button button-primary" data-inline-form-toggle="create-quiz" aria-controls="create-quiz" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> اختبار جديد</button></div>
                @endforelse
                <section class="quiz-detail-dock" data-quiz-detail-dock hidden></section>
                <div class="pagination">{{ $quizzes->links() }}</div>
            </main>
            <section id="create-quiz" class="card quiz-create-panel" @if(old('name_ar') === null) hidden @endif>
                <header><div><h2>إضافة اختبار جديد</h2><p>أدخل بيانات الاختبار الأساسية، ثم افتحه من القائمة لإضافة الأسئلة.</p></div><button type="button" data-inline-form-close="create-quiz" aria-label="إغلاق"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
                <form class="form-grid lesson-editor-form" method="post" action="{{ route('admin.question-bank.quizzes.store') }}">@csrf<label>اسم الاختبار بالعربية<input name="name_ar" value="{{ old('name_ar') }}" placeholder="اختبار الدرس الأول" required></label><label lang="en">English name<input dir="ltr" lang="en" name="name_en" value="{{ old('name_en') }}" placeholder="Optional"></label><label>نوع الاختبار<select name="type" required><option value="training" @selected(old('type') === 'training')>اختبار تدريب</option><option value="required" @selected(old('type') === 'required')>اختبار إلزامي للنجاح</option></select></label><label>درجة النجاح<input type="number" name="passing_score" min="1" max="100" value="{{ old('passing_score', 70) }}" required></label><label class="full">وصف مختصر<textarea name="description_ar" rows="3">{{ old('description_ar') }}</textarea></label><div class="full form-actions"><button type="button" class="button button-quiet" data-inline-form-close="create-quiz"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> إلغاء</button><button class="button button-primary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة الاختبار</button></div></form>
            </section>
        </section>
    @endif
</div>
@endsection
