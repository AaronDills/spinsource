<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'wikidata_qid',
        'iso_code',
    ];

    public function artists(): HasMany
    {
        return $this->hasMany(Artist::class);
    }

    public function genres(): HasMany
    {
        return $this->hasMany(Genre::class);
    }
}
