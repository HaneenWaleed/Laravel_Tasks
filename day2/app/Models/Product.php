<?php

namespace App\Models;

use App\Models\Category;
use App\Models\Order_Item;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //
    function category()
    {
        return $this->belongsTo(Category::class);
    }

    function orderItems()
    {
        return $this->hasMany(Order_Item::class);
    }
}
