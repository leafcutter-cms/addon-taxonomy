<h1>Tags</h1>
<?php
$page = $this->page();
$leafcutter = $this->leafcutter();
$terms = $page->meta('taxonomy_meta.terms.page');
seeded_shuffle($terms,crc32(serialize($terms)));

$page->metaMerge([
    'page_css' => ['@/~taxonomy/tags/tag-cloud.css']
]);

$min = min(array_map(function ($e) {return $e['count'];}, $terms));
$max = max(array_map(function ($e) {return $e['count'];}, $terms));
$scaleMin = 0.8;
$scaleRange = 2;

echo "<div class='tag-cloud'>";
foreach ($terms as $term) {
    $scale = $scaleMin + $scaleRange * ($term['count'] - $min) / ($max - $min);
    echo PHP_EOL . "<a href='" . $term['url'] . "' data-tag-cloud-scale='" . $scale . "' style='font-size:" . $scale . "em;'>" . $term['term'] . "</a>";
}
echo "</div>";

$leafcutter->templates()->apply(
    'partials/paginator.twig',
    [
        'arg' => 'page',
        'page' => $page->meta('taxonomy_meta.page'),
        'pageCount' => $page->meta('taxonomy_meta.pageCount'),
    ]
);

/**
 * Shuffles an array in a repeatable manner, if the same $seed is provided.
 * 
 * @param array &$items The array to be shuffled.
 * @param integer $seed The result of the shuffle will be the same for the same input ($items and $seed). If not given, uses the current time as seed.
 * @return void
 */
function seeded_shuffle(array &$items, $seed = false) {
    $items = array_values($items);
    mt_srand($seed ? $seed : time());
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        list($items[$i], $items[$j]) = array($items[$j], $items[$i]);
    }
}