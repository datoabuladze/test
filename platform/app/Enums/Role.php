<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case GameManager = 'game_manager';
    case ContentEditor = 'content_editor';
    case Moderator = 'moderator';
    case AnalyticsViewer = 'analytics_viewer';
    case Player = 'player';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => ['*'],
            self::Admin => [
                'admin.access', 'games.manage', 'games.publish', 'games.rights', 'categories.manage',
                'imports.manage', 'content.manage', 'design.manage', 'users.manage', 'users.moderate',
                'reports.manage', 'ads.manage', 'seo.manage', 'analytics.view', 'audit.view', 'settings.manage',
            ],
            self::GameManager => [
                'admin.access', 'games.manage', 'games.publish', 'games.rights', 'categories.manage',
                'imports.manage', 'reports.manage', 'analytics.view',
            ],
            self::ContentEditor => ['admin.access', 'content.manage', 'seo.manage', 'categories.manage'],
            self::Moderator => ['admin.access', 'users.moderate', 'reports.manage'],
            self::AnalyticsViewer => ['admin.access', 'analytics.view'],
            self::Player => [],
        };
    }

    public function can(string $permission): bool
    {
        $perms = $this->permissions();

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /** @return list<self> */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $r) => $r !== self::Player));
    }
}
