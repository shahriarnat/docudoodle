<?php

namespace Docudoodle\Commands;

use Illuminate\Console\Command;
use Docudoodle\Docudoodle;

class GenerateDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docudoodle:generate 
                            {--source=* : Directories to process (default: from config or app/, config/, routes/, database/)}
                            {--output= : Output directory for documentation (default: from config or "documentation")}
                            {--model= : OpenAI model to use (default: from config or gpt-4o-mini)}
                            {--max-tokens= : Maximum tokens for API calls (default: from config or 10000)}
                            {--extensions=* : File extensions to process (default: from config or php, yaml, yml)}
                            {--skip=* : Subdirectories to skip (default: from config or vendor/, node_modules/, tests/, cache/)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for your Laravel application using OpenAI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = config('docudoodle.openai_api_key');
        
        if (empty($apiKey)) {
            $this->error('Oops! OpenAI API key is not set in the configuration!');
            return 1;
        }
        
        // Parse command options with config fallbacks
        $sourceDirs = $this->option('source');
        if (empty($sourceDirs)) {
            $sourceDirs = config('docudoodle.source_dirs', ['app/', 'config/', 'routes/', 'database/']);
        }
        
        $outputDir = $this->option('output');
        if (empty($outputDir)) {
            $outputDir = config('docudoodle.output_dir', 'documentation');
        }
        
        $model = $this->option('model');
        if (empty($model)) {
            $model = config('docudoodle.model', 'gpt-4o-mini');
        }
        
        $maxTokens = $this->option('max-tokens');
        if (empty($maxTokens)) {
            $maxTokens = (int) config('docudoodle.max_tokens', 10000);
        } else {
            $maxTokens = (int) $maxTokens;
        }
        
        $extensions = $this->option('extensions');
        if (empty($extensions)) {
            $extensions = config('docudoodle.extensions', ['php', 'yaml', 'yml']);
        }
        
        $skipSubdirs = $this->option('skip');
        if (empty($skipSubdirs)) {
            $skipSubdirs = config('docudoodle.skip_subdirs', ['vendor/', 'node_modules/', 'tests/', 'cache/']);
        }
        
        // Convert relative paths to absolute paths based on Laravel's base path
        $sourceDirs = array_map(function($dir) {
            return base_path($dir);
        }, $sourceDirs);
        
        $outputDir = base_path($outputDir);
        
        $this->info('Starting documentation generation...');
        $this->info('Source directories: ' . implode(', ', $sourceDirs));
        $this->info('Output directory: ' . $outputDir);
        
        try {
            $generator = new Docudoodle(
                $apiKey,
                $sourceDirs,
                $outputDir,
                $model,
                $maxTokens,
                $extensions,
                $skipSubdirs
            );
            
            $generator->generate();
            
            $this->info('Documentation generated successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error generating documentation: ' . $e->getMessage());
            return 1;
        }
    }
}