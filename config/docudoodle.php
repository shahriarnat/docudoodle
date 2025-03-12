<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | This key is used to authenticate with the OpenAI API.
    | You can get your API key from https://platform.openai.com/account/api-keys
    |
    */
    'openai_api_key' => env('OPENAI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default model to use for generating documentation.
    |
    */
    'default_model' => env('DOCUDOODLE_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Tokens
    |--------------------------------------------------------------------------
    |
    | The maximum number of tokens to use for API calls.
    |
    */
    'max_tokens' => env('DOCUDOODLE_MAX_TOKENS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Default Extensions
    |--------------------------------------------------------------------------
    |
    | The default file extensions to process.
    |
    */
    'default_extensions' => ['php', 'yaml', 'yml'],

    /*
    |--------------------------------------------------------------------------
    | Default Skip Directories
    |--------------------------------------------------------------------------
    |
    | The default directories to skip during processing.
    |
    */
    'default_skip_dirs' => ['vendor/', 'node_modules/', 'tests/', 'cache/'],
];