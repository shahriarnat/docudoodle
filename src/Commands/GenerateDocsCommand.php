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
                            {--model= : Model to use (default: from config or gpt-4o-mini)}
                            {--max-tokens= : Maximum tokens for API calls (default: from config or 10000)}
                            {--extensions=* : File extensions to process (default: from config or php, yaml, yml)}
                            {--skip=* : Subdirectories to skip (default: from config or vendor/, node_modules/, tests/, cache/)}
                            {--api-provider= : API provider to use (default: from config or openai)}
                            {--prompt-template= : Path to the prompt template file (default: from config or default-prompt.md)}
                            {--cache-path= : Path to the cache file (overrides config)}
                            {--no-cache : Disable caching completely}
                            {--bypass-cache : Force regeneration of all documents ignoring cache}
                            {--azure-endpoint= : Azure OpenAI endpoint (default: from config)}
                            {--azure-deployment= : Azure OpenAI deployment ID (default: from config)}
                            {--azure-api-version= : Azure OpenAI API version (default: from config or 2023-05-15)}
                            {--jira : Enable Jira documentation}
                            {--confluence : Enable Confluence documentation}
                            {--no-files : Disable file system documentation output}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for your Laravel application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiProvider = $this->option('api-provider') ?: config('docudoodle.default_api_provider', 'openai');
        $apiKey = '';

        if ($apiProvider === 'openai') {
            $apiKey = config('docudoodle.openai_api_key');
            if (empty($apiKey)) {
                $this->error('Oops! OpenAI API key is not set in the configuration!');
                return 1;
            }
        } elseif ($apiProvider === 'claude') {
            $apiKey = config('docudoodle.claude_api_key');
            if (empty($apiKey)) {
                $this->error('Oops! Claude API key is not set in the configuration!');
                return 1;
            }
        } elseif ($apiProvider === 'gemini') {
            $apiKey = config('docudoodle.gemini_api_key');
            if (empty($apiKey)) {
                $this->error('Oops! Gemini API key is not set in the configuration!');
                return 1;
            }
        } elseif ($apiProvider === 'azure') {
            $apiKey = config('docudoodle.azure_api_key');
            if (empty($apiKey)) {
                $this->error('Oops! Azure OpenAI API key is not set in the configuration!');
                return 1;
            }
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
            $model = config('docudoodle.default_model', 'gpt-4o-mini');
        }

        $maxTokens = $this->option('max-tokens');
        if (empty($maxTokens)) {
            $maxTokens = (int)config('docudoodle.max_tokens', 10000);
        } else {
            $maxTokens = (int)$maxTokens;
        }

        $extensions = $this->option('extensions');
        if (empty($extensions)) {
            $extensions = config('docudoodle.extensions', ['php', 'yaml', 'yml']);
        }

        $skipSubdirs = $this->option('skip');
        if (empty($skipSubdirs)) {
            $skipSubdirs = config('docudoodle.default_skip_dirs', ['vendor/', 'node_modules/', 'tests/', 'cache/']);
        }

        $ollamaHost = config('docudoodle.ollama_host', 'localhost');
        $ollamaPort = config('docudoodle.ollama_port', 5000);


        // Azure OpenAI specific configuration
        $azureEndpoint = $this->option('azure-endpoint');
        $azureDeployment = $this->option('azure-deployment');
        $azureApiVersion = $this->option('azure-api-version');

        if (empty($azureEndpoint)) {
            $azureEndpoint = config('docudoodle.azure_endpoint', '');
        }
        if (empty($azureDeployment)) {
            $azureDeployment = config('docudoodle.azure_deployment', '');
        }
        if (empty($azureApiVersion)) {
            $azureApiVersion = config('docudoodle.azure_api_version', '2023-05-15');
        }


        // Handle Jira configuration
        $jiraConfig = [];
        if ($this->option('jira')) {
            $jiraConfig = [
                'enabled' => true,
                'host' => config('docudoodle.jira.host'),
                'api_token' => config('docudoodle.jira.api_token'),
                'email' => config('docudoodle.jira.email'),
                'project_key' => config('docudoodle.jira.project_key'),
                'issue_type' => config('docudoodle.jira.issue_type'),
            ];

            // Validate Jira configuration
            if (empty($jiraConfig['host']) || empty($jiraConfig['api_token']) ||
                empty($jiraConfig['email']) || empty($jiraConfig['project_key'])) {
                $this->error('Jira integration is enabled but configuration is incomplete. Please check your .env file.');
                return 1;
            }
        }

        // Handle Confluence configuration
        $confluenceConfig = [];
        if ($this->option('confluence')) {
            $confluenceConfig = [
                'enabled' => true,
                'host' => config('docudoodle.confluence.host'),
                'api_token' => config('docudoodle.confluence.api_token'),
                'email' => config('docudoodle.confluence.email'),
                'space_key' => config('docudoodle.confluence.space_key'),
                'parent_page_id' => config('docudoodle.confluence.parent_page_id'),
            ];

            // Validate Confluence configuration
            if (empty($confluenceConfig['host']) || empty($confluenceConfig['api_token']) ||
                empty($confluenceConfig['email']) || empty($confluenceConfig['space_key'])) {
                $this->error('Confluence integration is enabled but configuration is incomplete. Please check your .env file.');
                return 1;
            }
        }

        // Set output directory to 'none' if --no-files is specified
        if ($this->option('no-files')) {
            $outputDir = 'none';
        }

        // Convert relative paths to absolute paths based on Laravel's base path
        $sourceDirs = array_map(function ($dir) {
            return base_path($dir);
        }, $sourceDirs);

        $outputDir = base_path($outputDir);

        $this->info('Starting documentation generation...');
        $this->info('Source directories: ' . implode(', ', $sourceDirs));
        $this->info('Output directory: ' . $outputDir);
        $this->info('API provider: ' . $apiProvider);

        // Determine cache settings
        $useCache = !$this->option('no-cache') && config('docudoodle.use_cache', true);
        $bypassCache = $this->option('bypass-cache');

        $cachePath = $this->option('cache-path');
        if (empty($cachePath)) {
            $cachePath = config('docudoodle.cache_file_path', null);
        }
        if ($useCache) {
            $this->info('Cache enabled.' . ($cachePath ? " Path: {$cachePath}" : ' Using default path'));
            if ($bypassCache) {
                $this->info('Bypass cache flag set: Documents will be regenerated but cache will be updated.');
            }
        } else {
            $this->info('Cache disabled.' . ($this->option('no-cache') ? ' (--no-cache option)' : ' (from config)'));
        }

        $promptTemplate = $this->option('prompt-template');
        if (empty($promptTemplate)) {
            $promptTemplate = config('docudoodle.prompt_template', __DIR__ . '/../../resources/templates/default-prompt.md');
        }

        try {
            $generator = new Docudoodle(
                openaiApiKey: $apiKey,
                sourceDirs: $sourceDirs,
                outputDir: $outputDir,
                model: $model,
                maxTokens: $maxTokens,
                allowedExtensions: $extensions,
                skipSubdirectories: $skipSubdirs,
                apiProvider: $apiProvider,
                ollamaHost: $ollamaHost,
                ollamaPort: $ollamaPort,
                promptTemplate: $promptTemplate,
                useCache: $useCache,
                cacheFilePath: $cachePath,
                forceRebuild: $bypassCache,
                azureEndpoint: $azureEndpoint,
                azureDeployment: $azureDeployment,
                azureApiVersion: $azureApiVersion,
                jiraConfig: $jiraConfig,
                confluenceConfig: $confluenceConfig
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
