<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'plans';
    /**
     * Check if plan exist by name.
     * @param string $name
     * @return Plan|false
     */
    static function plan_exist( $name ){
        return self::where('name', $name)->first();
    }

    /**
     * Check new plan is upgrade.
     * @param string $plan_name
     * @param Plan $current_plan
     * @return boolean
     */
    static function is_upgrade( $plan_name, $current_plan ){
        $new_plan = self::plan_exist( $plan_name );
        if( $new_plan->price > $current_plan->price ){
            return true;
        }
        return false;
    }

    /**
     * Get default plan id.
     * @return int|boolean
     */
    static function get_default_plan_id(){
        $plan = self::where('id',1)->first();
        if( $plan ){
            return $plan;
        }
        return false;
    }
}
