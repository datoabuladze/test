<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    protected $table = 'search_queries';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;
}
