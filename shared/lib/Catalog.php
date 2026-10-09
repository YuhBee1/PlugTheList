<?php
declare(strict_types=1);

namespace PTL;

                                                                                                     
final class Catalog
{
                                                                                                                     
    private const PLATFORMS = [
        'spotify' => ['Spotify playlist', 'playlist', true],
        'apple_music' => ['Apple Music playlist', 'playlist', true],
        'audiomack' => ['Audiomack playlist', 'playlist', false],
        'boomplay' => ['Boomplay playlist', 'playlist', false],
        'youtube' => ['YouTube channel or playlist', 'channel', false],
        'soundcloud' => ['SoundCloud', 'channel', false],
        'whatsapp' => ['WhatsApp community or channel', 'community', false],
        'telegram' => ['Telegram channel or group', 'community', false],
        'instagram' => ['Instagram page', 'channel', false],
        'tiktok' => ['TikTok account', 'channel', false],
        'x' => ['X (Twitter) account', 'channel', false],
        'facebook' => ['Facebook page or group', 'community', false],
        'other' => ['Other', 'channel', false],
    ];

    private const SERVICES = [
        'review' => 'Review and playlist consideration',
        'feature' => 'Guaranteed feature for a set period',
        'post' => 'One post or shout-out',
        'pinned' => 'Pinned post for a set period',
    ];

    public const GENRES = [
        'Afrobeats', 'Afropop', 'Amapiano', 'Hip-hop and rap', 'R&B', 'Gospel', 'Highlife',
        'Fuji and Juju', 'Reggae and dancehall', 'Alté', 'Street-pop', 'Drill', 'Pop', 'Electronic', 'Other',
    ];

                                                                 
    public static function platforms(): array
    {
        return self::PLATFORMS;
    }

    public static function platformLabel(string $slug): string
    {
        return self::PLATFORMS[$slug][0] ?? $slug;
    }

    public static function platformKind(string $slug): string
    {
        return self::PLATFORMS[$slug][1] ?? 'channel';
    }

    public static function isDsp(string $slug): bool
    {
        return self::PLATFORMS[$slug][2] ?? false;
    }

                                        
    public static function services(): array
    {
        return self::SERVICES;
    }

    public static function serviceLabel(string $s): string
    {
        return self::SERVICES[$s] ?? $s;
    }

       
                                                                                                                       
                                                                                 
                                 
       
    public static function allowedServices(string $platform): array
    {
        if (!isset(self::PLATFORMS[$platform])) {
            return [];
        }
        if (self::isDsp($platform)) {
            return ['review'];
        }
        $ok = array_map('trim', explode(',', Settings::get('guaranteed_placement_platforms')));
        return in_array($platform, $ok, true) ? array_keys(self::SERVICES) : ['review'];
    }

    public static function genreValid(string $g): bool
    {
        return in_array($g, self::GENRES, true);
    }
}
