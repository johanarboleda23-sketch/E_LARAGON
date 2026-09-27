<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (session()->has('company_id')) {
                $builder->where($builder->getModel()->getTable().'.company_id', session('company_id'));
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && session()->has('company_id')) {
                $model->company_id = session('company_id');
            }
        });
    }
}
