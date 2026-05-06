<?php

namespace App\MoonShine\Handlers;

use App\MoonShine\Jobs\AppExportJob;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\Crud\Notifications\NotificationButton;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\Laravel\Notifications\MoonShineNotification;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Exceptions\ActionButtonException;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Override;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AppExportHandler extends ExportHandler
{
    #[Override]
    public function handle(): Response
    {
        /** @var array<string, array<string, array<string, string>|string>> $query */
        $query = collect(
            request()->query(),
        )->except(['_component_name', 'page'])->toArray();

        if (!$this->hasResource()) {
            throw ActionButtonException::resourceRequired();
        }

        $this->resolveStorage();

        $path = Storage::disk($this->getDisk())->path($this->generateFilePath());
        /** @var ModelResource<Model>|null $resource */
        $resource = $this->getResource();
        /** @var list<int|string> $notifyUsers */
        $notifyUsers = $this->getNotifyUsers();
        if (!$resource) {
            throw ResourceException::required();
        }
        if ($this->isQueue()) {
            AppExportJob::dispatch(
                static::class,
                $resource::class,
                $path,
                $query,
                $this->getDisk(),
                $this->getDir(),
                $this->getDelimiter(),
                $notifyUsers,
            );

            toast(__('moonshine::ui.resource.queued'));

            return back();
        }

        return response()->download(
            static::process(
                $path,
                $resource,
                $query,
                $this->getDisk(),
                $this->getDir(),
                $this->getDelimiter(),
                $notifyUsers,
            ),
        );
    }

    /**
     * @param  ModelResource<Model>  $resource
     * @param  array<string, array<string, array<string, string>|string>>  $query
     * @param  list<int|string>  $notifyUsers
     *
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws Throwable
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    #[Override]
    public static function process(
        string $path,
        ResourceContract $resource,
        array $query,
        string $disk = 'public',
        string $dir = '/',
        string $delimiter = ',',
        array $notifyUsers = [],
    ): string {
        if ($disk === 's3') {
            $path = Storage::disk('public')->path(str($path)->remove(Storage::disk($disk)->path('')));
            // Создание подпапки для экспорта локально, оригинал в функции resolveStorage
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }
        // Экспортируем локально
        $result = static::parentProcess($path, $resource, $query, $delimiter);
        if ($disk === 's3' && is_file($result)) {
            // Загружаем результат на S3 хранилище и отдаем ссылку S3
            $url = str($result)
                ->remove(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, Storage::disk('public')->path('')))
                ->value();
            if ($res = fopen($result, 'rb')) {
                Storage::disk($disk)->put($url, $res);
                fclose($res);
            }
            $downloadLink = $result = str_replace(['/', '\\'], '/', Storage::disk($disk)->url($url));
        } else {
            $url = str($path)
                ->remove(Storage::disk($disk)->path($dir))
                ->value();
            $downloadLink = Storage::disk($disk)->url(trim($dir, '/').$url);
        }
        MoonShineNotification::send(
            __('moonshine::ui.resource.export.exported'),
            new NotificationButton(label: __('moonshine::ui.download'), link: $downloadLink),
            ids: $notifyUsers,
        );

        return $result;
    }

    /**
     * @param  ModelResource<Model>  $resource
     * @param  array<string, array<string, array<string, string>|string>>  $query
     *
     * @throws Throwable
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    protected static function parentProcess(
        string $path,
        ResourceContract $resource,
        array $query,
        string $delimiter = ',',
    ): string {
        /** @var ModelResource<Model> $resource */
        if (!$resource instanceof HasImportExportContract) {
            throw new ResourceException('The resource must implement the HasImportExportContract interface.');
        }

        $resource->setQueryParams($query);

        $items = static function (ResourceContract $resource): Generator {
            /** @var ModelResource<Model> $resource */
            assert($resource instanceof HasImportExportContract);
            $page = 1;
            $perPage = 1000;
            $query = $resource->getQuery();
            do {
                $itemsChunk = $query->forPage($page, $perPage)->get();
                $hasItems = false;

                foreach ($itemsChunk as $indexInChunk => $item) {
                    $hasItems = true;
                    $row = [];

                    $fields = $resource->getExportFields();
                    /** @var array<string, mixed> $raw */
                    $raw = $item->toArray();
                    $absoluteIndex = ($page - 1) * $perPage + $indexInChunk;

                    $fields->fill($raw, $resource->getCaster()->cast($item), $absoluteIndex);

                    foreach ($fields as $field) {
                        $row[$field->getLabel()] = $field->rawMode()->preview();
                    }

                    yield $row;
                }

                $page++;
            } while ($hasItems && $itemsChunk->count() === $perPage);
        };

        $fastExcel = new FastExcel($items($resource));

        if (str($path)->contains('.csv')) {
            $fastExcel->configureCsv($delimiter);
        }

        return $fastExcel->export($path);
    }
}
