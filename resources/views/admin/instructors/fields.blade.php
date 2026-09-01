<label>الاسم العربي<input name="name" value="{{ old('name',$item?->name) }}" placeholder="الاسم الكامل" required></label>
<label lang="en">English name<input dir="ltr" name="name_en" value="{{ old('name_en',$item?->name_en) }}" placeholder="Full name" required></label>
<label>المسمى المهني<input name="headline" value="{{ old('headline',$item?->headline) }}" placeholder="مثال: مدرس Python"></label>
<label lang="en">English headline<input dir="ltr" name="headline_en" value="{{ old('headline_en',$item?->headline_en) }}" placeholder="Example: Python Instructor"></label>
<label class="full">البريد المهني<input dir="ltr" type="email" name="email" value="{{ old('email',$item?->email) }}" placeholder="instructor@example.com"></label>
<label>النبذة العربية<textarea name="bio" placeholder="الخبرة والتخصص وأسلوب التدريس">{{ old('bio',$item?->bio) }}</textarea></label>
<label lang="en">English bio<textarea dir="ltr" name="bio_en" placeholder="Experience, specialty, and teaching approach">{{ old('bio_en',$item?->bio_en) }}</textarea></label>
