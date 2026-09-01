@php
    $settings = $section->settingsWithDefaults();
    $items = $section->items();
    $structuredRows = collect((array) data_get($section->content, 'table.rows', []))
        ->map(fn ($row) => array_values(array_filter((array) $row, fn ($cell) => filled($cell))))
        ->filter(fn ($row) => count($row) > 1)
        ->values();
    $tableRows = $structuredRows->isNotEmpty() ? $structuredRows : collect($items)
        ->map(fn ($item) => array_map('trim', explode('|', $item)))
        ->filter(fn ($row) => count($row) > 1)
        ->values();
    $mistakeRows = collect($items)
        ->map(function ($item) {
            $parts = array_map('trim', explode('=>', $item, 2));
            return count($parts) === 2 ? $parts : null;
        })
        ->filter()
        ->values();
    $structuredMistake = data_get($section->content, 'common_mistake');
    $workedExample = collect((array) data_get($section->content, 'worked_example', []))->filter(fn ($value) => filled($value));
    $codeExample = collect((array) data_get($section->content, 'code_example', []))->filter(fn ($value) => filled($value));
    $codeBody = data_get($section->content, 'code_example.code') ?: $section->text();
    $question = (array) data_get($section->content, 'question', []);
    $questionOptions = array_values(array_filter((array) data_get($question, 'options', []), fn ($option) => filled($option)));
    $questionGroup = collect((array) data_get($section->content, 'question_group.questions', []))
        ->map(fn ($question) => trim((string) data_get($question, 'prompt', '')))
        ->filter()
        ->values();
    $answerSpaceLines = ['none' => 0, 'small' => 2, 'medium' => 3, 'large' => 6];
    $questionLines = $answerSpaceLines[data_get($question, 'answer_space', 'medium')] ?? 3;
    $questionGroupLines = $answerSpaceLines[data_get($section->content, 'question_group.answer_space', 'medium')] ?? 3;
    $includeAnswerKey = (bool) ($includeAnswerKey ?? (isset($lesson) ? $lesson->include_workbook_answer_key : false));
    $image = (array) data_get($section->content, 'image', []);
    $imageUrl = data_get($image, 'url');
    $imageMediaId = data_get($image, 'media_asset_id');
    if ($section->type === 'image' && $imageMediaId && isset($lesson) && $lesson->relationLoaded('mediaAssets')) {
        $imageAsset = $lesson->mediaAssets->firstWhere('id', $imageMediaId);
        if ($imageAsset && str_starts_with((string) $imageAsset->mime_type, 'image/')) {
            $imageUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('media.stream', now()->addMinutes(15), ['media' => $imageAsset->id, 'download' => 0]);
        }
    }
    $isTable = in_array($section->type, ['table', 'comparison', 'key_terms'], true) && $tableRows->isNotEmpty();
@endphp

@if($settings['page_break_before'] || $section->type === 'page_break')
    <div class="workbook-page-break"></div>
@endif

@if($section->type !== 'page_break')
    <section class="workbook-section {{ in_array($section->type, ['important_note','definition','example','worked_example','activity','question','question_group','challenge','answer_key'], true) ? 'workbook-callout' : '' }} {{ $section->type === 'important_note' ? 'is-important' : '' }} {{ $section->type === 'common_mistake' ? 'workbook-callout is-mistake' : '' }}" @if(! $settings['avoid_page_break_inside']) style="break-inside:auto" @endif>
        @if($section->title)
            <h2>{{ $section->title }}</h2>
        @endif

        @if($section->type === 'image' && filled($imageUrl))
            <figure class="workbook-image is-{{ data_get($image, 'alignment', 'center') }} is-{{ data_get($image, 'print_width', 'medium') }}">
                <img src="{{ $imageUrl }}" alt="{{ data_get($image, 'alt') ?: $section->title }}">
                @if(data_get($image, 'caption'))
                    <figcaption>{{ data_get($image, 'caption') }}</figcaption>
                @endif
            </figure>
        @elseif($section->type === 'question' && (filled(data_get($question, 'prompt')) || count($questionOptions)))
            @if(data_get($question, 'type'))
                <p class="workbook-code-label">{{ str_replace('_', ' ', data_get($question, 'type')) }}</p>
            @endif
            @if(data_get($question, 'prompt'))
                <p>{!! nl2br(e(data_get($question, 'prompt'))) !!}</p>
            @endif
            @if(count($questionOptions))
                <ul class="workbook-list">
                    @foreach($questionOptions as $option)
                        <li>{{ $option }}</li>
                    @endforeach
                </ul>
            @endif
            @if($questionLines > 0)
                <div class="answer-lines" aria-label="مساحة الإجابة">
                    @for($i = 0; $i < $questionLines; $i++)
                        <span></span>
                    @endfor
                </div>
            @endif
            @if($includeAnswerKey && (data_get($question, 'answer') || data_get($question, 'explanation')))
                <div class="workbook-field">
                    @if(data_get($question, 'answer'))
                        <strong>الإجابة النموذجية</strong>
                        <p>{!! nl2br(e(data_get($question, 'answer'))) !!}</p>
                    @endif
                    @if(data_get($question, 'explanation'))
                        <strong>شرح الإجابة</strong>
                        <p>{!! nl2br(e(data_get($question, 'explanation'))) !!}</p>
                    @endif
                </div>
            @endif
        @elseif($section->type === 'question_group' && $questionGroup->isNotEmpty())
            <ol class="workbook-list">
                @foreach($questionGroup as $prompt)
                    <li>{{ $prompt }}</li>
                @endforeach
            </ol>
            @if($questionGroupLines > 0)
                <div class="answer-lines" aria-label="مساحة الإجابة">
                    @for($i = 0; $i < $questionGroupLines; $i++)
                        <span></span>
                    @endfor
                </div>
            @endif
        @elseif($section->type === 'code')
            @if(data_get($section->content, 'language'))
                <p class="workbook-code-label">{{ data_get($section->content, 'language') }}</p>
            @endif
            @foreach(['problem' => 'المطلوب', 'logic' => 'الفكرة', 'explanation' => 'شرح الكود', 'try_it' => 'جرّب بنفسك'] as $field => $label)
                @if(data_get($section->content, "code_example.{$field}"))
                    <div class="workbook-field"><strong>{{ $label }}</strong><p>{!! nl2br(e(data_get($section->content, "code_example.{$field}"))) !!}</p></div>
                @endif
            @endforeach
            @if($codeBody)
                <pre class="workbook-code"><code>{{ $codeBody }}</code></pre>
            @endif
            @if(data_get($section->content, 'code_example.expected_output'))
                <div class="workbook-field"><strong>المخرجات المتوقعة</strong><pre class="workbook-code workbook-output"><code>{{ data_get($section->content, 'code_example.expected_output') }}</code></pre></div>
            @endif
        @elseif($section->type === 'worked_example' && $workedExample->isNotEmpty())
            @foreach(['problem' => 'المسألة', 'thinking' => 'طريقة التفكير', 'solution' => 'الحل', 'explanation' => 'شرح الحل', 'key_lesson' => 'الخلاصة'] as $field => $label)
                @if(data_get($section->content, "worked_example.{$field}"))
                    <div class="workbook-field"><strong>{{ $label }}</strong><p>{!! nl2br(e(data_get($section->content, "worked_example.{$field}"))) !!}</p></div>
                @endif
            @endforeach
        @elseif($section->type === 'common_mistake' && filled(data_get($structuredMistake, 'mistake')))
            <div class="workbook-mistakes">
                <article>
                    <strong>الخطأ:</strong>
                    <p>{{ data_get($structuredMistake, 'mistake') }}</p>
                    @if(filled(data_get($structuredMistake, 'correction')))
                        <strong>الصحيح:</strong>
                        <p>{{ data_get($structuredMistake, 'correction') }}</p>
                    @endif
                </article>
            </div>
        @elseif($section->text() !== '')
            <p>{!! nl2br(e($section->text())) !!}</p>
        @endif

        @if($isTable)
            <table class="workbook-resources workbook-content-table">
                <tbody>
                    @foreach($tableRows as $rowIndex => $row)
                        <tr>
                            @foreach($row as $cell)
                                @if($rowIndex === 0)
                                    <th>{{ $cell }}</th>
                                @else
                                    <td>{{ $cell }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif($section->type === 'common_mistake' && $mistakeRows->isNotEmpty())
            <div class="workbook-mistakes">
                @foreach($mistakeRows as [$mistake, $correction])
                    <article>
                        <strong>الخطأ:</strong>
                        <p>{{ $mistake }}</p>
                        <strong>الصحيح:</strong>
                        <p>{{ $correction }}</p>
                    </article>
                @endforeach
            </div>
        @elseif(count($items))
            <ul class="workbook-list">
                @foreach($items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif

        @if(in_array($section->type, ['activity','challenge'], true))
            <div class="answer-lines" aria-label="مساحة الإجابة"><span></span><span></span><span></span></div>
        @endif
    </section>
@endif

@if($settings['page_break_after'])
    <div class="workbook-page-break"></div>
@endif
