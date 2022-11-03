<?php

namespace Alexandrnya\Laravel\Files\Models;

use Alexandrnya\Laravel\Files\Exceptions\LogicException;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToDeleteFile;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Http\File as HttpFile;

/**
 * Модель временного файла. Отношение к этой модели создавать нельзя
 *
 * @property int $id
 * @property string $disk Диск
 * @property string $pathname Реальный путь относительно диска с именем
 * @property string $name Алиас файла
 * @property string $basename Алиас файла без расширения
 * @property string $extension Расширение файла
 *
 * @property-read string $dir Реальный путь относительно диска без имени
 * @property-read int $size
 * @property-read string $mime_type
 * @property-read string $url
 * @property-read Filesystem $storage
 */
abstract class FileModel extends Model
{
    protected $table = 'files';

    protected $fillable = ['disk', 'pathname', 'name', 'contents'];

    protected $visible = ['id', 'url', 'size', 'name', 'basename', 'extension'];

    protected $appends = ['url', 'size', 'extension', 'basename'];

    /**
     * Сохраняет файл. Все подготовления модели нужно производить до этого метода
     *
     * @param FileModel|StreamInterface|HttpFile|UploadedFile|string|resource $contents Содержимое файла или ресурс или объект файла
     * @param string|null $dir Папка относительно диска.
     * @param mixed $options
     *
     * @return bool
     */
    public function put($contents, string $dir = null, $options = []) : bool
    {
        $storage = $this->storage;
        $as = $this->name;

        if($contents instanceof StreamInterface) {
            if(is_null($as)) {
                $as = collect(explode(';', $contents->getMetadata('Content-Disposition')))->reduce(function($carry, $item) {
                    [$key, $value] = explode('=', trim($item), 2);
                    $carry[Str::lower($key)] = trim($value, '"');
                    return $carry;
                }, collect())->get('filename', null);
            }

            $contents = $contents->detach();
        } else if($contents instanceof self) {
            $as = $as ?? $contents->name;
            $contents = $contents->getContents();
        }

        if(is_null($as)) {
            if ($contents instanceof UploadedFile) {
                $as = $contents->getClientOriginalName();
            } else if ($contents instanceof HttpFile) {
                $as = $contents->getFilename();
            } else {
                $basename = Str::random(20);
                $extension = static::guessExtension(static::guessContentsType($contents));

                $extension = $extension ? ".$extension" : '';

                $as = $basename.$extension;
            }
        }

        $extension = pathinfo($as, PATHINFO_EXTENSION);
        $basename = pathinfo($as, PATHINFO_FILENAME);

        $slug = Str::slug($basename);

        $dir = ($dir && trim($dir, '/')) ? (trim($dir, '/').'/') : '';
        $extension = $extension ? ".$extension" : '';

        $pathname = $dir.$slug.'-'.Str::random(20).$extension;

        $result = $storage->put($pathname, $contents, $options);

        $this->pathname = $pathname;
        $this->name = $as;

        return $result;
    }

    public static function store($contents, $attributes = [], $path = '/')
    {
        return tap((new static($attributes)), fn(self $instance) => $instance->put($contents, $path));
    }

    public function getContents() : ?string
    {
        return $this->storage->get($this->pathname);
    }

    /**
     * Геттер для экземпляра Storage
     * @return Attribute
     */
    protected function storage() : Attribute
    {
        return Attribute::get(fn() => Storage::disk($this->disk));
    }

    protected function url() : Attribute
    {
        return Attribute::get(fn() => $this->storage->url($this->pathname));
    }

    protected function extension() : Attribute
    {
        return Attribute::get(fn() => pathinfo($this->pathname, PATHINFO_EXTENSION) ?: null);
    }

    protected function basename() : Attribute
    {
        return Attribute::get(fn() => pathinfo($this->name, PATHINFO_FILENAME));
    }

    protected function dir() : Attribute
    {
        return Attribute::get(fn() => pathinfo($this->pathname, PATHINFO_DIRNAME));
    }

    protected function size() : Attribute
    {
        return Attribute::get(fn() => $this->storage->size($this->pathname));
    }

    protected function mimeType() : Attribute
    {
        return Attribute::get(fn() => $this->storage->mimeType($this->pathname));
    }

    /**
     * @return resource
     */
    public function readStream()
    {
        return $this->storage->readStream($this->pathname);
    }

    public function response($name = null, $headers = []) : \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->storage->response($this->pathname, $name ?? $this->name, $headers);
    }

    public function download($name = null, $headers = []) : \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->storage->download($this->pathname, $name ?? $this->name, $headers);
    }

    public function getTemporaryUrl($expiration, $options = []) : string
    {
        return $this->storage->temporaryUrl($this->pathname, $expiration, $options);
    }

    public function exists() : bool
    {
        return $this->storage->exists($this->pathname);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function(self $model) {
            if(! $model->exists()) {
                throw new LogicException(sprintf('File does not exist on "%s" disk at path "%s"', $model->disk, $model->pathname));
            }
        });

        static::deleted(function(self $model) {
            try {
                $model->storage->delete($model->pathname);
            } catch (UnableToDeleteFile $e) {}
        });
    }

    /**
     * Returns the extension based on the mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getMimeType()
     * to guess the file extension.
     *
     * @see MimeTypes
     * @see getMimeType()
     */
    protected static function guessExtension($mime_type): ?string
    {
        if (!class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the extension as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->getExtensions($mime_type)[0] ?? null;
    }

    /**
     * Returns the mime type of the file.
     *
     * The mime type is guessed using a MimeTypeGuesserInterface instance,
     * which uses finfo_file() then the "file" system binary,
     * depending on which of those are available.
     *
     * @see MimeTypes
     */
    protected static function guessMimeType($filepath): ?string
    {
        if (!class_exists(MimeTypes::class)) {
            throw new \LogicException('You cannot guess the mime type as the Mime component is not installed. Try running "composer require symfony/mime".');
        }

        return MimeTypes::getDefault()->guessMimeType($filepath);
    }

    protected static function guessContentsType($contents): ?string
    {
        $filepath = tempnam(sys_get_temp_dir(), 'file');
        file_put_contents($filepath, $contents);

        $mimeType = static::guessMimeType($filepath);
        unlink($filepath);

        return $mimeType;
    }
}
