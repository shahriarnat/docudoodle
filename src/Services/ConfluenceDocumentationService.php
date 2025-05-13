<?php

namespace Docudoodle\Services;

use Exception;
use GuzzleHttp\Client;

class ConfluenceDocumentationService
{
    private $client;
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'base_uri' => rtrim($config['host'], '/') . '/wiki/rest/api/',
            'auth' => [$config['email'], $config['api_token']],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createOrUpdatePage(string $title, string $content, array $metadata = []): bool
    {
        try {
            // Search for existing page with this title
            $response = $this->client->get('content', [
                'query' => [
                    'spaceKey' => $this->config['space_key'],
                    'title' => $title,
                    'expand' => 'version'
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            $pageId = !empty($result['results']) ? $result['results'][0]['id'] : null;
            $version = !empty($result['results']) ? $result['results'][0]['version']['number'] : 0;

            $pageData = [
                'type' => 'page',
                'title' => $title,
                'space' => ['key' => $this->config['space_key']],
                'body' => [
                    'storage' => [
                        'value' => $content,
                        'representation' => 'storage'
                    ]
                ],
            ];

            if ($this->config['parent_page_id']) {
                $pageData['ancestors'] = [['id' => $this->config['parent_page_id']]];
            }

            if ($pageId) {
                // Update existing page
                $pageData['version'] = ['number' => $version + 1];
                $this->client->put("content/{$pageId}", [
                    'json' => $pageData
                ]);
            } else {
                // Create new page
                $this->client->post('content', [
                    'json' => $pageData
                ]);
            }

            return true;
        } catch (Exception $e) {
            // Log error or handle it as needed
            return false;
        }
    }
}
