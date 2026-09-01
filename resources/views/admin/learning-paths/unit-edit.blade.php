@extends('admin.layout')
@section('title','تحرير الوحدة')
@section('content')
<nav class="management-breadcrumb" aria-label="مسار التنقل"><a href="{{ route('admin.learning-paths.index') }}">المسارات التعليمية</a><span>←</span><a href="{{ route('admin.learning-paths.courses.show',$course) }}">{{ $course->title }}</a><span>←</span><a href="{{ route('admin.learning-paths.courses.units',$course) }}">الوحدات</a><span>←</span><strong>تحرير {{ $unit->title_ar }}</strong></nav>
<header class="page-header management-page-header"><div><div class="eyebrow">بيانات الوحدة</div><h1>تحرير الوحدة</h1><p>حدّث الاسم والمعرّف دون تغيير ترتيب الدروس أو محتواها.</p></div><span class="badge {{ $unit->status==='published'?'published':'draft' }}">{{ $unit->status==='published'?'منشورة':'مسودة' }}</span></header>
<form class="card form-grid unit-edit-form" method="post" action="{{ route('admin.learning-paths.units.update',[$course,$unit]) }}">@csrf @method('put')
    <label>العنوان العربي<input name="title_ar" value="{{ old('title_ar',$unit->title_ar) }}" maxlength="150" required></label>
    <label lang="en">English title<input name="title_en" dir="ltr" value="{{ old('title_en',$unit->title_en) }}" maxlength="150" required></label>
    <label class="full">المعرّف التقني<input name="slug" dir="ltr" value="{{ old('slug',$unit->slug) }}" maxlength="80" pattern="[a-z0-9-]+" title="استخدم حروفاً إنجليزية صغيرة وأرقاماً وشرطات فقط" required><small>حروف إنجليزية صغيرة وأرقام وشرطات فقط.</small></label>
    <div class="form-actions full"><a class="button button-quiet" href="{{ route('admin.learning-paths.courses.units',$course) }}">إلغاء</a><button class="button button-primary">حفظ التعديلات</button></div>
</form>
@endsection
