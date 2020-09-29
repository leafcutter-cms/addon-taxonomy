<?php
namespace Leafcutter\Addons\Leafcutter\Taxonomy;

use Leafcutter\Pages\Page;
use Leafcutter\URL;

class TaxonomyAddon extends \Leafcutter\Addons\AbstractAddon
{
    /**
     * Specify default config here. If it must include dynamic content, or
     * for some other reason can't be a constant, delete this constant and
     * override the method `getDefaultConfig()` instead.
     */
    const DEFAULT_CONFIG = [
        'taxonomies' => [
            'tags' => [
                'displayName' => 'Tags',
                'public' => true,
                'termsPerPage' => 100,
                'pagesPerPage' => 10,
                'patterns' => [
                    'hashtag' => [
                        'pattern' => '#(?P<name>[a-zA-Z0-9_\-]+)',
                        'keep' => true,
                    ],
                    'explicit' => [
                        'pattern' => '#\{(?P<name>[^\}]+)\}',
                        'keep' => false,
                    ],
                ],
            ],
            'categories' => [
                'displayName' => 'Categories',
                'public' => true,
                'termsPerPage' => 100,
                'pagesPerPage' => 10,
                'patterns' => [
                    'explicit' => [
                        'pattern' => '@category\{(?P<name>[^\}]+)\}',
                        'keep' => false,
                    ],
                ],
            ]
        ],
    ];

    /**
     * Method is executed as the first step when this Addon is activated.
     *
     * @return void
     */
    public function activate(): void
    {
        // set up namespace for delivering content files
        $this->leafcutter->content()->addDirectory(__DIR__ . '/../content', 'taxonomy');
        // set up indexes for all our taxonomies
        foreach ($this->config('taxonomies') as $name => $config) {
            if (!$config || @$config['disabled']) {
                continue;
            }
            $config['name'] = $name;
            $config['displayName'] = @$config['displayName'] ?? $name;
            $this->config('name',$config);
            $index = $this->leafcutter->indexer()->index('taxonomy__' . $name, TaxonomyIndex::class);
            $index->taxonomyConfig($config);
            $this->indexes[$name] = $index;
        }
    }

    public function onPageGet_namespace_taxonomy(URL $url): ?Page
    {
        $url->fixSlashes();
        if ($url->sitePath() == '') {
            $url->setQuery([]);
            $page = $this->leafcutter->pages()->getFromPath($url, 'index.*', 'taxonomy');
            $page->meta('taxonomy_meta', array_filter(array_map(
                function ($index) {
                    if (!$index->public()) {
                        return false;
                    }
                    return [
                        'name' => $index->displayName(),
                        'url' => $index->url(),
                    ];
                },
                $this->indexes
            )));
            return $page;
        }
        return null;
    }

    /**
     * Used after loading to give Leafcutter an array of event subscribers.
     * An easy way of rapidly developing simple Addons is to simply return [$this]
     * and put your event listener methods in this same single class.
     *
     * @return array
     */
    public function getEventSubscribers(): array
    {
        return [$this];
    }

    /**
     * Specify the names of the features this Addon provides. Some names may require
     * you to implement certain interfaces. Addon will also be available from
     * AddonProvider::get() by any names given here.
     *
     * @return array
     */
    public static function provides(): array
    {
        return ['taxonomy'];
    }

    /**
     * Specify an array of the names of features this Addon requires. Leafcutter
     * will attempt to automatically load the necessary Addons to provide these
     * features when this Addon is loaded.
     *
     * @return array
     */
    public static function requires(): array
    {
        return [];
    }

    /**
     * Return the canonical name of this plugin. Generally this should be the
     * same as the composer package name, so this example pulls it from your
     * composer.json automatically.
     *
     * @return string
     */
    public static function name(): string
    {
        if ($data = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true)) {
            return $data['name'];
        }
        return 'unknown/unknownaddon';
    }
}
