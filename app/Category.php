<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['id','name'];
    protected $keyType = 'string';
    public $timestamps = false;

    public function product()
    {
        return $this->hasMany('App\Product', 'id_category', 'id');
    }
}
