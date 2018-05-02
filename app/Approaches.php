<?php
namespace NGAFID;

use DB;
use Eloquent;

/**
 * NGAFID\Approaches
 *
 * TODO: Document properties and methods.
 *       This is currently just a passthrough for the approach table.
 * -- Matt Watson
 */

class Approaches extends Eloquent
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'approaches';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    ];
}
