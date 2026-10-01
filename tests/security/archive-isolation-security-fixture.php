<?php
namespace verbb\squeeze\services {
    function time(): int
    {
        return 1700000000;
    }
}

namespace verbb\squeeze {
    class Squeeze
    {
        public static object $plugin;
    }
}

namespace craft\elements {
    use RuntimeException;

    class Asset
    {
        public function __construct(
            public int $id,
            public string $filename,
            private string $contents,
            private bool $failOnRead = false,
        ) {
        }

        public static function find(): object
        {
            return new class {
                private array $ids = [];

                public function id(array $ids): self
                {
                    $this->ids = $ids;

                    return $this;
                }

                public function limit(mixed $limit): self
                {
                    return $this;
                }

                public function all(): array
                {
                    return array_values(array_filter(
                        $GLOBALS['archiveAssets'],
                        fn(Asset $asset): bool => in_array($asset->id, $this->ids, true),
                    ));
                }
            };
        }

        public function getVolume(): object
        {
            return (object)[
                'id' => 1,
                'handle' => 'allowed',
                'uid' => 'synthetic-volume',
            ];
        }

        public function getContents(): string
        {
            if ($this->failOnRead) {
                throw new RuntimeException('Synthetic asset read failure.');
            }

            return $this->contents;
        }

        public function canView(mixed $user): bool
        {
            return false;
        }
    }
}

namespace {
    use craft\elements\Asset;
    use verbb\squeeze\controllers\DownloadController;
    use verbb\squeeze\models\Settings;
    use verbb\squeeze\services\Service;
    use yii\web\Response;

    $vendorPath = getenv('VERBB_SQUEEZE_TEST_VENDOR');

    if (!$vendorPath || !is_file($vendorPath . '/autoload.php')) {
        fwrite(STDERR, "Set VERBB_SQUEEZE_TEST_VENDOR to a Craft 5 vendor directory.\n");
        exit(2);
    }

    if (!function_exists('pcntl_fork')) {
        fwrite(STDERR, "The pcntl extension is required for the concurrency fixture.\n");
        exit(2);
    }

    require $vendorPath . '/autoload.php';
    require $vendorPath . '/yiisoft/yii2/Yii.php';
    require $vendorPath . '/craftcms/cms/src/Craft.php';
    require dirname(__DIR__, 2) . '/src/models/Settings.php';
    require dirname(__DIR__, 2) . '/src/services/Service.php';
    require dirname(__DIR__, 2) . '/src/controllers/DownloadController.php';

    function check(string $label, bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed: ' . $label);
        }

        echo $label . ": PASS\n";
    }

    function zipContents(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open synthetic archive.');
        }

        $contents = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $contents[$name] = $zip->getFromIndex($index);
        }

        $zip->close();

        return $contents;
    }

    $tempPath = sys_get_temp_dir() . '/squeeze-archive-isolation-' . bin2hex(random_bytes(8));

    if (!mkdir($tempPath, 0700)) {
        throw new RuntimeException('Unable to create the synthetic temp directory.');
    }

    $settings = new Settings();
    $settings->allowedVolumes = ['allowed'];

    \verbb\squeeze\Squeeze::$plugin = new class($settings) {
        public function __construct(private Settings $settings)
        {
        }

        public function getSettings(): Settings
        {
            return $this->settings;
        }
    };

    Craft::$app = new class($tempPath) {
        public string $charset = 'UTF-8';

        public function __construct(private string $tempPath)
        {
        }

        public function getDb(): object
        {
            return new class {
                public function getSupportsMb4(): bool
                {
                    return true;
                }
            };
        }

        public function getConfig(): object
        {
            return new class {
                public function getGeneral(): object
                {
                    return (object)['defaultDirMode' => 0775];
                }
            };
        }

        public function getPath(): object
        {
            return new class($this->tempPath) {
                public function __construct(private string $tempPath)
                {
                }

                public function getTempPath(): string
                {
                    return $this->tempPath;
                }
            };
        }

        public function getUser(): object
        {
            return new class {
                public function getIdentity(): mixed
                {
                    return null;
                }
            };
        }
    };

    $GLOBALS['archiveAssets'] = [
        new Asset(101, 'first.txt', 'first-request-marker'),
        new Asset(202, 'second.txt', 'second-request-marker'),
    ];

    [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Unable to fork the concurrency fixture.');
    }

    if ($pid === 0) {
        fclose($parentSocket);
        $childArchive = (new Service())->archive('shared-name', [101, 202], [101]);
        fwrite($childSocket, $childArchive . "\n");
        fgets($childSocket);
        fclose($childSocket);
        exit(0);
    }

    fclose($childSocket);
    $childArchive = null;
    $parentArchive = null;

    try {
        $childArchive = trim((string)fgets($parentSocket));
        check('The first request remains in the post-build overlap window', is_file($childArchive));

        $parentArchive = (new Service())->archive('shared-name', [202], [202]);

        check('Concurrent requests with the same archive name use different temp paths', $parentArchive !== $childArchive);
        check('The first request retains the legacy download filename format', basename($childArchive) === 'shared-name_1700000000.zip');
        check('The second request retains the legacy download filename format', basename($parentArchive) === 'shared-name_1700000000.zip');
        check('The first archive contains only its authorized synthetic asset', zipContents($childArchive) === [
            'first.txt' => 'first-request-marker',
        ]);
        check('The second archive contains only its authorized synthetic asset', zipContents($parentArchive) === [
            'second.txt' => 'second-request-marker',
        ]);

        $directoriesBeforeFailure = glob($tempPath . '/squeeze-*', GLOB_ONLYDIR) ?: [];
        $GLOBALS['archiveAssets'] = [new Asset(303, 'unreadable.txt', '', true)];

        try {
            (new Service())->archive('failure', [303], [303]);
            throw new RuntimeException('Expected the synthetic read failure.');
        } catch (RuntimeException $e) {
            check('An asset read failure is propagated', $e->getMessage() === 'Synthetic asset read failure.');
        }

        check('A failed archive build leaves no partial temp directory behind', (glob($tempPath . '/squeeze-*', GLOB_ONLYDIR) ?: []) === $directoriesBeforeFailure);

        $GLOBALS['archiveAssets'] = [
            new Asset(401, 'Report.pdf', 'first-report-marker'),
            new Asset(402, 'report.pdf', 'second-report-marker'),
        ];
        $duplicateArchive = (new Service())->archive('duplicates', [401, 402], [401, 402]);

        check('Same-named assets remain distinct archive entries', zipContents($duplicateArchive) === [
            'Report.pdf' => 'first-report-marker',
            'report (2).pdf' => 'second-report-marker',
        ]);

        unlink($duplicateArchive);
        rmdir(dirname($duplicateArchive));

        $responseFailureDirectory = $tempPath . '/squeeze-response-failure';
        mkdir($responseFailureDirectory, 0700);
        $responseFailureArchive = $responseFailureDirectory . '/shared-name_1700000000.zip';
        file_put_contents($responseFailureArchive, 'synthetic-response-bytes');
        $response = new Response();
        $responseStream = fopen($responseFailureArchive, 'rb');
        $response->stream = [$responseStream, 0, filesize($responseFailureArchive) - 1];
        $cleanup = new ReflectionMethod(DownloadController::class, '_cleanupArchive');
        $cleanup->invoke(null, $responseFailureArchive, $response);

        check('Response-failure cleanup closes the prepared stream', !is_resource($responseStream) && $response->stream === null);
        check('Response-failure cleanup removes the archive and its temp directory', !is_dir($responseFailureDirectory));
    } finally {
        fwrite($parentSocket, "DONE\n");
        fclose($parentSocket);
        pcntl_waitpid($pid, $status);

        foreach ([$childArchive, $parentArchive] as $archive) {
            if (is_string($archive) && is_file($archive)) {
                unlink($archive);
                rmdir(dirname($archive));
            }
        }

        rmdir($tempPath);
    }
}
