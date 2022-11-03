<?php

namespace Alexandrnya\Laravel\Files\Relations;

use Illuminate\Database\Eloquent\Relations\MorphOne;

class HasOneFile extends MorphOne
{
    use HasOneOrManyFiles {
        store as storeForMany;
    }

    public function store($contents, $attributes = [])
    {
        parent::delete();

        return $this->storeForMany($contents, $attributes);
    }
}
