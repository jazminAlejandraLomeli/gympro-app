<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        
        <meta name="theme-color" content="<?php echo e($brand->primary); ?>">

        <title inertia><?php echo e($brand->name); ?></title>

        <?php if($brand->faviconUrl): ?>
            <link rel="icon" href="<?php echo e($brand->faviconUrl); ?>">
        <?php endif; ?>

        
        <style>
            :root {
                <?php $__currentLoopData = $brand->cssVariables(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo e($var); ?>: <?php echo e($value); ?>;
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            }
        </style>

        <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.jsx']); ?>
        <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="min-h-dvh bg-slate-50 font-sans text-slate-900 antialiased">
        <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><script data-page="app" type="application/json"><?php echo json_encode($page, JSON_HEX_TAG); ?></script><div id="app"></div><?php } ?>
    </body>
</html>
<?php /**PATH /Users/jazminlomeli/Documents/gympro-app/resources/views/app.blade.php ENDPATH**/ ?>