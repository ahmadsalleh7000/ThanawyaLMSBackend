<label>الاسم العربي<input name="name" value="<?php echo e(old('name',$item?->name)); ?>" placeholder="الاسم الكامل" required></label>
<label lang="en">English name<input dir="ltr" name="name_en" value="<?php echo e(old('name_en',$item?->name_en)); ?>" placeholder="Full name" required></label>
<label>المسمى المهني<input name="headline" value="<?php echo e(old('headline',$item?->headline)); ?>" placeholder="مثال: مدرس Python"></label>
<label lang="en">English headline<input dir="ltr" name="headline_en" value="<?php echo e(old('headline_en',$item?->headline_en)); ?>" placeholder="Example: Python Instructor"></label>
<label class="full">البريد المهني<input dir="ltr" type="email" name="email" value="<?php echo e(old('email',$item?->email)); ?>" placeholder="instructor@example.com"></label>
<label>النبذة العربية<textarea name="bio" placeholder="الخبرة والتخصص وأسلوب التدريس"><?php echo e(old('bio',$item?->bio)); ?></textarea></label>
<label lang="en">English bio<textarea dir="ltr" name="bio_en" placeholder="Experience, specialty, and teaching approach"><?php echo e(old('bio_en',$item?->bio_en)); ?></textarea></label>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/instructors/fields.blade.php ENDPATH**/ ?>