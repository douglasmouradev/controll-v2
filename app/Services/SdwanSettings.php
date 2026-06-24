<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

final class SdwanSettings
{
	public const KEY_XPADS_GOAL = 'sdwan_xpads_goal';
	public const KEY_LINK_MAX_SUBMISSIONS = 'sdwan_link_max_submissions';

	public static function xpadsGoal(): int
	{
		return max(0, (int) SystemSetting::get(self::KEY_XPADS_GOAL, '0'));
	}

	public static function linkMaxSubmissions(): int
	{
		$value = (int) SystemSetting::get(self::KEY_LINK_MAX_SUBMISSIONS, '50');

		return max(1, min($value, 500));
	}

	public static function setXpadsGoal(int $goal): bool
	{
		return SystemSetting::set(self::KEY_XPADS_GOAL, (string) max(0, $goal), 'Meta global de XPads do Projeto SDWAN');
	}

	public static function setLinkMaxSubmissions(int $max): bool
	{
		$max = max(1, min($max, 500));

		return SystemSetting::set(self::KEY_LINK_MAX_SUBMISSIONS, (string) $max, 'Limite de cadastros por link técnico SDWAN');
	}

	/** @return array{xpads_goal: int, link_max_submissions: int, can_manage: bool} */
	public static function apiPayload(): array
	{
		$summary = \App\Models\SdwanEntry::summary();
		$goal = self::xpadsGoal();
		$localizada = (int) ($summary['quantidade_localizada'] ?? 0);
		$percent = $goal > 0 ? min(100, (int) round(($localizada / $goal) * 100)) : 0;

		return [
			'xpads_goal' => $goal,
			'link_max_submissions' => self::linkMaxSubmissions(),
			'goal_progress' => [
				'localizada' => $localizada,
				'goal' => $goal,
				'percent' => $percent,
			],
			'can_manage' => SdwanPermission::canManage(),
		];
	}
}
