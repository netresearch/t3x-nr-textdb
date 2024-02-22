<?php

/**
 * This file represents the configuration for Code Sniffing PSR-2-related
 * automatic checks of coding guidelines
 * Install @fabpot's great php-cs-fixer tool via
 *
 *  $ composer global require friendsofphp/php-cs-fixer
 *
 * And then simply run
 *
 *  $ php-cs-fixer fix
 *
 * For more information read:
 *  http://www.php-fig.org/psr/psr-2/
 *  http://cs.sensiolabs.org
 */

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

$repositoryName = basename(dirname(__DIR__));

$header = <<<EOF
This file is part of the package netresearch/$repositoryName.

For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.
EOF;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // PSR-12
        '@PSR12'                                     => true,

        // Additional custom rules
        'declare_strict_types'                       => true,
        'header_comment'                             => [
            'header'       => $header,
            'comment_type' => 'PHPDoc',
            'location'     => 'after_open',
            'separate'     => 'both',
        ],
        'no_singleline_whitespace_before_semicolons' => true,
        'no_unused_imports'                          => true,
        'concat_space'                               => [
            'spacing' => 'one',
        ],
        'single_quote'                               => true,
        'no_empty_statement'                         => true,
        'no_extra_blank_lines'                       => [
            'tokens' => [
                'extra',
            ],
        ],
        'phpdoc_no_package'                          => true,
        'phpdoc_scalar'                              => true,
        'no_blank_lines_after_phpdoc'                => true,
        'array_syntax'                               => [
            'syntax' => 'short',
        ],
        'whitespace_after_comma_in_array'            => [
            'ensure_single_space' => true,
        ],
        'type_declaration_spaces'                    => true,
        'single_line_comment_style'                  => true,
        'no_alias_functions'                         => true,
        'no_leading_namespace_whitespace'            => true,
        'native_function_casing'                     => true,
        'self_accessor'                              => false,
        'no_short_bool_cast'                         => true,
        'no_unneeded_control_parentheses'            => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('.build')
            ->exclude('config')
            ->exclude('node_modules')
            ->exclude('var')
            ->in(__DIR__ . '/../')
    );
