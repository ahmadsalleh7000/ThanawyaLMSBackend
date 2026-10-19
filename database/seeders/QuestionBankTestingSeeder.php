<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\QuestionBankQuestion;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionBankTestingSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public const MARKER = 'testing.question_bank_seed.v1';

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();
        if (PlatformSetting::where('key', self::MARKER)->exists()) {
            $this->command?->info('Testing question-bank data already exists; nothing was changed.');

            return;
        }

        DB::transaction(function (): void {
            $creator = User::firstOrCreate(
                ['email' => 'content.dashboard@test.local'],
                ['name' => 'محرر بنك الأسئلة', 'password' => 'TestingPassword123!', 'role' => User::ROLE_ADMIN, 'admin_role' => 'content_editor', 'is_active' => true],
            );

            foreach ($this->questions() as $index => $question) {
                QuestionBankQuestion::create($question + ['created_by' => $creator->id, 'is_active' => $index !== 9]);
            }

            PlatformSetting::create(['key' => self::MARKER, 'value' => ['version' => 1, 'seeded_at' => now()->toISOString()], 'group' => 'testing']);
        });
    }

    private function questions(): array
    {
        return [
            $this->choice('Python Basics', 'أساسيات Python', 'Which function prints text in Python?', 'ما الدالة التي تطبع نصاً في Python؟', ['print()', 'show()', 'write()'], ['print()', 'show()', 'write()'], 0, 'print() writes values to standard output.', 'تكتب print() القيم في المخرجات القياسية.', 'easy'),
            $this->choice('Variables', 'المتغيرات', 'Which is a valid Python assignment?', 'أي تعبير يمثل إسناداً صحيحاً في Python؟', ['score = 10', 'let score = 10', 'score :=: 10'], ['score = 10', 'let score = 10', 'score :=: 10'], 0, 'Python uses the equals sign for assignment.', 'تستخدم Python علامة المساواة لإسناد القيم.', 'easy'),
            $this->choice('Conditions', 'الشروط', 'Which keyword starts a condition?', 'أي كلمة محجوزة تبدأ جملة شرطية؟', ['if', 'when', 'check'], ['if', 'when', 'check'], 0, 'A Python condition starts with if.', 'تبدأ الجملة الشرطية في Python بالكلمة if.', 'easy'),
            $this->choice('Loops', 'الحلقات', 'What does range(3) produce for iteration?', 'ما القيم التي ينتجها range(3) للتكرار؟', ['0, 1, 2', '1, 2, 3', '0, 1, 2, 3'], ['0، 1، 2', '1، 2، 3', '0، 1، 2، 3'], 0, 'The upper bound is excluded.', 'لا يتم تضمين الحد الأعلى في range.', 'medium'),
            $this->choice('Functions', 'الدوال', 'How do you return a value from a function?', 'كيف تعيد قيمة من داخل دالة؟', ['return value', 'send value', 'output value'], ['return value', 'send value', 'output value'], 0, 'return ends the function and provides its result.', 'تنهي return الدالة وتعيد نتيجتها.', 'medium'),
            $this->choice('Lists', 'القوائم', 'Which method adds one item to a list?', 'أي دالة تضيف عنصراً واحداً إلى القائمة؟', ['append()', 'push()', 'insertEnd()'], ['append()', 'push()', 'insertEnd()'], 0, 'append() adds an item to the end.', 'تضيف append() عنصراً إلى نهاية القائمة.', 'medium'),
            $this->boolean('Data Types', 'أنواع البيانات', 'A Python tuple can be changed after creation.', 'يمكن تعديل عناصر tuple بعد إنشائها.', 1, 'Tuples are immutable.', 'الـ tuple نوع غير قابل للتعديل.', 'medium'),
            $this->choice('Algorithms', 'الخوارزميات', 'What is binary search time complexity?', 'ما التعقيد الزمني للبحث الثنائي؟', ['O(log n)', 'O(n)', 'O(n²)'], ['O(log n)', 'O(n)', 'O(n²)'], 0, 'Each comparison halves the remaining search space.', 'كل مقارنة تقلل مساحة البحث المتبقية إلى النصف.', 'hard'),
            $this->choice('Object-Oriented Programming', 'البرمجة الكائنية', 'Which method initializes a Python object?', 'أي دالة تهيئ كائناً جديداً في Python؟', ['__init__', '__start__', '__create__'], ['__init__', '__start__', '__create__'], 0, '__init__ initializes a newly created instance.', 'تقوم __init__ بتهيئة النسخة الجديدة.', 'hard'),
            $this->boolean('Security', 'الأمان', 'User input should be trusted before validation.', 'يجب الوثوق بمدخلات المستخدم قبل التحقق منها.', 1, 'External input must always be validated.', 'يجب دائماً التحقق من المدخلات الخارجية.', 'easy'),
        ];
    }

    private function choice(string $topic, string $topicAr, string $prompt, string $promptAr, array $options, array $optionsAr, int $correct, string $explanation, string $explanationAr, string $difficulty): array
    {
        return ['type' => 'single_choice', 'difficulty' => $difficulty, 'topic' => $topic, 'topic_ar' => $topicAr, 'prompt' => $prompt, 'prompt_ar' => $promptAr, 'options' => $options, 'options_ar' => $optionsAr, 'correct_option' => $correct, 'explanation' => $explanation, 'explanation_ar' => $explanationAr];
    }

    private function boolean(string $topic, string $topicAr, string $prompt, string $promptAr, int $correct, string $explanation, string $explanationAr, string $difficulty): array
    {
        return ['type' => 'true_false', 'difficulty' => $difficulty, 'topic' => $topic, 'topic_ar' => $topicAr, 'prompt' => $prompt, 'prompt_ar' => $promptAr, 'options' => ['True', 'False'], 'options_ar' => ['صحيح', 'خطأ'], 'correct_option' => $correct, 'explanation' => $explanation, 'explanation_ar' => $explanationAr];
    }
}
