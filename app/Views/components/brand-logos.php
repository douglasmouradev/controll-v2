<?php
declare(strict_types=1);

/** @var string $variant sidebar|auth|header|topbar|inline */
$variant = $variant ?? 'header';

$controllClass = match ($variant) {
	'sidebar' => 'h-8 w-auto object-contain flex-shrink-0',
	'auth' => 'h-10 w-auto object-contain',
	'header' => 'h-10 w-auto object-contain',
	'topbar' => 'h-8 w-auto object-contain flex-shrink-0',
	default => 'h-8 w-auto object-contain',
};

$caClass = match ($variant) {
	'sidebar' => 'h-7 w-auto object-contain flex-shrink-0',
	'auth' => 'h-9 w-auto object-contain',
	'header' => 'h-9 w-auto object-contain',
	'topbar' => 'h-8 w-auto object-contain flex-shrink-0',
	default => 'h-8 w-auto object-contain',
};

$showDivider = in_array($variant, ['auth', 'header'], true);
$wrapperClass = match ($variant) {
	'sidebar' => 'flex items-center gap-2.5 flex-shrink-0',
	'auth' => 'flex items-center justify-center gap-4 mb-2',
	'header' => 'flex items-center gap-3',
	'topbar' => 'hidden sm:flex items-center gap-2.5 mr-1',
	default => 'inline-flex items-center gap-2.5',
};
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
	<img src="/logo-controll-it.png" onerror="this.onerror=null;this.src='/logo-controll-it.svg';" class="<?php echo htmlspecialchars($controllClass, ENT_QUOTES, 'UTF-8'); ?>" alt="Controll IT">
	<?php if ($showDivider): ?>
		<div class="h-10 w-px bg-slate-200 flex-shrink-0" aria-hidden="true"></div>
	<?php endif; ?>
	<img src="/logo-ca.png" onerror="this.onerror=null;this.src='/logo-ca.svg';" class="<?php echo htmlspecialchars($caClass, ENT_QUOTES, 'UTF-8'); ?>" alt="C&amp;A">
</div>
