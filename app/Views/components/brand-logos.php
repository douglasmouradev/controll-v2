<?php
declare(strict_types=1);

/** @var string $variant sidebar|auth|header|topbar|inline */
$variant = $variant ?? 'header';

$controllClass = match ($variant) {
	'sidebar' => 'brand-logo-controll h-8 w-auto object-contain flex-shrink-0',
	'auth' => 'brand-logo-controll',
	'header' => 'brand-logo-controll h-10 w-auto object-contain max-w-[9.5rem]',
	'topbar' => 'brand-logo-controll h-8 w-auto object-contain flex-shrink-0 max-w-[8rem]',
	default => 'brand-logo-controll h-8 w-auto object-contain',
};

$caClass = match ($variant) {
	'sidebar' => 'brand-logo-ca h-7 w-auto object-contain flex-shrink-0',
	'auth' => 'brand-logo-ca',
	'header' => 'brand-logo-ca h-9 w-auto object-contain max-w-[3.75rem]',
	'topbar' => 'brand-logo-ca h-8 w-auto object-contain flex-shrink-0 max-w-[3.5rem]',
	default => 'brand-logo-ca h-8 w-auto object-contain',
};

$showDivider = in_array($variant, ['auth', 'header'], true);
$wrapperClass = match ($variant) {
	'sidebar' => 'brand-logos flex items-center gap-2.5 flex-shrink-0',
	'auth' => 'brand-logos brand-logos--auth',
	'header' => 'brand-logos flex items-center gap-3',
	'topbar' => 'brand-logos hidden sm:flex items-center gap-2.5 mr-1',
	default => 'brand-logos inline-flex items-center gap-2.5',
};
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
	<img src="/logo-controll-it.png" onerror="this.onerror=null;this.src='/logo-controll-it.svg';" class="<?php echo htmlspecialchars($controllClass, ENT_QUOTES, 'UTF-8'); ?>" alt="Controll IT">
	<?php if ($showDivider): ?>
		<?php if ($variant === 'auth'): ?>
			<div class="brand-logos-divider" aria-hidden="true"></div>
		<?php else: ?>
			<div class="h-10 w-px bg-slate-200 flex-shrink-0" aria-hidden="true"></div>
		<?php endif; ?>
	<?php endif; ?>
	<img src="/logo-ca.png" onerror="this.onerror=null;this.src='/logo-ca.svg';" class="<?php echo htmlspecialchars($caClass, ENT_QUOTES, 'UTF-8'); ?>" alt="C&amp;A">
</div>
