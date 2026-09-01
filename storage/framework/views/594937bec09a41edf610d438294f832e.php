<div class="bank-field-row full">
    <label>نوع السؤال<select name="type" required><option value="single_choice" <?php if(old('type',$item?->type)==='single_choice'): echo 'selected'; endif; ?>>اختيار واحد</option><option value="true_false" <?php if(old('type',$item?->type)==='true_false'): echo 'selected'; endif; ?>>صح أو خطأ</option></select></label>
    <label>درجة الصعوبة<select name="difficulty" required><?php $__currentLoopData = ['easy'=>'سهل','medium'=>'متوسط','hard'=>'صعب']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('difficulty',$item?->difficulty ?? 'medium')===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
</div>
<label>الموضوع بالعربية<input name="topic_ar" value="<?php echo e(old('topic_ar',$item?->topic_ar)); ?>" placeholder="مثال: الحلقات" required></label>
<label lang="en">English topic<input dir="ltr" name="topic" value="<?php echo e(old('topic',$item?->topic)); ?>" placeholder="Example: Loops" required></label>
<label>نص السؤال بالعربية<textarea name="prompt_ar" placeholder="اكتب سؤالاً واضحاً ومباشراً" required><?php echo e(old('prompt_ar',$item?->prompt_ar)); ?></textarea></label>
<label lang="en">English question<textarea dir="ltr" name="prompt" placeholder="Write a clear, direct question" required><?php echo e(old('prompt',$item?->prompt)); ?></textarea></label>
<label>الإجابات العربية <small>إجابة واحدة في كل سطر</small><textarea name="options_ar_text" placeholder="الإجابة الأولى&#10;الإجابة الثانية"><?php echo e(old('options_ar_text',$item ? implode("\n",$item->options_ar) : '')); ?></textarea></label>
<label lang="en">English answers <small>One answer per line</small><textarea dir="ltr" name="options_text" placeholder="First answer&#10;Second answer"><?php echo e(old('options_text',$item ? implode("\n",$item->options) : '')); ?></textarea></label>
<fieldset class="bank-correct-options full">
    <legend>الإجابة الصحيحة</legend>
    <?php $__currentLoopData = [0,1,2,3]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <label><input type="radio" name="correct_option" value="<?php echo e($index); ?>" <?php if((int) old('correct_option',$item?->correct_option ?? 0) === $index): echo 'checked'; endif; ?>><span>الاختيار <?php echo e($index + 1); ?></span></label>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</fieldset>
<label>شرح الإجابة بالعربية<textarea name="explanation_ar" placeholder="اشرح لماذا هذه الإجابة صحيحة" required><?php echo e(old('explanation_ar',$item?->explanation_ar)); ?></textarea></label>
<label lang="en">English explanation<textarea dir="ltr" name="explanation" placeholder="Explain why this answer is correct" required><?php echo e(old('explanation',$item?->explanation)); ?></textarea></label>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/question-bank/fields.blade.php ENDPATH**/ ?>