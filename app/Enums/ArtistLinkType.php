<?php

namespace App\Enums;

/**
 * Canonical, app-level link types.
 *
 * Intentionally NOT a DB enum: platforms evolve and sources differ.
 */
enum ArtistLinkType: string
{
    case WEBSITE = 'website';
    case TWITTER = 'twitter';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case YOUTUBE = 'youtube';
    case SPOTIFY = 'spotify';
    case BANDCAMP = 'bandcamp';
    case SOUNDCLOUD = 'soundcloud';
    case APPLE_MUSIC = 'apple_music';
    case REDDIT = 'reddit';

    public static function fromWikidataProperty(string $property): ?self
    {
        return match ($property) {
            'P856'  => self::WEBSITE,
            'P2002' => self::TWITTER,
            'P2003' => self::INSTAGRAM,
            'P2013' => self::FACEBOOK,
            'P2397' => self::YOUTUBE,
            'P2850' => self::APPLE_MUSIC,
            'P1902' => self::SPOTIFY,
            'P3283' => self::BANDCAMP,
            'P3040' => self::SOUNDCLOUD,
            'P3984' => self::REDDIT,
            default => null,
        };
    }
}
