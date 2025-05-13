<?php

namespace Docudoodle\Services;

use Exception;
use GuzzleHttp\Client;

class JiraDocumentationService
{
    private $client;
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'base_uri' => rtrim($config['host'], '/') . '/rest/api/3/',
            'auth' => [$config['email'], $config['api_token']],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createOrUpdateDocumentation(string $title, string $content, array $metadata = []): bool
    {
        try {
            // Search for existing documentation with this title
            $jql = sprintf(
                'project = "%s" AND issuetype = "%s" AND summary ~ "%s"',
                $this->config['project_key'],
                $this->config['issue_type'],
                $title
            );

            $response = $this->client->get('search', [
                'query' => ['jql' => $jql]
            ]);

            $result = json_decode($response->getBody(), true);
            $issueId = !empty($result['issues']) ? $result['issues'][0]['id'] : null;

            $documentData = [
                'fields' => [
                    'project' => ['key' => $this->config['project_key']],
                    'summary' => $title,
                    'description' => [
                        'version' => 1,
                        'type' => 'doc',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => $content
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'issuetype' => ['name' => $this->config['issue_type']],
                ],
            ];

            if ($issueId) {
                // Update existing issue
                $this->client->put("issue/{$issueId}", [
                    'json' => $documentData
                ]);
            } else {
                // Create new issue
                $this->client->post('issue', [
                    'json' => $documentData
                ]);
            }

            return true;
        } catch (Exception $e) {
            // Log error or handle it as needed
            return false;
        }
    }
}
