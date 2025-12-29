<?php

namespace App\Enums;

/**
 * Closed, domain-level concept; safe to model as enum.
 */
enum AlbumType: string
{
    case ALBUM = 'album';
    case EP = 'ep';
    case SINGLE = 'single';
    case COMPILATION = 'compilation';
    case LIVE = 'live';
    case SOUNDTRACK = 'soundtrack';
    case REMIX = 'remix';
    case OTHER = 'other';
}
