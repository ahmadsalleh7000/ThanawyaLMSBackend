<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaLibraryItem;
use App\Services\AuditService;
use App\Services\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMediaLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['kind', 'status', 'health', 'usage', 'sort', 'view']), [
            'kind' => ['nullable', Rule::in(['video', 'resource'])],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'health' => ['nullable', Rule::in(['ready', 'processing', 'failed'])],
            'usage' => ['nullable', Rule::in(['attached', 'orphaned'])],
            'sort' => ['nullable', Rule::in(['latest', 'name', 'size', 'usage'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
        ])->validate();
        $search = $request->string('search')->trim()->toString();
        $status = $filters['status'] ?? ($request->boolean('archived') ? 'archived' : 'active');
        $usage = $filters['usage'] ?? ($request->boolean('orphaned') ? 'orphaned' : null);
        $itemsQuery = MediaLibraryItem::query()->with(['uploader:id,name', 'attachments.lesson.course:id,title'])->withCount('attachments')
            ->when($status === 'active', fn ($query) => $query->whereNull('archived_at'), fn ($query) => $query->whereNotNull('archived_at'))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['health'] ?? null, fn ($query, $health) => $query->where('processing_status', $health))
            ->when($usage === 'orphaned', fn ($query) => $query->doesntHave('attachments'))
            ->when($usage === 'attached', fn ($query) => $query->has('attachments'))
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('original_name', 'like', '%'.$search.'%')->orWhere('mime_type', 'like', '%'.$search.'%')));

        match ($filters['sort'] ?? 'latest') {
            'name' => $itemsQuery->orderBy('original_name'),
            'size' => $itemsQuery->orderByDesc('size_bytes'),
            'usage' => $itemsQuery->orderByDesc('attachments_count'),
            default => $itemsQuery->latest(),
        };

        return view('admin.media-library.index', [
            'items' => $itemsQuery->paginate(12)->withQueryString(),
            'display' => $filters['view'] ?? 'table',
            'courses' => Course::notArchived()->with('lessons:id,course_id,title')->orderBy('title')->get(['id', 'title']),
            'stats' => [
                'files' => MediaLibraryItem::whereNull('archived_at')->count(),
                'bytes' => (int) MediaLibraryItem::whereNull('archived_at')->sum('size_bytes'),
                'orphaned' => MediaLibraryItem::whereNull('archived_at')->doesntHave('attachments')->count(),
                'failed' => MediaLibraryItem::whereNull('archived_at')->where('processing_status', 'failed')->count(),
                'videos' => MediaLibraryItem::whereNull('archived_at')->where('kind', 'video')->count(),
                'resources' => MediaLibraryItem::whereNull('archived_at')->where('kind', 'resource')->count(),
            ],
        ]);
    }

    public function store(Request $request, MediaLibraryService $library, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['video', 'resource'])], 'file' => ['required', 'file', 'max:524288']]);
        $item = $library->upload($data['file'], $data['kind'], $request->user());
        $audit->record($request, 'media_library.uploaded', $item);

        return back()->with('success', 'تم رفع الملف إلى المكتبة ويمكن ربطه بأكثر من درس.');
    }

    public function attach(Request $request, MediaLibraryItem $item, MediaLibraryService $library, AuditService $audit): RedirectResponse
    {
        $lesson = Lesson::findOrFail($request->validate(['lesson_id' => ['required', 'exists:lessons,id']])['lesson_id']);
        $asset = $library->attach($item, $lesson);
        $audit->record($request, 'media_library.attached', $asset);

        return back()->with('success', 'تم ربط الملف بالدرس.');
    }

    public function verify(Request $request, MediaLibraryItem $item, AuditService $audit): RedirectResponse
    {
        $before = $item->attributesToArray();
        $exists = Storage::disk($item->disk)->exists($item->path);
        $checksum = $exists ? hash_file('sha256', Storage::disk($item->disk)->path($item->path)) : null;
        $valid = $exists && (! $item->checksum_sha256 || hash_equals($item->checksum_sha256, $checksum));
        $item->update(['processing_status' => $valid ? 'ready' : 'failed', 'processing_error' => $valid ? null : ($exists ? 'Checksum mismatch.' : 'File missing from storage.'), 'checksum_sha256' => $item->checksum_sha256 ?: $checksum]);
        $audit->record($request, 'media_library.verified', $item, $before);

        return back()->with('success', $valid ? 'تم التحقق من سلامة الملف.' : 'فشل التحقق؛ راجع حالة الملف.');
    }

    public function archive(Request $request, MediaLibraryItem $item, MediaLibraryService $library, AuditService $audit): RedirectResponse
    {
        $before = $item->attributesToArray();
        $library->archive($item);
        $audit->record($request, 'media_library.archived', $item, $before);

        return back()->with('success', 'تمت أرشفة الملف دون حذفه من التخزين.');
    }
}
