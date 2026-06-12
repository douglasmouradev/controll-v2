<?php
declare(strict_types=1);

if (!function_exists('asset_url')) {
	require_once BASE_PATH . '/app/Views/helpers/assets.php';
}

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
	'header' => 'brand-logo-ca h-10 w-auto object-contain max-w-[5.5rem]',
	'topbar' => 'brand-logo-ca h-8 w-auto object-contain flex-shrink-0 max-w-[5rem]',
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

$controllSrc = asset_url('/logo-controll-it.png');
$caSrc = asset_url('/logo-ca.png');
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
	<img src="<?php echo htmlspecialchars($controllSrc, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(asset_url('/logo-controll-it.svg'), ENT_QUOTES, 'UTF-8'); ?>';" class="<?php echo htmlspecialchars($controllClass, ENT_QUOTES, 'UTF-8'); ?>" alt="Controll IT" width="152" height="40">
	<?php if ($showDivider): ?>
		<?php if ($variant === 'auth'): ?>
			<div class="brand-logos-divider" aria-hidden="true"></div>
		<?php else: ?>
			<div class="h-10 w-px bg-slate-200 flex-shrink-0" aria-hidden="true"></div>
		<?php endif; ?>
	<?php endif; ?>
	<img src="<?php echo htmlspecialchars($caSrc, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(asset_url('/logo-ca.svg'), ENT_QUOTES, 'UTF-8'); ?>';" class="<?php echo htmlspecialchars($caClass, ENT_QUOTES, 'UTF-8'); ?>" alt="C&amp;A" width="120" height="120">
</div>
