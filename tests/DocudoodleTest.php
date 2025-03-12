<?php

use PHPUnit\Framework\TestCase;

class DocumentationGeneratorTest extends TestCase
{
    private $generator;

    protected function setUp(): void
    {
        $this->generator = new DocumentationGenerator("sk-XXXXXXXX");
    }

    public function testEnsureDirectoryExists()
    {
        $testDir = 'test_directory';
        $this->generator->ensureDirectoryExists($testDir);
        $this->assertTrue(file_exists($testDir));
        rmdir($testDir); // Clean up
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('php', $this->generator->getFileExtension('example.php'));
        $this->assertEquals('yaml', $this->generator->getFileExtension('example.yaml'));
    }

    public function testShouldProcessFile()
    {
        $this->assertTrue($this->generator->shouldProcessFile('example.php'));
        $this->assertFalse($this->generator->shouldProcessFile('.hidden.php'));
        $this->assertFalse($this->generator->shouldProcessFile('example.txt'));
    }

    public function testShouldProcessDirectory()
    {
        $this->assertTrue($this->generator->shouldProcessDirectory('src'));
        $this->assertFalse($this->generator->shouldProcessDirectory('vendor'));
    }

    public function testReadFileContent()
    {
        file_put_contents('test_file.php', '<?php echo "Hello World";');
        $content = $this->generator->readFileContent('test_file.php');
        $this->assertStringContainsString('Hello World', $content);
        unlink('test_file.php'); // Clean up
    }

    public function testGenerateDocumentation()
    {
        $content = '<?php echo "Hello World";';
        $docContent = $this->generator->generateDocumentation('test_file.php', $content);
        $this->assertStringContainsString('# Documentation: test_file.php', $docContent);
    }

    public function testCreateDocumentationFile()
    {
        $sourcePath = 'test_file.php';
        $relPath = 'test_file.md';
        file_put_contents($sourcePath, '<?php echo "Hello World";');
        
        $this->generator->createDocumentationFile($sourcePath, $relPath);
        $this->assertTrue(file_exists('documentation/test_file.md'));
        
        unlink($sourcePath); // Clean up
        unlink('documentation/test_file.md'); // Clean up
    }
}