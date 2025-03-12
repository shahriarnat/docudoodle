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
                            {--source=* : Directories to process (default: app/, config/, routes/, database/)}
                            {--output=documentation : Output directory for documentation}
                            {--model=gpt-4o-mini : OpenAI model to use}
                            {--max-tokens=10000 : Maximum tokens for API calls}
                            {--extensions=* : File extensions to process (default: php, yaml, yml)}
                            {--skip=* : Subdirectories to skip (default: vendor/, node_modules/, tests/, cache/)}';

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
        
        // Parse command options
        $sourceDirs = $this->option('source');
        if (empty($sourceDirs)) {
            $sourceDirs = ['app/', 'config/', 'routes/', 'database/'];
        }
        
        $outputDir = $this->option('output');
        $model = $this->option('model');
        $maxTokens = (int) $this->option('max-tokens');
        
        $extensions = $this->option('extensions');
        if (empty($extensions)) {
            $extensions = ['php', 'yaml', 'yml'];
        }
        
        $skipSubdirs = $this->option('skip');
        if (empty($skipSubdirs)) {
            $skipSubdirs = ['vendor/', 'node_modules/', 'tests/', 'cache/'];
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