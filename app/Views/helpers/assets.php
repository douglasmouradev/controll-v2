<?php
declare(strict_types=1);

function asset_url(string $path): string
{
	$path = '/' . ltrim($path, '/');
	$fullPath = BASE_PATH . '/public' . $path;
	$version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';

	return $path . '?v=' . rawurlencode($version);
}
