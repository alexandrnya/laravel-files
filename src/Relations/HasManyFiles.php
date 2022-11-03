<?php

namespace Alexandrnya\Laravel\Files\Relations;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class HasManyFiles extends MorphMany
{
    use HasOneOrManyFiles;
}
