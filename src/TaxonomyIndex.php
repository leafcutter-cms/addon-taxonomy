<?php
namespace Leafcutter\Addons\Leafcutter\Taxonomy;

use DOMElement;
use Flatrr\SelfReferencingFlatArray;
use Leafcutter\DOM\DOMEvent;
use Leafcutter\Indexer\AbstractIndex;
use Leafcutter\Pages\Page;
use Leafcutter\URL;

class TaxonomyIndex extends AbstractIndex
{
    protected $config;

    const UNPARSED_TAGS = [
        'head', 'style', 'code', 'textarea',
    ];

    protected function order()
    {
        return '`sort` DESC';
    }

    function public (): bool {
        return !!$this->config['public'];
    }

    public function displayName(): string
    {
        return $this->config['displayName'];
    }

    public function taxonomyConfig(array $config)
    {
        $this->config = new SelfReferencingFlatArray($config);
    }

    public function indexPage(Page $page)
    {
        $terms = $this->terms($page);
        $url = $page->url();
        // remove non-matching terms
        foreach ($this->getByURL($page->url()) as $item) {
            if (!in_array($item->value(), $terms)) {
                $item->delete();
            }
        }
        // add new terms
        foreach ($terms as $term) {
            $sort = $page->meta('date.created') ?? $page->meta('date.modified') ?? time();
            $sort = str_pad($sort, '0', STR_PAD_LEFT);
            $this->save($url, $term, $sort);
        }
    }

    protected function terms(Page $page): array
    {
        $terms = $page->meta('taxonomy.' . $this->config['name']) ?? [];
        if ($this->config['patterns']) {
            foreach ($this->config['patterns'] as $p) {
                $matches = [];
                preg_match_all('/' . $p['pattern'] . '/', $page->generateContent(), $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $terms[] = $m['name'];
                }
            }
        }
        $terms = array_unique(
            array_map(
                '\\strtolower',
                array_map('\\strip_tags', $terms)
            )
        );
        return $terms;
    }

    public function onDOMText_full(DOMEvent $event)
    {
        $newText = $text = $event->getNode()->textContent;
        if (!$this->config['public']) {
            return;
        }
        $parent = $event->getNode();
        while ($parent = $parent->parentNode) {
            if ($parent instanceof DOMElement) {
                if (in_array($parent->tagName, static::UNPARSED_TAGS)) {
                    return;
                }
            }
        }
        foreach ($this->config['patterns'] as $p) {
            $newText = preg_replace_callback(
                '/' . $p['pattern'] . '/',
                function ($m) use ($p) {
                    $term = $m['name'];
                    $text = $p['keep'] ? $m[0] : $term;
                    $text = '<![CDATA[' . $text . ']]>';
                    return "<a href='" . $this->termPage($term)->url() . "' data-" . $this->config['name'] . "='" . $term . "'>" . $text . "</a>";
                },
                $newText
            );
        }
        if ($newText != $text) {
            $event->setReplacement($newText);
        }
    }

    public function url(string $term = null): URL
    {
        $url = '@/~taxonomy/' . URL::base64_encode($this->config['name']) . '/';
        if ($term) {
            $url .= URL::base64_encode($term) . '.html';
        }
        return new URL($url);
    }

    public function onPageGet_namespace_taxonomy(URL $url): ?Page
    {
        $url->fixSlashes();
        $path = explode('/', $url->sitePath());
        if (count($path) == 2 && URL::base64_decode($path[0]) == $this->config['name']) {
            if ($path[1]) {
                $term = URL::base64_decode(preg_replace('/\.html$/', '', $path[1]));
                return $this->termPage($term, @$url->query()['page']);
            } else {
                return $this->termListPage(@$url->query()['page']);
            }
        }
        return null;
    }

    public function termPage(string $term, ?int $page = 1): ?Page
    {
        $url = $this->url($term);
        if ($page > 1) {
            $url->setQuery(['page' => $page]);
        } else {
            $page = 1;
        }
        $paths = [
            $this->config['name'] . '/' . $term . '.*',
            $this->config['name'] . '/~any.*',
            '~any/~any.*',
        ];
        $taxPage = null;
        foreach ($paths as $path) {
            if ($taxPage = $this->leafcutter->pages()->getfromPath($url, $path, 'taxonomy')) {
                $taxPage->setUrl($url);
                break;
            }
        }
        // get urls, calculate page info
        $urls = $this->getByValue($term);
        $perPage = $this->config['pagesPerPage'] ?? 10;
        $pages = ceil(count($urls) / $perPage);
        if ($page > $pages) {
            return null;
        }
        // set up metadata and return
        $taxPage->meta('taxonomy_meta', [
            'term' => $term,
            'page' => $page,
            'pageCount' => $pages,
            'perPage' => $perPage,
            'pages' => [
                'all' => $urls,
                'page' => array_slice($urls, ($page - 1) * $perPage, $perPage),
            ],
        ]);
        $taxPage->meta('name', $term);
        $taxPage->meta('title', $this->displayName() . ': ' . $term);
        return $taxPage;
    }

    public function termListPage(?int $page = 1): ?Page
    {
        $url = $this->url();
        if ($page > 1) {
            $url->setQuery(['page' => $page]);
        } else {
            $page = 1;
        }
        $paths = [
            $this->config['name'] . '/index.*',
            '~any/index.*',
        ];
        $taxPage = null;
        foreach ($paths as $path) {
            if ($taxPage = $this->leafcutter->pages()->getfromPath($url, $path, 'taxonomy')) {
                $taxPage->setUrl($url);
                break;
            }
        }
        // get urls, calculate page info
        $urls = $this->listValues();
        $urls = array_map(
            function ($e) {
                return [
                    'term' => $e->value(),
                    'count' => $e->count(),
                    'url' => $this->url($e->value()),
                ];
            },
            $urls
        );
        $perPage = $this->config['termsPerPage'] ?? 10;
        $pages = ceil(count($urls) / $perPage);
        if ($pages > 0 && $page > $pages) {
            return null;
        }
        // set up metadata and return
        $taxPage->meta('taxonomy_meta', [
            'name' => $this->displayName(),
            'page' => $page,
            'pageCount' => $pages,
            'perPage' => $perPage,
            'terms' => [
                'all' => $urls,
                'page' => array_slice($urls, ($page - 1) * $perPage, $perPage),
            ],
        ]);
        $taxPage->meta('name', $this->displayName());
        $taxPage->meta('title', 'Page Taxonomy: ' . $this->displayName());
        return $taxPage;
    }
}
