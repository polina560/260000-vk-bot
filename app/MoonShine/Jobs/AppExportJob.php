<?php

declare(strict_types=1);

namespace App\MoonShine\Jobs;

use App\MoonShine\Handlers\AppExportHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;
use MoonShine\Laravel\Resources\ModelResource;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Throwable;

class AppExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  class-string<AppExportHandler>  $handler
     * @param  class-string<ModelResource<Model>>  $resource
     * @param  array{ filter?: array<string, array<string, string>|string> }  $query
     * @param  list<int|string>  $notifyUsers
     */
    public function __construct(
        protected string $handler,
        protected string $resource,
        protected string $path,
        protected array $query,
        protected string $disk,
        protected string $dir,
        protected string $delimiter = ',',
        protected array $notifyUsers = [],
    ) {}

    /**
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws Throwable
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException
     */
    public function handle(): void
    {
        /** @var ModelResource<Model> $resource */
        $resource = app($this->resource);
        if (class_exists(TelescopeServiceProvider::class)
            && app()->environment('local')) {
            Telescope::stopRecording();
        }
        $this->handler::process(
            $this->path,
            $resource,
            $this->query,
            $this->disk,
            $this->dir,
            $this->delimiter,
            $this->notifyUsers,
        );
    }
}
