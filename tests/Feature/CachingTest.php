<?php

namespace Tests\Feature;

use Docudoodle\Docudoodle;
use PHPUnit\Framework\TestCase;
use phpmock\phpunit\PHPMock;

class CachingTest extends TestCase
{
    use PHPMock; // Use the trait for easy mock management

    private string $tempSourceDir;
    private string $tempOutputDir;
    private string $tempCacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        // Create temporary directories for source and output
        $this->tempSourceDir = sys_get_temp_dir() . '/' . uniqid('docudoodle_test_source_');
        $this->tempOutputDir = sys_get_temp_dir() . '/' . uniqid('docudoodle_test_output_');
        mkdir($this->tempSourceDir, 0777, true);
        mkdir($this->tempOutputDir, 0777, true);

        // Default cache file location for setup
        $this->tempCacheFile = $this->tempOutputDir . '/.docudoodle_cache.json';
    }

    protected function tearDown(): void
    {
        // Clean up temporary directories and files
        $this->deleteDirectory($this->tempSourceDir);
        $this->deleteDirectory($this->tempOutputDir);
        // Attempt to delete cache file if it exists outside output dir in some tests
        if ($this->tempCacheFile && file_exists($this->tempCacheFile) && strpos($this->tempCacheFile, $this->tempOutputDir) !== 0) {
             @unlink($this->tempCacheFile);
        }
        parent::tearDown();
    }

    // Helper function to recursively delete a directory
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    // Helper to create a dummy source file
    private function createSourceFile(string $relativePath, string $content): string
    {
        $fullPath = $this->tempSourceDir . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);
        return $fullPath;
    }
    
    // Helper to get Docudoodle instance with basic config
    private function getGenerator(
        bool $useCache = true, 
        ?string $cacheFilePath = null, 
        bool $forceRebuild = false
    ): Docudoodle // Return a real instance now
    {
        // Use a very simple model/API key for testing
        return new Docudoodle(
            openaiApiKey: 'test-key', 
            sourceDirs: [$this->tempSourceDir], 
            outputDir: $this->tempOutputDir, 
            model: 'test-model', 
            maxTokens: 100,
            allowedExtensions: ['php'],
            skipSubdirectories: [],
            apiProvider: 'openai', // Assume basic provider for test structure
            ollamaHost: 'localhost',
            ollamaPort: 5000,
            promptTemplate: __DIR__ . '/../../resources/templates/default-prompt.md', // Use default for now
            useCache: $useCache,
            cacheFilePath: $cacheFilePath ?? $this->tempCacheFile, // Pass explicitly
            forceRebuild: $forceRebuild
        );
    }

    // --- Test Cases --- 

    public function testSkipsUnchangedFiles(): void
    {
        // Define Mocks for curl functions within Docudoodle namespace
        $curlExecMock = $this->getFunctionMock('Docudoodle', 'curl_exec');
        $curlErrnoMock = $this->getFunctionMock('Docudoodle', 'curl_errno');
        $curlErrorMock = $this->getFunctionMock('Docudoodle', 'curl_error');
        // Mock curl_close to do nothing
        $this->getFunctionMock('Docudoodle', 'curl_close')->expects($this->any());
        // Mock curl_init to return a dummy resource (or handle)
        $this->getFunctionMock('Docudoodle', 'curl_init')->expects($this->any())->willReturn(curl_init()); // Return a real dummy handle
        // Mock curl_setopt_array/curl_setopt to do nothing (or check options if needed)
        $this->getFunctionMock('Docudoodle', 'curl_setopt_array')->expects($this->any());

        // Configure mock responses
        $mockApiResponse = json_encode([
            'choices' => [
                ['message' => ['content' => 'Mocked AI Response']]
            ]
        ]);
        $curlExecMock->expects($this->any())->willReturn($mockApiResponse);
        $curlErrnoMock->expects($this->any())->willReturn(0);
        $curlErrorMock->expects($this->any())->willReturn('');

        // 1. Create source file
        $sourcePath = $this->createSourceFile('test.php', '<?php echo "Hello";');
        $docPath = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/test.md'; // Correct path calculation
        $cachePath = $this->tempCacheFile;

        // 2. Run generator (first run)
        $generator1 = $this->getGenerator();
        $generator1->generate();

        // 3. Assert doc file exists, cache file exists
        $this->assertFileExists($docPath, 'Documentation file should be created on first run.');
        $this->assertFileExists($cachePath, 'Cache file should be created on first run.');
        $initialDocModTime = filemtime($docPath);

        // Wait briefly to ensure file modification times can differ
        usleep(10000); // 10ms should be enough

        // 4. Run generator again
        $generator2 = $this->getGenerator(); // Get a fresh instance
        // Capture output to check for "Skipping" message
        ob_start();
        $generator2->generate();
        $output = ob_get_clean();

        // 5. Assert generator indicates skipping 
        $this->assertStringContainsString('Skipping unchanged file', $output, 'Generator output should indicate skipping.');
        $this->assertStringContainsString($sourcePath, $output, 'Generator output should mention the skipped file path.');

        // 6. Assert doc file timestamp hasn't changed
        $this->assertFileExists($docPath); // Make sure it wasn't deleted
        $finalDocModTime = filemtime($docPath);
        $this->assertEquals($initialDocModTime, $finalDocModTime, 'Documentation file modification time should not change on second run.');
    }

    public function testReprocessesChangedFiles(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file
        // 2. Run generator
        // 3. Get doc file mod time/hash, cache file content
        // 4. Modify source file
        // 5. Run generator again
        // 6. Assert doc file mod time/hash changed
        // 7. Assert cache file content updated with new hash
    }

    public function testProcessesNewFiles(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file A
        // 2. Run generator
        // 3. Assert doc A exists
        // 4. Create source file B
        // 5. Run generator again
        // 6. Assert doc B exists
        // 7. Assert doc A was likely skipped (check mod time?)
        // 8. Assert cache contains both A and B
    }

    public function testOrphanCleanup(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source files A and B
        // 2. Run generator
        // 3. Assert docs A and B exist, cache contains A and B
        // 4. Delete source file A
        // 5. Run generator again
        // 6. Assert doc A does not exist
        // 7. Assert doc B still exists
        // 8. Assert cache contains only B
    }

    public function testConfigurationChangeInvalidation(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file
        // 2. Run generator with config X
        // 3. Get doc file mod time/hash, cache config hash
        // 4. Run generator again with config Y (e.g., different model)
        // 5. Assert doc file mod time/hash changed (indicating reprocessing)
        // 6. Assert cache config hash updated
    }

    public function testForceRebuildFlag(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file
        // 2. Run generator
        // 3. Get doc file mod time/hash
        // 4. Run generator again with forceRebuild = true
        // 5. Assert doc file mod time/hash changed (indicating reprocessing)
        // 6. Assert cache is updated correctly
    }

    public function testCacheDefaultLocation(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file
        // 2. Run generator with default cache path settings
        // 3. Assert cache file exists at tempOutputDir/.docudoodle_cache.json
    }

    public function testCacheCustomLocationConfig(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Set custom cache path (outside output dir)
        // 2. Create source file
        // 3. Run generator passing custom path
        // 4. Assert cache file exists at custom path
    }

    // Note: Testing command-line override requires a different approach, perhaps testing GenerateDocsCommand itself.

    public function testCacheDisabled(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
        // 1. Create source file
        // 2. Run generator with useCache = false
        // 3. Assert cache file does NOT exist
        // 4. Run generator again with useCache = false
        // 5. Assert it processed again (check doc mod time?)
    }
} 