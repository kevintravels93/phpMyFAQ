<?php

namespace phpMyFAQ\Search;

/**
 * phpMyFAQ Elasticsearch based search classes.
 *
 *
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at http://mozilla.org/MPL/2.0/.
 *
 * @category  phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2015-2018 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2015-12-25
 */

use Elasticsearch\Client;
use phpMyFAQ\Configuration;

if (!defined('IS_VALID_PHPMYFAQ')) {
    exit();
}

/**
 * PMF_Search_Elasticsearch.
 *
 * @category  phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2015-2018 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2015-12-25
 */
class Elasticsearch extends AbstractSearch implements SearchInterface
{
    /** @var Client */
    private $client = null;

    /** @var array */
    private $esConfig = [];

    /** @var string */
    private $language = '';

    /** @var array */
    private $categoryIds = [];

    /**
     * Constructor.
     *
     * @param Configuration
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);

        $this->client = $this->_config->getElasticsearch();
        $this->esConfig = $this->_config->getElasticsearchConfig();
    }

    /**
     * Prepares the search and executes it.
     *
     * @param string $searchTerm Search term
     *
     * @throws Exception
     *
     * @return array
     */
    public function search($searchTerm)
    {
        $searchParams = [
            'index' => $this->esConfig['index'],
            'type' => $this->esConfig['type'],
            'size' => 100,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'fields' => [
                                    'question', 'answer', 'keywords'
                                ],
                                'query' => $searchTerm,
                                'fuzziness' => 'AUTO'
                            ]
                        ],
                        'filter' => [
                            'terms' => ['category_id' => $this->getCategoryIds()]
                        ],
                    ]
                ]
            ]
        ];

        $result = $this->client->search($searchParams);

        if (0 !== $result['hits']['total']) {

            foreach ($result['hits']['hits'] as $hit) {
                $resultSet = new \stdClass();
                $resultSet->id = $hit['_source']['id'];
                $resultSet->lang = $hit['_source']['lang'];
                $resultSet->question = $hit['_source']['question'];
                $resultSet->answer = $hit['_source']['answer'];
                $resultSet->keywords = $hit['_source']['keywords'];
                $resultSet->category_id = $hit['_source']['category_id'];
                $resultSet->score = $hit['_score'];

                $this->resultSet[] = $resultSet;
            }

        } else {
            $this->resultSet = [];
        }

        return $this->resultSet;
    }

    /**
     * Prepares the auto complete search and executes it.
     *
     * @param string $searchTerm Search term for autocompletion
     *
     * @throws Exception
     *
     * @return array
     */
    public function autoComplete($searchTerm)
    {
        $searchParams = [
            'index' => $this->esConfig['index'],
            'type' => $this->esConfig['type'],
            'size' => 100,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'fields' => [
                                    'question', 'answer', 'keywords'
                                ],
                                'query' => $searchTerm,
                                'fuzziness' => 'AUTO'
                            ]
                        ],
                        'filter' => [
                            'term' => [
                                'lang' => $this->getLanguage()
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->client->search($searchParams);

        if (0 !== $result['hits']['total']) {

            foreach ($result['hits']['hits'] as $hit) {
                $resultSet = new \stdClass();
                $resultSet->id = $hit['_source']['id'];
                $resultSet->lang = $hit['_source']['lang'];
                $resultSet->question = $hit['_source']['question'];
                $resultSet->answer = $hit['_source']['answer'];
                $resultSet->keywords = $hit['_source']['keywords'];
                $resultSet->category_id = $hit['_source']['category_id'];
                $resultSet->score = $hit['_score'];

                $this->resultSet[] = $resultSet;
            }

        } else {
            $this->resultSet = [];
        }

        return $this->resultSet;
    }

    /**
     * Returns the current category ID
     *
     * @return array
     */
    public function getCategoryIds()
    {
        return $this->categoryIds;
    }

    /**
     * Sets the current category ID
     *
     * @param array $categoryIds
     */
    public function setCategoryIds(Array $categoryIds)
    {
        $this->categoryIds = $categoryIds;
    }

    /**
     * Returns the current language, empty string if all languages
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Sets the current language
     *
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }
}
