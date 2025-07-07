<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;


class FrontDetail extends BaseModel
{
    protected $table = 'front_details';

    protected $guarded = ['id'];

    protected $appends = [
        'image_url',
    ];

    public function imageUrl(): Attribute
    {
        return Attribute::get(fn(): string => $this->image ? asset('user-uploads/header/' . $this->image) : asset('landing/dashboard.png'));
    }
}
