<?php

declare(strict_types=1);

namespace Valbeat\Result\Tests\Examples;

use PHPUnit\Framework\TestCase;
use Valbeat\Result\Ok;
use Valbeat\Result\Err;
use Valbeat\Result\Result;

/**
 * ファイルシステム操作の実例
 */
class FileSystemTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/result_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    /**
     * ファイル読み書きの基本例
     */
    public function testFileReadWrite(): void
    {
        $filePath = $this->tempDir . '/test.txt';

        $writeFile = function (string $path, string $content): Result {
            $result = @file_put_contents($path, $content);
            if ($result === false) {
                return new Err("Failed to write file: $path");
            }
            return new Ok($result);
        };

        $readFile = function (string $path): Result {
            if (!file_exists($path)) {
                return new Err("File not found: $path");
            }
            
            $content = @file_get_contents($path);
            if ($content === false) {
                return new Err("Failed to read file: $path");
            }
            
            return new Ok($content);
        };

        // ファイルの書き込みと読み込み
        $result = $writeFile($filePath, 'Hello, World!')
            ->andThen(fn() => $readFile($filePath))
            ->map(fn($content) => strtoupper($content));

        $this->assertTrue($result->isOk());
        $this->assertSame('HELLO, WORLD!', $result->unwrap());

        // 存在しないファイルの読み込み
        $result = $readFile($this->tempDir . '/nonexistent.txt');
        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('File not found', $result->unwrapErr());
    }

    /**
     * 設定ファイルの処理例
     */
    public function testConfigFileProcessing(): void
    {
        $configPath = $this->tempDir . '/config.json';

        $saveConfig = function (array $config, string $path): Result {
            $json = json_encode($config, JSON_PRETTY_PRINT);
            if ($json === false) {
                return new Err('Failed to encode config');
            }
            
            $result = @file_put_contents($path, $json);
            if ($result === false) {
                return new Err("Failed to save config to: $path");
            }
            
            return new Ok($path);
        };

        $loadConfig = function (string $path): Result {
            if (!file_exists($path)) {
                return new Err("Config file not found: $path");
            }
            
            $content = @file_get_contents($path);
            if ($content === false) {
                return new Err("Failed to read config: $path");
            }
            
            $config = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Err('Invalid JSON in config: ' . json_last_error_msg());
            }
            
            return new Ok($config);
        };

        $validateConfig = function (array $config): Result {
            $required = ['app_name', 'version', 'database'];
            
            foreach ($required as $key) {
                if (!isset($config[$key])) {
                    return new Err("Missing required config key: $key");
                }
            }
            
            if (!is_array($config['database'])) {
                return new Err('Database config must be an array');
            }
            
            return new Ok($config);
        };

        // 正常な設定の保存と読み込み
        $config = [
            'app_name' => 'MyApp',
            'version' => '1.0.0',
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ];

        $result = $saveConfig($config, $configPath)
            ->andThen($loadConfig)
            ->andThen($validateConfig)
            ->map(fn($cfg) => "Config loaded: {$cfg['app_name']} v{$cfg['version']}");

        $this->assertTrue($result->isOk());
        $this->assertSame('Config loaded: MyApp v1.0.0', $result->unwrap());

        // 不正な設定のバリデーション
        $invalidConfig = ['app_name' => 'MyApp'];
        file_put_contents($configPath, json_encode($invalidConfig));

        $result = $loadConfig($configPath)->andThen($validateConfig);
        $this->assertTrue($result->isErr());
        $this->assertSame('Missing required config key: version', $result->unwrapErr());
    }

    /**
     * CSVファイルの処理例
     */
    public function testCsvProcessing(): void
    {
        $csvPath = $this->tempDir . '/data.csv';

        $writeCsv = function (array $data, string $path): Result {
            $handle = @fopen($path, 'w');
            if ($handle === false) {
                return new Err("Failed to open file for writing: $path");
            }

            foreach ($data as $row) {
                if (fputcsv($handle, $row, ',', '"', '\\') === false) {
                    fclose($handle);
                    return new Err('Failed to write CSV row');
                }
            }

            fclose($handle);
            return new Ok(count($data));
        };

        $readCsv = function (string $path): Result {
            if (!file_exists($path)) {
                return new Err("CSV file not found: $path");
            }

            $handle = @fopen($path, 'r');
            if ($handle === false) {
                return new Err("Failed to open CSV file: $path");
            }

            $data = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $data[] = $row;
            }

            fclose($handle);
            return new Ok($data);
        };

        $processCsv = function (array $data): Result {
            if (empty($data)) {
                return new Err('CSV is empty');
            }

            $headers = array_shift($data);
            $records = [];

            foreach ($data as $row) {
                if (count($row) !== count($headers)) {
                    return new Err('CSV row column count mismatch');
                }
                $records[] = array_combine($headers, $row);
            }

            return new Ok($records);
        };

        // CSVの書き込み、読み込み、処理
        $csvData = [
            ['name', 'age', 'city'],
            ['Alice', '30', 'New York'],
            ['Bob', '25', 'London'],
            ['Charlie', '35', 'Tokyo'],
        ];

        $result = $writeCsv($csvData, $csvPath)
            ->andThen(fn() => $readCsv($csvPath))
            ->andThen($processCsv)
            ->map(fn($records) => array_column($records, 'name'));

        $this->assertTrue($result->isOk());
        $this->assertSame(['Alice', 'Bob', 'Charlie'], $result->unwrap());
    }

    /**
     * ディレクトリ操作の例
     */
    public function testDirectoryOperations(): void
    {
        $createDirectory = function (string $path): Result {
            if (file_exists($path)) {
                return new Err("Directory already exists: $path");
            }

            if (!@mkdir($path, 0777, true)) {
                return new Err("Failed to create directory: $path");
            }

            return new Ok($path);
        };

        $listDirectory = function (string $path): Result {
            if (!is_dir($path)) {
                return new Err("Not a directory: $path");
            }

            $files = scandir($path);
            if ($files === false) {
                return new Err("Failed to scan directory: $path");
            }

            // . と .. を除外
            $files = array_diff($files, ['.', '..']);
            return new Ok(array_values($files));
        };

        $cleanDirectory = function (string $path): Result {
            if (!is_dir($path)) {
                return new Err("Not a directory: $path");
            }

            $files = glob($path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (!@unlink($file)) {
                        return new Err("Failed to delete file: $file");
                    }
                }
            }

            return new Ok(true);
        };

        // ディレクトリの作成とファイルの配置
        $subDir = $this->tempDir . '/subdir';
        
        $result = $createDirectory($subDir)
            ->andThen(function ($dir) {
                file_put_contents($dir . '/file1.txt', 'content1');
                file_put_contents($dir . '/file2.txt', 'content2');
                file_put_contents($dir . '/file3.txt', 'content3');
                return new Ok($dir);
            })
            ->andThen($listDirectory);

        $this->assertTrue($result->isOk());
        $files = $result->unwrap();
        $this->assertCount(3, $files);
        $this->assertContains('file1.txt', $files);

        // ディレクトリのクリーンアップ
        $result = $cleanDirectory($subDir)->andThen(fn() => $listDirectory($subDir));
        $this->assertTrue($result->isOk());
        $this->assertEmpty($result->unwrap());
    }

    /**
     * ログファイル処理の例
     */
    public function testLogFileProcessing(): void
    {
        $logPath = $this->tempDir . '/app.log';

        $logger = new class($logPath) {
            private string $path;

            public function __construct(string $path) {
                $this->path = $path;
            }

            public function log(string $level, string $message): Result {
                $timestamp = date('Y-m-d H:i:s');
                $line = "[$timestamp] [$level] $message" . PHP_EOL;
                
                $result = @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
                if ($result === false) {
                    return new Err('Failed to write log');
                }
                
                return new Ok(true);
            }

            public function readLogs(): Result {
                if (!file_exists($this->path)) {
                    return new Ok([]);
                }

                $content = @file_get_contents($this->path);
                if ($content === false) {
                    return new Err('Failed to read log file');
                }

                $lines = explode(PHP_EOL, trim($content));
                return new Ok($lines);
            }

            public function parseLogs(): Result {
                return $this->readLogs()->map(function ($lines) {
                    $logs = [];
                    foreach ($lines as $line) {
                        if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
                            $logs[] = [
                                'timestamp' => $matches[1],
                                'level' => $matches[2],
                                'message' => $matches[3],
                            ];
                        }
                    }
                    return $logs;
                });
            }

            public function filterByLevel(string $level): Result {
                return $this->parseLogs()->map(function ($logs) use ($level) {
                    return array_filter($logs, fn($log) => $log['level'] === $level);
                });
            }
        };

        // ログの書き込みとフィルタリング
        $logger->log('INFO', 'Application started')
            ->andThen(fn() => $logger->log('ERROR', 'Database connection failed'))
            ->andThen(fn() => $logger->log('INFO', 'Retrying connection'))
            ->andThen(fn() => $logger->log('INFO', 'Connection successful'));

        $result = $logger->filterByLevel('ERROR');
        $this->assertTrue($result->isOk());
        $errors = array_values($result->unwrap());
        $this->assertCount(1, $errors);
        $this->assertSame('Database connection failed', $errors[0]['message']);

        // 全ログの取得
        $result = $logger->parseLogs();
        $this->assertTrue($result->isOk());
        $this->assertCount(4, $result->unwrap());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}