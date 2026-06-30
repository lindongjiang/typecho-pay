<?php

/**
 * Static regression checks for comment rendering query count.
 */

$root = dirname(__DIR__);
$source = file_get_contents($root . '/libs/Comments.php');
$passed = 0;
$failed = 0;

function cpt_assert(bool $condition, string $label): void
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

$getParent = '';
if (preg_match('/private function getParent\(\).*?^    \}/ms', $source, $m)) {
    $getParent = $m[0];
}

$getLikesAndDislikes = '';
if (preg_match('/private function getLikesAndDislikes\(\).*?^    \}/ms', $source, $m)) {
    $getLikesAndDislikes = $m[0];
}

cpt_assert($getParent !== '', 'Found getParent helper');
cpt_assert($getLikesAndDislikes !== '', 'Found getLikesAndDislikes helper');
cpt_assert(strpos($getParent, 'fetchRow') === false, 'Parent comment lookup does not query per rendered comment');
cpt_assert(strpos($getParent, '_commentAuthors') !== false, 'Parent comment lookup reuses loaded author map');
cpt_assert(strpos($getLikesAndDislikes, 'fetchRow') === false, 'Vote metadata does not query per rendered comment');
cpt_assert(strpos($getLikesAndDislikes, '$this->likes') !== false, 'Vote metadata reuses loaded comment row');

echo "\n\n--- CommentsPerformanceTest ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
