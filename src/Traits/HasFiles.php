<?php

namespace Alexandrnya\Laravel\Files\Traits;

use Alexandrnya\Laravel\Files\Exceptions\LogicException;
use Alexandrnya\Laravel\Files\Models\TempFileModel;
use Alexandrnya\Laravel\Files\Relations\HasManyFiles;
use Alexandrnya\Laravel\Files\Relations\HasOneFile;

trait HasFiles
{
    public function hasManyFiles($related, $name, $type = null, $id = null, $localKey = null, $disk = null) : HasManyFiles
    {
        $instance = $this->newRelatedInstance($related);

        if($instance instanceof TempFileModel) {
            throw new LogicException(sprintf('Отношение %s к временному файлу недопустимо.', $name));
        }

        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        $disk = $disk ?? config('filesystems.default');

        return (new HasManyFiles($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey))->disk($disk);
    }

    public function hasFile($related, $name, $type = null, $id = null, $localKey = null, $disk = null) : HasOneFile
    {
        $instance = $this->newRelatedInstance($related);

        if($instance instanceof TempFileModel) {
            throw new LogicException(sprintf('Отношение %s к временному файлу недопустимо.', $name));
        }

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return (new HasOneFile($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey))->disk($disk);
    }
}
