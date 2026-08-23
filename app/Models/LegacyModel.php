<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class LegacyModel extends EloquentModel
{
    public $timestamps = false;
}
