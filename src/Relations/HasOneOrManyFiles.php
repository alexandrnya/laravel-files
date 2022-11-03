<?php

namespace Alexandrnya\Laravel\Files\Relations;

use Alexandrnya\Laravel\Files\Models\FileModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Support\Str;

trait HasOneOrManyFiles
{
    protected \Closure $resolver;
    protected $disk;

    public function path($resolver) : static
    {
        $this->resolver = $resolver;

        return $this;
    }

    public function disk($disk) : static
    {
        $this->disk = $disk;

        return $this;
    }

    protected function resolvePathFor(Model $parent)
    {
        $resolver = $this->resolver ?? function(Model $parent) {
            return Str::snake($parent->getTable(), '-').'/'.$parent->getKey();
        };

        return ltrim('/', $resolver($parent));
    }

    protected function setForeignAttributesForCreate(Model $model)
    {
        parent::setForeignAttributesForCreate($model);

        $model->disk = $this->disk;
    }

    public function store($contents, $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function (FileModel $instance) use($contents) {
            $this->setForeignAttributesForCreate($instance);

            $instance->put($contents, $this->resolvePathFor($this->parent));

            $instance->save();
        });
    }

}
