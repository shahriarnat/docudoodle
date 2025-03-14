<?php

namespace Docudoodle;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * PHP Documentation Generator
 *
 * This class generates documentation for a PHP codebase by analyzing source files
 * and using the OpenAI API to create comprehensive documentation.
 */
class Docudoodle
{
    /**
     * Constructor for Docudoodle
     *
     * @param string $apiKey OpenAI/Claude/Gemini API key (not needed for Ollama)
     * @param array $sourceDirs Directories to process
     * @param string $outputDir Directory for generated documentation
     * @param string $model AI model to use
     * @param int $maxTokens Maximum tokens for API calls
     * @param array $allowedExtensions File extensions to process
     * @param array $skipSubdirectories Subdirectories to skip
     * @param string $apiProvider API provider to use (default: 'openai')
     * @param string $ollamaHost Ollama host (default: 'localhost')
     * @param int $ollamaPort Ollama port (default: 5000)
     */
    public function __construct(
        private string $openaiApiKey = "",
        private array $sourceDirs = ["app/", "config/", "routes/", "database/"],
        private string $outputDir = "documentation/",
        private string $model = "gpt-4o-mini",
        private int $maxTokens = 10000,
        private array $allowedExtensions = ["php", "yaml", "yml"],
        private array $skipSubdirectories = [
            "vendor/",
            "node_modules/",
            "tests/",
            "cache/",
        ],
        private string $apiProvider = "openai",
        private string $ollamaHost = "localhost",
        private int $ollamaPort = 5000
    ) {
    }

    /**
     * Ensure the output directory exists
     */
    private function ensureDirectoryExists($directoryPath): void
    {
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }
    }

    /**
     * Get the file extension
     */
    private function getFileExtension($filePath): string
    {
        return pathinfo($filePath, PATHINFO_EXTENSION);
    }

    /**
     * Determine if file should be processed based on extension
     */
    private function shouldProcessFile($filePath): bool
    {
        $ext = strtolower($this->getFileExtension($filePath));
        $baseName = basename($filePath);

        // Skip hidden files
        if (strpos($baseName, ".") === 0) {
            return false;
        }

        // Only process files with allowed extensions
        return in_array($ext, $this->allowedExtensions);
    }

    /**
     * Check if directory should be processed based on allowed subdirectories
     */
    private function shouldProcessDirectory($dirPath): bool
    {
        // Normalize directory path for comparison
        $dirPath = rtrim($dirPath, "/") . "/";

        // Check if directory or any parent directory is in the skip list
        foreach ($this->skipSubdirectories as $skipDir) {
            $skipDir = rtrim($skipDir, "/") . "/";

            // Check if this directory is a subdirectory of a skipped directory
            // or if it matches exactly a skipped directory
            if (strpos($dirPath, $skipDir) === 0 || $dirPath === $skipDir) {
                return false;
            }

            // Also check if any segment of the path matches a skipped directory
            $pathParts = explode("/", trim($dirPath, "/"));
            foreach ($pathParts as $part) {
                if ($part . "/" === $skipDir) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Read the content of a file safely
     */
    private function readFileContent($filePath): string
    {
        try {
            return file_get_contents($filePath);
        } catch (Exception $e) {
            return "Error reading file: " . $e->getMessage();
        }
    }

    /**
     * Generate documentation using the selected API provider
     */
    private function generateDocumentation($filePath, $content): string
    {
        if ($this->apiProvider === "ollama") {
            return $this->generateDocumentationWithOllama($filePath, $content);
        } elseif ($this->apiProvider === "claude") {
            return $this->generateDocumentationWithClaude($filePath, $content);
        } elseif ($this->apiProvider === "gemini") {
            return $this->generateDocumentationWithGemini($filePath, $content);
        } else {
            return $this->generateDocumentationWithOpenAI($filePath, $content);
        }
    }

    /**
     * Generate documentation using OpenAI API
     */
    private function generateDocumentationWithOpenAI($filePath, $content): string
    {
        try {
            // Check content length and truncate if necessary
            if (strlen($content) > $this->maxTokens * 4) {
                // Rough estimate of token count
                $content =
                    substr($content, 0, $this->maxTokens * 4) .
                    "\n...(truncated for length)...";
            }

            // Extract file name for the title
            $fileName = basename($filePath);

            $prompt = "
            You are documenting a PHP codebase. Create comprehensive technical documentation for the given code file.
            
            File: {$filePath}
            
            Content:
            ```
            {$content}
            ```
            
            Create detailed markdown documentation following this structure:
            
            1. Start with a descriptive title that includes the file name (e.g., \"# [ClassName] Documentation\")
            2. Include a table of contents with links to each section when appropriate
            3. Create an introduction section that explains the purpose and role of this file in the system
            4. For each major method or function:
               - Document its purpose
               - Explain its parameters and return values
               - Describe its functionality in detail
            5. Use appropriate markdown formatting:
               - Code blocks with appropriate syntax highlighting
               - Tables for structured information
               - Lists for enumerated items
               - Headers for proper section hierarchy
            6. Include technical details but explain them clearly
            7. For controller classes, document the routes they handle
            8. For models, document their relationships and important attributes
            
            Focus on accuracy and comprehensiveness. Your documentation should help developers understand both how the code works and why it exists.
            ";

            $postData = [
                "model" => $this->model,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "You are a technical documentation specialist with expertise in PHP applications.",
                    ],
                    ["role" => "user", "content" => $prompt],
                ],
                "max_tokens" => 1500,
            ];

            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->openaiApiKey,
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            curl_close($ch);

            $responseData = json_decode($response, true);

            if (isset($responseData["choices"][0]["message"]["content"])) {
                return $responseData["choices"][0]["message"]["content"];
            } else {
                throw new Exception("Unexpected API response format");
            }
        } catch (Exception $e) {
            return "# Documentation Generation Error\n\nThere was an error generating documentation for this file: " .
                $e->getMessage();
        }
    }

    /**
     * Generate documentation using Ollama API
     */
    private function generateDocumentationWithOllama($filePath, $content): string
    {
        try {
            // Check content length and truncate if necessary
            if (strlen($content) > $this->maxTokens * 4) {
                // Rough estimate of token count
                $content =
                    substr($content, 0, $this->maxTokens * 4) .
                    "\n...(truncated for length)...";
            }

            // Extract file name for the title
            $fileName = basename($filePath);

            $prompt = "
            You are documenting a PHP codebase. Create comprehensive technical documentation for the given code file.
            
            File: {$filePath}
            
            Content:
            ```
            {$content}
            ```
            
            Create detailed markdown documentation following this structure:
            
            1. Start with a descriptive title that includes the file name (e.g., \"# [ClassName] Documentation\")
            2. Include a table of contents with links to each section when appropriate
            3. Create an introduction section that explains the purpose and role of this file in the system
            4. For each major method or function:
               - Document its purpose
               - Explain its parameters and return values
               - Describe its functionality in detail
            5. Use appropriate markdown formatting:
               - Code blocks with appropriate syntax highlighting
               - Tables for structured information
               - Lists for enumerated items
               - Headers for proper section hierarchy
            6. Include technical details but explain them clearly
            7. For controller classes, document the routes they handle
            8. For models, document their relationships and important attributes
            
            Focus on accuracy and comprehensiveness. Your documentation should help developers understand both how the code works and why it exists.
            ";

            $postData = [
                "model" => $this->model,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "You are a technical documentation specialist with expertise in PHP applications.",
                    ],
                    ["role" => "user", "content" => $prompt],
                ],
                "max_tokens" => $this->maxTokens,
                "stream" => false,
            ];

            // Ollama runs locally on the configured host and port
            $ch = curl_init(
                "http://{$this->ollamaHost}:{$this->ollamaPort}/api/chat"
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            curl_close($ch);

            $responseData = json_decode($response, true);

            if (isset($responseData["message"]["content"])) {
                return $responseData["message"]["content"];
            } else {
                throw new Exception("Unexpected API response format");
            }
        } catch (Exception $e) {
            return "# Documentation Generation Error\n\nThere was an error generating documentation for this file: " .
                $e->getMessage();
        }
    }

    /**
     * Generate documentation using Claude API
     */
    private function generateDocumentationWithClaude($filePath, $content): string
    {
        try {
            // Check content length and truncate if necessary
            if (strlen($content) > $this->maxTokens * 4) {
                // Rough estimate of token count
                $content =
                    substr($content, 0, $this->maxTokens * 4) .
                    "\n...(truncated for length)...";
            }

            // Extract file name for the title
            $fileName = basename($filePath);

            $prompt = "
            You are documenting a PHP codebase. Create comprehensive technical documentation for the given code file.
            
            File: {$filePath}
            
            Content:
            ```
            {$content}
            ```
            
            Create detailed markdown documentation following this structure:
            
            1. Start with a descriptive title that includes the file name (e.g., \"# [ClassName] Documentation\")
            2. Include a table of contents with links to each section when appropriate
            3. Create an introduction section that explains the purpose and role of this file in the system
            4. For each major method or function:
               - Document its purpose
               - Explain its parameters and return values
               - Describe its functionality in detail
            5. Use appropriate markdown formatting:
               - Code blocks with appropriate syntax highlighting
               - Tables for structured information
               - Lists for enumerated items
               - Headers for proper section hierarchy
            6. Include technical details but explain them clearly
            7. For controller classes, document the routes they handle
            8. For models, document their relationships and important attributes
            
            Focus on accuracy and comprehensiveness. Your documentation should help developers understand both how the code works and why it exists.
            ";

            $postData = [
                "model" => $this->model,
                "messages" => [
                    [
                        "role" => "system",
                        "content" =>
                            "You are a technical documentation specialist with expertise in PHP applications.",
                    ],
                    ["role" => "user", "content" => $prompt],
                ],
                "max_tokens" => $this->maxTokens,
                "stream" => false,
            ];

            // Claude API endpoint
            $ch = curl_init("https://api.claude.ai/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->openaiApiKey,
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            curl_close($ch);

            $responseData = json_decode($response, true);

            if (isset($responseData["choices"][0]["message"]["content"])) {
                return $responseData["choices"][0]["message"]["content"];
            } else {
                throw new Exception("Unexpected API response format");
            }
        } catch (Exception $e) {
            return "# Documentation Generation Error\n\nThere was an error generating documentation for this file: " .
                $e->getMessage();
        }
    }

    /**
     * Generate documentation using Gemini API
     */
    private function generateDocumentationWithGemini($filePath, $content): string
    {
        try {
            // Check content length and truncate if necessary
            if (strlen($content) > $this->maxTokens * 4) {
                // Rough estimate of token count
                $content =
                    substr($content, 0, $this->maxTokens * 4) .
                    "\n...(truncated for length)...";
            }

            // Extract file name for the title
            $fileName = basename($filePath);

            $prompt = "
            You are documenting a PHP codebase. Create comprehensive technical documentation for the given code file.
            
            File: {$filePath}
            
            Content:
            ```
            {$content}
            ```
            
            Create detailed markdown documentation following this structure:
            
            1. Start with a descriptive title that includes the file name (e.g., \"# [ClassName] Documentation\")
            2. Include a table of contents with links to each section when appropriate
            3. Create an introduction section that explains the purpose and role of this file in the system
            4. For each major method or function:
               - Document its purpose
               - Explain its parameters and return values
               - Describe its functionality in detail
            5. Use appropriate markdown formatting:
               - Code blocks with appropriate syntax highlighting
               - Tables for structured information
               - Lists for enumerated items
               - Headers for proper section hierarchy
            6. Include technical details but explain them clearly
            7. For controller classes, document the routes they handle
            8. For models, document their relationships and important attributes
            
            Focus on accuracy and comprehensiveness. Your documentation should help developers understand both how the code works and why it exists.
            ";

            $postData = [
                "contents" => [
                    [
                        "role" => "user",
                        "parts" => [
                            ["text" => $prompt]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "maxOutputTokens" => $this->maxTokens,
                    "temperature" => 0.2,
                    "topP" => 0.9
                ]
            ];

            // Determine which Gemini model to use (gemini-1.5-pro by default if not specified)
            $geminiModel = ($this->model === "gemini" || $this->model === "gemini-pro") ? "gemini-1.5-pro" : $this->model;
            
            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$this->openaiApiKey}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            curl_close($ch);

            $responseData = json_decode($response, true);

            if (isset($responseData["candidates"][0]["content"]["parts"][0]["text"])) {
                return $responseData["candidates"][0]["content"]["parts"][0]["text"];
            } else {
                throw new Exception("Unexpected Gemini API response format: " . json_encode($responseData));
            }
        } catch (Exception $e) {
            return "# Documentation Generation Error\n\nThere was an error generating documentation for this file: " .
                $e->getMessage();
        }
    }

    /**
     * Create documentation file for a given source file
     */
    private function createDocumentationFile($sourcePath, $relPath, $sourceDir): void
    {
        // Define output path - preserve complete directory structure including source directory name
        $outputDir = rtrim($this->outputDir, "/") . "/";

        // Get just the source directory basename (without full path)
        $sourceDirName = basename(rtrim($sourceDir, "/"));

        // Prepend the source directory name to the relative path to maintain the full structure
        $fullRelPath = $sourceDirName . "/" . $relPath;
        $relDir = dirname($fullRelPath);
        $fileName = pathinfo($relPath, PATHINFO_FILENAME);

        // Create proper output path
        $outputPath = $outputDir . $relDir . "/" . $fileName . ".md";

        // Skip if documentation file already exists
        if (file_exists($outputPath)) {
            echo "Documentation already exists: {$outputPath} - skipping\n";
            return;
        }

        // Ensure the directory exists
        $this->ensureDirectoryExists(dirname($outputPath));

        // Check if file is valid for processing
        if (!$this->shouldProcessFile($sourcePath)) {
            return;
        }

        // Read content
        $content = $this->readFileContent($sourcePath);

        // Generate documentation
        echo "Generating documentation for {$sourcePath}...\n";
        $docContent = $this->generateDocumentation($sourcePath, $content);

        // Write to file
        $fileContent = "# Documentation: " . basename($sourcePath) . "\n\n";
        $fileContent .= "Original file: `{$fullRelPath}`\n\n"; // Use full relative path here
        $fileContent .= $docContent;

        file_put_contents($outputPath, $fileContent);

        echo "Documentation created: {$outputPath}\n";

        // Rate limiting to avoid hitting API limits
        usleep(500000); // 0.5 seconds
    }

    /**
     * Process all files in directory recursively
     */
    private function processDirectory($baseDir): void
    {
        $baseDir = rtrim($baseDir, "/");

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $baseDir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            // Skip directories
            if ($file->isDir()) {
                continue;
            }

            $sourcePath = $file->getPathname();
            $dirName = basename(dirname($sourcePath));
            $fileName = $file->getBasename();

            // Skip hidden files and directories
            if (strpos($fileName, ".") === 0 || strpos($dirName, ".") === 0) {
                continue;
            }

            // Calculate relative path from the source directory
            $relFilePath = substr($sourcePath, strlen($baseDir) + 1);

            // Check if parent directory should be processed
            $relDirPath = dirname($relFilePath);
            if (!$this->shouldProcessDirectory($relDirPath)) {
                continue;
            }

            $this->createDocumentationFile($sourcePath, $relFilePath, $baseDir);
        }
    }

    /**
     * Main method to execute the documentation generation
     */
    public function generate(): void
    {
        // Ensure output directory exists
        $this->ensureDirectoryExists($this->outputDir);

        // Process each source directory
        foreach ($this->sourceDirs as $sourceDir) {
            if (file_exists($sourceDir)) {
                echo "Processing directory: {$sourceDir}\n";
                $this->processDirectory($sourceDir);
            } else {
                echo "Directory not found: {$sourceDir}\n";
            }
        }

        echo "\nDocumentation generation complete! Files are available in the '{$this->outputDir}' directory.\n";
    }
}
