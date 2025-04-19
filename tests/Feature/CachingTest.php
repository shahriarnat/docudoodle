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
        // Define Mocks for curl functions
        $curlExecMock = $this->getFunctionMock('Docudoodle', 'curl_exec');
        $curlErrnoMock = $this->getFunctionMock('Docudoodle', 'curl_errno');
        $curlErrorMock = $this->getFunctionMock('Docudoodle', 'curl_error');
        $this->getFunctionMock('Docudoodle', 'curl_close')->expects($this->any());
        $this->getFunctionMock('Docudoodle', 'curl_init')->expects($this->any())->willReturn(curl_init());
        $this->getFunctionMock('Docudoodle', 'curl_setopt_array')->expects($this->any());

        $mockApiResponse = json_encode([
            'choices' => [
                ['message' => ['content' => 'Mocked AI Response v1']]
            ]
        ]);
        $curlExecMock->expects($this->atLeastOnce())->willReturn($mockApiResponse); // Expect it to be called
        $curlErrnoMock->expects($this->any())->willReturn(0);
        $curlErrorMock->expects($this->any())->willReturn('');

        // 1. Create source file
        $sourcePath = $this->createSourceFile('test.php', '<?php echo "Version 1";');
        $docPath = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/test.md';
        $cachePath = $this->tempCacheFile;
        $initialHash = sha1_file($sourcePath);

        // 2. Run generator
        $generator1 = $this->getGenerator();
        $generator1->generate();

        // 3. Get initial state
        $this->assertFileExists($docPath);
        $this->assertFileExists($cachePath);
        $initialDocModTime = filemtime($docPath);
        $initialCacheData = json_decode(file_get_contents($cachePath), true);
        $this->assertEquals($initialHash, $initialCacheData[$sourcePath] ?? null, 'Initial cache should contain correct hash.');

        // Wait briefly
        usleep(10000);

        // 4. Modify source file
        $this->createSourceFile('test.php', '<?php echo "Version 2 - Changed";');
        $newHash = sha1_file($sourcePath);
        $this->assertNotEquals($initialHash, $newHash, 'File hashes should differ after modification.');
        
        // Update mock response for second run if needed (optional, depends on assertion needs)
        $mockApiResponseV2 = json_encode([
            'choices' => [
                ['message' => ['content' => 'Mocked AI Response v2']]
            ]
        ]);
        // Re-configure mock if you want to ensure it returns V2 specifically on the second call
        // $curlExecMock->expects($this->exactly(2))->willReturnOnConsecutiveCalls($mockApiResponse, $mockApiResponseV2); 
        // For simplicity, we can let the existing mock return the same $mockApiResponse (V1)
        // as we primarily care that the file was reprocessed, not the exact AI content.

        // 5. Run generator again
        $generator2 = $this->getGenerator();
        // Capture output to ensure it DOES NOT say skipping
        ob_start();
        $generator2->generate();
        $output = ob_get_clean();

        // Assert it didn't skip
        $this->assertStringNotContainsString('Skipping unchanged file', $output, 'Generator output should NOT indicate skipping.');
        $this->assertStringContainsString('Generating documentation', $output, 'Generator output should indicate generation.');

        // 6. Assert doc file mod time/hash changed
        $this->assertFileExists($docPath);
        clearstatcache(); // Clear stat cache before checking filemtime again
        $finalDocModTime = filemtime($docPath);
        $this->assertGreaterThan($initialDocModTime, $finalDocModTime, 'Documentation file modification time should update on second run.');

        // 7. Assert cache file content updated with new hash
        $this->assertFileExists($cachePath);
        $finalCacheData = json_decode(file_get_contents($cachePath), true);
        $this->assertEquals($newHash, $finalCacheData[$sourcePath] ?? null, 'Cache should be updated with the new hash.');
        $this->assertEquals($initialCacheData['_config_hash'] ?? null, $finalCacheData['_config_hash'] ?? null, 'Config hash should not change.');
    }

    public function testProcessesNewFiles(): void
    {
        // Define Mocks for curl functions
        $curlExecMock = $this->getFunctionMock('Docudoodle', 'curl_exec');
        $curlErrnoMock = $this->getFunctionMock('Docudoodle', 'curl_errno');
        $curlErrorMock = $this->getFunctionMock('Docudoodle', 'curl_error');
        $this->getFunctionMock('Docudoodle', 'curl_close')->expects($this->any());
        $this->getFunctionMock('Docudoodle', 'curl_init')->expects($this->any())->willReturn(curl_init());
        $this->getFunctionMock('Docudoodle', 'curl_setopt_array')->expects($this->any());

        $mockApiResponse = json_encode([
            'choices' => [
                ['message' => ['content' => 'Mocked AI Response']]
            ]
        ]);
        $curlExecMock->expects($this->atLeastOnce())->willReturn($mockApiResponse);
        $curlErrnoMock->expects($this->any())->willReturn(0);
        $curlErrorMock->expects($this->any())->willReturn('');

        // 1. Create source file A
        $sourcePathA = $this->createSourceFile('fileA.php', '<?php echo "File A v1";');
        $docPathA = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/fileA.md';
        $cachePath = $this->tempCacheFile;
        $hashA = sha1_file($sourcePathA);

        // 2. Run generator (first run)
        $generator1 = $this->getGenerator();
        $generator1->generate();

        // 3. Assert doc A exists
        $this->assertFileExists($docPathA, 'Doc A should exist after first run.');
        $this->assertFileExists($cachePath, 'Cache should exist after first run.');
        $initialDocAModTime = filemtime($docPathA);
        $cacheData1 = json_decode(file_get_contents($cachePath), true);
        $this->assertEquals($hashA, $cacheData1[$sourcePathA] ?? null, 'Cache should contain hash for file A.');
        $this->assertCount(2, $cacheData1, 'Cache should have 2 entries (config + file A).'); // Config hash + File A

        // Wait briefly
        usleep(10000);

        // 4. Create source file B
        $sourcePathB = $this->createSourceFile('fileB.php', '<?php echo "File B v1";');
        $docPathB = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/fileB.md';
        $hashB = sha1_file($sourcePathB);

        // 5. Run generator again
        $generator2 = $this->getGenerator();
        // Capture output
        ob_start();
        $generator2->generate();
        $output = ob_get_clean();

        // Assert file A was skipped and file B was generated
        $this->assertStringContainsString("Skipping unchanged file: {$sourcePathA}", $output, 'Generator should skip file A.');
        $this->assertStringContainsString("Generating documentation for {$sourcePathB}", $output, 'Generator should generate file B.');

        // 6. Assert doc B exists
        $this->assertFileExists($docPathB, 'Doc B should exist after second run.');

        // 7. Assert doc A was likely skipped (check mod time?)
        clearstatcache();
        $finalDocAModTime = filemtime($docPathA);
        $this->assertEquals($initialDocAModTime, $finalDocAModTime, 'Doc A modification time should not change.');

        // 8. Assert cache contains both A and B
        $this->assertFileExists($cachePath);
        $cacheData2 = json_decode(file_get_contents($cachePath), true);
        $this->assertEquals($hashA, $cacheData2[$sourcePathA] ?? null, 'Cache should still contain correct hash for file A.');
        $this->assertEquals($hashB, $cacheData2[$sourcePathB] ?? null, 'Cache should now contain hash for file B.');
        $this->assertCount(3, $cacheData2, 'Cache should have 3 entries (config + file A + file B).'); // Config hash + File A + File B
        $this->assertEquals($cacheData1['_config_hash'] ?? null, $cacheData2['_config_hash'] ?? null, 'Config hash should not change.');
    }

    public function testOrphanCleanup(): void
    {
        // Define Mocks for curl functions
        $curlExecMock = $this->getFunctionMock('Docudoodle', 'curl_exec');
        $curlErrnoMock = $this->getFunctionMock('Docudoodle', 'curl_errno');
        $curlErrorMock = $this->getFunctionMock('Docudoodle', 'curl_error');
        $this->getFunctionMock('Docudoodle', 'curl_close')->expects($this->any());
        $this->getFunctionMock('Docudoodle', 'curl_init')->expects($this->any())->willReturn(curl_init());
        $this->getFunctionMock('Docudoodle', 'curl_setopt_array')->expects($this->any());

        $mockApiResponse = json_encode([
            'choices' => [
                ['message' => ['content' => 'Mocked AI Response']]
            ]
        ]);
        $curlExecMock->expects($this->atLeastOnce())->willReturn($mockApiResponse);
        $curlErrnoMock->expects($this->any())->willReturn(0);
        $curlErrorMock->expects($this->any())->willReturn('');

        // 1. Create source files A and B
        $sourcePathA = $this->createSourceFile('fileA.php', '<?php echo "File A";');
        $sourcePathB = $this->createSourceFile('fileB.php', '<?php echo "File B";');
        $docPathA = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/fileA.md';
        $docPathB = $this->tempOutputDir . '/' . basename($this->tempSourceDir) . '/fileB.md';
        $cachePath = $this->tempCacheFile;
        $hashA = sha1_file($sourcePathA);
        $hashB = sha1_file($sourcePathB);

        // 2. Run generator
        $generator1 = $this->getGenerator();
        $generator1->generate();

        // 3. Assert docs A and B exist, cache contains A and B
        $this->assertFileExists($docPathA, 'Doc A should exist initially.');
        $this->assertFileExists($docPathB, 'Doc B should exist initially.');
        $this->assertFileExists($cachePath, 'Cache should exist initially.');
        $cacheData1 = json_decode(file_get_contents($cachePath), true);
        $this->assertArrayHasKey($sourcePathA, $cacheData1);
        $this->assertArrayHasKey($sourcePathB, $cacheData1);
        $this->assertEquals($hashA, $cacheData1[$sourcePathA]);
        $this->assertEquals($hashB, $cacheData1[$sourcePathB]);
        $this->assertCount(3, $cacheData1); // config + A + B

        // 4. Delete source file A
        unlink($sourcePathA);
        $this->assertFileDoesNotExist($sourcePathA, 'Source file A should be deleted.');

        // Wait briefly
        usleep(10000);

        // 5. Run generator again
        $generator2 = $this->getGenerator();
        ob_start();
        $generator2->generate();
        $output = ob_get_clean();

        // 7. Assert output indicates cleanup and deletion
        $this->assertStringContainsString('Cleaning up documentation for deleted source files', $output, 'Generator should mention cleanup.');
        $this->assertStringContainsString("Deleting orphan documentation: {$docPathA}", $output, 'Generator should mention deleting orphan doc A.');
        $this->assertStringContainsString("Skipping unchanged file: {$sourcePathB}", $output, 'Generator should skip file B.');

        // 6. Assert doc A does not exist
        $this->assertFileDoesNotExist($docPathA, 'Doc A should be deleted after cleanup.');

        // 8. Assert doc B still exists
        $this->assertFileExists($docPathB, 'Doc B should still exist.');

        // 9. Assert cache contains only B
        $this->assertFileExists($cachePath, 'Cache should still exist.');
        $cacheData2 = json_decode(file_get_contents($cachePath), true);
        $this->assertArrayNotHasKey($sourcePathA, $cacheData2, 'Cache should not contain file A after cleanup.');
        $this->assertArrayHasKey($sourcePathB, $cacheData2, 'Cache should still contain file B.');
        $this->assertEquals($hashB, $cacheData2[$sourcePathB], 'Cache should have correct hash for file B.');
        $this->assertCount(2, $cacheData2, 'Cache should have 2 entries (config + file B).'); // config + B
        $this->assertEquals($cacheData1['_config_hash'] ?? null, $cacheData2['_config_hash'] ?? null, 'Config hash should not change during orphan cleanup.');
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