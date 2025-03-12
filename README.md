![Docudoodle Logo](docudoodle.png)
# Docudoodle
## by Genericmilk

This is Docudoodle! 👋 The PHP documentation generator that analyzes your codebase and creates comprehensive documentation using the OpenAI API. It's perfect for helping you and your team understand your code better through detailed insights into your application's structure and functionality.

Docudoodle is fab if you've taken on an application with no existing documentation allowing your team to get up to speed right away.

Docudoodle writes Markdown files which show up great in Github and other source control providers allowing you to get teams up to speed in a matter of moments.

Better yet, Docudoodle skips already existing documentation files. ALlowing a quick top-up run after you have concluded a feature, meaning that the entire process of getting good documentation written is a thing of the past 🚀

## Features

- **Automatic Documentation Generation**: Effortlessly generates documentation for PHP files by analyzing their content.
- **OpenAI Integration**: Leverages the power of OpenAI to create human-readable, high-quality documentation.
- **Customizable**: Easily configure source directories, output folders, and other settings to match your workflow.
- **Command-Line Interface**: Includes a simple command-line script for quick documentation generation.

## Installation

Getting started with Docudoodle is super easy! Just use Composer with this command:

```
composer install genericmilk/docudoodle
```

This will set up all the necessary dependencies defined in the `composer.json` file.

## Usage

Ready to create some amazing documentation? Just run this simple command:

```
php artisan docudoodle:generate
```

Don't forget to set your OpenAI key which looks a bit like this: `sk-XXXXXXXX` in the application configuration file with your actual OpenAI API key!

## Configuration

Docudoodle is highly customizable! The package includes a configuration file at `config/docudoodle.php` that lets you tailor everything to your needs:

### OpenAI API Key
```php
'openai_api_key' => env('OPENAI_API_KEY', ''),
```
Set your OpenAI API key here or in your `.env` file as `OPENAI_API_KEY`. Keys typically start with `sk-XXXXXXXX`.

### Model Selection
```php
'default_model' => env('DOCUDOODLE_MODEL', 'gpt-4o-mini'),
```
Choose which OpenAI model to use. The default is `gpt-4o-mini`, but you can change it in your `.env` file with the `DOCUDOODLE_MODEL` variable.

### Token Limits
```php
'max_tokens' => env('DOCUDOODLE_MAX_TOKENS', 10000),
```
Control the maximum number of tokens for API calls. Adjust this in your `.env` file with `DOCUDOODLE_MAX_TOKENS` if needed.

### File Extensions
```php
'default_extensions' => ['php', 'yaml', 'yml'],
```
Specify which file types Docudoodle should process. By default, it handles PHP and YAML files.

### Skip Directories
```php
'default_skip_dirs' => ['vendor/', 'node_modules/', 'tests/', 'cache/'],
```
Define directories that should be excluded from documentation generation.

You can publish the configuration file to your project using:

```
php artisan vendor:publish --tag=docudoodle-config
```

## Running Tests

Want to make sure everything's working perfectly? Run the tests with:

```
./vendor/bin/phpunit
```

Or if you're using Laravel's test runner:

```
php artisan test
```

## License

This project is licensed under the MIT License. Check out the LICENSE file for all the details.

## Contributing

We'd love your help making Docudoodle even better! Feel free to submit a pull request or open an issue for any enhancements or bug fixes. Everyone's welcome! 🎉