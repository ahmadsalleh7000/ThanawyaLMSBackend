<?php ($admin = auth()->user()); ?>
<nav class="admin-nav" aria-label="أقسام لوحة الإدارة">
    <div class="nav-group">
        <p class="nav-group-label">الرئيسية</p>
        <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.dashboard'),'active' => request()->routeIs('admin.dashboard'),'icon' => 'home','label' => 'نظرة عامة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.dashboard')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.dashboard')),'icon' => 'home','label' => 'نظرة عامة']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
    </div>

    <?php if($admin->hasAdminPermission('content.manage') || $admin->hasAdminPermission('instructors.manage')): ?>
    <div class="nav-group">
        <p class="nav-group-label">التعلّم والمحتوى</p>
        <?php if($admin->hasAdminPermission('content.manage')): ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.academics.index'),'active' => request()->routeIs('admin.academics.*'),'icon' => 'analytics','label' => 'الهيكل الأكاديمي']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.academics.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.academics.*')),'icon' => 'analytics','label' => 'الهيكل الأكاديمي']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.learning-paths.index'),'active' => request()->routeIs('admin.learning-paths.*'),'icon' => 'course','label' => 'المسارات التعليمية']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.learning-paths.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.learning-paths.*')),'icon' => 'course','label' => 'المسارات التعليمية']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.courses.index'),'active' => request()->routeIs('admin.courses.*', 'admin.lessons.*', 'admin.assessment.*', 'admin.questions.*', 'admin.media.*'),'icon' => 'course','label' => 'الدورات والمحتوى']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.courses.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.courses.*', 'admin.lessons.*', 'admin.assessment.*', 'admin.questions.*', 'admin.media.*')),'icon' => 'course','label' => 'الدورات والمحتوى']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.question-bank.index'),'active' => request()->routeIs('admin.question-bank.*'),'icon' => 'question','label' => 'بنك الأسئلة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.question-bank.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.question-bank.*')),'icon' => 'question','label' => 'بنك الأسئلة']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.media-library.index'),'active' => request()->routeIs('admin.media-library.*'),'icon' => 'media','label' => 'مكتبة الوسائط']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.media-library.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.media-library.*')),'icon' => 'media','label' => 'مكتبة الوسائط']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if($admin->hasAdminPermission('instructors.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.instructors.index'),'active' => request()->routeIs('admin.instructors.*'),'icon' => 'teacher','label' => 'المدرسون']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.instructors.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.instructors.*')),'icon' => 'teacher','label' => 'المدرسون']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($admin->hasAdminPermission('users.manage') || $admin->hasAdminPermission('enrollments.manage') || $admin->hasAdminPermission('announcements.manage')): ?>
    <div class="nav-group">
        <p class="nav-group-label">الطلاب والتواصل</p>
        <?php if($admin->hasAdminPermission('users.manage')): ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.users.index'),'active' => request()->routeIs('admin.users.*'),'icon' => 'users','label' => 'الطلاب والأسر']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.users.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.users.*')),'icon' => 'users','label' => 'الطلاب والأسر']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.support.index'),'active' => request()->routeIs('admin.support.*'),'icon' => 'support','label' => 'الدعم والمتابعة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.support.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.support.*')),'icon' => 'support','label' => 'الدعم والمتابعة']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.support-tickets.index'),'active' => request()->routeIs('admin.support-tickets.*'),'icon' => 'announce','label' => 'طلبات الدعم']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.support-tickets.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.support-tickets.*')),'icon' => 'announce','label' => 'طلبات الدعم']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.contact-messages.index'),'active' => request()->routeIs('admin.contact-messages.*'),'icon' => 'announce','label' => 'رسائل التواصل']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.contact-messages.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.contact-messages.*')),'icon' => 'announce','label' => 'رسائل التواصل']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if($admin->hasAdminPermission('enrollments.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.enrollments.index'),'active' => request()->routeIs('admin.enrollments.*'),'icon' => 'enrollment','label' => 'التسجيلات والصلاحيات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.enrollments.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.enrollments.*')),'icon' => 'enrollment','label' => 'التسجيلات والصلاحيات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
        <?php if($admin->hasAdminPermission('announcements.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.announcements.index'),'active' => request()->routeIs('admin.announcements.*'),'icon' => 'announce','label' => 'الإعلانات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.announcements.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.announcements.*')),'icon' => 'announce','label' => 'الإعلانات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($admin->hasAdminPermission('finance.manage')): ?>
    <div class="nav-group">
        <p class="nav-group-label">المالية والتجارة</p>
        <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.orders.index'),'active' => request()->routeIs('admin.orders.*'),'icon' => 'orders','label' => 'الطلبات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.orders.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.orders.*')),'icon' => 'orders','label' => 'الطلبات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.payment-reviews.index'),'active' => request()->routeIs('admin.payment-reviews.*'),'icon' => 'payment','label' => 'مراجعة التحويلات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.payment-reviews.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.payment-reviews.*')),'icon' => 'payment','label' => 'مراجعة التحويلات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
        <?php if($admin->hasAdminPermission('reconciliation.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.reconciliation.index'),'active' => request()->routeIs('admin.reconciliation.*'),'icon' => 'reconcile','label' => 'المطابقة المالية']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.reconciliation.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.reconciliation.*')),'icon' => 'reconcile','label' => 'المطابقة المالية']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
        <?php if($admin->hasAdminPermission('pricing.manage')): ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.commerce.index'),'active' => request()->routeIs('admin.commerce.index'),'icon' => 'orders','label' => 'ملخص التجارة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.commerce.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.commerce.index')),'icon' => 'orders','label' => 'ملخص التجارة']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.commerce.subscription-plans.index'),'active' => request()->routeIs('admin.commerce.subscription-plans.*'),'icon' => 'orders','label' => 'خطط الاشتراك']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.commerce.subscription-plans.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.commerce.subscription-plans.*')),'icon' => 'orders','label' => 'خطط الاشتراك']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.commerce.coupons.index'),'active' => request()->routeIs('admin.commerce.coupons.*'),'icon' => 'payment','label' => 'أكواد الخصم']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.commerce.coupons.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.commerce.coupons.*')),'icon' => 'payment','label' => 'أكواد الخصم']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.commerce.payment-settings.index'),'active' => request()->routeIs('admin.commerce.payment-settings.*'),'icon' => 'payment','label' => 'قنوات التحويل']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.commerce.payment-settings.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.commerce.payment-settings.*')),'icon' => 'payment','label' => 'قنوات التحويل']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.commerce.bundles.index'),'active' => request()->routeIs('admin.commerce.bundles.*'),'icon' => 'course','label' => 'الباقات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.commerce.bundles.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.commerce.bundles.*')),'icon' => 'course','label' => 'الباقات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($admin->hasAdminPermission('analytics.view') || $admin->hasAdminPermission('trust.manage')): ?>
    <div class="nav-group">
        <p class="nav-group-label">القياس والجودة</p>
        <?php if($admin->hasAdminPermission('analytics.view')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.analytics.index'),'active' => request()->routeIs('admin.analytics.*'),'icon' => 'analytics','label' => 'التحليلات والتقارير']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.analytics.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.analytics.*')),'icon' => 'analytics','label' => 'التحليلات والتقارير']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
        <?php if($admin->hasAdminPermission('trust.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.trust.index'),'active' => request()->routeIs('admin.trust.*'),'icon' => 'trust','label' => 'الثقة والشهادات']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.trust.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.trust.*')),'icon' => 'trust','label' => 'الثقة والشهادات']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($admin->hasAdminPermission('staff.manage') || $admin->hasAdminPermission('system.manage')): ?>
    <div class="nav-group">
        <p class="nav-group-label">إدارة المنصة</p>
        <?php if($admin->hasAdminPermission('staff.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.staff.index'),'active' => request()->routeIs('admin.staff.*'),'icon' => 'staff','label' => 'فريق الإدارة']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.staff.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.staff.*')),'icon' => 'staff','label' => 'فريق الإدارة']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
        <?php if($admin->hasAdminPermission('system.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.system.index'),'active' => request()->routeIs('admin.system.*'),'icon' => 'system','label' => 'النظام والأمان']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.system.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.system.*')),'icon' => 'system','label' => 'النظام والأمان']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
        <?php if($admin->hasAdminPermission('system.manage')): ?><?php if (isset($component)) { $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.nav-link','data' => ['href' => route('admin.privacy.index'),'active' => request()->routeIs('admin.privacy.*'),'icon' => 'trust','label' => 'الخصوصية والحذف']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.nav-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.privacy.index')),'active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(request()->routeIs('admin.privacy.*')),'icon' => 'trust','label' => 'الخصوصية والحذف']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $attributes = $__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__attributesOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9)): ?>
<?php $component = $__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9; ?>
<?php unset($__componentOriginal2a216ff9e9eb80e1c598e0a42b5b7ec9); ?>
<?php endif; ?><?php endif; ?>
    </div>
    <?php endif; ?>
</nav>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/partials/navigation.blade.php ENDPATH**/ ?>