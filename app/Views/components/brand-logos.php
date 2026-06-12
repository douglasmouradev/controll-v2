<?php
declare(strict_types=1);

/** @var string $variant sidebar|auth|header|topbar|inline */
$variant = $variant ?? 'header';

$controllClass = match ($variant) {
	'sidebar' => 'h-8 w-8 object-contain brightness-0 invert flex-shrink-0',
	'auth' => 'h-11 object-contain',
	'header' => 'h-11 object-contain',
	default => 'h-8 object-contain',
};

$caClass = match ($variant) {
	'sidebar' => 'h-7 w-7 object-contain',
	'auth' => 'h-9 w-9 object-contain',
	'header' => 'h-9 w-9 object-contain',
	'topbar' => 'h-8 w-8 object-contain',
	default => 'h-8 w-8 object-contain',
};

$caFrameClass = match ($variant) {
	'sidebar' => 'inline-flex items-center justify-center bg-white rounded-md p-1 flex-shrink-0',
	'topbar' => 'inline-flex items-center justify-center bg-white rounded-md p-1 border border-slate-200 flex-shrink-0',
	'auth', 'header' => 'inline-flex items-center justify-center bg-white rounded-md p-1 border border-slate-100 flex-shrink-0',
	default => 'inline-flex items-center justify-center',
};

$showControll = $variant !== 'topbar';
$showDivider = in_array($variant, ['auth', 'header'], true);
$wrapperClass = match ($variant) {
	'sidebar' => 'flex items-center gap-2 flex-shrink-0',
	'auth' => 'flex items-center justify-center gap-4 mb-2',
	'header' => 'flex items-center gap-3',
	'topbar' => 'hidden sm:flex items-center mr-1',
	default => 'inline-flex items-center gap-2',
};
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
	<?php if ($showControll): ?>
		<img src="/logo-controll-it.png" onerror="this.onerror=null;this.src='/logo-controll-it.svg';" class="<?php echo htmlspecialchars($controllClass, ENT_QUOTES, 'UTF-8'); ?>" alt="Controll IT">
	<?php endif; ?>
	<?php if ($showDivider): ?>
		<div class="h-10 w-px bg-slate-200 flex-shrink-0" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="<?php echo htmlspecialchars($caFrameClass, ENT_QUOTES, 'UTF-8'); ?>">
		<img src="/logo-ca.png" onerror="this.onerror=null;this.src='/logo-ca.svg';" class="<?php echo htmlspecialchars($caClass, ENT_QUOTES, 'UTF-8'); ?>" alt="C&amp;A">
	</div>
</div>
