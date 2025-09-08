<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'test_id','type','title','prompt','multiple','language','starter','tests_json','position'
    ];

    protected $casts = [
        'multiple'   => 'boolean',
        'tests_json' => 'array',
    ];

    public function test()    { return $this->belongsTo(Test::class); }
    public function options() { return $this->hasMany(Option::class); }
    public function correctOptions()
    {
        return $this->belongsToMany(Option::class, 'correct_options', 'question_id', 'option_id');
    }
}
