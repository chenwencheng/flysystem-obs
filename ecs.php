<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Zing\CodingStandard\Set\ECSSetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([ECSSetList::PHP_80, ECSSetList::CUSTOM]);
    $ecsConfig->parallel();
    $ecsConfig->skip([
        \PHP_CodeSniffer\Standards\PSR1\Sniffs\Methods\CamelCapsMethodNameSniff::class => [
            __DIR__ . '/tests/ObsAdapterTest.php',
        ],
        \PhpCsFixer\Fixer\PhpUnit\PhpUnitMethodCasingFixer::class => [__DIR__ . '/tests/ObsAdapterTest.php'],
        \PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer::class => [__DIR__ . '/tests/ObsAdapterTest.php'],
        // bug
        ReturnAssignmentFixer::class,
        \PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer::class => [__DIR__ . '/tests/ValidAdapterTest.php'],
    ]);
    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/ecs.php', __DIR__ . '/rector.php']);
};
