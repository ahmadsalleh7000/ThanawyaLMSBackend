@props(['display' => 'table'])
<nav class="view-switcher" aria-label="طريقة العرض">
    <a href="{{ url()->current().'?'.http_build_query(array_merge(request()->except('page', 'view'), ['view' => 'table'])) }}" class="{{ $display === 'table' ? 'active' : '' }}" @if($display === 'table') aria-current="true" @endif>
        <span aria-hidden="true">☷</span> جدول
    </a>
    <a href="{{ url()->current().'?'.http_build_query(array_merge(request()->except('page', 'view'), ['view' => 'grid'])) }}" class="{{ $display === 'grid' ? 'active' : '' }}" @if($display === 'grid') aria-current="true" @endif>
        <span aria-hidden="true">▦</span> شبكة
    </a>
</nav>
