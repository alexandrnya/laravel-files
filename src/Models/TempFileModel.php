<?php

namespace Alexandrnya\Laravel\Files\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class TempFileModel extends FileModel
{
    protected $table = 'temp_files';

    protected $disk = 'temp';

    protected $fillable = [
        'uuid',
        'name',
    ];

    protected function url() : Attribute
    {
        return Attribute::get(fn() => route('temp-files.show', [$this->id, $this->name]));
    }
}
