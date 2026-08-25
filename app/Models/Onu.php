<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $genieacs_id
 * @property string $name
 * @property string|null $pppoe_user
 * @property string|null $description
 */
#[Fillable(['genieacs_id', 'name', 'pppoe_user', 'description'])]
class Onu extends Model {}
