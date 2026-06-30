<?php

/**
 * Static runtime checks for content rendering security.
 */

class Helper
{
    public static function options()
    {
        return (object) ['lazyload' => '0'];
    }
}

$GLOBALS['VOIDSetting'] = [
    'CDNType' => [],
    'parseFigcaption' => true,
    'bluredLazyload' => false,
    'browserLevelLoadingLazy' => false,
];

require_once dirname(__DIR__) . '/libs/Contents.php';

$passed = 0;
$failed = 0;

function cst_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo '.';
    } else {
        $failed++;
        echo "\nFAIL: {$label}\n";
    }
}

$html = Contents::parseFancyBox(
    '<p><img src="https://example.com/a.jpg?x=1&y=2" alt="<img src=x onerror=alert(1)>"></p>'
);

cst_assert(strpos($html, '<figcaption><img') === false, 'Image alt is not rendered as raw figcaption HTML');
cst_assert(strpos($html, 'data-caption="<img') === false, 'Image alt is not rendered as raw fancybox caption HTML');
cst_assert(strpos($html, 'alt="<img') === false, 'Image alt is not rendered as raw alt attribute HTML');
cst_assert(strpos($html, '&lt;img src=x onerror=alert(1)&gt;') !== false, 'Image alt is preserved as escaped text');
cst_assert(strpos($html, 'href="https://example.com/a.jpg?x=1&amp;y=2"') !== false, 'Image src attributes are escaped');

$encodedHtml = Contents::parseFancyBox(
    '<p><img src="https://example.com/a.jpg?x=1&amp;y=2" alt="safe"></p>'
);
cst_assert(strpos($encodedHtml, '&amp;amp;') === false, 'Existing entities in image src are not double encoded');

echo "\n\n--- ContentsSecurityTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
