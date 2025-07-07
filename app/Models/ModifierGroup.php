<?php

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class ModifierGroup extends BaseModel
{
    use HasFactory, HasBranch;

    protected $guarded = ['id'];

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class, 'modifier_group_id');
    }

    public function itemModifiers(): HasMany
    {
        return $this->hasMany(ItemModifier::class);
    }
}
